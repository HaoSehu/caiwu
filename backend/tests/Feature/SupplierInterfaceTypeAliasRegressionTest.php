<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\Supplier;
use App\Services\Integrations\Plugins\PluginInstaller;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Support\AdminPermissions;
use Illuminate\Database\Schema\Blueprint;
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
            'interface_type' => 'mofang_finance_api',
            'api_url' => 'https://supplier-'.$suffix.'.example.com',
            'api_username' => 'demo',
            'api_key' => 'secret',
            'status' => 1,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.interface_type', 'mofang_finance_api');

        $supplier = Supplier::query()
            ->where('name', 'Mofang Alias '.$suffix)
            ->first();

        $this->assertNotNull($supplier);
        $this->assertSame('mofang_finance_api', $supplier->interface_type);
    }

    public function test_admin_update_preserves_existing_api_key_when_sensitive_field_is_blank(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $this->actingAsProductManager($suffix);

        $supplier = Supplier::query()->create([
            'name' => 'Mofang Preserve '.$suffix,
            'code' => 'mofang_preserve_'.$suffix,
            'interface_type' => 'mofang_finance_api',
            'api_url' => 'https://supplier-'.$suffix.'.example.com',
            'api_username' => 'demo',
            'api_key' => 'secret-value-'.$suffix,
            'status' => 1,
            'sort_order' => 0,
        ]);

        $response = $this->putJson('/api/admin/suppliers/'.$supplier->id, [
            'name' => 'Mofang Preserve Updated '.$suffix,
            'interface_type' => 'mofang_finance_api',
            'api_url' => 'https://supplier-updated-'.$suffix.'.example.com',
            'api_username' => 'demo-updated',
            'api_key' => '',
            'status' => 1,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.has_api_key', true);

        $payload = $response->json('data');
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('api_key', $payload);
        $this->assertSame('secret-value-'.$suffix, $supplier->fresh()->api_key);
    }

    public function test_admin_can_fetch_provider_type_options_from_registered_drivers(): void
    {
        $this->actingAsProductManager(bin2hex(random_bytes(4)));

        $payload = $this->getJson('/api/admin/suppliers/provider-types')
            ->assertOk()
            ->json('data.list');

        $this->assertContains(['value' => 'hosting_panel_api', 'label' => '主机面板接口'], $payload);
        $this->assertContains(['value' => 'mofang_finance_api', 'label' => '魔方财务接口'], $payload);
    }

    private function actingAsProductManager(string $suffix): void
    {
        $role = Role::query()->create([
            'name' => 'supplier-alias-'.$suffix,
            'label' => 'Supplier Alias',
            'permissions' => [AdminPermissions::PRODUCT_MANAGE],
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
        $this->ensurePluginTables();

        foreach (['hosting_panel_api', 'mofang_finance'] as $slug) {
            $scanner = app(PluginScanner::class);
            $installer = app(PluginInstaller::class);
            $scanner->requireManifest('upstream', $slug);
            $plugin = $installer->install('upstream', $slug);
            $installer->enable($plugin);
        }

        $this->app->forgetInstance(\App\Services\Upstream\ProviderRegistry::class);
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
