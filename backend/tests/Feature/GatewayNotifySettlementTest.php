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
use App\Models\RechargeRecord;
use App\Models\User;
use App\Services\Finance\GatewayInvoicePaymentService;
use App\Services\Integrations\Payments\Data\PaymentPrecreateRequest;
use App\Services\Integrations\Payments\Data\PaymentPrecreateResult;
use App\Services\Integrations\Payments\Data\PaymentQueryResult;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\User\AccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 在线支付入账主链路的行为快照（handleGatewayNotify 正常入账此前零覆盖）：
 * 成功回调 -> 支付单 SUCCESS + 账单 PAID + 第三方实付凭证 + 回调投影齐全；
 * 幂等重放、验签/商户/金额三道闸、已付账单重复回调转余额（credited_to_balance）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class GatewayNotifySettlementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_success_notify_settles_invoice_and_records_documents(): void
    {
        $user = $this->makeUser();
        $accounts = app(AccountService::class);
        $accounts->setCashBalance($user, 100.00);
        $this->registerStubGateway();

        $invoice = $this->makeInvoice($user, 100.00);
        $payload = app(GatewayInvoicePaymentService::class)->payByGateway($invoice, $user, PaymentGatewayCode::ALIPAY);
        $payment = Payment::query()->where('payment_no', $payload['payment_no'])->firstOrFail();
        $this->assertSame(PaymentStatus::PENDING, (int) $payment->status);

        $tradeNo = 'TRADE'.date('YmdHis').mt_rand(100000, 999999);
        $notifyParams = [
            'out_trade_no' => $payment->payment_no,
            'trade_no' => $tradeNo,
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '100.00',
            'app_id' => 'stub-merchant',
        ];
        $this->assertTrue(app(GatewayInvoicePaymentService::class)->handleGatewayNotify(PaymentGatewayCode::ALIPAY, $notifyParams));

        // 支付单与账单状态机推进，订单外资金不动余额。
        $payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, (int) $payment->status);
        $this->assertSame($tradeNo, (string) $payment->trade_no);
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertEqualsWithDelta(100.00, (float) $invoice->paid_amount, 0.001);
        $this->assertEqualsWithDelta(100.00, $accounts->cashBalance($user), 0.001);
        $this->assertSame(0, $this->countLedger($user, FinanceLedgerEventType::INVOICE_PAYMENT));

        // 第三方实付凭证：new 类型账单按 payment_id 幂等记一条 RechargeRecord。
        $record = RechargeRecord::query()->where('payment_id', (int) $payment->id)->firstOrFail();
        $this->assertSame('third_party_payment', (string) $record->entry_type);
        $this->assertSame('new_purchase', (string) $record->scene);
        $this->assertSame('100.00', (string) $record->amount);
        $this->assertSame((int) $invoice->id, (int) $record->invoice_id);

        // 回调投影：验签通过且带网关流水号。
        $callbacks = PaymentCallback::query()->where('payment_id', (int) $payment->id)->get();
        $this->assertSame(1, $callbacks->count());
        $this->assertSame('payment', (string) $callbacks->first()->callback_type);
        $this->assertSame(1, (int) $callbacks->first()->is_verified);
        $this->assertSame($tradeNo, (string) $callbacks->first()->gateway_trade_no);

        // 幂等重放：同一回调重复投递不重复入账、不重复记凭证。
        $this->assertTrue(app(GatewayInvoicePaymentService::class)->handleGatewayNotify(PaymentGatewayCode::ALIPAY, $notifyParams));
        $this->assertEqualsWithDelta(100.00, $accounts->cashBalance($user), 0.001);
        $this->assertSame(1, RechargeRecord::query()->where('payment_id', (int) $payment->id)->count());
        $this->assertSame(1, PaymentCallback::query()->where('payment_id', (int) $payment->id)->count());
    }

    public function test_verify_failure_rejects_without_any_side_effect(): void
    {
        $user = $this->makeUser();
        $this->registerStubGateway();
        $stub = $this->stub;
        $stub->verifyResult = false;

        $invoice = $this->makeInvoice($user, 50.00);
        $payload = app(GatewayInvoicePaymentService::class)->payByGateway($invoice, $user, PaymentGatewayCode::ALIPAY);
        $payment = Payment::query()->where('payment_no', $payload['payment_no'])->firstOrFail();

        $this->assertFalse(app(GatewayInvoicePaymentService::class)->handleGatewayNotify(PaymentGatewayCode::ALIPAY, [
            'out_trade_no' => $payment->payment_no,
            'trade_no' => 'TRADEFAKE'.mt_rand(100000, 999999),
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '50.00',
            'app_id' => 'stub-merchant',
        ]));

        $payment->refresh();
        $this->assertSame(PaymentStatus::PENDING, (int) $payment->status);
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->refresh()->status);
        $this->assertSame(0, $this->countVerifiedCallbacks((int) $payment->id));
    }

    public function test_missing_merchant_id_rejects_callback(): void
    {
        $user = $this->makeUser();
        $this->registerStubGateway();

        $invoice = $this->makeInvoice($user, 50.00);
        $payload = app(GatewayInvoicePaymentService::class)->payByGateway($invoice, $user, PaymentGatewayCode::ALIPAY);
        $payment = Payment::query()->where('payment_no', $payload['payment_no'])->firstOrFail();

        // 缺商户号字段不得跳过校验：网关要求配置了就必须匹配。
        $this->assertFalse(app(GatewayInvoicePaymentService::class)->handleGatewayNotify(PaymentGatewayCode::ALIPAY, [
            'out_trade_no' => $payment->payment_no,
            'trade_no' => 'TRADE'.mt_rand(100000, 999999),
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '50.00',
        ]));

        $payment->refresh();
        $this->assertSame(PaymentStatus::PENDING, (int) $payment->status);
        $this->assertSame(0, $this->countVerifiedCallbacks((int) $payment->id));
    }

    public function test_amount_mismatch_rejects_settlement(): void
    {
        $user = $this->makeUser();
        $this->registerStubGateway();

        $invoice = $this->makeInvoice($user, 50.00);
        $payload = app(GatewayInvoicePaymentService::class)->payByGateway($invoice, $user, PaymentGatewayCode::ALIPAY);
        $payment = Payment::query()->where('payment_no', $payload['payment_no'])->firstOrFail();

        $this->assertFalse(app(GatewayInvoicePaymentService::class)->handleGatewayNotify(PaymentGatewayCode::ALIPAY, [
            'out_trade_no' => $payment->payment_no,
            'trade_no' => 'TRADE'.mt_rand(100000, 999999),
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '49.00',
            'app_id' => 'stub-merchant',
        ]));

        $payment->refresh();
        $this->assertSame(PaymentStatus::PENDING, (int) $payment->status);
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->refresh()->status);
        $this->assertSame(0, $this->countVerifiedCallbacks((int) $payment->id));
    }

    public function test_non_success_trade_status_records_callback_without_settlement(): void
    {
        $user = $this->makeUser();
        $this->registerStubGateway();

        $invoice = $this->makeInvoice($user, 50.00);
        $payload = app(GatewayInvoicePaymentService::class)->payByGateway($invoice, $user, PaymentGatewayCode::ALIPAY);
        $payment = Payment::query()->where('payment_no', $payload['payment_no'])->firstOrFail();

        $this->assertTrue(app(GatewayInvoicePaymentService::class)->handleGatewayNotify(PaymentGatewayCode::ALIPAY, [
            'out_trade_no' => $payment->payment_no,
            'trade_no' => 'TRADE'.mt_rand(100000, 999999),
            'trade_status' => 'WAIT_BUYER_PAY',
            'total_amount' => '50.00',
            'app_id' => 'stub-merchant',
        ]));

        // 非成功状态：记录回调供审计，但不入账。
        $payment->refresh();
        $this->assertSame(PaymentStatus::PENDING, (int) $payment->status);
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->refresh()->status);
        $this->assertSame(1, PaymentCallback::query()->where('payment_id', (int) $payment->id)->count());
    }

    public function test_duplicate_notify_after_invoice_paid_credits_gateway_amount_once(): void
    {
        $user = $this->makeUser();
        $accounts = app(AccountService::class);
        $accounts->setCashBalance($user, 100.00);
        $this->registerStubGateway();

        $invoice = $this->makeInvoice($user, 100.00);
        $payload = app(GatewayInvoicePaymentService::class)->payByGateway($invoice, $user, PaymentGatewayCode::ALIPAY);
        $payment = Payment::query()->where('payment_no', $payload['payment_no'])->firstOrFail();
        app(GatewayInvoicePaymentService::class)->handleGatewayNotify(PaymentGatewayCode::ALIPAY, [
            'out_trade_no' => $payment->payment_no,
            'trade_no' => 'TRADEFIRST'.mt_rand(100000, 999999),
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '100.00',
            'app_id' => 'stub-merchant',
        ]);

        // 账单已 PAID 后另一笔同金额支付单的成功回调：不得二次推进账单，网关款转入余额。
        $secondPayment = Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => 100.00,
            'status' => PaymentStatus::PENDING,
            'callback_raw' => ['source' => 'alipay_precreate'],
        ]);
        $this->assertTrue(app(GatewayInvoicePaymentService::class)->handleGatewayNotify(PaymentGatewayCode::ALIPAY, [
            'out_trade_no' => $secondPayment->payment_no,
            'trade_no' => 'TRADESECOND'.mt_rand(100000, 999999),
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '100.00',
            'app_id' => 'stub-merchant',
        ]));

        $secondPayment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, (int) $secondPayment->status);
        $raw = (array) ($secondPayment->callback_raw ?? []);
        $this->assertTrue((bool) ($raw['credited_to_balance'] ?? false));
        $this->assertSame('invoice_already_paid', (string) ($raw['credit_reason'] ?? ''));
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertEqualsWithDelta(200.00, $accounts->cashBalance($user), 0.001);
        $this->assertSame(1, $this->countLedger($user, FinanceLedgerEventType::RECHARGE, '异常支付转入余额'));

        // 幂等闸：同一支付单重复回调不重复转余额。
        $this->assertTrue(app(GatewayInvoicePaymentService::class)->handleGatewayNotify(PaymentGatewayCode::ALIPAY, [
            'out_trade_no' => $secondPayment->payment_no,
            'trade_no' => 'TRADESECOND'.mt_rand(100000, 999999),
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '100.00',
            'app_id' => 'stub-merchant',
        ]));
        $this->assertEqualsWithDelta(200.00, $accounts->cashBalance($user), 0.001);
        $this->assertSame(1, $this->countLedger($user, FinanceLedgerEventType::RECHARGE, '异常支付转入余额'));
    }

    private ?object $stub = null;

    /**
     * 注册 alipay 网关测试替身：验签/商户号/查询结果可按用例切换（与 MixPaymentBalanceRestoreTest 同思路）。
     * 真实网关插件的 RSA 验签在测试环境无法替身，注册前先禁用支付域已启用插件，
     * 避免真实适配器占用 alipay key 导致替身注册冲突（DatabaseTransactions 会回滚插件状态）。
     */
    private function registerStubGateway(): void
    {
        if (Schema::hasTable('integration_plugins')) {
            IntegrationPlugin::query()
                ->where('domain', PluginDomain::PAYMENT)
                ->update(['status' => IntegrationPlugin::STATUS_DISABLED]);
        }

        $this->stub = new class implements PaymentGatewayInterface
        {
            public bool $verifyResult = true;

            public string $expectedMerchantId = 'stub-merchant';

            public ?PaymentQueryResult $queryResult = null;

            public function key(): string
            {
                return PaymentGatewayCode::ALIPAY;
            }

            public function name(): string
            {
                return '在线入账测试替身网关';
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function matchesMerchantId(?string $merchantId): bool
            {
                return (string) $merchantId === $this->expectedMerchantId;
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
                return $this->verifyResult;
            }

            public function buildNotifyResponse(bool $success): Response
            {
                return response($success ? 'success' : 'fail', 200)
                    ->header('Content-Type', 'text/plain');
            }
        };

        app(PaymentGatewayRegistry::class)->register($this->stub);
    }

    /**
     * 统计验签通过的入账回调投影行（payByGateway 下单时 syncProjection 会写一条
     * is_verified=0 的预投影行，notify 后 updateOrInsert 覆盖同一行）。
     */
    private function countVerifiedCallbacks(int $paymentId): int
    {
        return PaymentCallback::query()
            ->where('payment_id', $paymentId)
            ->where('is_verified', 1)
            ->count();
    }

    private function countLedger(User $user, string $eventType, ?string $remarkPrefix = null): int
    {
        $query = AccountTransaction::query()
            ->where('user_id', (int) $user->id)
            ->where('event_type', $eventType);

        if ($remarkPrefix !== null) {
            $query->where('remark', 'like', $remarkPrefix.'%');
        }

        return $query->count();
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'notify-settle-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'notify-settle-tester',
            'total_sales_amount' => 0,
            'is_verified' => 1,
        ]);
    }

    private function makeInvoice(User $user, float $amount): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => $amount,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
        ]);
    }
}
