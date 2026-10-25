<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Contracts\Integrations\PluginIdentity;
use App\Models\FinanceRefund;
use App\Models\IntegrationPlugin;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\VerificationHistory;
use App\Services\Auth\VerificationService;
use App\Services\Finance\InvoiceRefundService;
use App\Services\Finance\RechargePaymentService;
use App\Services\Integrations\Payments\Data\PaymentPrecreateRequest;
use App\Services\Integrations\Payments\Data\PaymentPrecreateResult;
use App\Services\Integrations\Payments\Data\PaymentQueryResult;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use App\Services\Integrations\Payments\PaymentGatewayOperationService;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Integrations\Plugins\PaymentGatewayBindingResolver;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\System\GatewayLogService;
use App\Services\Verification\Contracts\VerificationDriver;
use App\Services\Verification\Contracts\VerifiesVerificationCallbacks;
use App\Services\Verification\Data\VerificationCallbackRequest;
use App\Services\Verification\Data\VerificationCallbackVerificationResult;
use App\Services\Verification\Data\VerificationInitializeRequest;
use App\Services\Verification\Data\VerificationInitializeResult;
use App\Services\Verification\Data\VerificationScanUrlResult;
use App\Services\Verification\Data\VerificationStatusResult;
use App\Services\Verification\VerificationDriverManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Verification\FakeVerificationDriver;
use Tests\Support\SeedsFinanceBalance;
use Tests\TestCase;

/**
 * 插件数据链写入链路回归（批次 2）：支付创建/退款/充值/网关日志/实名三处写入/实名回调驱动选择。
 */
class PluginDataChainWritePathTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsFinanceBalance;

    #[Test]
    public function payment_options_carry_plugin_identity(): void
    {
        $this->registerYiPayIdentityGateway();
        $this->registerAliPayIdentityGateway();

        $options = app(PaymentGatewayManager::class)->paymentOptionsFor(PaymentGatewayCode::YIPAY);
        $this->assertCount(2, $options);

        $wechat = $options[0];
        $this->assertSame('yipay', $wechat['key']);
        $this->assertSame('易支付 - 微信', $wechat['name']);
        $this->assertSame('yipay:wxpay', $wechat['option_key']);
        $this->assertSame('wxpay', $wechat['payment_type']);
        $this->assertSame(778, $wechat['plugin_id']);
        $this->assertSame('yi_pay', $wechat['plugin_key']);
        $this->assertSame('易支付', $wechat['plugin_name']);

        $default = app(PaymentGatewayManager::class)->paymentOptionsFor(PaymentGatewayCode::ALIPAY);
        $this->assertCount(1, $default);
        $this->assertSame('alipay', $default[0]['key']);
        $this->assertSame('alipay', $default[0]['option_key']);
        $this->assertSame(777, $default[0]['plugin_id']);
        $this->assertSame('ali_pay', $default[0]['plugin_key']);
        $this->assertSame('支付宝当面付', $default[0]['plugin_name']);
    }

    #[Test]
    public function payment_create_payload_carries_plugin_snapshot_and_channel(): void
    {
        $this->registerYiPayIdentityGateway();

        $payload = app(PaymentGatewayOperationService::class)->paymentCreatePayload([
            'payment_no' => 'PY'.uniqid(),
            'user_id' => 1,
            'gateway' => PaymentGatewayCode::YIPAY,
            'payment_type' => 'wxpay',
            'amount' => 1.00,
            'status' => PaymentStatus::PENDING,
            'callback_raw' => [],
        ]);

        $this->assertArrayNotHasKey('gateway', $payload);
        $this->assertSame('yipay', $payload['gateway_key']);
        $this->assertSame(778, $payload['plugin_id']);
        $this->assertSame('yi_pay', $payload['plugin_key']);
        $this->assertSame('易支付', $payload['plugin_name']);
        $this->assertSame('wxpay', $payload['payment_type']);
        $this->assertSame('yipay:wxpay', $payload['option_key']);
        $this->assertSame('易支付 - 微信', $payload['option_name']);
    }

    #[Test]
    public function payment_create_payload_defaults_option_for_gateway_without_declared_options(): void
    {
        $this->registerAliPayIdentityGateway();

        $payload = app(PaymentGatewayOperationService::class)->paymentCreatePayload([
            'payment_no' => 'PY'.uniqid(),
            'user_id' => 1,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => 1.00,
            'status' => PaymentStatus::PENDING,
            'callback_raw' => [],
        ]);

        $this->assertSame('alipay', $payload['gateway_key']);
        $this->assertSame(777, $payload['plugin_id']);
        $this->assertSame('ali_pay', $payload['plugin_key']);
        $this->assertArrayNotHasKey('payment_type', $payload);
        $this->assertSame('alipay', $payload['option_key']);
        $this->assertSame('支付宝当面付', $payload['option_name']);
    }

    #[Test]
    public function payment_audit_only_fills_empty_snapshot_columns(): void
    {
        $this->registerYiPayIdentityGateway();
        $user = $this->makeUser('plugin-audit');

        $plugin = IntegrationPlugin::query()->create([
            'domain' => PluginDomain::PAYMENT,
            'slug' => 'yi-pay-audit-test',
            'plugin_key' => PaymentGatewayCode::YIPAY,
            'name' => '易支付',
            'entry_class' => 'App\\Plugins\\Demo',
            'status' => IntegrationPlugin::STATUS_ENABLED,
        ]);

        $empty = Payment::query()->create([
            'payment_no' => 'PYAUDIT'.uniqid(),
            'user_id' => $user->id,
            'gateway' => PaymentGatewayCode::YIPAY,
            'amount' => 1.00,
            'status' => PaymentStatus::PENDING,
            'callback_raw' => [],
        ]);
        $this->assertNull($empty->plugin_id);

        app(PaymentGatewayOperationService::class)->ensurePaymentAudit($empty, PaymentGatewayCode::YIPAY);

        $filled = $empty->fresh();
        // plugin_id 由绑定解析器 context 提供（快照链路刻意不覆盖该列）。
        $this->assertSame((int) $plugin->id, (int) $filled->plugin_id);
        $this->assertSame('yi_pay', $filled->plugin_key);
        $this->assertSame('易支付', $filled->plugin_name);
        $this->assertSame('yipay', $filled->gateway_key);

        $kept = Payment::query()->create([
            'payment_no' => 'PYAUDIT'.uniqid(),
            'user_id' => $user->id,
            'gateway' => PaymentGatewayCode::YIPAY,
            'amount' => 1.00,
            'status' => PaymentStatus::PENDING,
            'plugin_key' => 'keep-me',
            'callback_raw' => [],
        ]);

        app(PaymentGatewayOperationService::class)->ensurePaymentAudit($kept, PaymentGatewayCode::YIPAY);

        $this->assertSame('keep-me', $kept->fresh()->plugin_key);
    }

    #[Test]
    public function plugin_snapshot_resolver_never_guesses_ambiguous_identity(): void
    {
        $resolver = app(PaymentGatewayBindingResolver::class);

        $this->assertSame(
            ['plugin_id' => null, 'plugin_key' => null, 'plugin_name' => null],
            $resolver->pluginSnapshotForGateway(PaymentGatewayCode::ALIPAY),
        );

        $plugin = IntegrationPlugin::query()->create([
            'domain' => PluginDomain::PAYMENT,
            'slug' => 'ali-pay-snapshot-test',
            'plugin_key' => PaymentGatewayCode::ALIPAY,
            'name' => '支付宝当面付',
            'entry_class' => 'App\\Plugins\\Demo',
        ]);

        $snapshot = $resolver->pluginSnapshotForGateway(PaymentGatewayCode::ALIPAY);
        $this->assertSame((int) $plugin->id, $snapshot['plugin_id']);
        $this->assertSame('alipay', $snapshot['plugin_key']);
        $this->assertSame('支付宝当面付', $snapshot['plugin_name']);

        $this->assertSame(
            ['plugin_id' => null, 'plugin_key' => null, 'plugin_name' => null],
            $resolver->pluginSnapshotForGateway(PaymentGatewayCode::BALANCE),
        );
        $this->assertSame(
            ['plugin_id' => null, 'plugin_key' => null, 'plugin_name' => null],
            $resolver->pluginSnapshotForGateway(''),
        );
    }

    #[Test]
    public function refund_document_copies_payment_plugin_snapshot(): void
    {
        $payer = $this->makeUser('plugin-refund');
        $this->seedFinanceBalance($payer, 0.00);

        $plugin = IntegrationPlugin::query()->create([
            'domain' => PluginDomain::PAYMENT,
            'slug' => 'yi-pay-refund-test',
            'plugin_key' => PaymentGatewayCode::YIPAY,
            'name' => '易支付',
            'entry_class' => 'App\\Plugins\\Demo',
        ]);

        $order = $this->makeOrder($payer, 100.00);
        $invoice = $this->makeInvoice($payer, 100.00, ['order_id' => $order->id]);
        $payment = $this->makeSuccessPayment($payer, $invoice, 100.00);
        $payment->forceFill([
            'plugin_id' => $plugin->id,
            'plugin_key' => 'yi_pay',
            'plugin_name' => '易支付',
            'payment_type' => 'wxpay',
            'option_key' => 'yipay:wxpay',
            'option_name' => '易支付 - 微信',
        ])->save();

        $order->forceFill(['status' => OrderStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();

        app(InvoiceRefundService::class)->refundInvoiceToBalance($payer, $invoice, [], [
            'operator_name' => '插件快照测试',
            'operator_id' => 1,
            'trace_id' => 'plugin-refund-test',
        ]);

        $refund = FinanceRefund::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame((int) $plugin->id, (int) $refund->plugin_id);
        $this->assertSame('yi_pay', $refund->plugin_key);
        $this->assertSame('易支付', $refund->plugin_name);
        $this->assertSame('alipay', $refund->gateway_key);
        $this->assertSame('wxpay', $refund->payment_type);
    }

    #[Test]
    public function recharge_callback_raw_only_keeps_payment_context(): void
    {
        $user = $this->makeVerifiedUser('plugin-recharge');
        $this->seedFinanceBalance($user, 0.00);
        $this->registerRechargeStubGateway();

        $result = app(RechargePaymentService::class)->rechargeByGateway(
            $user,
            50.00,
            PaymentGatewayCode::ALIPAY,
            ['payment_type' => 'wxpay'],
        );

        $payment = Payment::query()->where('payment_no', $result['payment_no'])->firstOrFail();

        $this->assertSame('wxpay', $payment->payment_type);
        $this->assertSame('alipay:wxpay', $payment->option_key);
        $this->assertSame(['payment_type' => 'wxpay'], $payment->callback_raw['payment_context']);
        $this->assertArrayNotHasKey('payment_type', $payment->callback_raw);
    }

    #[Test]
    public function gateway_log_records_plugin_key_with_raw_gateway_string(): void
    {
        $user = $this->makeUser('plugin-log');
        $payment = Payment::query()->create([
            'payment_no' => 'PYLOG'.uniqid(),
            'user_id' => $user->id,
            'gateway' => PaymentGatewayCode::YIPAY,
            'plugin_key' => 'yi_pay',
            'plugin_name' => '易支付',
            'amount' => 1.00,
            'status' => PaymentStatus::PENDING,
            'callback_raw' => [],
        ]);

        $service = app(GatewayLogService::class);

        $fromPayment = $service->record(
            'yipay',
            'precreate',
            $payment->payment_no,
            null,
            null,
            ['step' => 'precreate'],
            ['ok' => true],
            'success',
        );
        $this->assertSame('yi_pay', $fromPayment->plugin_key);
        $this->assertSame('yipay', $fromPayment->gateway);

        $plugin = IntegrationPlugin::query()->create([
            'domain' => PluginDomain::PAYMENT,
            'slug' => 'yi-pay-log-test',
            'plugin_key' => PaymentGatewayCode::YIPAY,
            'name' => '易支付',
            'entry_class' => 'App\\Plugins\\Demo',
        ]);

        $fromResolver = $service->record(
            'yi_pay',
            'precreate',
            'NO-SUCH-PAYMENT-'.uniqid(),
            null,
            null,
            [],
            [],
            'unknown',
        );
        // gateway 列保留插件上报原文（yi_pay），gateway_key 归一化为 yipay。
        $this->assertSame('yi_pay', $fromResolver->gateway);
        $this->assertSame('yipay', $fromResolver->gateway_key);
        $this->assertSame('yipay', $fromResolver->plugin_key);
        $this->assertSame((int) $plugin->id, (int) $fromResolver->plugin_id);

        $nonThirdParty = $service->record('balance', 'precreate', 'NO-SUCH-PAYMENT-'.uniqid());
        $this->assertNull($nonThirdParty->plugin_key);
        $this->assertNull($nonThirdParty->plugin_id);
    }

    #[Test]
    public function verification_history_writes_carry_channel_on_create_sync_and_unbind(): void
    {
        config(['integrations.identity.default' => 'baidu_face']);

        $plugin = IntegrationPlugin::query()->create([
            'domain' => PluginDomain::VERIFICATION,
            'slug' => 'baidu-face-snapshot-test',
            'plugin_key' => 'baidu_face',
            'name' => '百度智能云人脸实名认证',
            'entry_class' => 'App\\Plugins\\Demo',
            'status' => IntegrationPlugin::STATUS_ENABLED,
        ]);

        app(VerificationDriverManager::class)->register(new FakeVerificationDriver('baidu_face'));

        $user = $this->makeUser('plugin-verification');
        $service = app(VerificationService::class);

        $session = $service->startVerificationSession($user, '张三', '110101199001011234');
        $this->assertSame('cert-fake-001', $session['certify_id']);

        $created = VerificationHistory::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame((int) $plugin->id, (int) $created->plugin_id);
        $this->assertSame('baidu_face', $created->plugin_key);
        $this->assertSame('百度智能云人脸实名认证', $created->plugin_name);
        $this->assertSame('baidu_face', $created->driver_key);

        $verified = $service->syncUserStatus($user->fresh(), ['status' => 1, 'msg' => '审核通过'], $session['certify_id']);
        $this->assertSame(2, (int) $verified->verification_status);

        $synced = VerificationHistory::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame(2, (int) $synced->verification_status);
        $this->assertNotNull($synced->completed_at);
        $this->assertSame('baidu_face', $synced->plugin_key);
        $this->assertSame('百度智能云人脸实名认证', $synced->plugin_name);
        $this->assertSame('baidu_face', $synced->driver_key);

        $service->unbind($verified->fresh(), 1, '管理员', '测试解绑');

        $unbound = VerificationHistory::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame(5, (int) $unbound->verification_status);
        $this->assertSame((int) $plugin->id, (int) $unbound->plugin_id);
        $this->assertSame('baidu_face', $unbound->plugin_key);
        $this->assertSame('百度智能云人脸实名认证', $unbound->plugin_name);
        $this->assertSame('baidu_face', $unbound->driver_key);
    }

    #[Test]
    public function verification_callback_resolves_driver_from_history_record(): void
    {
        $user = $this->makeUser('plugin-callback');

        $spy = new VerificationCallbackSpy;

        app(VerificationDriverManager::class)->register(new FakeVerificationDriver('stay33'));
        app(VerificationDriverManager::class)->register($this->makeCallbackVerifyingDriver('baidu_face', $spy));

        $matchedCertifyId = 'cert-v06-'.uniqid();
        $this->makeVerificationHistory($user, $matchedCertifyId, 'baidu_face');

        $response = $this->postJson('/api/v2/client/verification/callback', ['certify_id' => $matchedCertifyId]);
        $response->assertStatus(404);
        $this->assertSame(1, $spy->calls);
        $response->assertJsonPath('code', 40400);

        $unverifiedCertifyId = 'cert-v06-stay33-'.uniqid();
        $this->makeVerificationHistory($user, $unverifiedCertifyId, 'stay33');

        $rejected = $this->postJson('/api/v2/client/verification/callback', ['certify_id' => $unverifiedCertifyId]);
        $rejected->assertStatus(401);
        $rejected->assertJsonPath('code', 40001);
        $this->assertSame(1, $spy->calls);
    }

    private function makeCallbackVerifyingDriver(string $driverKey, VerificationCallbackSpy $spy): VerificationDriver
    {
        return new class($driverKey, $spy) implements VerificationDriver, VerifiesVerificationCallbacks
        {
            public function __construct(
                private readonly string $driverKey,
                private readonly VerificationCallbackSpy $spy,
            ) {}

            public function key(): string
            {
                return $this->driverKey;
            }

            public function label(): string
            {
                return '可验签实名驱动';
            }

            public function initialize(VerificationInitializeRequest $request): VerificationInitializeResult
            {
                return new VerificationInitializeResult(200, '初始化成功', 'cert-v06-'.uniqid());
            }

            public function generateScanUrl(string $certifyId): VerificationScanUrlResult
            {
                return new VerificationScanUrlResult(200, '获取成功', 'https://verify.example.test/'.rawurlencode($certifyId));
            }

            public function queryStatus(string $certifyId): VerificationStatusResult
            {
                return new VerificationStatusResult(1, '审核通过');
            }

            public function verifyCallback(VerificationCallbackRequest $request): VerificationCallbackVerificationResult
            {
                $this->spy->calls++;

                return VerificationCallbackVerificationResult::pass();
            }
        };
    }

    private function makeVerificationHistory(User $user, string $certifyId, string $driverKey): VerificationHistory
    {
        return VerificationHistory::query()->create([
            'user_id' => $user->id,
            'real_name' => '张三',
            'id_card' => '110101199001011234',
            'verification_status' => 4,
            'verification_message' => '等待认证',
            'verification_certify_id' => $certifyId,
            'verification_biz_code' => 'FACE',
            'verification_type' => 'personal',
            'driver_key' => $driverKey,
            'submitted_at' => now(),
        ]);
    }

    private function registerYiPayIdentityGateway(): void
    {
        $this->disablePaymentPlugins();

        app(PaymentGatewayRegistry::class)->register(new class implements PaymentGatewayInterface, PluginIdentity
        {
            public function key(): string
            {
                return PaymentGatewayCode::YIPAY;
            }

            public function name(): string
            {
                return '易支付';
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
                return new PaymentPrecreateResult('https://stub.gateway.test/qr/'.rawurlencode($request->outTradeNo), $request->outTradeNo);
            }

            public function query(string $outTradeNo): PaymentQueryResult
            {
                return new PaymentQueryResult('WAIT_BUYER_PAY', '', $outTradeNo, '0.00');
            }

            public function verifyNotify(array $payload): bool
            {
                return true;
            }

            public function buildNotifyResponse(bool $success): Response
            {
                return response($success ? 'success' : 'fail', 200)->header('Content-Type', 'text/plain');
            }

            /**
             * @return array<int, array<string, mixed>>
             */
            public function paymentOptions(): array
            {
                return [
                    [
                        'key' => PaymentGatewayCode::YIPAY,
                        'name' => '易支付 - 微信',
                        'label' => '微信支付',
                        'option_key' => 'yipay:wxpay',
                        'payment_type' => 'wxpay',
                    ],
                    [
                        'key' => PaymentGatewayCode::YIPAY,
                        'name' => '易支付 - 支付宝',
                        'label' => '支付宝',
                        'option_key' => 'yipay:alipay',
                        'payment_type' => 'alipay',
                    ],
                ];
            }

            public function pluginId(): int
            {
                return 778;
            }

            public function pluginKey(): string
            {
                return 'yi_pay';
            }

            public function pluginSlug(): string
            {
                return 'yi-pay-test';
            }

            public function pluginName(): string
            {
                return '易支付';
            }
        });
    }

    private function registerAliPayIdentityGateway(): void
    {
        $this->disablePaymentPlugins();

        app(PaymentGatewayRegistry::class)->register(new class implements PaymentGatewayInterface, PluginIdentity
        {
            public function key(): string
            {
                return PaymentGatewayCode::ALIPAY;
            }

            public function name(): string
            {
                return '支付宝当面付';
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
                return new PaymentPrecreateResult('https://stub.gateway.test/qr/'.rawurlencode($request->outTradeNo), $request->outTradeNo);
            }

            public function query(string $outTradeNo): PaymentQueryResult
            {
                return new PaymentQueryResult('WAIT_BUYER_PAY', '', $outTradeNo, '0.00');
            }

            public function verifyNotify(array $payload): bool
            {
                return true;
            }

            public function buildNotifyResponse(bool $success): Response
            {
                return response($success ? 'success' : 'fail', 200)->header('Content-Type', 'text/plain');
            }

            public function pluginId(): int
            {
                return 777;
            }

            public function pluginKey(): string
            {
                return 'ali_pay';
            }

            public function pluginSlug(): string
            {
                return 'ali-pay-test';
            }

            public function pluginName(): string
            {
                return '支付宝当面付';
            }
        });
    }

    private function registerRechargeStubGateway(): void
    {
        $this->disablePaymentPlugins();

        app(PaymentGatewayRegistry::class)->register(new class implements PaymentGatewayInterface
        {
            public function key(): string
            {
                return PaymentGatewayCode::ALIPAY;
            }

            public function name(): string
            {
                return '充值链路测试替身网关';
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
                return new PaymentPrecreateResult('https://stub.gateway.test/qr/'.rawurlencode($request->outTradeNo), $request->outTradeNo, ['stub' => true]);
            }

            public function query(string $outTradeNo): PaymentQueryResult
            {
                return new PaymentQueryResult('WAIT_BUYER_PAY', '', $outTradeNo, '0.00', ['stub' => true]);
            }

            public function verifyNotify(array $payload): bool
            {
                return true;
            }

            public function buildNotifyResponse(bool $success): Response
            {
                return response($success ? 'success' : 'fail', 200)->header('Content-Type', 'text/plain');
            }
        });
    }

    private function disablePaymentPlugins(): void
    {
        if (! Schema::hasTable('integration_plugins')) {
            return;
        }

        IntegrationPlugin::query()
            ->where('domain', PluginDomain::PAYMENT)
            ->update(['status' => IntegrationPlugin::STATUS_DISABLED]);
    }

    private function makeUser(string $prefix): User
    {
        return User::query()->create([
            'email' => $prefix.'-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $prefix.'-tester',
            'total_sales_amount' => 0,
            'status' => 1,
        ]);
    }

    private function makeVerifiedUser(string $prefix): User
    {
        $user = $this->makeUser($prefix);
        $user->forceFill(['is_verified' => 1, 'verification_status' => 2, 'verified_at' => now()])->save();

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeInvoice(User $user, float $amount, array $overrides = []): Invoice
    {
        return Invoice::query()->create(array_merge([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => $amount,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
        ], $overrides));
    }

    private function makeOrder(User $user, float $amount): Order
    {
        return Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => $amount,
            'status' => OrderStatus::PENDING,
        ]);
    }

    /**
     * @param  array<string, mixed>  $rawOverrides
     */
    private function makeSuccessPayment(User $user, Invoice $invoice, float $amount, array $rawOverrides = []): Payment
    {
        return Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => $amount,
            'status' => PaymentStatus::SUCCESS,
            'trade_no' => 'TRADE'.date('YmdHis').mt_rand(100000, 999999),
            'paid_at' => now(),
            'callback_raw' => array_merge(['source' => 'alipay_precreate'], $rawOverrides),
        ]);
    }
}

/**
 * 计数回调验签次数（V-06：按记录 driver_key 解析驱动）。
 */
final class VerificationCallbackSpy
{
    public int $calls = 0;
}
