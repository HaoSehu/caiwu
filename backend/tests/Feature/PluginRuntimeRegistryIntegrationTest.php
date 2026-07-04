<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\PaymentGatewayCode;
use App\Models\IntegrationPlugin;
use App\Models\Setting;
use App\Services\Auth\GeeTestService;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Integrations\Plugins\Adapters\PluginPaymentGateway;
use App\Services\Integrations\Plugins\Adapters\PluginSmsDriver;
use App\Services\Integrations\Plugins\Adapters\PluginUpstreamDriver;
use App\Services\Integrations\Plugins\Adapters\PluginVerificationDriver;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Integrations\Plugins\PluginInstaller;
use App\Services\Integrations\Plugins\PluginRuntimeRegistry;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Services\Sms\SmsDriverManager;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\ProviderKey;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Verification\Contracts\ProvidesVerificationFeeConfig;
use App\Services\Verification\Contracts\VerifiesVerificationCallbacks;
use App\Services\Verification\Data\VerificationCallbackRequest;
use App\Services\Verification\VerificationDriverManager;
use Caiwu\Plugins\Captcha\Geetest\GeetestPlugin;
use Caiwu\Plugins\Captcha\Geetest\Lib\GeetestCaptchaService;
use Caiwu\Plugins\Certification\Stay33\Logic\Stay33;
use Caiwu\Plugins\Certification\Stay33\Stay33Plugin;
use Caiwu\Plugins\Gateways\AliPay\AliPayPlugin;
use Caiwu\Plugins\Gateways\AliPay\Controller\IndexController;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangFinanceAdapter;
use Caiwu\Plugins\Servers\MofangFinance\Logic\MofangFinance;
use Caiwu\Plugins\Servers\MofangFinance\MofangFinancePlugin;
use Caiwu\Plugins\Sms\Aliyun\AliyunPlugin;
use Caiwu\Plugins\Sms\Aliyun\Lib\AliyunSmsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PluginRuntimeRegistryIntegrationTest extends TestCase
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

    public function test_runtime_executes_enabled_plugin_through_standard_execute_entry(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('verification', 'demo_verification', [
            'api_url' => 'https://example.test',
            'app_id' => 'demo-app',
            'app_secret' => 'demo-secret',
        ]);

        $result = app(PluginRuntimeRegistry::class)->execute(
            domain: 'verification',
            slugOrKey: 'demo_verification',
            action: 'certification.initialize',
            payload: [
                'real_name' => 'Test User',
                'id_card' => '110101199001010011',
                'cert_type' => 'FACE',
                'return_url' => 'https://example.test/return',
            ],
            context: ['trace_id' => 'plugin-runtime-test'],
        );

        $this->assertTrue((bool) ($result['success'] ?? false));
        $this->assertSame('certification.initialize', $result['action'] ?? null);
        $this->assertSame('demo_verification', $result['plugin']['key'] ?? null);
        $this->assertSame(
            'demo-certify-'.sha1('Test User110101199001010011'),
            $result['data']['certify_id'] ?? null,
        );
    }

    public function test_plugin_manifests_do_not_expose_ssl_configuration(): void
    {
        $scanner = app(PluginScanner::class);

        $alipayConfigKeys = collect($scanner->requireManifest('payment', 'ali_pay')->configSchema)
            ->pluck('key')
            ->all();
        $yipayConfigKeys = collect($scanner->requireManifest('payment', 'yi_pay')->configSchema)
            ->pluck('key')
            ->all();
        $geetestConfigKeys = collect($scanner->requireManifest('captcha', 'geetest')->configSchema)
            ->pluck('key')
            ->all();
        $aliyunConfigKeys = collect($scanner->requireManifest('sms', 'aliyun')->configSchema)
            ->pluck('key')
            ->all();

        $this->assertContains('api_endpoint', $aliyunConfigKeys);

        $this->assertNotContains('gateway', $alipayConfigKeys);
        $this->assertNotContains('notify_url', $alipayConfigKeys);
        $this->assertNotContains('api_base_url', $yipayConfigKeys);
        $this->assertNotContains('notify_url', $yipayConfigKeys);

        foreach ([$alipayConfigKeys, $yipayConfigKeys, $geetestConfigKeys, $aliyunConfigKeys] as $configKeys) {
            $this->assertNotContains('ssl_verify', $configKeys);
            $this->assertNotContains('ca_bundle', $configKeys);
        }
    }

    public function test_geetest_captcha_plugin_drives_auth_captcha_service(): void
    {
        $this->ensurePluginTables();
        Setting::setValues('system', [
            'captcha_enabled' => '1',
        ]);

        $this->activatePlugin('captcha', 'geetest', [
            'captcha_id' => 'captcha-id',
            'captcha_key' => 'captcha-key',
        ]);

        Http::fake([
            'https://gcaptcha4.geetest.com/validate' => Http::response(['result' => 'success'], 200),
        ]);

        $service = new GeeTestService(app(PluginRuntimeRegistry::class));

        $this->assertTrue($service->isEnabled());
        $this->assertSame('captcha-id', $service->getCaptchaId());
        $this->assertSame('/api/client/auth/captcha-script', $service->getScriptUrl());

        $result = $service->verify([
            'lot_number' => 'lot-number',
            'captcha_output' => 'captcha-output',
            'pass_token' => 'pass-token',
            'gen_time' => '1234567890',
        ]);

        $this->assertSame(['ok' => true], $result);
        $this->assertDatabaseHas('integration_plugin_bindings', [
            'domain' => 'captcha',
            'binding_key' => 'captcha_driver',
            'provider_key' => 'geetest',
            'status' => 1,
        ]);
        $this->assertTrue(class_exists(GeetestPlugin::class));
        $this->assertTrue(class_exists(GeetestCaptchaService::class));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://gcaptcha4.geetest.com/validate'
            && $request['captcha_id'] === 'captcha-id'
            && $request['sign_token'] === hash_hmac('sha256', 'lot-number', 'captcha-key'));
    }

    public function test_payment_manager_prefers_enabled_payment_plugin_without_duplicate_registration(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('payment', 'ali_pay', [
            'alipay_enabled' => true,
            'app_id' => 'app-id',
            'private_key' => 'private-key',
            'alipay_public_key' => 'public-key',
        ]);

        $this->app->forgetInstance(PaymentGatewayRegistry::class);
        $this->app->forgetInstance(PaymentGatewayManager::class);

        $gateway = app(PaymentGatewayManager::class)->gateway(PaymentGatewayCode::ALIPAY);

        $this->assertSame(PluginPaymentGateway::class, $gateway::class);
        $this->assertTrue(class_exists(AliPayPlugin::class));
        $this->assertTrue(class_exists(IndexController::class));
        $this->assertSame([PaymentGatewayCode::ALIPAY], app(PaymentGatewayRegistry::class)->keys());
    }

    public function test_verification_manager_prefers_enabled_verification_plugin_without_duplicate_registration(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('verification', 'stay33', [
            'api' => 'verification-api',
            'key' => 'verification-secret',
            'biz_code' => 'FACE',
        ]);

        $this->app->forgetInstance(VerificationDriverManager::class);

        $driver = app(VerificationDriverManager::class)->resolve('stay33');

        $this->assertSame(PluginVerificationDriver::class, $driver::class);
        $this->assertTrue(class_exists(Stay33Plugin::class));
        $this->assertTrue(class_exists(Stay33::class));
        $this->assertContains(
            ['value' => 'stay33', 'label' => $driver->label()],
            app(VerificationDriverManager::class)->options(),
        );
    }

    public function test_stay33_verification_plugin_owns_callback_verification_and_fee_config(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('verification', 'stay33', [
            'api' => 'verification-api',
            'key' => 'callback-secret',
            'biz_code' => 'FACE',
            'charge_enabled' => true,
            'amount' => 8.5,
            'free_times' => 5,
        ]);

        $this->app->forgetInstance(VerificationDriverManager::class);

        $driver = app(VerificationDriverManager::class)->resolve('stay33');
        $this->assertInstanceOf(VerifiesVerificationCallbacks::class, $driver);
        $this->assertInstanceOf(ProvidesVerificationFeeConfig::class, $driver);

        $this->assertSame([
            'free_attempts' => 5,
            'retry_fee' => 8.5,
            'charge_enabled' => true,
            'amount' => 8.5,
        ], $driver->feeConfig()->toArray());

        $payload = [
            'certify_id' => 'CERT-PLUGIN-CALLBACK',
            'timestamp' => (string) now()->timestamp,
            'nonce' => 'nonce-'.bin2hex(random_bytes(4)),
        ];
        $payload['sign'] = hash_hmac('sha256', $this->canonicalVerificationPayload($payload), 'callback-secret');

        $result = $driver->verifyCallback(new VerificationCallbackRequest(
            payload: $payload,
            headers: [],
            method: 'POST',
            path: 'api/client/verification/callback',
            rawBody: '',
        ));

        $this->assertTrue($result->passed);
        $this->assertSame('CERT-PLUGIN-CALLBACK|'.$payload['nonce'], $result->replayKey);

        $badPayload = $payload;
        $badPayload['sign'] = 'invalid-signature';
        $this->assertFalse($driver->verifyCallback(new VerificationCallbackRequest(
            payload: $badPayload,
            headers: [],
            method: 'POST',
            path: 'api/client/verification/callback',
            rawBody: '',
        ))->passed);
    }

    public function test_sms_manager_prefers_enabled_sms_plugin_without_duplicate_registration(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('sms', 'aliyun', [
            'access_key' => 'sms-access-key',
            'secret_key' => 'sms-secret-key',
            'sign_name' => 'Caiwu',
            'template_code' => 'SMS_100001',
        ]);

        $this->app->forgetInstance(SmsDriverManager::class);

        $driver = app(SmsDriverManager::class)->resolve('aliyun');

        $this->assertSame(PluginSmsDriver::class, $driver::class);
        $this->assertTrue(class_exists(AliyunPlugin::class));
        $this->assertTrue(class_exists(AliyunSmsService::class));
        $this->assertSame([['value' => 'aliyun', 'label' => $driver->label()]], app(SmsDriverManager::class)->options());
    }

    public function test_upstream_registry_prefers_enabled_upstream_plugin_without_duplicate_registration(): void
    {
        $this->ensurePluginTables();
        $this->activatePlugin('upstream', 'mofang_finance', []);

        $this->app->forgetInstance(ProviderRegistry::class);

        $registry = app(ProviderRegistry::class);
        $mofangDriver = $registry->find(ProviderKey::MOFANG_FINANCE_API);

        $this->assertNotNull($mofangDriver);
        $this->assertSame(PluginUpstreamDriver::class, $mofangDriver::class);
        $this->assertTrue(class_exists(MofangFinancePlugin::class));
        $this->assertTrue(class_exists(MofangFinance::class));
        $this->assertContains(ProvidesConsoleCatalog::class, $mofangDriver->capabilities());
        $mofangCatalog = $mofangDriver->resolve(ProvidesConsoleCatalog::class);
        $this->assertInstanceOf(ProvidesConsoleCatalog::class, $mofangCatalog);
        $this->assertInstanceOf(MofangFinanceAdapter::class, $mofangCatalog);
        $this->assertSame([ProviderKey::MOFANG_FINANCE_API], $registry->keys());
    }

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

        if (! Schema::hasTable('integration_plugin_bindings')) {
            Schema::create('integration_plugin_bindings', function (Blueprint $table): void {
                $table->id();
                $table->string('domain', 32);
                $table->unsignedBigInteger('plugin_id');
                $table->string('binding_type', 50);
                $table->string('bindable_type', 120)->default('global');
                $table->unsignedBigInteger('bindable_id')->default(0);
                $table->string('binding_key', 120);
                $table->string('provider_key', 120)->nullable();
                $table->integer('priority')->default(0);
                $table->unsignedTinyInteger('status')->default(1);
                $table->json('config_json')->nullable();
                $table->longText('secret_json')->nullable();
                $table->json('has_secret_json')->nullable();
                $table->json('runtime_policy_json')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->string('backfill_batch_id', 64)->nullable();
                $table->timestamps();
                $table->unique(['domain', 'binding_type', 'bindable_type', 'bindable_id', 'binding_key'], 'plugin_bindings_unique');
            });
        }
    }

    private function cleanPluginTables(): void
    {
        if (! Schema::hasTable('integration_plugins')) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if (Schema::hasTable('integration_plugin_bindings')) {
            DB::table('integration_plugin_bindings')->truncate();
        }
        if (Schema::hasTable('integration_plugin_configs')) {
            DB::table('integration_plugin_configs')->truncate();
        }
        DB::table('integration_plugins')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
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
