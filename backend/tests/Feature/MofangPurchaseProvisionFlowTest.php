<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\CheckoutService;
use App\Services\Finance\PaymentService;
use App\Services\Integrations\Plugins\PluginFileLoader;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Services\Upstream\Contracts\ProvidesProvisioning;
use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Upstream\ProviderKey;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MofangPurchaseProvisionFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('queue.default', 'sync');

        app(PluginFileLoader::class)->ensureLoaded(
            app(PluginScanner::class)->requireManifest('upstream', 'mofang_finance')
        );
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_07_03_130000_create_plugin_binding_runtime_and_audit_tables.php',
            '--force' => true,
        ]);
        $this->activateIntegrationPluginForTest('upstream', 'mofang_finance');
    }

    public function test_mofang_product_purchase_by_balance_triggers_upstream_provisioning(): void
    {
        $provisioning = new FakeMofangProvisioningCapability;
        $this->bindFakeMofangDriver($provisioning);

        $suffix = bin2hex(random_bytes(4));
        $user = User::query()->create([
            'email' => "mofang-purchase-{$suffix}@example.com",
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'is_verified' => 1,
            'verification_status' => 2,
            'nickname' => 'Mofang Purchase',
        ]);
        $user->forceFill(['balance' => '200.00'])->save();

        $supplier = Supplier::query()->create([
            'name' => 'Mofang Purchase Supplier '.$suffix,
            'code' => 'mofang-purchase-'.$suffix,
            'interface_type' => ProviderKey::MOFANG_FINANCE_API,
            'api_url' => 'https://mofang-'.$suffix.'.example.test',
            'api_username' => 'demo',
            'api_key' => 'secret',
            'status' => 1,
            'sort_order' => 1,
        ]);

        $upstreamProductId = random_int(10000, 99999);
        $product = Product::query()->create([
            'name' => '魔方财务购买开通测试商品 '.$suffix,
            'product_type' => 'server',
            'pricing' => ['monthly' => '50.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [
                'upstream_default_config' => [
                    'hostname' => 'mofang-'.$suffix.'.example.test',
                ],
            ],
            'stock' => 5,
            'status' => 1,
            'auto_setup' => 1,
            'supplier_id' => (int) $supplier->id,
            'provision_module' => ProviderKey::MOFANG_FINANCE_API,
            'supplier_product_id' => $upstreamProductId,
        ]);
        $this->bindProductToMofang($supplier, $product, $upstreamProductId);

        $checkout = app(CheckoutService::class);
        $checkoutSecurity = app(CheckoutSecurityService::class);
        $quote = $checkout->quote($product, 'monthly', [], 1);
        $tokenData = $checkoutSecurity->issueQuoteToken($product->id, 'monthly', [], array_merge($quote, [
            'subtotal_amount' => $quote['total_amount'],
        ]));

        $invoice = $checkout->create((int) $user->id, [
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'quantity' => 1,
            'config' => [],
            'quote_token' => (string) $tokenData['quote_token'],
        ], [
            'idempotency_key' => 'mofang-purchase-'.$suffix,
            'trace_id' => 'trace-mofang-purchase-'.$suffix,
        ]);

        app(PaymentService::class)->payByBalance($invoice, $user, [
            'trace_id' => 'trace-mofang-pay-'.$suffix,
        ]);

        $order = Order::query()->findOrFail((int) $invoice->fresh()->order_id);
        $service = Service::query()->where('order_id', (int) $order->id)->firstOrFail();
        $provisionData = (array) $service->provision_data;

        $this->assertSame(1, $provisioning->callCount);
        $this->assertSame(ProviderKey::MOFANG_FINANCE_API, $provisioning->lastProviderKey);
        $this->assertSame((int) $supplier->id, $provisioning->lastSupplierId);
        $this->assertSame($upstreamProductId, $provisioning->lastUpstreamProductId);

        $this->assertDatabaseHas('invoices', [
            'id' => (int) $invoice->id,
            'status' => InvoiceStatus::PAID,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => (int) $order->id,
            'status' => OrderStatus::COMPLETED,
            'service_id' => (int) $service->id,
        ]);
        $this->assertDatabaseHas('services', [
            'id' => (int) $service->id,
            'status' => ServiceStatus::ACTIVE,
        ]);
        $this->assertSame(ProviderKey::MOFANG_FINANCE_API, $provisionData['provider_key'] ?? null);
        $this->assertSame($upstreamProductId, (int) ($provisionData['upstream_product_id'] ?? 0));
        $this->assertSame(88001, (int) ($provisionData['upstream_host_id'] ?? 0));
        $this->assertSame(['203.0.113.10', '203.0.113.11'], $provisionData['assigned_ips'] ?? null);
        $this->assertDatabaseHas('service_upstream_bindings', [
            'service_id' => (int) $service->id,
            'provider_key' => ProviderKey::MOFANG_FINANCE_API,
            'upstream_service_id' => '88001',
        ]);
    }

    private function bindFakeMofangDriver(FakeMofangProvisioningCapability $provisioning): void
    {
        $registry = new ProviderRegistry([
            new FakeMofangProvisioningDriver($provisioning),
        ]);

        $this->app->instance(ProviderRegistry::class, $registry);
        $this->app->forgetInstance(ProviderResolver::class);
        $this->app->instance(ProviderResolver::class, new ProviderResolver($registry));
    }

    private function bindProductToMofang(Supplier $supplier, Product $product, int $upstreamProductId): void
    {
        $pluginId = (int) DB::table('integration_plugins')
            ->where('domain', 'upstream')
            ->where('plugin_key', ProviderKey::MOFANG_FINANCE_API)
            ->value('id');

        $this->assertGreaterThan(0, $pluginId);

        $supplierBindingId = (int) DB::table('supplier_plugin_bindings')->insertGetId([
            'supplier_id' => (int) $supplier->id,
            'plugin_id' => $pluginId,
            'provider_key' => ProviderKey::MOFANG_FINANCE_API,
            'environment' => 'production',
            'status' => 1,
            'priority' => 0,
            'base_url' => (string) $supplier->api_url,
            'account_name' => (string) $supplier->api_username,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_upstream_bindings')->insert([
            'product_id' => (int) $product->id,
            'supplier_plugin_binding_id' => $supplierBindingId,
            'plugin_id' => $pluginId,
            'provider_key' => ProviderKey::MOFANG_FINANCE_API,
            'upstream_product_id' => (string) $upstreamProductId,
            'auto_setup' => 1,
            'status' => 1,
            'last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

final class FakeMofangProvisioningCapability implements ProvidesProvisioning
{
    public int $callCount = 0;

    public ?string $lastProviderKey = null;

    public int $lastSupplierId = 0;

    public int $lastUpstreamProductId = 0;

    public function provisionOrder(Order $order, Supplier $supplier, ?Service $existingService = null): array
    {
        $this->callCount++;
        $this->lastSupplierId = (int) $supplier->id;
        $this->lastProviderKey = (string) DB::table('product_upstream_bindings')
            ->where('product_id', (int) $order->product_id)
            ->value('provider_key');
        $this->lastUpstreamProductId = (int) DB::table('product_upstream_bindings')
            ->where('product_id', (int) $order->product_id)
            ->value('upstream_product_id');

        $requestedHost = (string) data_get($order->config_snapshot, 'hostname', 'mofang-provision.example.test');

        return [
            'requested_host' => $requestedHost,
            'upstream_invoice_id' => 99001,
            'upstream_host_ids' => [88001],
            'upstream_host_id' => 88001,
            'host_detail' => [
                'domain' => $requestedHost,
                'domainstatus' => 'Active',
                'product_name' => (string) ($order->product?->name ?? ''),
                'dedicatedip' => '203.0.113.10',
                'assignedips' => ['203.0.113.10', '203.0.113.11'],
                'config_option' => [],
                'username' => 'root',
                'password' => 'ProvisionSecret123',
                'port' => 22,
                'os' => 'linux',
            ],
        ];
    }
}

final class FakeMofangProvisioningDriver implements UpstreamDriver
{
    public function __construct(private readonly FakeMofangProvisioningCapability $provisioning) {}

    public function key(): string
    {
        return ProviderKey::MOFANG_FINANCE_API;
    }

    public function label(): string
    {
        return '魔方财务接口';
    }

    public function capabilities(): array
    {
        return [ProvidesProvisioning::class];
    }

    public function supports(string $capability): bool
    {
        return $capability === ProvidesProvisioning::class;
    }

    public function resolve(string $capability): ?object
    {
        return $this->supports($capability) ? $this->provisioning : null;
    }
}
