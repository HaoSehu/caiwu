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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUserManualServiceStockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->activateIntegrationPluginForTest('upstream', 'zjmf_finance');
    }

    public function test_manual_admin_service_can_be_created_when_product_stock_is_zero(): void
    {
        $suffix = bin2hex(random_bytes(4));
        [$user, $product] = $this->createUserAndProduct($suffix, 0);

        $result = app(UserService::class)->createManualService($user, [
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'source_type' => 'manual',
            'name' => 'Manual Zero Stock Service '.$suffix,
            'amount' => '19.00',
            'status' => ServiceStatus::ACTIVE,
            'auto_renew' => 1,
        ], [
            'operator_id' => 9001,
            'operator_name' => 'Admin Stock Tester',
            'trace_id' => 'manual-zero-stock-'.$suffix,
            'ip_address' => '127.0.0.1',
        ]);

        $serviceId = (int) ($result['service_id'] ?? $result['id'] ?? 0);

        $this->assertGreaterThan(0, $serviceId);
        $this->assertDatabaseHas('services', [
            'id' => $serviceId,
            'product_id' => (int) $product->id,
            'status' => ServiceStatus::ACTIVE,
        ]);
        $this->assertSame(0, (int) $product->refresh()->stock);
    }

    public function test_manual_admin_service_does_not_decrement_positive_stock(): void
    {
        $suffix = bin2hex(random_bytes(4));
        [$user, $product] = $this->createUserAndProduct($suffix, 3);

        app(UserService::class)->createManualService($user, [
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'source_type' => 'manual',
            'name' => 'Manual Positive Stock Service '.$suffix,
            'amount' => '19.00',
            'status' => ServiceStatus::ACTIVE,
            'auto_renew' => 1,
        ], [
            'operator_id' => 9001,
            'operator_name' => 'Admin Stock Tester',
            'trace_id' => 'manual-positive-stock-'.$suffix,
            'ip_address' => '127.0.0.1',
        ]);

        $this->assertSame(3, (int) $product->refresh()->stock);
    }

    public function test_upstream_admin_service_is_still_blocked_when_product_stock_is_zero(): void
    {
        $suffix = bin2hex(random_bytes(4));
        [$user, $product] = $this->createUserAndProduct($suffix, 0, true);

        $this->expectExceptionMessage('该商品库存不足，无法继续开通');

        app(UserService::class)->createManualService($user, [
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'source_type' => 'upstream',
            'name' => 'Upstream Zero Stock Service '.$suffix,
            'amount' => '19.00',
            'status' => ServiceStatus::ACTIVE,
            'auto_renew' => 1,
            'upstream_host_id' => 10001,
        ], [
            'operator_id' => 9001,
            'operator_name' => 'Admin Stock Tester',
            'trace_id' => 'upstream-zero-stock-'.$suffix,
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_upstream_manual_service_writes_zjmf_finance_provider_when_supplier_is_zjmf(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'manual-zjmf-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'Zjmf Manual Supplier '.$suffix,
            'code' => 'zjmf-manual-'.$suffix,
            'interface_type' => ProviderKey::ZJMF_FINANCE_API,
            'api_url' => 'https://zjmf-manual-'.$suffix.'.example.com',
            'api_username' => 'demo',
            'api_key' => 'secret',
            'status' => 1,
            'sort_order' => 1,
        ]);

        $product = Product::query()->create([
            'name' => 'Zjmf Manual Product '.$suffix,
            'product_type' => 'server',
            'pricing' => ['monthly' => '29.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => 5,
            'status' => 1,
            'auto_setup' => 1,
            'supplier_id' => (int) $supplier->id,
            'supplier_product_id' => 60001,
            'provision_module' => ProviderKey::ZJMF_FINANCE_API,
        ]);
        $this->createProductUpstreamBinding($supplier, $product, 60001);

        $result = app(UserService::class)->createManualService($user, [
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'source_type' => 'upstream',
            'name' => 'Zjmf Upstream Service '.$suffix,
            'amount' => '29.00',
            'status' => ServiceStatus::ACTIVE,
            'auto_renew' => 1,
            'upstream_host_id' => 70001,
        ], [
            'operator_id' => 9001,
            'operator_name' => 'Admin Zjmf Tester',
            'trace_id' => 'zjmf-manual-'.$suffix,
            'ip_address' => '127.0.0.1',
        ]);

        $serviceId = (int) ($result['service_id'] ?? $result['id'] ?? 0);
        $this->assertGreaterThan(0, $serviceId);

        $service = Service::query()->find($serviceId);
        $provisionData = (array) $service->provision_data;

        $this->assertSame(ProviderKey::ZJMF_FINANCE_API, $provisionData['provider_key'] ?? null);
        $this->assertNotSame(ProviderKey::HOSTING_PANEL_API, $provisionData['provider_key'] ?? null);
        $this->assertSame((int) $supplier->id, $provisionData['supplier_id'] ?? null);
        $this->assertSame(70001, $provisionData['upstream_host_id'] ?? null);
        $this->assertDatabaseHas('service_upstream_bindings', [
            'service_id' => $serviceId,
            'provider_key' => ProviderKey::ZJMF_FINANCE_API,
            'upstream_service_id' => '70001',
        ]);
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function createUserAndProduct(string $suffix, int $stock, bool $withSupplier = false): array
    {
        $user = User::query()->create([
            'email' => 'manual-stock-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
        ]);

        $supplierId = null;
        if ($withSupplier) {
            $supplier = Supplier::query()->create([
                'name' => 'Manual Stock Supplier '.$suffix,
                'code' => 'manual-stock-'.$suffix,
                'interface_type' => ProviderKey::HOSTING_PANEL_API,
                'api_url' => 'https://supplier-'.$suffix.'.example.com',
                'api_username' => 'demo',
                'api_key' => 'secret',
                'status' => 1,
                'sort_order' => 1,
            ]);
            $supplierId = (int) $supplier->id;
        }

        $product = Product::query()->create([
            'name' => 'Manual Stock Product '.$suffix,
            'product_type' => 'server',
            'pricing' => ['monthly' => '19.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => $stock,
            'status' => 1,
            'auto_setup' => $withSupplier ? 1 : 0,
            'supplier_id' => $supplierId,
            'supplier_product_id' => $withSupplier ? 10001 : null,
            'provision_module' => $withSupplier ? ProviderKey::HOSTING_PANEL_API : null,
        ]);

        return [$user, $product];
    }

    private function createProductUpstreamBinding(Supplier $supplier, Product $product, int $upstreamProductId): void
    {
        $pluginId = (int) DB::table('integration_plugins')
            ->where('domain', 'upstream')
            ->where('plugin_key', ProviderKey::ZJMF_FINANCE_API)
            ->value('id');

        $now = now();
        $supplierBindingId = DB::table('supplier_plugin_bindings')->insertGetId([
            'supplier_id' => (int) $supplier->id,
            'plugin_id' => $pluginId,
            'provider_key' => ProviderKey::ZJMF_FINANCE_API,
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
            'provider_key' => ProviderKey::ZJMF_FINANCE_API,
            'upstream_product_id' => (string) $upstreamProductId,
            'auto_setup' => (int) ($product->auto_setup ?? 0) === 1 ? 1 : 0,
            'status' => 1,
            'last_synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
