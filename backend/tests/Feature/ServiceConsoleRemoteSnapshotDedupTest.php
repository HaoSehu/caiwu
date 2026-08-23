<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ServiceStatus;
use App\Models\FirstProductGroup;
use App\Models\Product;
use App\Models\SecondProductGroup;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\ThirdProductGroup;
use App\Models\User;
use App\Services\ClientServiceConsole\ServiceDetailService;
use App\Services\ClientServiceConsole\ServiceResolverService;
use App\Services\ClientServiceConsole\ServiceTransformService;
use App\Services\System\OperationLogService;
use App\Services\System\SettingService;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\Contracts\ProvidesConsoleRuntime;
use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Upstream\ProviderKey;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceConsoleRemoteSnapshotDedupTest extends TestCase
{
    private SpyRuntimeDriver $driver;

    private Service $service;

    protected function setUp(): void
    {
        parent::setup();

        $this->activateIntegrationPluginForTest('upstream', 'zjmf_finance');
        Cache::flush();

        $this->driver = new SpyRuntimeDriver;
        $this->bindProviderResolver($this->driver);
        $this->service = $this->makeBoundServiceFixture();
    }

    #[Test]
    public function repeated_remote_state_queries_share_one_upstream_round(): void
    {
        $detailService = $this->makeDetailService();

        $first = $detailService->fetchRemoteState($this->service);
        $second = $detailService->fetchRemoteState($this->service);

        $this->assertSame('snapshot-jwt', $first['jwt']);
        $this->assertSame((int) $first['jwt'], (int) $second['jwt']);
        $this->assertSame(1, $this->driver->countOf('login'));
        $this->assertSame(1, $this->driver->countOf('get_host_detail'));
        $this->assertSame(1, $this->driver->countOf('get_module_status'));
        $this->assertSame(1, $this->driver->countOf('get_text'));
    }

    #[Test]
    public function fresh_snapshot_bypasses_shared_cache(): void
    {
        $detailService = $this->makeDetailService();

        $detailService->fetchRemoteState($this->service);
        $detailService->fetchRemoteState($this->service, null, null, true);

        $this->assertSame(2, $this->driver->countOf('login'));
        $this->assertSame(2, $this->driver->countOf('get_host_detail'));
        $this->assertSame(2, $this->driver->countOf('get_module_status'));
        $this->assertSame(2, $this->driver->countOf('get_text'));
    }

    #[Test]
    public function forget_detail_caches_purges_shared_snapshot(): void
    {
        $detailService = $this->makeDetailService();

        $detailService->fetchRemoteState($this->service);
        $detailService->forgetDetailCaches($this->service);
        $detailService->fetchRemoteState($this->service);

        $this->assertSame(2, $this->driver->countOf('login'));
        $this->assertSame(2, $this->driver->countOf('get_host_detail'));
        $this->assertSame(2, $this->driver->countOf('get_module_status'));
        $this->assertSame(2, $this->driver->countOf('get_text'));
    }

    private function makeBoundServiceFixture(): Service
    {
        $suffix = bin2hex(random_bytes(4));
        $pluginId = (int) DB::table('integration_plugins')
            ->where('domain', 'upstream')
            ->where('plugin_key', ProviderKey::ZJMF_FINANCE_API)
            ->value('id');

        $this->assertGreaterThan(0, $pluginId);

        $user = User::query()->create([
            'email' => 'snapshot-dedup-'.$suffix.'@example.test',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'Dedup Supplier '.$suffix,
            'code' => 'dedup-'.$suffix,
            'status' => 1,
            'sort_order' => 1,
        ]);

        $rootGroup = FirstProductGroup::query()->create([
            'code' => 'vps-'.$suffix,
            'name' => 'VPS '.$suffix,
            'slug' => 'vps-'.$suffix,
            'sort_order' => 0,
            'is_visible' => 1,
            'is_system' => 0,
        ]);

        $category = SecondProductGroup::query()->create([
            'first_product_group_id' => (int) $rootGroup->id,
            'name' => 'Dedup Category '.$suffix,
            'slug' => 'dedup-category-'.$suffix,
            'sort_order' => 0,
            'is_visible' => 1,
        ]);
        $thirdGroup = ThirdProductGroup::query()->create([
            'second_product_group_id' => (int) $category->id,
            'name' => 'Dedup Leaf '.$suffix,
            'slug' => 'dedup-leaf-'.$suffix,
            'sort_order' => 0,
            'is_visible' => 1,
        ]);

        $product = Product::query()->create([
            'product_group_id' => (int) $thirdGroup->id,
            'service_type_code' => 'vps',
            'custom_display_name' => 'Dedup Product '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '99.00'],
            'setup_fee' => '0.00',
            'purchase_requires' => [],
            'stock' => -1,
            'status' => 1,
            'auto_setup' => 1,
        ]);

        $service = Service::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'name' => 'Dedup Service '.$suffix,
            'domain' => 'dedup-'.$suffix.'.example.test',
            'billing_cycle' => 'monthly',
            'amount' => '99.00',
            'status' => ServiceStatus::ACTIVE,
            'locked_pricing' => [],
            'expires_at' => now()->addMonth(),
            'auto_renew' => 1,
        ]);

        $supplierBindingId = DB::table('supplier_plugin_bindings')->insertGetId([
            'supplier_id' => (int) $supplier->id,
            'plugin_id' => $pluginId,
            'provider_key' => ProviderKey::ZJMF_FINANCE_API,
            'environment' => 'production',
            'status' => 1,
            'priority' => 0,
            'base_url' => 'https://supplier-'.$suffix.'.example.test/api',
            'account_name' => 'snapshot-account',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productBindingId = DB::table('product_upstream_bindings')->insertGetId([
            'product_id' => (int) $service->product_id,
            'supplier_plugin_binding_id' => $supplierBindingId,
            'plugin_id' => $pluginId,
            'provider_key' => ProviderKey::ZJMF_FINANCE_API,
            'upstream_product_id' => '8001',
            'auto_setup' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('service_upstream_bindings')->insertGetId([
            'service_id' => (int) $service->id,
            'product_upstream_binding_id' => $productBindingId,
            'supplier_plugin_binding_id' => $supplierBindingId,
            'plugin_id' => $pluginId,
            'provider_key' => ProviderKey::ZJMF_FINANCE_API,
            'upstream_service_id' => '88001',
            'status_snapshot' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $service->fresh();
    }

    private function makeDetailService(): ServiceDetailService
    {
        return new ServiceDetailService(
            app(ProviderResolver::class),
            $this->createMock(OperationLogService::class),
            new ServiceResolverService,
            new ServiceTransformService(new ServiceResolverService, new SettingService),
        );
    }

    private function bindProviderResolver(UpstreamDriver $driver): void
    {
        $resolver = new ProviderResolver(new ProviderRegistry([$driver]));
        $this->app->instance(ProviderResolver::class, $resolver);
    }
}

final class SpyRuntimeDriver implements ProvidesConsoleCatalog, ProvidesConsoleRuntime, UpstreamDriver
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public function countOf(string $method): int
    {
        return count(array_filter(
            $this->calls,
            static fn (array $call): bool => ($call['method'] ?? null) === $method
        ));
    }

    private function record(string $method, mixed ...$args): void
    {
        $this->calls[] = ['method' => $method] + $args;
    }

    public function key(): string
    {
        return ProviderKey::ZJMF_FINANCE_API;
    }

    public function label(): string
    {
        return 'Zjmf Spy Driver';
    }

    public function capabilities(): array
    {
        return [ProvidesConsoleRuntime::class, ProvidesConsoleCatalog::class];
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    public function resolve(string $capability): ?object
    {
        return $this->supports($capability) ? $this : null;
    }

    public function login(Supplier $supplier): string
    {
        $this->record('login', supplier_id: (int) $supplier->id);

        return 'snapshot-jwt';
    }

    public function loginResponse(Supplier $supplier): array
    {
        return ['status' => 200, 'data' => ['jwt' => 'snapshot-jwt']];
    }

    /** @return array{status:int,data:array{host:array<string,mixed>}} */
    public function getHostDetail(Supplier $supplier, int $hostId, string $jwt): array
    {
        $this->record('get_host_detail', supplier_id: (int) $supplier->id, host_id: $hostId);

        return [
            'status' => 200,
            'data' => [
                'host' => [
                    'domainstatus' => 'Active',
                    'bwusage' => 256,
                    'bwlimit' => 1024,
                ],
            ],
        ];
    }

    public function getModuleStatus(Supplier $supplier, int $hostId, string $type, string $jwt): array
    {
        $this->record('get_module_status', supplier_id: (int) $supplier->id, host_id: $hostId, type: $type);

        return [
            'status' => 200,
            'data' => [
                'status' => 'Active',
                'des' => '运行中',
            ],
        ];
    }

    public function getText(Supplier $supplier, string $uri, ?string $jwt = null, array $query = [], array $headers = []): string
    {
        $this->record('get_text', supplier_id: (int) $supplier->id, uri: $uri);

        return '<html><body></body></html>';
    }

    public function getSupportedModules(Supplier $supplier, int $hostId, string $jwt): array
    {
        return ['status' => 200, 'data' => ['list' => []]];
    }

    public function fetchCustomModulePage(Supplier $supplier, int $hostId, string $moduleKey, string $jwt): string
    {
        return '<html></html>';
    }

    public function getCustomModuleActionEndpoint(Supplier $supplier, int $hostId): string
    {
        return "https://upstream.example/provision/custom/{$hostId}";
    }

    /** @param array|string $payload */
    public function post(Supplier $supplier, string $uri, array|string $payload = [], ?string $jwt = null, array $headers = [], array $query = []): array
    {
        return ['status' => 200, 'data' => []];
    }

    public function getHostUpgradeConfigOptions(Supplier $supplier, int $hostId, string $jwt): array
    {
        return [
            'response' => ['status' => 200],
            'payload' => [],
            'options' => [],
        ];
    }
}