<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\Admin\V2\AdminManualEntryV2Service;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentCallbackProjector;
use App\Services\Order\OrderService;
use App\Services\Provisioning\ServiceRenewService;
use App\Services\User\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsFinanceBalance;
use Tests\TestCase;

/**
 * 财务审查修复批次（2026-11-09）之二：补录纠错与部分核销。
 * 覆盖 M-57a（补录续费单不阻塞自助续费）、M-57b（补录订单账单允许红字冲抵）、
 * M-57c（补录 PAID 订单管理员取消通道）、M-60（余额扣款账单拒绝红字冲抵）、
 * M-62（部分核销关闭 PENDING 支付单）、M-23（混付预扣后 can_pay 仍为 true）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class FixManualEntryReviewTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsFinanceBalance;

    public function test_admin_manual_paid_renew_invoice_does_not_block_self_service_renewal(): void
    {
        [$user, $service, $product] = $this->renewFixture();
        $renewService = app(ServiceRenewService::class);

        // 管理员补录一张同周期续费单：订单带 admin_manual 标记（createFromOrder 继承到账单）
        $manualOrder = Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'service_id' => (int) $service->id,
            'type' => OrderType::RENEW,
            'amount' => 35.00,
            'billing_cycle' => 'monthly',
            'status' => OrderStatus::PAID,
            'paid_amount' => 35.00,
            'paid_at' => now(),
            'config_snapshot' => ['admin_manual' => true],
        ]);
        Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $user->id,
            'order_id' => (int) $manualOrder->id,
            'product_id' => (int) $product->id,
            'service_id' => (int) $service->id,
            'type' => InvoiceType::RENEW,
            'amount' => 35.00,
            'paid_amount' => 35.00,
            'billing_cycle' => 'monthly',
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'config_snapshot' => ['admin_manual' => true],
        ]);

        $invoice = $renewService->createRenewInvoiceForUser($user, (int) $service->id, 'monthly');

        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->status, '补录续费单不得阻塞同周期自助续费（M-57a）');
        $this->assertNotSame((int) $manualOrder->id, (int) $invoice->order_id, '必须新建续费账单而不是复用补录账单');
    }

    public function test_manual_order_invoice_can_be_reversed_by_red_letter(): void
    {
        $user = $this->makeUser();
        [$order, $invoice] = $this->makeManualOrderWithInvoice($user, '120.00');

        $result = app(AdminManualEntryV2Service::class)->reverseManualInvoice($user, (int) $invoice->id, [
            'remark' => '补录续费周期填错，红字冲抵',
        ], $this->context());

        $this->assertSame('completed', $result['status']);
        $this->assertFalse((bool) $result['detail']['already_reversed']);

        $reversal = Invoice::query()
            ->where('origin_invoice_id', (int) $invoice->id)
            ->where('type', InvoiceType::REFUND)
            ->firstOrFail();
        $this->assertSame('-120.00', number_format((float) $reversal->amount, 2, '.', ''));
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->fresh()->status, '原单不得被触碰');
        $this->assertSame(OrderStatus::PAID, (int) $order->fresh()->status, '订单状态不得被冲抵改写');
    }

    public function test_manual_order_invoice_with_third_party_payment_still_rejects_reversal(): void
    {
        $user = $this->makeUser();
        [, $invoice] = $this->makeManualOrderWithInvoice($user, '88.00');
        // 该补录订单名下另挂了一笔真实网关支付单：说明资金真实进过系统，必须走退款。
        Payment::query()->create([
            'payment_no' => Payment::generatePaymentNo(),
            'user_id' => (int) $user->id,
            'invoice_id' => (int) $invoice->id,
            'order_id' => (int) $invoice->order_id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => '88.00',
            'status' => PaymentStatus::SUCCESS,
            'paid_at' => now(),
            'callback_raw' => ['source' => 'alipay_precreate'],
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('仅支持对补录账单发起红字冲抵');
        app(AdminManualEntryV2Service::class)->reverseManualInvoice($user, (int) $invoice->id, [
            'remark' => '网关支付的补录单',
        ], $this->context());
    }

    public function test_balance_deducted_manual_invoice_rejects_reversal_with_clear_message(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 100.00);
        $invoice = $this->makeManualServiceInvoice($user, [
            'deduct_balance' => 1,
        ]);

        try {
            app(AdminManualEntryV2Service::class)->reverseManualInvoice($user, (int) $invoice->id, [
                'remark' => '余额扣款单冲抵',
            ], $this->context());
            $this->fail('余额扣款产生的账单不得红字冲抵');
        } catch (BusinessException $exception) {
            $this->assertSame('该账单由余额扣款产生，请使用退款到余额', $exception->getMessage());
        }

        $this->assertSame(0, Invoice::query()->where('origin_invoice_id', (int) $invoice->id)->count());
    }

    public function test_manual_service_invoice_from_balance_carries_paid_from_balance_marker(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 100.00);

        $invoice = $this->makeManualServiceInvoice($user, ['deduct_balance' => 1]);

        $snapshot = (array) ($invoice->config_snapshot ?? []);
        $this->assertTrue((bool) ($snapshot['paid_from_balance'] ?? false), '余额扣款开通账单必须带 paid_from_balance 标记（M-60）');
        $this->assertEqualsWithDelta(34.00, $this->financeBalance($user), 0.001, '余额确实被扣款 66');
    }

    public function test_manual_service_invoice_without_balance_deduction_has_no_marker_and_can_be_reversed(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeManualServiceInvoice($user, ['deduct_balance' => 0]);

        $snapshot = (array) ($invoice->config_snapshot ?? []);
        $this->assertFalse((bool) ($snapshot['paid_from_balance'] ?? false));

        // 未扣余额的挂账账单先由管理员手工入账，再走红字冲抵。
        app(InvoiceService::class)->markPaidManually($invoice->fresh(), [
            'amount' => '66.00',
            'remark' => '线下收款入账',
        ], $this->context());

        $result = app(AdminManualEntryV2Service::class)->reverseManualInvoice($user, (int) $invoice->id, [
            'remark' => '非余额扣款，允许冲抵',
        ], $this->context());
        $this->assertSame('completed', $result['status']);
    }

    public function test_admin_can_cancel_paid_manual_order_and_flags_requires_refund(): void
    {
        $user = $this->makeUser();
        [$order, $invoice] = $this->makeManualOrderWithInvoice($user, '99.00');

        app(OrderService::class)->cancel($order->fresh(), [
            'actor_type' => 'admin',
            'actor_user_id' => 1,
            'actor_name' => 'admin-test',
            'reason' => 'admin_order_cancel',
        ]);

        $this->assertSame(OrderStatus::CANCELLED, (int) $order->fresh()->status, '补录 PAID 订单必须可被管理员取消（M-57c）');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status, '补录账单保持已支付，等待红字冲抵/人工退款');
        $this->assertTrue((bool) $invoice->requires_refund, '补录 PAID 账单取消后打 requires_refund 标记');

        $log = DB::table('activity_logs')
            ->where('action', 'order.cancel')
            ->where('subject_id', (int) $order->id)
            ->first();
        $this->assertNotNull($log);
        $context = json_decode((string) $log->context, true);
        $this->assertTrue((bool) ($context['requires_refund'] ?? false), '审计必须记录 requires_refund 分支');

        // 取消后该账单仍可走补录红字冲抵（M-57b 组合能力）
        $result = app(AdminManualEntryV2Service::class)->reverseManualInvoice($user, (int) $invoice->id, [
            'remark' => '取消后红字冲抵',
        ], $this->context());
        $this->assertSame('completed', $result['status']);
    }

    public function test_admin_cannot_cancel_paid_manual_order_with_real_gateway_payment(): void
    {
        $user = $this->makeUser();
        [$order, $invoice] = $this->makeManualOrderWithInvoice($user, '99.00');
        Payment::query()->create([
            'payment_no' => Payment::generatePaymentNo(),
            'user_id' => (int) $user->id,
            'invoice_id' => (int) $invoice->id,
            'order_id' => (int) $order->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => '99.00',
            'status' => PaymentStatus::SUCCESS,
            'paid_at' => now(),
            'callback_raw' => ['source' => 'alipay_precreate'],
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('仅待支付订单可取消');
        app(OrderService::class)->cancel($order->fresh(), ['actor_type' => 'admin']);
    }

    public function test_partial_manual_entry_closes_existing_pending_gateway_payment(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 100.00);
        $pending = Payment::query()->create([
            'payment_no' => Payment::generatePaymentNo(),
            'user_id' => (int) $user->id,
            'invoice_id' => (int) $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => '100.00',
            'status' => PaymentStatus::PENDING,
            'callback_raw' => ['source' => 'alipay_precreate'],
        ]);

        app(InvoiceService::class)->markPaidManually($invoice, [
            'amount' => '30.00',
            'remark' => '定金',
        ], $this->context());

        $pending->refresh();
        $this->assertSame(PaymentStatus::CANCELLED, (int) $pending->status, '部分核销必须关闭旧 PENDING 网关单（M-62）');
        $raw = (array) ($pending->callback_raw ?? []);
        $this->assertSame('invoice_partially_paid_manually', (string) ($raw['closed_reason'] ?? ''));
    }

    public function test_mix_reservation_keeps_client_detail_payable(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 100.00, ['type' => InvoiceType::NEW_PURCHASE]);
        // 混付预扣 30：派生态为「部分已付」=2，但库内 UNPAID、支付窗口未关闭。
        $invoice->forceFill(['paid_amount' => '30.00'])->save();

        $detail = app(InvoiceService::class)->clientDetail($invoice->fresh());

        $this->assertSame(InvoiceStatus::PARTIAL_PAID, (int) $detail['status'], '展示态仍派生为部分已付');
        $this->assertSame(InvoiceStatus::UNPAID, (int) $detail['raw_status'], '必须透传真实库内状态（M-23）');
        $this->assertTrue((bool) $detail['can_pay'], '混付预扣后支付窗口未关闭时应可继续支付');
    }

    public function test_paid_invoice_client_detail_cannot_pay(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 100.00, [
            'status' => InvoiceStatus::PAID,
            'paid_amount' => '100.00',
            'paid_at' => now(),
        ]);

        $detail = app(InvoiceService::class)->clientDetail($invoice);

        $this->assertSame(InvoiceStatus::PAID, (int) $detail['raw_status']);
        $this->assertFalse((bool) $detail['can_pay']);
    }

    /**
     * 造一张 `type=manual + order_id>0 + 全部 manual 支付` 的补录订单与账单。
     *
     * @return array{0: Order, 1: Invoice}
     */
    private function makeManualOrderWithInvoice(User $user, string $amount): array
    {
        $order = Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => (int) $user->id,
            'type' => OrderType::RENEW,
            'amount' => (float) $amount,
            'billing_cycle' => 'monthly',
            'quantity' => 1,
            'status' => OrderStatus::PAID,
            'paid_amount' => (float) $amount,
            'paid_at' => now(),
            'config_snapshot' => ['admin_manual' => true],
        ]);
        $invoice = Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'type' => InvoiceType::RENEW,
            'amount' => (float) $amount,
            'paid_amount' => (float) $amount,
            'billing_cycle' => 'monthly',
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'config_snapshot' => ['admin_manual' => true],
        ]);

        $payment = new Payment([
            'payment_no' => Payment::generatePaymentNo(),
            'user_id' => (int) $user->id,
            'invoice_id' => (int) $invoice->id,
            'order_id' => (int) $order->id,
            'gateway' => PaymentGatewayCode::MANUAL,
            'amount' => (float) $amount,
            'status' => PaymentStatus::SUCCESS,
            'paid_at' => now(),
            'callback_raw' => ['source' => 'admin_manual_entry', 'payment_gateway' => 'cash'],
        ]);
        $payment->allowNonThirdPartyGateway = true;
        $payment->save();
        app(PaymentCallbackProjector::class)->syncProjection($payment);

        return [$order, $invoice];
    }

    /**
     * 走真实「添加实例」链路生成一张 MANUAL 账单（可勾选余额扣款）。
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeManualServiceInvoice(User $user, array $overrides = []): Invoice
    {
        $product = Product::query()->create([
            'name' => '手工实例商品'.uniqid(),
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '66.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'auto_setup' => 0,
        ]);

        app(UserService::class)->createManualService($user, array_merge([
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'create_order' => 0,
            'create_invoice' => 1,
            'deduct_balance' => 0,
        ], $overrides), ['operator_id' => 1, 'operator_name' => 'tester']);

        return Invoice::query()
            ->where('user_id', (int) $user->id)
            ->where('product_id', (int) $product->id)
            ->latest('id')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeInvoice(User $user, float $amount, array $overrides = []): Invoice
    {
        return Invoice::query()->create(array_merge([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(1000000, 9999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => $amount,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(7),
        ], $overrides));
    }

    /**
     * @return array{0: User, 1: Service, 2: Product}
     */
    private function renewFixture(): array
    {
        $user = $this->makeUser();
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->addDays(10),
        ]);

        return [$user, $service, $product];
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [
            'operator_id' => 1,
            'operator_name' => 'admin-test',
            'trace_id' => 'fix-review-'.uniqid(),
            'ip_address' => '127.0.0.1',
        ];
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'fix-review-manual-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'fix-review-manual-tester',
            'total_sales_amount' => 0,
        ]);
    }
}
