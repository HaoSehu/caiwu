<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Http\Requests\Admin\V2\Invoice\RefundInvoiceToBalanceRequest;
use App\Models\FinanceRefund;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\Finance\InvoiceRefundService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsFinanceBalance;
use Tests\TestCase;

/**
 * 退款交付守卫（B-09 修复回归）：默认 block，已交付服务的账单退款必须拒绝；
 * warn 模式是演练/回滚降级，必须带二次确认标记才放行。
 */
class RefundDeliveryGuardTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsFinanceBalance;

    protected function tearDown(): void
    {
        config(['finance.refund.delivery_guard' => 'block']);
        parent::tearDown();
    }

    public function test_default_config_is_block(): void
    {
        // 配置默认值必须是 block（不带 env 覆盖时）。
        $this->assertSame('block', (string) config('finance.refund.delivery_guard'));
    }

    public function test_block_mode_rejects_refund_for_delivered_service(): void
    {
        config(['finance.refund.delivery_guard' => 'block']);
        [$user, $order, $invoice] = $this->makePaidOrderWithActiveService();
        $balanceBefore = $this->financeBalance($user, true);

        try {
            app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice);
            $this->fail('已交付服务的账单退款必须被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('已交付', $exception->getMessage());
        }

        // 资金未动、无退款单。
        $this->assertEqualsWithDelta($balanceBefore, $this->financeBalance($user, true), 0.001);
        $this->assertSame(0, FinanceRefund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_warn_mode_requires_explicit_confirmation(): void
    {
        config(['finance.refund.delivery_guard' => 'warn']);
        [$user, $order, $invoice] = $this->makePaidOrderWithActiveService();

        // 未确认：拒绝（不静默放行）。
        try {
            app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice);
            $this->fail('warn 模式未二次确认不得放行');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('二次确认', $exception->getMessage());
        }

        // 带确认：放行并落交付证据。
        $result = app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice, [
            'amount' => 100.00,
            'confirm_delivered_refund' => true,
        ]);
        $this->assertFalse((bool) ($result['already_refunded'] ?? true));

        $refund = FinanceRefund::query()->where('invoice_id', (int) $invoice->id)->firstOrFail();
        $evidence = (array) $refund->delivery_evidence;
        $this->assertSame('warn', (string) ($evidence['mode'] ?? ''));
        $this->assertNotEmpty($evidence['services'] ?? []);
    }

    public function test_request_whitelist_allows_confirmation_flag(): void
    {
        // 请求层 payload() 白名单必须放行 confirm_delivered_refund，否则 warn 模式的
        // 二次确认永远传不到守卫（这是审查中实际发现的断点）。
        $request = new RefundInvoiceToBalanceRequest;
        $rules = (new \ReflectionMethod(RefundInvoiceToBalanceRequest::class, 'rules'))->invoke($request);

        $this->assertArrayHasKey('confirm_delivered_refund', $rules);
    }

    public function test_block_mode_rejects_renewal_invoice_without_service_order_id(): void
    {
        config(['finance.refund.delivery_guard' => 'block']);
        [$user, $order, $renewalInvoice, $service] = $this->makeRenewalInvoiceWithActiveService();
        $balanceBefore = $this->financeBalance($user, true);

        // 续费账单的订单与服务的 order_id 不一致（服务仍挂在首开订单上），
        // 只能用 order.service_id 反查或 services.invoice_id 命中，否则会被漏判成「未交付」。
        $this->assertNotSame((int) $order->id, (int) $service->order_id);

        try {
            app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $renewalInvoice);
            $this->fail('续费账单关联服务仍在运行，退款必须被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('已交付', $exception->getMessage());
        }

        $this->assertEqualsWithDelta($balanceBefore, $this->financeBalance($user, true), 0.001);
        $this->assertSame(0, FinanceRefund::query()->where('invoice_id', (int) $renewalInvoice->id)->count());
    }

    public function test_block_mode_rejects_renewal_invoice_without_order(): void
    {
        config(['finance.refund.delivery_guard' => 'block']);
        [$user, $order, $renewalInvoice, $service] = $this->makeRenewalInvoiceWithActiveService();

        // order_id 缺失时仍要走 services.invoice_id 兜底，不能直接 return null 放行。
        $renewalInvoice->forceFill(['order_id' => null])->save();
        $renewalInvoice->refresh();

        try {
            app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $renewalInvoice);
            $this->fail('无订单的续费账单仍需按 invoice_id 兜底判定交付');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('已交付', $exception->getMessage());
        }

        $this->assertSame((int) $service->invoice_id, (int) $renewalInvoice->id);
    }

    public function test_guard_passes_when_order_has_no_live_service(): void
    {
        config(['finance.refund.delivery_guard' => 'block']);
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 0.00);
        $order = $this->makeOrder($user, 100.00);
        $invoice = $this->makePaidInvoice($user, $order, 100.00);

        $result = app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice);
        $this->assertFalse((bool) ($result['already_refunded'] ?? true));
        $this->assertSame(InvoiceStatus::REFUNDED, (int) $invoice->refresh()->status);
    }

    /**
     * @return array{0: User, 1: Order, 2: Invoice}
     */
    private function makePaidOrderWithActiveService(): array
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 0.00);
        $order = $this->makeOrder($user, 100.00);
        $invoice = $this->makePaidInvoice($user, $order, 100.00);

        $product = Product::query()->create([
            'name' => '交付守卫测试商品',
            'product_type' => 'server',
            'pricing' => ['monthly' => 100.00],
            'status' => 1,
        ]);

        Service::query()->create([
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'product_id' => (int) $product->id,
            'name' => '已交付测试实例',
            'status' => ServiceStatus::ACTIVE,
            'billing_cycle' => 'monthly',
            'amount' => 100.00,
            'expires_at' => now()->addMonth(),
        ]);

        return [$user, $order, $invoice];
    }

    /**
     * 构造「续费账单」场景：服务挂在首开订单上，续费只回写 service.invoice_id，
     * 续费账单的 order_id 指向影子订单（与 service.order_id 不同）。
     *
     * @return array{0: User, 1: Order, 2: Invoice, 3: Service}
     */
    private function makeRenewalInvoiceWithActiveService(): array
    {
        [$user, $firstOrder, $firstInvoice] = $this->makePaidOrderWithActiveService();
        $service = Service::query()->where('order_id', (int) $firstOrder->id)->firstOrFail();
        $service->forceFill(['invoice_id' => (int) $firstInvoice->id])->save();

        // 续费影子订单：服务未回填到影子订单，模拟历史遗留数据。
        $renewOrder = $this->makeOrder($user, 100.00);
        $renewalInvoice = $this->makePaidInvoice($user, $renewOrder, 100.00);
        $service->forceFill(['invoice_id' => (int) $renewalInvoice->id])->save();

        return [$user, $renewOrder, $renewalInvoice, $service];
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'delivery-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'delivery-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeOrder(User $user, float $amount): Order
    {
        return Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => (int) $user->id,
            'type' => 'new',
            'amount' => $amount,
            'status' => OrderStatus::PAID,
            'paid_amount' => $amount,
            'paid_at' => now(),
        ]);
    }

    private function makePaidInvoice(User $user, Order $order, float $amount): Invoice
    {
        $invoice = Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'type' => 'new',
            'amount' => $amount,
            'paid_amount' => $amount,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);

        Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => (int) $user->id,
            'invoice_id' => (int) $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => $amount,
            'status' => PaymentStatus::SUCCESS,
            'trade_no' => 'TRADE'.date('YmdHis').mt_rand(100000, 999999),
            'paid_at' => now(),
            'callback_raw' => ['source' => 'alipay_precreate'],
        ]);

        return $invoice;
    }
}
