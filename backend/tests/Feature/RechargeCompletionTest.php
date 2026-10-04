<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Models\AccountTransaction;
use App\Models\IntegrationPlugin;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\RechargeRecord;
use App\Models\User;
use App\Services\Finance\RechargePaymentService;
use App\Services\Integrations\Payments\Data\PaymentPrecreateRequest;
use App\Services\Integrations\Payments\Data\PaymentPrecreateResult;
use App\Services\Integrations\Payments\Data\PaymentQueryResult;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\User\AccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 充值到账主链路的行为快照（completeRechargePayment 此前零覆盖）：
 * 轮询/回调到账 -> 余额入账 + RECHARGE 台账（source=payment）+ 充值账单 + 充值凭证
 * 四表一致；幂等重放不重复入账；金额不符拒绝入账。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class RechargeCompletionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_recharge_settles_via_query_recharge_status_with_four_table_consistency(): void
    {
        $user = $this->makeUser();
        $accounts = app(AccountService::class);
        $accounts->setCashBalance($user, 0.00);
        $tradeNo = 'STUBTRADE'.mt_rand(100000, 999999);
        $this->registerStubGateway(new PaymentQueryResult(
            tradeStatus: 'TRADE_SUCCESS',
            tradeNo: $tradeNo,
            outTradeNo: '',
            totalAmount: '50.00',
            raw: ['stub' => true],
        ));

        $payload = app(RechargePaymentService::class)->rechargeByGateway($user, 50.00, PaymentGatewayCode::ALIPAY);
        $payment = Payment::query()->where('payment_no', $payload['payment_no'])->firstOrFail();
        $this->assertSame(PaymentStatus::PENDING, (int) $payment->status);
        $this->assertNull($payment->invoice_id);
        $this->assertEqualsWithDelta(0.00, $accounts->cashBalance($user), 0.001);

        $result = app(RechargePaymentService::class)->queryRechargeStatus($payment);
        $this->assertTrue((bool) ($result['paid'] ?? false));

        // 支付单：SUCCESS 且落网关流水号。
        $payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, (int) $payment->status);
        $this->assertSame($tradeNo, (string) $payment->trade_no);

        // 余额：精确入账一次。
        $this->assertEqualsWithDelta(50.00, $accounts->cashBalance($user), 0.001);

        // 台账：RECHARGE 一行，source 精确挂支付单，余额快照一致。
        $transaction = AccountTransaction::query()
            ->where('user_id', (int) $user->id)
            ->where('event_type', FinanceLedgerEventType::RECHARGE)
            ->firstOrFail();
        $this->assertSame('payment', (string) $transaction->source_type);
        $this->assertSame((int) $payment->id, (int) $transaction->source_id);
        $this->assertEqualsWithDelta(50.00, (float) $transaction->change_amount, 0.001);
        $this->assertEqualsWithDelta(50.00, (float) $transaction->balance_after, 0.001);

        // 充值账单：type=recharge，回调路径直接 PAID。
        $invoice = Invoice::query()->where('user_id', (int) $user->id)->where('type', InvoiceType::RECHARGE)->firstOrFail();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertEqualsWithDelta(50.00, (float) $invoice->amount, 0.001);

        // 充值凭证：scene=user_recharge，三向绑定（账单/支付单/台账）。
        $record = RechargeRecord::query()->where('invoice_id', (int) $invoice->id)->firstOrFail();
        $this->assertSame('user_recharge', (string) $record->scene);
        $this->assertSame('account_recharge', (string) $record->entry_type);
        $this->assertSame((int) $payment->id, (int) $record->payment_id);
        $this->assertSame((int) $transaction->id, (int) $record->account_transaction_id);

        // 幂等重放：重复轮询不重复加余额、不重复建账单/台账/凭证。
        $replay = app(RechargePaymentService::class)->queryRechargeStatus($payment);
        $this->assertTrue((bool) ($replay['paid'] ?? false));
        $this->assertEqualsWithDelta(50.00, $accounts->cashBalance($user), 0.001);
        $this->assertSame(1, $this->countLedger($user, FinanceLedgerEventType::RECHARGE));
        $this->assertSame(1, Invoice::query()->where('user_id', (int) $user->id)->where('type', InvoiceType::RECHARGE)->count());
        $this->assertSame(1, RechargeRecord::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_recharge_query_amount_mismatch_rejects_settlement(): void
    {
        $user = $this->makeUser();
        $accounts = app(AccountService::class);
        $accounts->setCashBalance($user, 0.00);
        $this->registerStubGateway(new PaymentQueryResult(
            tradeStatus: 'TRADE_SUCCESS',
            tradeNo: 'STUBTRADE'.mt_rand(100000, 999999),
            outTradeNo: '',
            totalAmount: '45.00',
            raw: ['stub' => true],
        ));

        $payload = app(RechargePaymentService::class)->rechargeByGateway($user, 50.00, PaymentGatewayCode::ALIPAY);
        $payment = Payment::query()->where('payment_no', $payload['payment_no'])->firstOrFail();

        // 轮询路径不经过验签，金额比对是唯一防线：不符拒绝入账。
        $result = app(RechargePaymentService::class)->queryRechargeStatus($payment);
        $this->assertFalse((bool) ($result['paid'] ?? false));

        $payment->refresh();
        $this->assertSame(PaymentStatus::PENDING, (int) $payment->status);
        $this->assertEqualsWithDelta(0.00, $accounts->cashBalance($user), 0.001);
        $this->assertSame(0, $this->countLedger($user, FinanceLedgerEventType::RECHARGE));
        $this->assertSame(0, Invoice::query()->where('user_id', (int) $user->id)->where('type', InvoiceType::RECHARGE)->count());
    }

    public function test_complete_recharge_payment_direct_call_is_idempotent(): void
    {
        $user = $this->makeUser();
        $accounts = app(AccountService::class);
        $accounts->setCashBalance($user, 0.00);

        $payment = Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'invoice_id' => null,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => 30.00,
            'status' => PaymentStatus::PENDING,
            'callback_raw' => ['source' => 'alipay_recharge_precreate'],
        ]);

        $service = app(RechargePaymentService::class);
        $this->invokeCompleteRechargePayment($service, $payment, 'TRADE'.mt_rand(100000, 999999));

        $payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, (int) $payment->status);
        $this->assertEqualsWithDelta(30.00, $accounts->cashBalance($user), 0.001);
        $transaction = AccountTransaction::query()
            ->where('user_id', (int) $user->id)
            ->where('event_type', FinanceLedgerEventType::RECHARGE)
            ->firstOrFail();
        $this->assertSame('payment', (string) $transaction->source_type);
        $this->assertSame((int) $payment->id, (int) $transaction->source_id);
        $invoice = Invoice::query()->where('user_id', (int) $user->id)->where('type', InvoiceType::RECHARGE)->firstOrFail();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertSame(1, RechargeRecord::query()->where('invoice_id', (int) $invoice->id)->count());

        // 幂等重放：已 SUCCESS 走补开票短路分支，余额与各单据不再变化。
        $this->invokeCompleteRechargePayment($service, $payment, 'TRADE'.mt_rand(100000, 999999));
        $this->assertEqualsWithDelta(30.00, $accounts->cashBalance($user), 0.001);
        $this->assertSame(1, $this->countLedger($user, FinanceLedgerEventType::RECHARGE));
        $this->assertSame(1, Invoice::query()->where('user_id', (int) $user->id)->where('type', InvoiceType::RECHARGE)->count());
    }

    /**
     * 通过反射调用私有的 completeRechargePayment，直接固化到账核心的行为快照
     * （循 MixPaymentBalanceRestoreTest 的反射先例）。
     */
    private function invokeCompleteRechargePayment(RechargePaymentService $service, Payment $payment, string $tradeNo): void
    {
        $method = new ReflectionMethod(RechargePaymentService::class, 'completeRechargePayment');
        $method->invoke($service, $payment, $tradeNo, []);
    }

    private function countLedger(User $user, string $eventType): int
    {
        return AccountTransaction::query()
            ->where('user_id', (int) $user->id)
            ->where('event_type', $eventType)
            ->count();
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'recharge-complete-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'recharge-complete-tester',
            'total_sales_amount' => 0,
            'is_verified' => 1,
        ]);
    }

    /**
     * 注册 alipay 网关测试替身：验签恒过、商户恒匹配、主动查询返回可控结果。
     * 真实网关插件的 RSA 验签在测试环境无法替身，注册前先禁用支付域已启用插件
     * （DatabaseTransactions 会回滚插件状态）。
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
                return '充值到账测试替身网关';
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
}
