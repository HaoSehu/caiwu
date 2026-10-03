<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ServiceStatus;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Automation\ServiceStatusSyncService;
use App\Services\Upstream\Contracts\ProvidesStatusSync;
use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 状态同步的续费保护回归：
 * - 本地已开通且未到期、无本地暂停原因，而上游仍显示暂停（续费后上游未解除暂停的
 *   过渡态）时，保留本地 ACTIVE，避免"续费成功却仍被暂停"甚至触发到期暂停误杀；
 * - 上游 nextduedate 早于本地 expires_at（上游可能尚未同步续费结果）时，不缩短本地
 *   有效期，防止续费后的延期被上游旧到期时间回退；
 * - 上游到期更新晚于本地时照常采纳；本地已到期的暂停同步不受保护影响。
 * 使用 DatabaseTransactions：所有写入在测试结束后回滚，不污染现有数据。
 */
class ServiceStatusSyncRenewGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_local_active_state_is_preserved_when_upstream_still_suspended(): void
    {
        // 续费保护①：上游 suspended 但本地已开通未到期 → 保留 ACTIVE；
        // 上游到期时间晚于本地 → 照常采纳为新的 expires_at。
        $driver = $this->fakeStatusSyncDriver([
            'domainstatus' => 'suspended',
            'nextduedate' => now()->addDays(40)->getTimestamp(),
        ]);
        $service = $this->syncFixture($driver, [
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->addDays(20),
        ]);

        app($this->syncService($driver))->syncServices($this->collectService($service));

        $fresh = $service->refresh();
        $this->assertSame(ServiceStatus::ACTIVE, (int) $fresh->status);
        $this->assertSame(
            now()->addDays(40)->format('Y-m-d H'),
            $fresh->expires_at?->format('Y-m-d H')
        );
    }

    public function test_local_expiry_is_not_rolled_back_by_stale_upstream_due_date(): void
    {
        // 续费保护②：上游 nextduedate 早于本地 expires_at（上游未同步续费结果的旧数据）
        // → 保留本地较晚的到期时间，不回退。
        $driver = $this->fakeStatusSyncDriver([
            'domainstatus' => 'active',
            'nextduedate' => now()->subDays(3)->getTimestamp(),
        ]);
        $service = $this->syncFixture($driver, [
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->addDays(20),
        ]);

        app($this->syncService($driver))->syncServices($this->collectService($service));

        $fresh = $service->refresh();
        $this->assertSame(ServiceStatus::ACTIVE, (int) $fresh->status);
        $this->assertSame(
            now()->addDays(20)->format('Y-m-d H'),
            $fresh->expires_at?->format('Y-m-d H')
        );
    }

    public function test_expired_service_suspension_syncs_normally_without_guard(): void
    {
        // 负向对照：本地已到期 + 上游暂停 → 不属于续费过渡态，暂停同步照常生效。
        $driver = $this->fakeStatusSyncDriver([
            'domainstatus' => 'suspended',
            'nextduedate' => now()->subDays(3)->getTimestamp(),
        ]);
        $service = $this->syncFixture($driver, [
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->subDay(),
        ]);

        app($this->syncService($driver))->syncServices($this->collectService($service));

        $this->assertSame(ServiceStatus::SUSPENDED, (int) $service->refresh()->status);
    }

    /**
     * 状态同步假驱动：按 host 固定快照应答（不触网）。
     */
    private function fakeStatusSyncDriver(array $host): object
    {
        return new class($host) implements ProvidesStatusSync, UpstreamDriver
        {
            public function __construct(private readonly array $host) {}

            public function key(): string
            {
                return 'fake_syncable';
            }

            public function label(): string
            {
                return '测试状态同步驱动';
            }

            public function capabilities(): array
            {
                return [ProvidesStatusSync::class];
            }

            public function supports(string $capability): bool
            {
                return $capability === ProvidesStatusSync::class;
            }

            public function resolve(string $capability): ?object
            {
                return $this->supports($capability) ? $this : null;
            }

            public function syncServiceStatuses(Supplier $supplier, array $items, int $chunkSize = 10): array
            {
                $services = [];
                foreach ($items as $item) {
                    $services[(int) $item['service_id']] = [
                        'host' => $this->host,
                        'runtime' => [],
                    ];
                }

                return ['services' => $services];
            }
        };
    }

    private function syncService(object $driver): string
    {
        $this->app->instance(
            ProviderResolver::class,
            new ProviderResolver(new ProviderRegistry([$driver]))
        );

        return ServiceStatusSyncService::class;
    }

    private function collectService(Service $service): EloquentCollection
    {
        return Service::query()
            ->whereKey((int) $service->id)
            ->get();
    }

    /**
     * 造"服务→商品→供应商"绑定链（服务级绑定存在 upstream_service_id，供应商经商品绑定解析）。
     */
    private function syncFixture(object $driver, array $serviceOverrides): Service
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        $service = Service::query()->create(array_merge([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->addDays(20),
        ], $serviceOverrides));

        $supplier = Supplier::query()->create([
            'name' => '测试供应商-'.uniqid(),
            'code' => 'SUP-SG-'.uniqid(),
            'status' => 1,
        ]);
        $supplierBindingId = (int) DB::table('supplier_plugin_bindings')->insertGetId([
            'supplier_id' => $supplier->id,
            'plugin_id' => $this->pluginId,
            'provider_key' => self::FAKE_PROVIDER_KEY,
            'status' => 1,
        ]);
        DB::table('product_upstream_bindings')->insert([
            'product_id' => $product->id,
            'supplier_plugin_binding_id' => $supplierBindingId,
            'plugin_id' => $this->pluginId,
            'provider_key' => self::FAKE_PROVIDER_KEY,
            'upstream_product_id' => 'p-'.uniqid(),
            'status' => 1,
        ]);
        DB::table('service_upstream_bindings')->insert([
            'service_id' => $service->id,
            'plugin_id' => $this->pluginId,
            'provider_key' => self::FAKE_PROVIDER_KEY,
            'upstream_service_id' => '85845',
            'supplier_plugin_binding_id' => null,
            'product_upstream_binding_id' => null,
        ]);

        return $service;
    }

    private const FAKE_PROVIDER_KEY = 'fake_syncable';

    private int $pluginId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // 注册 fake 插件行：provider_key → plugin_id 解析依赖 integration_plugins；
        // 插入发生在测试事务内，由 DatabaseTransactions 统一回滚，不做手动清理
        $existing = DB::table('integration_plugins')
            ->where('domain', 'upstream')
            ->where('plugin_key', self::FAKE_PROVIDER_KEY)
            ->first(['id']);
        $this->pluginId = $existing !== null
            ? (int) $existing->id
            : (int) DB::table('integration_plugins')->insertGetId([
                'domain' => 'upstream',
                'slug' => self::FAKE_PROVIDER_KEY,
                'plugin_key' => self::FAKE_PROVIDER_KEY,
                'name' => '测试状态同步插件',
                'entry_class' => 'Tests\\FakeSyncable',
                'status' => 1,
            ]);
    }
}
