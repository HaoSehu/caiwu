<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Models\AccountTransaction;
use App\Models\IntegrationPlugin;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Models\User;
use App\Services\Finance\CheckoutService;
use App\Services\Finance\PaymentService;
use App\Services\Integrations\Payments\Data\PaymentPrecreateRequest;
use App\Services\Integrations\Payments\Data\PaymentPrecreateResult;
use App\Services\Integrations\Payments\Data\PaymentQueryResult;
use App\Services\Integrations\Payments\Data\PaymentRefundRequest;
use App\Services\Integrations\Payments\Data\PaymentRefundResult;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\User\AccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 组合支付（余额+网关）预扣余额退回的幂等回归：
 * 取消账单退回一次预扣余额后，网关重复回调/并发轮询不得把预扣余额退第二次
 * （balance_restored 幂等闸），也不得把同一笔网关款重复转入余额
 * （credited_to_balance 幂等闸）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class MixPaymentBalanceRestoreTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cancelled_mix_invoice_callback_restores_balance_only_once(): void
    {
        $user = $this->makeUser();
        $accounts = app(AccountService::class);
        $accounts->setCashBalance($user, 100.00);

        // 模拟组合支付下单完成后的状态：账单 100 = 预扣余额 30 + 网关款 70，
        // 预扣后用户余额 70，账单 paid_amount 计入余额部分 30。
        $invoice = $this->makeMixInvoice($user, 30.00, 70.00);
        $payment = $this->makeMixPayment($user, $invoice, 70.00, 30.00);
        $accounts->setCashBalance($user, 70.00);
        $invoice->forceFill(['paid_amount' => 30.00])->save();

        // 取消账单：预扣余额 30 恰好退回一次，支付单被置为已取消。
        $cancelled = app(CheckoutService::class)->cancel($invoice);
        $this->assertSame(InvoiceStatus::CANCELLED, (int) $cancelled->status);
        $payment->refresh();
        $this->assertSame(PaymentStatus::CANCELLED, (int) $payment->status);
        $this->assertEqualsWithDelta(100.00, $accounts->cashBalance($user), 0.001);
        $this->assertSame(1, $this->countBalanceLogs($user, FinanceLedgerEventType::INVOICE_REFUND, '组合支付取消退回余额'));

        $service = app(PaymentService::class);
        $callbackContext = [
            'closed_reason' => 'cancelled_invoice_captured',
            'mark_payment_failed' => false,
        ];

        // 回调到达（已取消账单分支的第一步）：预扣余额不得二次退回。
        $this->assertFalse($service->restoreReservedMixBalance($payment, $callbackContext));
        $this->assertEqualsWithDelta(100.00, $accounts->cashBalance($user), 0.001);

        // 网关款 70 按「异常支付转入余额」入账一次。
        $this->creditCapturedPaymentToBalance($service, $payment, $invoice, 'cancelled_invoice');
        $payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, (int) $payment->status);
        $this->assertEqualsWithDelta(170.00, $accounts->cashBalance($user), 0.001);

        // 网关重复回调：余额退回与网关款入账均被幂等闸拦截，余额不再变化。
        $this->assertFalse($service->restoreReservedMixBalance($payment, $callbackContext));
        $this->creditCapturedPaymentToBalance($service, $payment, $invoice, 'cancelled_invoice');
        $this->assertEqualsWithDelta(170.00, $accounts->cashBalance($user), 0.001);
        $this->assertSame(1, $this->countBalanceLogs($user, FinanceLedgerEventType::INVOICE_REFUND, '组合支付取消退回余额'));
        $this->assertSame(1, $this->countBalanceLogs($user, FinanceLedgerEventType::RECHARGE, '异常支付转入余额'));
    }

    public function test_pending_mix_payment_does_not_restore_twice_when_mark_payment_failed_false(): void
    {
        $user = $this->makeUser();
        $accounts = app(AccountService::class);
        $accounts->setCashBalance($user, 70.00);

        // 窗口过期路径（mark_payment_failed=false）退回后支付单仍保持 PENDING，
        // 重复回调/并发轮询再次进入时必须被 balance_restored 幂等闸拦截。
        $invoice = $this->makeMixInvoice($user, 30.00, 70.00);
        $invoice->forceFill(['paid_amount' => 30.00])->save();
        $payment = $this->makeMixPayment($user, $invoice, 70.00, 30.00);
        $service = app(PaymentService::class);

        $context = ['closed_reason' => 'payment_window_expired_captured', 'mark_payment_failed' => false];

        $this->assertTrue($service->restoreReservedMixBalance($payment, $context));
        $this->assertEqualsWithDelta(100.00, $accounts->cashBalance($user), 0.001);
        $payment->refresh();
        $this->assertSame(PaymentStatus::PENDING, (int) $payment->status);

        $this->assertFalse($service->restoreReservedMixBalance($payment, $context));
        $this->assertEqualsWithDelta(100.00, $accounts->cashBalance($user), 0.001);
        $this->assertSame(1, $this->countBalanceLogs($user, FinanceLedgerEventType::INVOICE_REFUND, '组合支付取消退回余额'));
    }

    public function test_unregistered_gateway_alias_callback_is_not_500(): void
    {
        // 未注册网关别名（ali_pay）：被路由 whereIn 约束拦截为 404，绝不允许 500。
        $response = $this->post('/api/v2/client/payment/notify/ali_pay', ['out_trade_no' => 'PYMIXTEST'.mt_rand(1000, 9999)]);
        $this->assertSame(404, $response->status());

        // 已注册网关但签名无效：按网关约定返回 200 fail 文本，不产生 500。
        $response = $this->post('/api/v2/client/payment/notify/alipay', ['out_trade_no' => 'PYMIXTEST'.mt_rand(1000, 9999)]);
        $this->assertSame(200, $response->status());
    }

    public function test_gateway_notify_full_flow_after_cancel_credits_gateway_part_once(): void
    {
        $user = $this->makeUser();
        $accounts = app(AccountService::class);
        $accounts->setCashBalance($user, 100.00);
        $this->registerStubGateway();

        // 组合支付下单（余额+网关）真实入口：扣余额 30，剩余 70 生成网关支付单。
        $invoice = $this->makeMixInvoice($user, 30.00, 70.00);
        $payload = app(PaymentService::class)->payByBalanceAndGateway(
            $invoice, $user, 30.00, PaymentGatewayCode::ALIPAY
        );
        $payment = Payment::query()->where('payment_no', $payload['payment_no'])->firstOrFail();
        $this->assertSame(PaymentStatus::PENDING, (int) $payment->status);
        $this->assertEqualsWithDelta(70.00, $accounts->cashBalance($user), 0.001);
        $this->assertEqualsWithDelta(30.00, (float) $invoice->refresh()->paid_amount, 0.001);

        // 取消账单：预扣余额退回一次，支付单置为已取消。
        $cancelled = app(CheckoutService::class)->cancel($invoice);
        $this->assertSame(InvoiceStatus::CANCELLED, (int) $cancelled->status);
        $payment->refresh();
        $this->assertSame(PaymentStatus::CANCELLED, (int) $payment->status);
        $this->assertEqualsWithDelta(100.00, $accounts->cashBalance($user), 0.001);

        // 真实入口 handleGatewayNotify：同一 trade_no 的成功通知重复发送两次。
        $service = app(PaymentService::class);
        $tradeNo = 'TRADE'.date('YmdHis').mt_rand(100000, 999999);
        $notifyParams = [
            'out_trade_no' => $payment->payment_no,
            'trade_no' => $tradeNo,
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '70.00',
        ];
        $this->assertTrue($service->handleGatewayNotify(PaymentGatewayCode::ALIPAY, $notifyParams));
        $this->assertTrue($service->handleGatewayNotify(PaymentGatewayCode::ALIPAY, $notifyParams));

        // 余额只退一次（取消已退 30），网关款 70 只入账一次，最终余额精确。
        $payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, (int) $payment->status);
        $this->assertEqualsWithDelta(170.00, $accounts->cashBalance($user), 0.001);
        $this->assertSame(1, $this->countBalanceLogs($user, FinanceLedgerEventType::INVOICE_REFUND, '组合支付取消退回余额'));
        $this->assertSame(1, $this->countBalanceLogs($user, FinanceLedgerEventType::RECHARGE, '异常支付转入余额'));

        // payment_callbacks 记录齐全：同一支付单同一类型只保留一行，验签通过且带网关流水号。
        $callbacks = PaymentCallback::query()->where('payment_id', (int) $payment->id)->get();
        $this->assertSame(1, $callbacks->count());
        $this->assertSame('payment', (string) $callbacks->first()->callback_type);
        $this->assertSame(1, (int) $callbacks->first()->is_verified);
        $this->assertSame($tradeNo, (string) $callbacks->first()->gateway_trade_no);
    }

    public function test_active_query_on_cancelled_mix_invoice_does_not_double_restore_or_credit(): void
    {
        $user = $this->makeUser();
        $accounts = app(AccountService::class);
        $accounts->setCashBalance($user, 100.00);
        $this->registerStubGateway(new PaymentQueryResult(
            tradeStatus: 'TRADE_SUCCESS',
            tradeNo: 'STUBTRADE'.mt_rand(100000, 999999),
            outTradeNo: '',
            totalAmount: '70.00',
            raw: ['stub' => true],
        ));

        // 组合支付下单后取消：余额退回一次（100），网关支付单保持已取消。
        $invoice = $this->makeMixInvoice($user, 30.00, 70.00);
        app(PaymentService::class)->payByBalanceAndGateway($invoice, $user, 30.00, PaymentGatewayCode::ALIPAY);
        $payment = Payment::query()
            ->where('invoice_id', (int) $invoice->id)
            ->whereGatewayKey(PaymentGatewayCode::ALIPAY)
            ->firstOrFail();
        app(CheckoutService::class)->cancel($invoice);
        $payment->refresh();
        $this->assertSame(PaymentStatus::CANCELLED, (int) $payment->status);
        $this->assertEqualsWithDelta(100.00, $accounts->cashBalance($user), 0.001);

        // 轮询通道真实入口：账单已取消状态下主动查询到网关单已支付。
        $service = app(PaymentService::class);
        $firstQuery = $service->queryGatewayPaymentStatus($payment);
        $this->assertNotFalse((bool) ($firstQuery['paid'] ?? false));

        $payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, (int) $payment->status);
        $this->assertEqualsWithDelta(170.00, $accounts->cashBalance($user), 0.001);
        $this->assertSame(1, $this->countBalanceLogs($user, FinanceLedgerEventType::INVOICE_REFUND, '组合支付取消退回余额'));
        $this->assertSame(1, $this->countBalanceLogs($user, FinanceLedgerEventType::RECHARGE, '异常支付转入余额'));

        // 重复轮询：支付单已成功后入口短路，余额不二次退、入账不重复。
        $secondQuery = $service->queryGatewayPaymentStatus($payment);
        $this->assertNotFalse((bool) ($secondQuery['paid'] ?? false));
        $this->assertEqualsWithDelta(170.00, $accounts->cashBalance($user), 0.001);
        $this->assertSame(1, $this->countBalanceLogs($user, FinanceLedgerEventType::RECHARGE, '异常支付转入余额'));

        // 轮询通道同样留有回调审计记录（source=active_query）。
        $callbacks = PaymentCallback::query()->where('payment_id', (int) $payment->id)->get();
        $this->assertSame(1, $callbacks->count());
        $this->assertSame(1, (int) $callbacks->first()->is_verified);
    }

    /**
     * 注册 alipay 网关测试替身：验签恒过、商户恒匹配，回调/主动查询由此驱动真实入账分支。
     * 真实网关插件的 RSA 验签在测试环境无法替身，注册前先禁用支付域已启用插件，
     * 避免真实适配器占用 alipay key 导致替身注册冲突（DatabaseTransactions 会回滚插件状态）。
     */
    private function registerStubGateway(?PaymentQueryResult $queryResult = null): void
    {
        if (Schema::hasTable('integration_plugins')) {
            IntegrationPlugin::query()
                ->where('domain', PluginDomain::PAYMENT)
                ->update(['status' => IntegrationPlugin::STATUS_DISABLED]);
        }

        app(PaymentGatewayRegistry::class)->register(new class($queryResult) implements PaymentGatewayInterface
        {
            public function __construct(
                private readonly ?PaymentQueryResult $queryResult,
            ) {}

            public function key(): string
            {
                return PaymentGatewayCode::ALIPAY;
            }

            public function name(): string
            {
                return '组合支付测试替身网关';
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function matchesMerchantId(?string $merchantId): bool
            {
                return true;
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
                    tradeStatus: $this->queryResult?->tradeStatus ?? 'WAIT_BUYER_PAY',
                    tradeNo: $this->queryResult?->tradeNo ?? '',
                    outTradeNo: $outTradeNo,
                    totalAmount: $this->queryResult?->totalAmount ?? '0.00',
                    raw: $this->queryResult?->raw ?? ['stub' => true],
                );
            }

            public function refund(PaymentRefundRequest $request): PaymentRefundResult
            {
                return new PaymentRefundResult(
                    tradeNo: $request->tradeNo ?? '',
                    outTradeNo: $request->outTradeNo,
                    refundFee: number_format($request->refundAmount, 2, '.', ''),
                    fundChange: 'N',
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
        });
    }

    /**
     * 通过反射调用私有的 creditCapturedPaymentToBalance，
     * 复现回调「已取消账单/窗口过期」分支中网关款转余额的处理步骤。
     */
    private function creditCapturedPaymentToBalance(PaymentService $service, Payment $payment, Invoice $invoice, string $reason): void
    {
        $method = new ReflectionMethod(PaymentService::class, 'creditCapturedPaymentToBalance');
        $method->invoke(
            $service,
            $payment,
            $invoice,
            'TESTTRADE'.date('YmdHis').mt_rand(100000, 999999),
            ['trace_id' => 'mix-restore-test'],
            $reason,
        );
    }

    private function countBalanceLogs(User $user, string $eventType, string $remarkPrefix): int
    {
        return AccountTransaction::query()
            ->where('user_id', (int) $user->id)
            ->where('event_type', $eventType)
            ->where('remark', 'like', $remarkPrefix.'%')
            ->count();
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'mix-restore-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'mix-restore-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeMixInvoice(User $user, float $balanceAmount, float $gatewayAmount): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => round($balanceAmount + $gatewayAmount, 2),
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
        ]);
    }

    private function makeMixPayment(User $user, Invoice $invoice, float $gatewayAmount, float $balanceAmount): Payment
    {
        return Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => $gatewayAmount,
            'status' => PaymentStatus::PENDING,
            'callback_raw' => [
                'source' => 'alipay_precreate_mix',
                'mix_payment' => true,
                'balance_amount' => number_format($balanceAmount, 2, '.', ''),
            ],
        ]);
    }
}
