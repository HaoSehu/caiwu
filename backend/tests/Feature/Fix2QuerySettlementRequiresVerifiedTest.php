<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Models\IntegrationPlugin;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Models\User;
use App\Services\Finance\GatewayInvoicePaymentService;
use App\Services\Integrations\Payments\Data\PaymentPrecreateRequest;
use App\Services\Integrations\Payments\Data\PaymentPrecreateResult;
use App\Services\Integrations\Payments\Data\PaymentQueryResult;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Integrations\Plugins\PluginDomain;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\Support\SeedsFinanceBalance;
use Tests\TestCase;

/**
 * M-39 回归：主动查询结果未携带验签标记时不得作为入账依据。
 *
 * 第一轮已在网关客户端补响应验签（有 sign 必验、失败抛异常），但无 sign / 无公钥时
 * 客户端仍返回查询结果。后端入账闸门要求结果显式携带 verified=true 才走结算，
 * 否则只落一条未验签回调证据、支付单保持 PENDING，由异步回调路径完成入账。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class Fix2QuerySettlementRequiresVerifiedTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsFinanceBalance;

    public function test_unverified_active_query_records_callback_but_keeps_payment_pending(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 100.00);
        $this->registerStubGateway(new PaymentQueryResult(
            tradeStatus: 'TRADE_SUCCESS',
            tradeNo: 'STUBQUERY'.mt_rand(100000, 999999),
            outTradeNo: '',
            totalAmount: '100.00',
            // 无 verified 标记：响应未经签名验证，只能作前端提示。
            raw: ['stub' => true],
        ));

        $invoice = $this->makeInvoice($user, 100.00);
        $payload = app(GatewayInvoicePaymentService::class)->payByGateway($invoice, $user, PaymentGatewayCode::ALIPAY);
        $payment = Payment::query()->where('payment_no', $payload['payment_no'])->firstOrFail();

        $result = app(GatewayInvoicePaymentService::class)->queryGatewayPaymentStatus($payment);

        // 查询结果不驱动入账：前端仍按未支付展示，等异步回调完成结算。
        $this->assertFalse((bool) $result['paid']);
        $payment->refresh();
        $this->assertSame(PaymentStatus::PENDING, (int) $payment->status);
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->status);
        $this->assertEqualsWithDelta(0.00, (float) $invoice->paid_amount, 0.001);

        // 未验签回调证据已留痕，来源为主动查询。
        $callback = PaymentCallback::query()->where('payment_id', (int) $payment->id)->firstOrFail();
        $this->assertSame(0, (int) $callback->is_verified);
        $callbackPayload = (array) $callback->payload_json;
        $this->assertSame('active_query', (string) ($callbackPayload['source'] ?? ''));
    }

    public function test_verified_active_query_settles_invoice(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 100.00);
        $this->registerStubGateway(new PaymentQueryResult(
            tradeStatus: 'TRADE_SUCCESS',
            tradeNo: 'STUBVERIFIED'.mt_rand(100000, 999999),
            outTradeNo: '',
            totalAmount: '100.00',
            // 网关客户端已完成响应验签并透传标记：允许作为入账依据。
            raw: ['stub' => true, 'verified' => true],
        ));

        $invoice = $this->makeInvoice($user, 100.00);
        $payload = app(GatewayInvoicePaymentService::class)->payByGateway($invoice, $user, PaymentGatewayCode::ALIPAY);
        $payment = Payment::query()->where('payment_no', $payload['payment_no'])->firstOrFail();

        $result = app(GatewayInvoicePaymentService::class)->queryGatewayPaymentStatus($payment);

        $this->assertTrue((bool) $result['paid']);
        $payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, (int) $payment->status);
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertEqualsWithDelta(100.00, (float) $invoice->paid_amount, 0.001);

        $callback = PaymentCallback::query()->where('payment_id', (int) $payment->id)->firstOrFail();
        $this->assertSame(1, (int) $callback->is_verified);
    }

    /**
     * 注册 alipay 网关测试替身：查询返回指定结果。
     * 注册前禁用支付域已启用插件并清空注册表，避免真实适配器占用 alipay key。
     */
    private function registerStubGateway(PaymentQueryResult $queryResult): void
    {
        $registry = app(PaymentGatewayRegistry::class);
        $gateways = new ReflectionProperty(PaymentGatewayRegistry::class, 'gateways');
        $gateways->setAccessible(true);
        $gateways->setValue($registry, []);

        if (Schema::hasTable('integration_plugins')) {
            IntegrationPlugin::query()
                ->where('domain', PluginDomain::PAYMENT)
                ->update(['status' => IntegrationPlugin::STATUS_DISABLED]);
        }

        app(PaymentGatewayRegistry::class)->register(new class($queryResult) implements PaymentGatewayInterface
        {
            public function __construct(private readonly PaymentQueryResult $queryResult) {}

            public function key(): string
            {
                return PaymentGatewayCode::ALIPAY;
            }

            public function name(): string
            {
                return '查询验签测试替身网关';
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
                    tradeStatus: $this->queryResult->tradeStatus,
                    tradeNo: $this->queryResult->tradeNo,
                    outTradeNo: $outTradeNo,
                    totalAmount: $this->queryResult->totalAmount,
                    raw: $this->queryResult->raw,
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

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'query-verified-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'query-verified-tester',
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
