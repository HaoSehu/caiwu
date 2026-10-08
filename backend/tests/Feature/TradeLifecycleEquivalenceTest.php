<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Constants\UserCouponStatus;
use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Exceptions\BusinessException;
use App\Models\IntegrationPlugin;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\UserCoupon;
use App\Services\Finance\BalanceInvoicePaymentService;
use App\Services\Finance\CheckoutService;
use App\Services\Finance\CouponService;
use App\Services\Finance\GatewayInvoicePaymentService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\MixPaymentService;
use App\Services\Finance\PaymentCallbackRaw;
use App\Services\Finance\TradeLifecycleService;
use App\Services\Integrations\Payments\Data\PaymentPrecreateRequest;
use App\Services\Integrations\Payments\Data\PaymentPrecreateResult;
use App\Services\Integrations\Payments\Data\PaymentQueryResult;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\Order\OrderService;
use App\Services\Order\PaidOrderBusinessFlowDispatcher;
use App\Services\User\AccountService;
use App\Services\User\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 生命周期状态变更入口的行为快照（方案 2 批次 1 特征测试）：
 * 锁住状态入口迁移到 TradeLifecycleService 前后的等价行为——
 * 网关入账、余额支付、手动入账、管理员建实例扣款、订单取消、
 * 账单取消（含 PENDING 订单级联与券释放）、过期捕获款取消、混合支付回退递减，
 * 以及状态机幂等守卫（已 PAID 重入不覆盖部分支付累计、已 PAID 账单仍投影订单）。
 * 订单余额支付路径（payOrderByBalance）已随方案 2 批次 2 删除。
 * 退款链路由 RefundInvoiceToBalanceTest 覆盖。使用 DatabaseTransactions，测试结束回滚。
 */
class TradeLifecycleEquivalenceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(PaidOrderBusinessFlowDispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatchPaidInvoice')->andReturnNull();
            $mock->shouldReceive('dispatchPaidOrder')->andReturnNull();
        });
    }

    public function test_gateway_settlement_marks_invoice_and_order_paid(): void
    {
        [$user, $order, $invoice] = $this->pair(100.00);
        $this->registerStubGateway();

        $payload = app(GatewayInvoicePaymentService::class)->payByGateway($invoice, $user, PaymentGatewayCode::ALIPAY);
        $payment = Payment::query()->where('payment_no', $payload['payment_no'])->firstOrFail();
        $traceNo = 'TRADE'.date('YmdHis').mt_rand(100000, 999999);

        $this->assertTrue(app(GatewayInvoicePaymentService::class)->handleGatewayNotify(PaymentGatewayCode::ALIPAY, [
            'out_trade_no' => $payment->payment_no,
            'trade_no' => $traceNo,
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '100.00',
            'app_id' => 'stub-merchant',
        ]));

        $invoice->refresh();
        $order->refresh();
        $payment->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertSame('100.00', number_format((float) $invoice->paid_amount, 2, '.', ''));
        $this->assertNotNull($invoice->paid_at);
        $this->assertStringStartsWith('alipay:invoice:', (string) $invoice->trace_id, '账单 trace_id 落入账 trace，网关流水号落支付单');
        $this->assertSame($traceNo, (string) $payment->trade_no);
        $this->assertSame(OrderStatus::PAID, (int) $order->status);
        $this->assertSame('100.00', number_format((float) $order->paid_amount, 2, '.', ''));
        $this->assertNotNull($order->paid_at);
    }

    public function test_balance_pay_marks_invoice_and_order_paid(): void
    {
        [$user, $order, $invoice] = $this->pair(40.00);
        app(AccountService::class)->setCashBalance($user, 50.00);

        app(BalanceInvoicePaymentService::class)->payByBalance($invoice, $user, ['trace_id' => 'tr-balance-1']);

        $invoice->refresh();
        $order->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertSame('40.00', number_format((float) $invoice->paid_amount, 2, '.', ''));
        $this->assertSame('tr-balance-1', (string) $invoice->trace_id);
        $this->assertSame(OrderStatus::PAID, (int) $order->status);
        $this->assertSame('40.00', number_format((float) $order->paid_amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float) $user->fresh()->balance, 2, '.', ''));
    }

    public function test_manual_mark_paid_projects_order_with_custom_paid_at(): void
    {
        [$user, $order, $invoice] = $this->pair(88.00);
        $paidAt = now()->subHours(3)->startOfSecond();

        app(InvoiceService::class)->markPaidManually($invoice, [
            'paid_at' => $paidAt->toDateTimeString(),
            'trade_no' => 'MANUAL-1',
        ], ['operator_id' => 1, 'operator_name' => 'tester']);

        $invoice->refresh();
        $order->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertSame('88.00', number_format((float) $invoice->paid_amount, 2, '.', ''));
        $this->assertSame($paidAt->getTimestamp(), optional($invoice->paid_at)->getTimestamp());
        $this->assertSame(OrderStatus::PAID, (int) $order->status);
        $this->assertSame('88.00', number_format((float) $order->paid_amount, 2, '.', ''));
        $this->assertSame($paidAt->getTimestamp(), optional($order->paid_at)->getTimestamp());

        // manual Payment 审计单：operator/remark 落列，且回调投影行同步生成（is_verified=1）。
        $manualPayment = Payment::query()
            ->where('invoice_id', (int) $invoice->id)
            ->whereGatewayKey(PaymentGatewayCode::MANUAL)
            ->firstOrFail();
        $this->assertSame('tester', (string) $manualPayment->operator);
        $this->assertSame(1, PaymentCallback::query()
            ->where('payment_id', (int) $manualPayment->id)
            ->where('callback_type', 'payment')
            ->where('is_verified', 1)
            ->count());
    }

    public function test_order_cancel_cascades_invoice_and_coupon(): void
    {
        [$user, $order, $invoice, $userCoupon] = $this->pairWithCoupon(20.00);
        $invoice->forceFill(['status' => InvoiceStatus::UNPAID])->save();

        app(OrderService::class)->cancel($order, ['actor_type' => 'admin', 'actor_name' => 'tester']);

        $order->refresh();
        $invoice->refresh();
        $userCoupon->refresh();
        $this->assertSame(OrderStatus::CANCELLED, (int) $order->status);
        $this->assertSame(InvoiceStatus::CANCELLED, (int) $invoice->status);
        $this->assertSame(UserCouponStatus::OWNED, (int) $userCoupon->status, '订单取消后券必须回落可复用');
    }

    public function test_mark_paid_is_idempotent_for_already_paid_invoice(): void
    {
        [$user, $order, $invoice] = $this->pair(100.00);
        // 部分支付累计：已 PAID 账单重入时不得被全额覆盖（守卫来自对账修复等二次调用场景）
        $invoice->forceFill([
            'status' => InvoiceStatus::PAID,
            'paid_amount' => '40.00',
            'paid_at' => now()->subHour(),
        ])->save();
        $order->forceFill(['status' => OrderStatus::PENDING, 'paid_amount' => '0.00'])->save();

        $result = app(TradeLifecycleService::class)->markInvoicePaid($invoice);

        $this->assertSame(InvoiceStatus::PAID, (int) $result->status);
        $this->assertSame('40.00', number_format((float) $result->paid_amount, 2, '.', ''), '已 PAID 账单的资金列不得被重写');
        $order->refresh();
        $this->assertSame(OrderStatus::PAID, (int) $order->status, '已 PAID 账单重入时订单投影必须补齐');
        $this->assertSame('40.00', number_format((float) $order->paid_amount, 2, '.', ''), '订单投影对齐账单累计实收');
    }

    public function test_mark_paid_rejects_cancelled_invoice(): void
    {
        [$user, $order, $invoice] = $this->pair(50.00);
        $invoice->forceFill(['status' => InvoiceStatus::CANCELLED])->save();

        $this->expectException(BusinessException::class);
        app(TradeLifecycleService::class)->markInvoicePaid($invoice);
    }

    public function test_cancel_trade_rejects_paid_invoice(): void
    {
        [$user, $order, $invoice] = $this->pair(50.00);
        $invoice->forceFill([
            'status' => InvoiceStatus::PAID,
            'paid_amount' => '50.00',
            'paid_at' => now(),
        ])->save();

        $this->expectException(BusinessException::class);
        app(TradeLifecycleService::class)->cancelTrade($invoice);
    }

    public function test_invoice_cancel_cascades_pending_order_and_releases_coupon(): void
    {
        [$user, $order, $invoice, $userCoupon] = $this->pairWithCoupon(20.00);

        app(CheckoutService::class)->cancel($invoice, ['actor_type' => 'admin', 'actor_name' => 'tester']);

        $invoice->refresh();
        $order->refresh();
        $userCoupon->refresh();
        $this->assertSame(InvoiceStatus::CANCELLED, (int) $invoice->status);
        $this->assertSame(OrderStatus::CANCELLED, (int) $order->status, 'PENDING 订单必须随账单一并级联取消');
        $this->assertSame(UserCouponStatus::OWNED, (int) $userCoupon->status);
        $this->assertSame(0, Payment::query()->where('invoice_id', (int) $invoice->id)->where('status', PaymentStatus::PENDING)->count());
    }

    public function test_expired_session_captured_payment_cancels_invoice_and_cascades_order(): void
    {
        [$user, $order, $invoice] = $this->pair(60.00);
        $this->registerStubGateway();

        $payload = app(GatewayInvoicePaymentService::class)->payByGateway($invoice, $user, PaymentGatewayCode::ALIPAY);
        $payment = Payment::query()->where('payment_no', $payload['payment_no'])->firstOrFail();
        // 支付窗口过期：created_at 拨回到 TTL 之外（须在预下单之后，否则 precreate 前置检查直接拒绝）
        Invoice::query()->whereKey((int) $invoice->id)->update(['created_at' => now()->subHours(12)]);
        $invoice->refresh();

        $this->assertTrue(app(GatewayInvoicePaymentService::class)->handleGatewayNotify(PaymentGatewayCode::ALIPAY, [
            'out_trade_no' => $payment->payment_no,
            'trade_no' => 'TRADEEXP'.mt_rand(100000, 999999),
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '60.00',
            'app_id' => 'stub-merchant',
        ]));

        $invoice->refresh();
        $order->refresh();
        $payment->refresh();
        $this->assertSame(InvoiceStatus::CANCELLED, (int) $invoice->status, '过期窗口内的捕获款必须取消账单而非入账');
        $this->assertSame(OrderStatus::CANCELLED, (int) $order->status, 'PENDING 订单必须级联取消');
        $this->assertSame(0, Payment::query()->where('invoice_id', (int) $invoice->id)->where('status', PaymentStatus::PENDING)->count());
    }

    public function test_mix_balance_restore_decrements_invoice_and_order_paid_amount(): void
    {
        [$user, $order, $invoice] = $this->pair(30.00);
        $invoice->forceFill(['paid_amount' => '20.00'])->save();
        $order->forceFill(['paid_amount' => '20.00'])->save();
        $payment = Payment::query()->create([
            'payment_no' => Payment::generatePaymentNo(),
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'invoice_id' => (int) $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => 20.00,
            'status' => PaymentStatus::PENDING,
            'callback_raw' => [
                'source' => 'mix_test',
                PaymentCallbackRaw::KEY_MIX_PAYMENT => true,
                PaymentCallbackRaw::KEY_BALANCE_AMOUNT => 20.00,
            ],
        ]);
        app(AccountService::class)->setCashBalance($user, 0.00);

        $restored = app(MixPaymentService::class)->restoreReservedMixBalance($payment, [
            'balance_amount' => 20.00,
            'closed_reason' => 'mix_test_restore',
        ]);

        $this->assertTrue($restored);
        $invoice->refresh();
        $order->refresh();
        $this->assertSame('0.00', number_format((float) $invoice->paid_amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $order->paid_amount, 2, '.', ''));
        $this->assertSame('20.00', number_format((float) $user->fresh()->balance, 2, '.', ''));
    }

    public function test_mix_balance_reserve_increments_invoice_and_order_paid_amount(): void
    {
        [$user, $order, $invoice] = $this->pair(100.00);
        $invoice->setRelation('order', $order);

        // 预扣投影递增：账单与订单 paid_amount 同步递增，状态不变（入账仍由 markInvoicePaid 收口）。
        app(TradeLifecycleService::class)->reservePaidProjection($invoice, 30.00);

        $invoice->refresh();
        $order->refresh();
        $this->assertSame('30.00', number_format((float) $invoice->paid_amount, 2, '.', ''));
        $this->assertSame('30.00', number_format((float) $order->paid_amount, 2, '.', ''));
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->status);
        $this->assertSame(OrderStatus::PENDING, (int) $order->status);

        // 非正金额为空操作。
        app(TradeLifecycleService::class)->reservePaidProjection($invoice, 0.00);
        $this->assertSame('30.00', number_format((float) $invoice->refresh()->paid_amount, 2, '.', ''));

        // 与回补递减对称：递减后账单/订单同步归零。
        app(TradeLifecycleService::class)->decrementPaidProjection($invoice, 30.00);
        $invoice->refresh();
        $order->refresh();
        $this->assertSame('0.00', number_format((float) $invoice->paid_amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $order->paid_amount, 2, '.', ''));
    }

    public function test_admin_manual_service_marks_order_and_invoice_paid(): void
    {
        $user = User::factory()->create(['is_verified' => 1]);
        app(AccountService::class)->setCashBalance($user, 100.00);
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

        app(UserService::class)->createManualService($user, [
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'deduct_balance' => 1,
            'create_order' => 1,
            'create_invoice' => 1,
        ], ['operator_id' => 1, 'operator_name' => 'tester']);

        $service = Service::query()
            ->where('user_id', (int) $user->id)
            ->where('product_id', (int) $product->id)
            ->latest('id')
            ->firstOrFail();
        $order = Order::query()->findOrFail((int) $service->order_id);
        $invoice = Invoice::query()->findOrFail((int) $service->invoice_id);
        $this->assertSame(OrderStatus::PAID, (int) $order->status);
        $this->assertSame('66.00', number_format((float) $order->paid_amount, 2, '.', ''));
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertSame('66.00', number_format((float) $invoice->paid_amount, 2, '.', ''));
        $this->assertSame('34.00', number_format((float) $user->fresh()->balance, 2, '.', ''));
    }

    /**
     * @return array{0: User, 1: Order, 2: Invoice}
     */
    private function pair(float $amount): array
    {
        $user = User::factory()->create(['is_verified' => 1]);
        $order = Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => (int) $user->id,
            'type' => 'new',
            'billing_cycle' => 'monthly',
            'quantity' => 1,
            'amount' => number_format($amount, 2, '.', ''),
            'status' => OrderStatus::PENDING,
        ]);
        $invoice = Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'type' => 'new',
            'billing_cycle' => 'monthly',
            'amount' => number_format($amount, 2, '.', ''),
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDay(),
        ]);
        $order->setRelation('invoice', $invoice);

        return [$user, $order, $invoice];
    }

    /**
     * @return array{0: User, 1: Order, 2: Invoice, 3: UserCoupon}
     */
    private function pairWithCoupon(float $amount): array
    {
        $user = User::factory()->create(['is_verified' => 1]);
        $coupon = app(CouponService::class)->createCoupon([
            'name' => '生命周期券'.uniqid(),
            'code' => 'LC'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
            'min_amount' => 0,
            'status' => 1,
        ], ['operator' => 'tester']);
        $userCoupon = UserCoupon::query()
            ->where('user_id', (int) $user->id)
            ->where('coupon_id', (int) $coupon['id'])
            ->firstOrFail();

        $order = Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => (int) $user->id,
            'type' => 'new',
            'billing_cycle' => 'monthly',
            'quantity' => 1,
            'amount' => number_format($amount, 2, '.', ''),
            'status' => OrderStatus::PENDING,
        ]);
        $invoice = Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'coupon_id' => (int) $coupon['id'],
            'user_coupon_id' => (int) $userCoupon->id,
            'type' => 'new',
            'billing_cycle' => 'monthly',
            'amount' => number_format($amount, 2, '.', ''),
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDay(),
        ]);
        $order->setRelation('invoice', $invoice);

        return [$user, $order, $invoice, $userCoupon];
    }

    private ?object $stub = null;

    private function registerStubGateway(): void
    {
        if (Schema::hasTable('integration_plugins')) {
            IntegrationPlugin::query()
                ->where('domain', PluginDomain::PAYMENT)
                ->update(['status' => IntegrationPlugin::STATUS_DISABLED]);
        }

        $this->stub = new class implements PaymentGatewayInterface
        {
            public function key(): string
            {
                return PaymentGatewayCode::ALIPAY;
            }

            public function name(): string
            {
                return '生命周期测试替身网关';
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function matchesMerchantId(?string $merchantId): bool
            {
                return (string) $merchantId === 'stub-merchant';
            }

            public function supportsAction(string $action): bool
            {
                return false;
            }

            public function precreate(PaymentPrecreateRequest $request): PaymentPrecreateResult
            {
                return new PaymentPrecreateResult(
                    qrCode: 'https://stub.gateway.test/qr/'.rawurlencode($request->outTradeNo),
                    outTradeNo: $request->outTradeNo,
                    raw: ['stub' => true],
                );
            }

            public function query(string $outTradeNo): PaymentQueryResult
            {
                return new PaymentQueryResult(
                    tradeStatus: 'WAIT_BUYER_PAY',
                    tradeNo: '',
                    outTradeNo: $outTradeNo,
                    totalAmount: '0.00',
                    raw: ['stub' => true],
                );
            }

            public function verifyNotify(array $payload): bool
            {
                return true;
            }

            public function buildNotifyResponse(bool $success): Response
            {
                return response($success ? 'success' : 'fail', 200)
                    ->header('Content-Type', 'text/plain');
            }
        };

        app(PaymentGatewayRegistry::class)->register($this->stub);
    }
}
