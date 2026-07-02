<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\IntegrationPlugin;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Integrations\Plugins\IntegrationPluginService;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Integrations\Plugins\PluginInstaller;
use App\Services\Integrations\Plugins\PluginRuntimeRegistry;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\Contracts\ProvidesConsoleRuntime;
use App\Services\Upstream\ProviderRegistry;
use Caiwu\Plugins\Certification\DemoVerification\DemoVerificationPlugin;
use Caiwu\Plugins\Gateways\DemoPay\DemoPayPlugin;
use Caiwu\Plugins\Mail\DemoMail\DemoMailPlugin;
use Caiwu\Plugins\Servers\DemoServers\DemoServersPlugin;
use Caiwu\Plugins\Servers\DemoServers\Logic\DemoServers;
use Caiwu\Plugins\Sms\DemoSms\DemoSmsPlugin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PluginSimulationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanPluginTables();
    }

    protected function tearDown(): void
    {
        $this->cleanPluginTables();
        parent::tearDown();
    }

    private function cleanPluginTables(): void
    {
        if (Schema::hasTable('integration_plugin_configs')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('integration_plugin_configs')->truncate();
            DB::table('integration_plugins')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    // ──────────────────────────────────────────────
    //  支付模拟
    // ──────────────────────────────────────────────

    public function test_demo_pay_precreate_returns_qrcode(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('payment', 'demo_pay', [
            'merchant_id' => 'test_merchant',
            'enabled' => true,
        ]);

        $result = app(PluginRuntimeRegistry::class)->execute(
            domain: 'payment',
            slugOrKey: 'demo_pay',
            action: 'payment.precreate',
            payload: [
                'out_trade_no' => 'SIM-PAY-001',
                'amount' => 29.90,
                'subject' => '模拟月付云服务器',
                'timeout_express' => '30m',
            ],
        );

        $this->assertTrue($result['success']);
        $this->assertSame('payment.precreate', $result['action']);
        $this->assertStringContainsString('out_trade_no=SIM-PAY-001', $result['data']['qr_code'] ?? '');
        $this->assertSame('SIM-PAY-001', $result['data']['out_trade_no'] ?? '');
    }

    public function test_demo_pay_exposes_manager_control_actions(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('payment', 'demo_pay', [
            'merchant_id' => 'test_merchant',
            'enabled' => true,
        ]);

        $this->app->forgetInstance(PaymentGatewayRegistry::class);
        $this->app->forgetInstance(PaymentGatewayManager::class);

        $gateway = app(PaymentGatewayManager::class)->gateway('demo_pay');

        $this->assertTrue($gateway->isEnabled());
        $this->assertTrue($gateway->matchesMerchantId(null));
        $this->assertTrue($gateway->matchesMerchantId('test_merchant'));
        $this->assertFalse($gateway->matchesMerchantId('other_merchant'));
    }

    public function test_demo_pay_enabled_flag_comes_from_plugin_config(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('payment', 'demo_pay', [
            'merchant_id' => 'test_merchant',
            'enabled' => false,
        ]);

        $result = app(PluginRuntimeRegistry::class)->execute(
            domain: 'payment',
            slugOrKey: 'demo_pay',
            action: 'payment.is_enabled',
            payload: [],
        );

        $this->assertTrue($result['success']);
        $this->assertFalse($result['data']['enabled'] ?? true);
    }

    public function test_demo_pay_query_returns_wait_status(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('payment', 'demo_pay', ['merchant_id' => 'test_merchant', 'enabled' => true]);

        $result = app(PluginRuntimeRegistry::class)->execute(
            domain: 'payment',
            slugOrKey: 'demo_pay',
            action: 'payment.query',
            payload: ['out_trade_no' => 'SIM-PAY-001'],
        );

        $this->assertTrue($result['success']);
        $this->assertSame('WAIT_BUYER_PAY', $result['data']['trade_status'] ?? '');
        $this->assertStringStartsWith('DEMO', $result['data']['trade_no'] ?? '');
    }

    public function test_demo_pay_refund_returns_refund_data(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('payment', 'demo_pay', ['merchant_id' => 'test_merchant', 'enabled' => true]);

        $result = app(PluginRuntimeRegistry::class)->execute(
            domain: 'payment',
            slugOrKey: 'demo_pay',
            action: 'payment.refund',
            payload: [
                'out_trade_no' => 'SIM-PAY-001',
                'refund_amount' => 29.90,
                'refund_reason' => '测试退款',
                'trade_no' => 'DEMO20260701001',
            ],
        );

        $this->assertTrue($result['success']);
        $this->assertSame('29.90', $result['data']['refund_fee'] ?? '');
        $this->assertSame('SIM-PAY-001', $result['data']['out_trade_no'] ?? '');
    }

    public function test_demo_pay_verify_notify_passes_on_correct_sign(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('payment', 'demo_pay', ['merchant_id' => 'test_merchant', 'enabled' => true]);

        $result = app(PluginRuntimeRegistry::class)->execute(
            domain: 'payment',
            slugOrKey: 'demo_pay',
            action: 'payment.verify_notify',
            payload: ['demo_sign' => 'ok', 'out_trade_no' => 'SIM-PAY-001', 'trade_status' => 'TRADE_SUCCESS'],
        );

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['verified'] ?? false);
    }

    public function test_demo_pay_verify_notify_rejects_bad_sign(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('payment', 'demo_pay', ['merchant_id' => 'test_merchant', 'enabled' => true]);

        $result = app(PluginRuntimeRegistry::class)->execute(
            domain: 'payment',
            slugOrKey: 'demo_pay',
            action: 'payment.verify_notify',
            payload: ['demo_sign' => 'bad_sign', 'out_trade_no' => 'SIM-PAY-001'],
        );

        $this->assertTrue($result['success']);
        $this->assertFalse($result['data']['verified'] ?? true);
    }

    public function test_demo_pay_unsupported_action_returns_failure(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('payment', 'demo_pay', ['merchant_id' => 'test_merchant', 'enabled' => true]);

        $result = app(PluginRuntimeRegistry::class)->execute(
            domain: 'payment',
            slugOrKey: 'demo_pay',
            action: 'payment.some_future_action',
            payload: [],
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Unsupported', $result['message'] ?? '');
    }

    // ──────────────────────────────────────────────
    //  短信模拟
    // ──────────────────────────────────────────────

    public function test_demo_sms_send_verify_code_succeeds(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('sms', 'demo_sms', [
            'access_key' => 'demo_ak',
            'sign_name' => '测试签名',
            'template_code' => 'SMS_001',
        ]);

        $result = app(PluginRuntimeRegistry::class)->execute(
            domain: 'sms',
            slugOrKey: 'demo_sms',
            action: 'sms.send_verify_code',
            payload: [
                'phone' => '13800138000',
                'code' => '654321',
                'options' => ['sign_name' => '测试签名', 'template_code' => 'SMS_001'],
            ],
        );

        $this->assertTrue($result['success']);
        $this->assertSame('success', $result['data']['status'] ?? '');
        $this->assertStringStartsWith('demo-sms-', $result['data']['request_id'] ?? '');
        $this->assertSame('SMS_001', $result['data']['template_code'] ?? '');
        $this->assertArrayHasKey('code', $result['data']['template_params'] ?? []);
        $this->assertSame('654321', $result['data']['template_params']['code'] ?? '');
    }

    public function test_demo_sms_supports_admin_test_action(): void
    {
        $this->ensurePluginTables();
        $plugin = $this->activatePlugin('sms', 'demo_sms', [
            'access_key' => 'demo_ak',
            'sign_name' => 'Demo Sign',
            'template_code' => 'SMS_TEST',
        ]);

        $result = app(IntegrationPluginService::class)->testSms($plugin, [
            'phone' => '13800138000',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('sms.test', $result['action']);
        $this->assertSame('success', $result['data']['status'] ?? '');
        $this->assertSame('888888', $result['data']['template_params']['code'] ?? '');
    }

    public function test_demo_sms_unsupported_action_returns_failure(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('sms', 'demo_sms', [
            'access_key' => 'demo_ak',
            'sign_name' => '测试签名',
            'template_code' => 'SMS_002',
        ]);

        $result = app(PluginRuntimeRegistry::class)->execute(
            domain: 'sms',
            slugOrKey: 'demo_sms',
            action: 'sms.send_marketing',
            payload: [],
        );

        $this->assertFalse($result['success']);
    }

    // ──────────────────────────────────────────────
    //  邮件模拟
    // ──────────────────────────────────────────────

    public function test_demo_mail_send_html_succeeds(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('mail', 'demo_mail', [
            'from_address' => 'noreply@test.example',
            'from_name' => 'Test Mailer',
        ]);

        $html = '<html><body><h1>验证码: 123456</h1></body></html>';

        $result = app(PluginRuntimeRegistry::class)->execute(
            domain: 'mail',
            slugOrKey: 'demo_mail',
            action: 'mail.send_html',
            payload: [
                'to' => 'user@example.com',
                'subject' => '您的验证码',
                'html' => $html,
                'context' => ['code' => '123456', 'product' => '云服务器'],
            ],
        );

        $this->assertTrue($result['success']);
        $this->assertSame('mail.send_html', $result['action']);
        $this->assertTrue($result['data']['sent'] ?? false);
    }

    public function test_demo_mail_supports_admin_test_action(): void
    {
        $this->ensurePluginTables();
        $plugin = $this->activatePlugin('mail', 'demo_mail', [
            'from_address' => 'noreply@test.example',
            'from_name' => 'Test Mailer',
        ]);

        $result = app(IntegrationPluginService::class)->testEmail($plugin, [
            'account_index' => 0,
            'to' => 'user@example.com',
            'subject' => 'Plugin mail test',
            'body' => 'Hello from demo mail',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('mail.test_smtp', $result['action']);
        $this->assertTrue($result['data']['sent'] ?? false);
        $this->assertSame('user@example.com', $result['data']['to'] ?? '');
    }

    public function test_demo_mail_unsupported_action_returns_failure(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('mail', 'demo_mail', [
            'from_address' => 'noreply@test.example',
            'from_name' => 'Test Mailer',
        ]);

        $result = app(PluginRuntimeRegistry::class)->execute(
            domain: 'mail',
            slugOrKey: 'demo_mail',
            action: 'mail.send_raw',
            payload: [],
        );

        $this->assertFalse($result['success']);
    }

    // ──────────────────────────────────────────────
    //  上游模拟
    // ──────────────────────────────────────────────

    public function test_demo_servers_registers_as_upstream_provider(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('upstream', 'demo_servers', [
            'demo_region' => 'ap-test-1',
            'enabled' => true,
        ]);

        $this->app->forgetInstance(ProviderRegistry::class);

        $driver = app(ProviderRegistry::class)->find('demo_servers');

        $this->assertNotNull($driver);
        $this->assertSame('demo_servers', $driver->key());
        $this->assertSame('Demo 上游服务', $driver->label());
        $this->assertContains(ProvidesConsoleCatalog::class, $driver->capabilities());

        $catalog = $driver->resolve(ProvidesConsoleCatalog::class);

        $this->assertInstanceOf(ProvidesConsoleCatalog::class, $catalog);
        $this->assertInstanceOf(DemoServers::class, $catalog);
        $this->assertSame('Demo 云服务器 1C2G', $catalog->getProductCatalog($this->makeDemoSupplier())['products'][0]['name'] ?? null);
    }

    public function test_demo_servers_returns_safe_simulated_console_payloads(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('upstream', 'demo_servers', [
            'demo_region' => 'ap-test-1',
            'enabled' => true,
        ]);

        $runtime = app(PluginRuntimeRegistry::class);

        $metadata = $runtime->execute('upstream', 'demo_servers', 'server.metadata');
        $this->assertTrue($metadata['success']);
        $this->assertSame('demo_servers', $metadata['data']['key'] ?? null);

        $resolved = $runtime->execute('upstream', 'demo_servers', 'server.resolve_capability', [
            'capability' => ProvidesConsoleRuntime::class,
        ]);
        $console = $resolved['data']['resolved'] ?? null;

        $this->assertInstanceOf(DemoServers::class, $console);
        $this->assertSame('running', $console->getHostDetail($this->makeDemoSupplier(), 1001)['data']['host']['power_state'] ?? null);
        $this->assertStringContainsString('demo-vnc-token-1001', $console->getVncUrl($this->makeDemoSupplier(), 1001)['data']['url'] ?? '');
        $this->assertSame('reboot', $console->powerAction($this->makeDemoSupplier(), 1001, 'reboot')['data']['action'] ?? null);
    }

    // ──────────────────────────────────────────────
    //  边界：未安装/未启用
    // ──────────────────────────────────────────────

    public function test_demo_verification_actions_return_standard_status_codes(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('verification', 'demo_verification', [
            'api_url' => 'https://example.test',
            'app_id' => 'demo-app',
            'app_secret' => 'demo-secret',
        ]);

        $initialize = app(PluginRuntimeRegistry::class)->execute(
            domain: 'verification',
            slugOrKey: 'demo_verification',
            action: 'certification.initialize',
            payload: [
                'real_name' => 'Test User',
                'id_card' => '110101199001010011',
            ],
        );

        $certifyId = (string) ($initialize['data']['certify_id'] ?? '');
        $this->assertTrue($initialize['success']);
        $this->assertSame(200, $initialize['data']['status'] ?? null);
        $this->assertSame('demo-certify-'.sha1('Test User110101199001010011'), $certifyId);

        $scanUrl = app(PluginRuntimeRegistry::class)->execute(
            domain: 'verification',
            slugOrKey: 'demo_verification',
            action: 'certification.scan_url',
            payload: ['certify_id' => $certifyId],
        );

        $this->assertTrue($scanUrl['success']);
        $this->assertSame(200, $scanUrl['data']['status'] ?? null);
        $this->assertStringContainsString(rawurlencode($certifyId), $scanUrl['data']['url'] ?? '');

        $query = app(PluginRuntimeRegistry::class)->execute(
            domain: 'verification',
            slugOrKey: 'demo_verification',
            action: 'certification.query_status',
            payload: ['certify_id' => $certifyId],
        );

        $this->assertTrue($query['success']);
        $this->assertSame(4, $query['data']['status'] ?? null);
    }

    public function test_demo_verification_callback_signature_and_fee_config(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('verification', 'demo_verification', [
            'api_url' => 'https://example.test',
            'app_id' => 'demo-app',
            'app_secret' => 'demo-secret',
            'charge_enabled' => true,
            'amount' => 6.5,
            'free_times' => 2,
        ]);

        $payload = [
            'certify_id' => 'demo-certify-callback',
            'timestamp' => (string) time(),
            'nonce' => 'nonce-test',
        ];
        $payload['sign'] = hash_hmac('sha256', $this->canonicalVerificationPayload($payload), 'demo-secret');

        $verified = app(PluginRuntimeRegistry::class)->execute(
            domain: 'verification',
            slugOrKey: 'demo_verification',
            action: 'certification.verify_callback',
            payload: [
                'payload' => $payload,
                'headers' => [],
                'method' => 'POST',
                'path' => 'api/client/verification/callback',
                'raw_body' => '',
            ],
        );

        $this->assertTrue($verified['success']);
        $this->assertTrue($verified['data']['passed'] ?? false);
        $this->assertSame('demo-certify-callback|nonce-test', $verified['data']['replay_key'] ?? '');

        $badPayload = $payload;
        $badPayload['sign'] = 'bad-signature';
        $rejected = app(PluginRuntimeRegistry::class)->execute(
            domain: 'verification',
            slugOrKey: 'demo_verification',
            action: 'certification.verify_callback',
            payload: [
                'payload' => $badPayload,
                'headers' => [],
            ],
        );

        $this->assertFalse($rejected['data']['passed'] ?? true);
        $this->assertSame(401, $rejected['data']['http_status'] ?? null);

        $feeConfig = app(PluginRuntimeRegistry::class)->execute(
            domain: 'verification',
            slugOrKey: 'demo_verification',
            action: 'certification.fee_config',
        );

        $this->assertSame(2, $feeConfig['data']['free_attempts'] ?? null);
        $this->assertSame(6.5, $feeConfig['data']['retry_fee'] ?? null);
        $this->assertTrue($feeConfig['data']['charge_enabled'] ?? false);
    }

    public function test_execute_fails_when_plugin_not_installed(): void
    {
        $this->ensurePluginTables();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('插件未安装或未启用');

        app(PluginRuntimeRegistry::class)->execute(
            domain: 'payment',
            slugOrKey: 'nonexistent_plugin',
            action: 'payment.precreate',
            payload: [],
        );
    }

    public function test_execute_fails_when_plugin_disabled(): void
    {
        $this->ensurePluginTables();
        $scanner = app(PluginScanner::class);
        $installer = app(PluginInstaller::class);

        $manifest = $scanner->requireManifest('sms', 'demo_sms');
        $plugin = $installer->install('sms', 'demo_sms');
        $installer->disable($plugin);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('插件未安装或未启用');

        app(PluginRuntimeRegistry::class)->execute(
            domain: 'sms',
            slugOrKey: 'demo_sms',
            action: 'sms.send_verify_code',
            payload: [],
        );
    }

    public function test_plugin_entry_classes_are_loadable(): void
    {
        // 确保三个 demo 插件的入口类和 config.php 可被扫描加载
        $this->assertTrue(class_exists(DemoPayPlugin::class));
        $this->assertTrue(class_exists(DemoSmsPlugin::class));
        $this->assertTrue(class_exists(DemoMailPlugin::class));
        $this->assertTrue(class_exists(DemoVerificationPlugin::class));
        $this->assertTrue(class_exists(DemoServersPlugin::class));
    }

    // ──────────────────────────────────────────────
    //  helpers
    // ──────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $config
     */
    private function activatePlugin(string $domain, string $slug, array $config): IntegrationPlugin
    {
        $scanner = app(PluginScanner::class);
        $installer = app(PluginInstaller::class);
        $configRepository = app(PluginConfigRepository::class);

        $manifest = $scanner->requireManifest($domain, $slug);
        $plugin = $installer->install($domain, $slug);
        if ($config !== []) {
            $configRepository->save($plugin, $manifest, $config);
        }

        return $installer->enable($plugin);
    }

    private function ensurePluginTables(): void
    {
        if (! Schema::hasTable('integration_plugins')) {
            Schema::create('integration_plugins', function (Blueprint $table): void {
                $table->id();
                $table->string('domain', 32);
                $table->string('slug', 120);
                $table->string('plugin_key', 120);
                $table->string('name', 120);
                $table->string('version', 32)->default('1.0.0');
                $table->string('provider_class', 255)->nullable();
                $table->string('entry_class', 255);
                $table->json('capabilities_json')->nullable();
                $table->json('config_schema_json')->nullable();
                $table->unsignedTinyInteger('status')->default(0);
                $table->timestamp('installed_at')->nullable();
                $table->timestamps();
                $table->unique(['domain', 'slug']);
                $table->unique(['domain', 'plugin_key']);
            });
        }

        if (! Schema::hasTable('integration_plugin_configs')) {
            Schema::create('integration_plugin_configs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('plugin_id');
                $table->json('config_json')->nullable();
                $table->longText('secret_json')->nullable();
                $table->json('has_secret_json')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique('plugin_id');
            });
        }
    }

    private function makeDemoSupplier(): \App\Models\Supplier
    {
        $supplier = new \App\Models\Supplier;
        $supplier->id = 100;
        $supplier->name = 'Demo 上游供应商';
        $supplier->interface_type = 'demo_servers';

        return $supplier;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function canonicalVerificationPayload(array $payload): string
    {
        unset($payload['sign'], $payload['signature']);
        $this->ksortRecursive($payload);

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ksortRecursive(array &$payload): void
    {
        ksort($payload);

        foreach ($payload as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
    }
}
