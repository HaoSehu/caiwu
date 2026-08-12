<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Auth\LegacyPasswordVerifier;
use App\Services\Integrations\Plugins\Adapters\PluginUpstreamDriver;
use App\Services\Integrations\Plugins\PluginInstaller;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\Contracts\UpstreamBillingRestoreProfile;
use App\Services\Upstream\Data\UpstreamProviderDescriptor;
use App\Services\Upstream\ProviderKey;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\Support\WebSessionCookieParser;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfBillingRestoreProfile;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfBillingRestoreService;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfFinanceAdapter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ZjmfServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureZjmfPluginEnabled();
    }

    public function test_it_registers_zjmf_driver_through_upstream_plugin(): void
    {
        $registry = app(ProviderRegistry::class);
        $driver = $registry->find(ProviderKey::ZJMF_FINANCE_API);

        $this->assertInstanceOf(PluginUpstreamDriver::class, $driver);
        $this->assertSame('ZJMF 财务接口', $driver?->label());
        $this->assertInstanceOf(
            ZjmfFinanceAdapter::class,
            $driver?->resolve(ProvidesConsoleCatalog::class)
        );
    }

    public function test_zjmf_adapter_declares_platform_used_methods_explicitly_without_dynamic_forwarding(): void
    {
        $reflection = new \ReflectionClass(ZjmfFinanceAdapter::class);

        foreach ([
            'login',
            'refreshJwt',
            'loginResponse',
            'getUserProfile',
            'getBalance',
            'getProductCatalog',
            'getProductConfigTemplate',
            'fetchRealConfigOptions',
            'fetchBatchProductConfigOptions',
            'fetchBatchProductStocks',
            'provisionOrder',
            'getProductProvisionConfig',
            'renewHost',
            'renewServiceInvoice',
            'recoverRenewInvoice',
            'recoverRenewInvoiceWithContext',
            'syncServiceStatuses',
            'getHostDetail',
            'getVncUrl',
            'powerAction',
            'getModuleStatus',
            'getReinstallOptions',
            'resetPassword',
            'reinstall',
            'getSupportedModules',
            'fetchCustomModulePage',
            'getHostUpgradeConfigOptions',
            'previewHostConfigUpgrade',
            'checkoutHostConfigUpgrade',
            'getHostUpgradePromoPreview',
            'removeHostUpgradePromoCode',
            'getHostUpgradeOptions',
            'previewHostUpgrade',
            'applyHostUpgradePromoCode',
            'checkoutHostUpgrade',
            'buyFlowPacket',
            'fundInvoice',
            'purchaseTrafficPackage',
            'purchaseHostUpgrade',
            'submitCustomModuleAction',
            'getCustomModuleActionEndpoint',
            'post',
            'get',
            'getText',
            'parallelGet',
            'put',
            'delete',
            'requestText',
            'request',
        ] as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method) && $reflection->getMethod($method)->getDeclaringClass()->getName() === ZjmfFinanceAdapter::class,
                "ZjmfFinanceAdapter must explicitly declare {$method}()."
            );
        }

        $this->assertFalse($reflection->hasMethod('__call'));
    }

    public function test_provider_registry_exports_driver_metadata_without_provider_key_labels(): void
    {
        $registry = app(ProviderRegistry::class);

        $this->assertSame(ProviderKey::ZJMF_FINANCE_API, ProviderKey::label(ProviderKey::ZJMF_FINANCE_API));
        $this->assertContains(ProviderKey::ZJMF_FINANCE_API, $registry->keys());
        $option = collect($registry->options())
            ->firstWhere('value', ProviderKey::ZJMF_FINANCE_API);
        $this->assertIsArray($option);
        $this->assertSame('ZJMF 财务接口', $option['label']);
        $this->assertArrayHasKey('supplier_form', $option);

        $descriptor = collect($registry->descriptors())
            ->first(fn (UpstreamProviderDescriptor $item): bool => $item->key === ProviderKey::ZJMF_FINANCE_API);

        $this->assertInstanceOf(UpstreamProviderDescriptor::class, $descriptor);
        $descriptorPayload = $descriptor->toArray();
        $this->assertSame(ProviderKey::ZJMF_FINANCE_API, $descriptorPayload['key']);
        $this->assertSame('ZJMF 财务接口', $descriptorPayload['label']);
        $this->assertSame($descriptor->capabilities, $descriptorPayload['capabilities']);
        $this->assertArrayHasKey('supplier_form', $descriptorPayload);
        $this->assertContains(ProvidesConsoleCatalog::class, $descriptor->capabilities);
    }

    public function test_it_registers_zjmf_legacy_password_verifier_in_chain(): void
    {
        $needsRehash = false;
        $matched = app(LegacyPasswordVerifier::class)->verify(
            'Secret123',
            '###'.md5('Secret123'),
            $needsRehash
        );

        $this->assertTrue($matched);
        $this->assertTrue($needsRehash);
    }

    public function test_it_registers_zjmf_billing_restore_module(): void
    {
        $profile = app(UpstreamBillingRestoreProfile::class);

        $this->assertInstanceOf(ZjmfBillingRestoreProfile::class, $profile);
        $this->assertInstanceOf(ZjmfBillingRestoreService::class, app(ZjmfBillingRestoreService::class));
        $this->assertSame('RESTORE_ZJMF_BILLING', $profile->defaultConfirmationPhrase());
        $this->assertContains('RESTORE_ZJMF_BILLING', $profile->confirmationPhrases());
    }

    private function ensureZjmfPluginEnabled(): void
    {
        $this->ensurePluginTables();

        $scanner = app(PluginScanner::class);
        $installer = app(PluginInstaller::class);
        $scanner->requireManifest('upstream', 'zjmf_finance');
        $plugin = $installer->install('upstream', 'zjmf_finance');
        $installer->enable($plugin);

        $this->app->forgetInstance(ProviderRegistry::class);
        $this->app->forgetInstance(LegacyPasswordVerifier::class);
        $this->app->forgetInstance(WebSessionCookieParser::class);
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
}
