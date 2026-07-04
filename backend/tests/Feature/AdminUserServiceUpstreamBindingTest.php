<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ServiceStatus;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Upstream\ProviderKey;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUserServiceUpstreamBindingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->activateIntegrationPluginForTest('upstream', 'mofang_finance');
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_07_03_130000_create_plugin_binding_runtime_and_audit_tables.php',
            '--force' => true,
        ]);
    }

    public function test_manual_service_can_bind_mofang_finance_supplier_and_override_upstream_product_id(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'service-upstream-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'Mofang Service Supplier '.$suffix,
            'code' => 'mofang-service-'.$suffix,
            'interface_type' => ProviderKey::MOFANG_FINANCE_API,
            'api_url' => 'https://supplier-'.$suffix.'.example.com',
            'api_username' => 'demo',
            'api_key' => 'secret',
            'status' => 1,
            'sort_order' => 1,
        ]);

        $product = Product::query()->create([
            'name' => 'Mofang Service Product '.$suffix,
            'product_type' => 'server',
            'pricing' => ['monthly' => '99.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'status' => 1,
            'auto_setup' => 0,
            'supplier_id' => (int) $supplier->id,
            'supplier_product_id' => 10001,
            'provision_module' => ProviderKey::MOFANG_FINANCE_API,
        ]);
        $this->createProductUpstreamBinding($supplier, $product, 10001);

        $service = Service::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'name' => 'Manual Local Service '.$suffix,
            'domain' => 'manual-'.$suffix.'.example.com',
            'billing_cycle' => 'monthly',
            'amount' => '99.00',
            'status' => ServiceStatus::ACTIVE,
            'locked_pricing' => [],
            'provision_data' => [
                'source_type' => 'manual',
                'created_from_admin' => true,
            ],
            'expires_at' => now()->addMonth(),
            'auto_renew' => 1,
        ]);

        app(UserService::class)->updateServiceMeta($user, (int) $service->id, [
            'supplier_id' => (int) $supplier->id,
            'upstream_product_id' => 20002,
            'upstream_host_id' => 30003,
        ]);

        $provisionData = (array) $service->refresh()->provision_data;

        $this->assertSame('upstream', $provisionData['source_type'] ?? null);
        $this->assertSame(ProviderKey::MOFANG_FINANCE_API, $provisionData['provider_key'] ?? null);
        $this->assertSame((int) $supplier->id, $provisionData['supplier_id'] ?? null);
        $this->assertSame(20002, $provisionData['upstream_product_id'] ?? null);
        $this->assertSame(30003, $provisionData['upstream_host_id'] ?? null);

        $binding = DB::table('service_upstream_bindings')
            ->where('service_id', (int) $service->id)
            ->where('provider_key', ProviderKey::MOFANG_FINANCE_API)
            ->where('upstream_service_id', '30003')
            ->first();

        $this->assertNotNull($binding);
        $this->assertDatabaseHas('service_runtime_snapshots', [
            'service_id' => (int) $service->id,
            'service_upstream_binding_id' => (int) $binding->id,
            'provider_key' => ProviderKey::MOFANG_FINANCE_API,
        ]);
    }

    public function test_update_upstream_host_id_only_corrects_provider_from_supplier_binding(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'service-host-only-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'Mofang Host Only Supplier '.$suffix,
            'code' => 'mofang-host-only-'.$suffix,
            'interface_type' => ProviderKey::MOFANG_FINANCE_API,
            'api_url' => 'https://supplier-host-only-'.$suffix.'.example.com',
            'api_username' => 'demo',
            'api_key' => 'secret',
            'status' => 1,
            'sort_order' => 1,
        ]);

        $product = Product::query()->create([
            'name' => 'Mofang Host Only Product '.$suffix,
            'product_type' => 'server',
            'pricing' => ['monthly' => '88.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'status' => 1,
            'auto_setup' => 1,
            'supplier_id' => (int) $supplier->id,
            'supplier_product_id' => 80001,
            'provision_module' => ProviderKey::MOFANG_FINANCE_API,
        ]);
        $this->createProductUpstreamBinding($supplier, $product, 80001);

        $service = Service::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'name' => 'Mismatched Provider Service '.$suffix,
            'domain' => 'mismatch-'.$suffix.'.example.com',
            'billing_cycle' => 'monthly',
            'amount' => '88.00',
            'status' => ServiceStatus::ACTIVE,
            'locked_pricing' => [],
            'provision_data' => [
                'source_type' => 'upstream',
                'provider_key' => ProviderKey::HOSTING_PANEL_API,
                'supplier_id' => (int) $supplier->id,
                'upstream_product_id' => 80001,
                'upstream_host_id' => 90001,
            ],
            'expires_at' => now()->addMonth(),
            'auto_renew' => 1,
        ]);

        app(UserService::class)->updateServiceMeta($user, (int) $service->id, [
            'upstream_host_id' => 90002,
        ]);

        $provisionData = (array) $service->refresh()->provision_data;

        $this->assertSame(ProviderKey::MOFANG_FINANCE_API, $provisionData['provider_key'] ?? null,
            'When only updating upstream_host_id, the provider key is corrected from the normalized product/supplier binding');
        $this->assertSame(80001, $provisionData['upstream_product_id'] ?? null);
        $this->assertSame(90002, $provisionData['upstream_host_id'] ?? null);
    }

    public function test_rebind_supplier_corrects_mismatched_provider(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'service-rebind-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'Mofang Rebind Supplier '.$suffix,
            'code' => 'mofang-rebind-'.$suffix,
            'interface_type' => ProviderKey::MOFANG_FINANCE_API,
            'api_url' => 'https://supplier-rebind-'.$suffix.'.example.com',
            'api_username' => 'demo',
            'api_key' => 'secret',
            'status' => 1,
            'sort_order' => 1,
        ]);

        $product = Product::query()->create([
            'name' => 'Mofang Rebind Product '.$suffix,
            'product_type' => 'server',
            'pricing' => ['monthly' => '77.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'status' => 1,
            'auto_setup' => 1,
            'supplier_id' => (int) $supplier->id,
            'supplier_product_id' => 80002,
            'provision_module' => ProviderKey::MOFANG_FINANCE_API,
        ]);
        $this->createProductUpstreamBinding($supplier, $product, 80002);

        $service = Service::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'name' => 'Rebind Correction Service '.$suffix,
            'domain' => 'rebind-'.$suffix.'.example.com',
            'billing_cycle' => 'monthly',
            'amount' => '77.00',
            'status' => ServiceStatus::ACTIVE,
            'locked_pricing' => [],
            'provision_data' => [
                'source_type' => 'upstream',
                'provider_key' => ProviderKey::HOSTING_PANEL_API,
                'supplier_id' => (int) $supplier->id,
                'upstream_product_id' => 80002,
                'upstream_host_id' => 91001,
            ],
            'expires_at' => now()->addMonth(),
            'auto_renew' => 1,
        ]);

        app(UserService::class)->updateServiceMeta($user, (int) $service->id, [
            'supplier_id' => (int) $supplier->id,
            'upstream_host_id' => 91002,
        ]);

        $provisionData = (array) $service->refresh()->provision_data;

        $this->assertSame(ProviderKey::MOFANG_FINANCE_API, $provisionData['provider_key'] ?? null,
            'Rebinding same supplier must correct a mismatched provider key to the normalized supplier binding');
        $this->assertSame(80002, $provisionData['upstream_product_id'] ?? null);
        $this->assertSame(91002, $provisionData['upstream_host_id'] ?? null);
        $this->assertDatabaseHas('service_upstream_bindings', [
            'service_id' => (int) $service->id,
            'provider_key' => ProviderKey::MOFANG_FINANCE_API,
            'upstream_service_id' => '91002',
        ]);
    }

    private function createProductUpstreamBinding(Supplier $supplier, Product $product, int $upstreamProductId): void
    {
        $pluginId = (int) DB::table('integration_plugins')
            ->where('domain', 'upstream')
            ->where('plugin_key', ProviderKey::MOFANG_FINANCE_API)
            ->value('id');

        $now = now();
        $supplierBindingId = DB::table('supplier_plugin_bindings')->insertGetId([
            'supplier_id' => (int) $supplier->id,
            'plugin_id' => $pluginId,
            'provider_key' => ProviderKey::MOFANG_FINANCE_API,
            'environment' => 'production',
            'status' => 1,
            'priority' => 1,
            'base_url' => 'https://binding-'.$supplier->id.'.example.com',
            'account_name' => 'demo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('product_upstream_bindings')->insert([
            'product_id' => (int) $product->id,
            'supplier_plugin_binding_id' => $supplierBindingId,
            'plugin_id' => $pluginId,
            'provider_key' => ProviderKey::MOFANG_FINANCE_API,
            'upstream_product_id' => (string) $upstreamProductId,
            'auto_setup' => (int) ($product->auto_setup ?? 0) === 1 ? 1 : 0,
            'status' => 1,
            'last_synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
