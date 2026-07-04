<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\Supplier;
use App\Services\Integrations\Plugins\PluginBindingResolver;
use App\Services\Integrations\Plugins\PluginInstaller;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Services\Integrations\Plugins\UpstreamBindingWriter;
use App\Services\Upstream\ProviderRegistry;
use App\Support\AdminPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierInterfaceTypeAliasRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureUpstreamPluginsEnabled();
    }

    public function test_admin_can_create_supplier_with_mofang_finance_provider(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $this->actingAsProductManager($suffix);

        $response = $this->postJson('/api/admin/suppliers', [
            'name' => 'Mofang Alias '.$suffix,
            'upstream_binding' => [
                'provider_key' => 'mofang_finance_api',
                'base_url' => 'https://supplier-'.$suffix.'.example.com',
                'account_name' => 'demo',
            ],
            'api_key' => 'secret',
            'status' => 1,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.provider_key', 'mofang_finance_api')
            ->assertJsonPath('data.upstream_binding.provider_key', 'mofang_finance_api');

        $supplier = Supplier::query()
            ->where('name', 'Mofang Alias '.$suffix)
            ->first();

        $this->assertNotNull($supplier);
        $this->assertDatabaseHas('supplier_plugin_bindings', [
            'supplier_id' => (int) $supplier->id,
            'provider_key' => 'mofang_finance_api',
        ]);
    }

    public function test_admin_update_preserves_existing_api_key_when_sensitive_field_is_blank(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $this->actingAsProductManager($suffix);

        $supplier = Supplier::query()->create([
            'name' => 'Mofang Preserve '.$suffix,
            'code' => 'mofang_preserve_'.$suffix,
            'status' => 1,
            'sort_order' => 0,
        ]);
        app(UpstreamBindingWriter::class)->syncSupplierBinding($supplier, [
            'provider_key' => 'mofang_finance_api',
            'base_url' => 'https://supplier-'.$suffix.'.example.com',
            'account_name' => 'demo',
            'api_key' => 'secret-value-'.$suffix,
            'provider_config' => [],
            'status' => 1,
            'priority' => 0,
        ]);

        $response = $this->putJson('/api/admin/suppliers/'.$supplier->id, [
            'name' => 'Mofang Preserve Updated '.$suffix,
            'upstream_binding' => [
                'provider_key' => 'mofang_finance_api',
                'base_url' => 'https://supplier-updated-'.$suffix.'.example.com',
                'account_name' => 'demo-updated',
            ],
            'api_key' => '',
            'status' => 1,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.has_api_key', true);

        $payload = $response->json('data');
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('api_key', $payload);
        $binding = app(PluginBindingResolver::class)->supplierBindingProjection($supplier->fresh(), includeSecrets: true);
        $this->assertSame('secret-value-'.$suffix, $binding['api_key'] ?? null);
    }

    public function test_admin_can_fetch_provider_type_options_from_registered_drivers(): void
    {
        $this->actingAsProductManager(bin2hex(random_bytes(4)));

        $payload = $this->getJson('/api/admin/suppliers/provider-types')
            ->assertOk()
            ->json('data.list');

        $mofang = collect($payload)->firstWhere('value', 'mofang_finance_api');

        $this->assertIsArray($mofang);
        $this->assertSame('魔方财务接口', $mofang['label'] ?? null);
        $this->assertSame('魔方财务地址', $mofang['supplier_form']['fields'][0]['label'] ?? null);
        $this->assertSame('api_key', $mofang['supplier_form']['fields'][2]['key'] ?? null);
        $this->assertTrue((bool) ($mofang['supplier_form']['fields'][2]['secret'] ?? false));
        $this->assertFalse(collect($payload)->contains(fn (array $item): bool => ($item['value'] ?? null) === 'hosting_panel_api'));
    }

    public function test_admin_can_store_plugin_specific_supplier_config(): void
    {
        $this->ensureUpstreamPluginEnabled('demo_servers');
        $suffix = bin2hex(random_bytes(4));

        $this->actingAsProductManager($suffix);

        $response = $this->postJson('/api/admin/suppliers', [
            'name' => 'Demo Provider '.$suffix,
            'upstream_binding' => [
                'provider_key' => 'demo_servers',
            ],
            'provider_config' => [
                'demo_region' => 'ap-demo-custom',
            ],
            'status' => 1,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.provider_key', 'demo_servers')
            ->assertJsonPath('data.upstream_binding.provider_key', 'demo_servers')
            ->assertJsonPath('data.provider_config.demo_region', 'ap-demo-custom');

        $supplier = Supplier::query()
            ->where('name', 'Demo Provider '.$suffix)
            ->first();

        $this->assertNotNull($supplier);
        $binding = app(PluginBindingResolver::class)->supplierBindingProjection($supplier, includeSecrets: true);
        $this->assertSame('ap-demo-custom', $binding['provider_config']['demo_region'] ?? null);

        $rawProviderConfig = (string) DB::table('supplier_plugin_bindings')
            ->where('supplier_id', (int) $supplier->id)
            ->value('secret_json');
        $this->assertNotSame('', $rawProviderConfig);
        $this->assertStringNotContainsString('ap-demo-custom', $rawProviderConfig);

        $this->getJson('/api/admin/suppliers/'.$supplier->id)
            ->assertOk()
            ->assertJsonPath('data.provider_config.demo_region', 'ap-demo-custom')
            ->assertJsonPath('data.has_provider_secret_values', [])
            ->assertJsonMissingPath('data.api_key');

        $this->getJson('/api/admin/suppliers?keyword=Demo%20Provider%20'.$suffix)
            ->assertOk()
            ->assertJsonPath('data.list.0.provider_config.demo_region', 'ap-demo-custom')
            ->assertJsonPath('data.list.0.has_provider_secret_values', [])
            ->assertJsonMissingPath('data.list.0.api_key');
    }

    private function actingAsProductManager(string $suffix): void
    {
        $role = Role::query()->create([
            'name' => 'supplier-alias-'.$suffix,
            'label' => 'Supplier Alias',
            'permissions' => [
                AdminPermissions::SUPPLIER_LIST,
                AdminPermissions::SUPPLIER_DETAIL,
                AdminPermissions::SUPPLIER_MANAGE,
            ],
        ]);

        $admin = AdminUser::query()->create([
            'username' => 'supplier-alias-'.$suffix,
            'password' => 'Temp@123456',
            'role_id' => (int) $role->id,
            'nickname' => 'Supplier Alias',
            'email' => 'supplier-alias-'.$suffix.'@example.com',
            'status' => 1,
        ]);

        Sanctum::actingAs($admin);
    }

    private function ensureUpstreamPluginsEnabled(): void
    {
        $this->ensureUpstreamPluginEnabled('mofang_finance');
    }

    private function ensureUpstreamPluginEnabled(string $slug): void
    {
        $this->ensurePluginTables();

        $scanner = app(PluginScanner::class);
        $installer = app(PluginInstaller::class);
        $scanner->requireManifest('upstream', $slug);
        $plugin = $installer->install('upstream', $slug);
        $installer->enable($plugin);

        $this->app->forgetInstance(ProviderRegistry::class);
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
