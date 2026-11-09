<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Models\IntegrationPlugin;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\ClientInvoicePaymentWorkflowService;
use App\Services\Integrations\Payments\Data\PaymentPrecreateRequest;
use App\Services\Integrations\Payments\Data\PaymentPrecreateResult;
use App\Services\Integrations\Payments\Data\PaymentQueryResult;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Integrations\Plugins\PluginDomain;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SeedsFinanceBalance;
use Tests\TestCase;

/**
 * 组合支付（余额+网关）的渠道选择回归（M-22）：
 *
 * 修复前 `ClientInvoicePaymentWorkflowService::payByBalanceAndAlipay` 把第三方网关
 * 硬编码为 ALIPAY，用户在「易支付-微信」下勾选余额抵扣时，后端仍用真支付宝创建
 * 支付单（资金渠道错配）。修复后渠道取请求传入的 gateway / payment_type，
 * 未传才回落 alipay 兼容旧客户端。
 *
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class FixMixPaymentGatewaySelectionTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsFinanceBalance;

    public function test_mix_payment_uses_requested_gateway_instead_of_hardcoded_alipay(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 100.00);
        $this->registerStubGateways();

        $invoice = $this->makeUnpaidInvoice($user, 100.00);
        $sessionToken = (string) (app(CheckoutSecurityService::class)
            ->issueInvoicePaymentSession($invoice, (int) $user->id)['session_token'] ?? '');

        app(ClientInvoicePaymentWorkflowService::class)->payByBalanceAndAlipay(
            $user,
            (int) $invoice->id,
            $sessionToken,
            30.00,
            'yi_pay', // 前端透传的易支付 key，normalize 后是 yipay
            'wxpay',
            [],
            [],
            '127.0.0.1',
        );

        $payments = Payment::query()
            ->where('invoice_id', (int) $invoice->id)
            ->where('status', PaymentStatus::PENDING)
            ->get();

        $this->assertCount(1, $payments, '混付应只创建一条网关支付单');
        $this->assertSame(
            PaymentGatewayCode::YIPAY,
            $payments->first()->gatewayKey(),
            '混付必须使用请求传入的易支付渠道，而不是硬编码的真支付宝'
        );
        $this->assertSame('wxpay', (string) $payments->first()->payment_type);
        // 余额部分按请求预扣
        $this->assertEqualsWithDelta(70.00, $this->financeBalance($user), 0.001);
    }

    public function test_mix_payment_falls_back_to_alipay_when_gateway_missing(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 100.00);
        $this->registerStubGateways();

        $invoice = $this->makeUnpaidInvoice($user, 100.00);
        $sessionToken = (string) (app(CheckoutSecurityService::class)
            ->issueInvoicePaymentSession($invoice, (int) $user->id)['session_token'] ?? '');

        app(ClientInvoicePaymentWorkflowService::class)->payByBalanceAndAlipay(
            $user,
            (int) $invoice->id,
            $sessionToken,
            30.00,
            '', // 旧客户端不传渠道
            '',
            [],
            [],
            '127.0.0.1',
        );

        $payment = Payment::query()
            ->where('invoice_id', (int) $invoice->id)
            ->where('status', PaymentStatus::PENDING)
            ->firstOrFail();

        $this->assertSame(
            PaymentGatewayCode::ALIPAY,
            $payment->gatewayKey(),
            '未传渠道时必须回落支付宝以兼容旧客户端'
        );
    }

    public function test_mix_payment_api_accepts_gateway_and_payment_type_from_request(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 100.00);
        $this->registerStubGateways();

        $invoice = $this->makeUnpaidInvoice($user, 100.00);

        Sanctum::actingAs($user);
        $sessionToken = (string) $this->getJson("/api/v2/client/invoices/{$invoice->id}")
            ->assertOk()
            ->json('data.invoice.payment_options.payment_security.session_token');
        $this->assertNotSame('', $sessionToken, '账单详情未下发支付会话令牌');

        $this->postJson("/api/v2/client/invoices/{$invoice->id}/pay/mix", [
            'payment_session_token' => $sessionToken,
            'balance_amount' => '30.00',
            'gateway' => 'yipay',
            'payment_type' => 'wxpay',
        ])->assertOk();

        $payment = Payment::query()
            ->where('invoice_id', (int) $invoice->id)
            ->where('status', PaymentStatus::PENDING)
            ->firstOrFail();

        $this->assertSame(PaymentGatewayCode::YIPAY, $payment->gatewayKey());
        $this->assertSame('wxpay', (string) $payment->payment_type);
    }

    public function test_mix_payment_api_rejects_balance_amount_with_more_than_two_decimals(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 100.00);
        $this->registerStubGateways();

        $invoice = $this->makeUnpaidInvoice($user, 100.00);

        Sanctum::actingAs($user);
        $sessionToken = (string) $this->getJson("/api/v2/client/invoices/{$invoice->id}")
            ->assertOk()
            ->json('data.invoice.payment_options.payment_security.session_token');

        // M-6：10.999 不能静默舍入成 11.00，必须在入参校验就被拒绝
        $this->postJson("/api/v2/client/invoices/{$invoice->id}/pay/mix", [
            'payment_session_token' => $sessionToken,
            'balance_amount' => '10.999',
            'gateway' => 'yipay',
            'payment_type' => 'wxpay',
        ])->assertStatus(422);
    }

    /**
     * 注册两个可用的测试替身网关（alipay + yipay），并停用真实插件，
     * 避免本地插件配置影响可用支付方式列表。
     */
    private function registerStubGateways(): void
    {
        if (Schema::hasTable('integration_plugins')) {
            IntegrationPlugin::query()
                ->where('domain', PluginDomain::PAYMENT)
                ->update(['status' => IntegrationPlugin::STATUS_DISABLED]);
        }

        $registry = new PaymentGatewayRegistry([
            $this->stubGateway(PaymentGatewayCode::ALIPAY, '支付宝测试替身', []),
            $this->stubGateway(PaymentGatewayCode::YIPAY, '易支付测试替身', ['alipay', 'wxpay']),
        ]);

        $this->app->instance(PaymentGatewayRegistry::class, $registry);
        $this->app->forgetInstance(PaymentGatewayManager::class);
        $this->app->forgetInstance(ClientInvoicePaymentWorkflowService::class);
    }

    /**
     * @param  list<string>  $paymentTypes  声明的支付渠道（空数组 = 不声明，走网关级默认）
     */
    private function stubGateway(string $key, string $name, array $paymentTypes): PaymentGatewayInterface
    {
        return new class($key, $name, $paymentTypes) implements PaymentGatewayInterface
        {
            /**
             * @param  list<string>  $paymentTypes
             */
            public function __construct(
                private readonly string $key,
                private readonly string $name,
                private readonly array $paymentTypes,
            ) {}

            public function key(): string
            {
                return $this->key;
            }

            public function name(): string
            {
                return $this->name;
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

            /**
             * 渠道声明：同一插件下的支付宝与微信是两条独立支付方式。
             *
             * @return array<int, array<string, mixed>>
             */
            public function paymentOptions(): array
            {
                return array_map(fn (string $paymentType): array => [
                    'key' => $this->key,
                    'payment_type' => $paymentType,
                    'label' => PaymentGatewayCode::channelLabel($this->key, $paymentType),
                ], $this->paymentTypes);
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
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'mix-gateway-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'mix-gateway-tester',
            'status' => 1,
            'total_sales_amount' => 0,
        ]);
    }

    private function makeUnpaidInvoice(User $user, float $amount): Invoice
    {
        $order = Order::query()->create([
            'order_no' => 'ODMIXGW'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => $amount,
            'status' => 0,
        ]);

        return Invoice::query()->create([
            'invoice_no' => 'IVMIXGW'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => InvoiceType::NEW_PURCHASE,
            'amount' => $amount,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
        ]);
    }
}
