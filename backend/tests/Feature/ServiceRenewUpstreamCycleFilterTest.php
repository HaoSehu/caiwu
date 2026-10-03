<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\Provisioning\ServiceRenewService;
use App\Services\Upstream\Contracts\ProvidesRenewal;
use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 续费周期按上游可续周期收敛回归：
 * 上游对"换周期续费"会校验商品定价，未启用该周期时返回"续费周期无效"；若本地不过滤，
 * 用户可以选中上游不支持的周期并完成本地扣款，履约在上游被拒后失败，形成"扣款成功、
 * 上游未续费"。有上游续费能力时预览周期必须收敛到上游认可集合（/host/renewpage 口径）；
 * 上游不可达（返回 null）或交集为空时回退本地集合，保证预览/建单不因上游抖动而不可用。
 * 使用 DatabaseTransactions：所有写入在测试结束后回滚，不污染现有数据。
 */
class ServiceRenewUpstreamCycleFilterTest extends TestCase
{
    use DatabaseTransactions;

    private const FAKE_PROVIDER_KEY = 'fake_renewable';

    public function test_preview_cycles_are_narrowed_to_upstream_renewable_cycles(): void
    {
        // 本地 monthly/quarterly 均有定价，上游只认可 monthly：预览必须只剩 monthly，
        // 避免用户选中 quarterly 后本地扣款成功而上游续费被拒。
        $driver = $this->fakeRenewalDriver();
        $driver->renewableCyclesResult = ['monthly'];

        $this->bindFakeResolver($driver);

        $user = User::factory()->create();
        $service = $this->renewFixture($driver, $user, [
            'monthly' => '35.00',
            'quarterly' => '90.00',
        ]);

        $preview = app(ServiceRenewService::class)->previewForUser($user, (int) $service->id);

        $this->assertSame(
            ['monthly'],
            array_column($preview['cycles'], 'billing_cycle')
        );
        $this->assertSame('monthly', (string) $preview['default_cycle']);
    }

    public function test_preview_falls_back_to_local_cycles_when_upstream_unreachable(): void
    {
        // 上游不可达（renewableCycles 返回 null）：回退本地周期集合，续费入口不因上游抖动失效。
        $driver = $this->fakeRenewalDriver();
        $driver->renewableCyclesResult = null;

        $this->bindFakeResolver($driver);

        $user = User::factory()->create();
        $service = $this->renewFixture($driver, $user, [
            'monthly' => '35.00',
            'quarterly' => '90.00',
        ]);

        $preview = app(ServiceRenewService::class)->previewForUser($user, (int) $service->id);

        // 本地定价缺省周期按服务当前金额推算，回退集合为完整 4 周期（排序见 CYCLE_SORT_MAP）
        $this->assertSame(
            ['monthly', 'quarterly', 'semiannually', 'annually'],
            array_column($preview['cycles'], 'billing_cycle')
        );
    }

    public function test_preview_falls_back_to_local_cycles_when_upstream_set_has_no_overlap(): void
    {
        // 上游返回本地不存在的周期集合（交集为空）：同样回退本地集合并记告警日志，防止口径漂移误伤。
        $driver = $this->fakeRenewalDriver();
        $driver->renewableCyclesResult = ['weekly'];

        $this->bindFakeResolver($driver);

        $user = User::factory()->create();
        $service = $this->renewFixture($driver, $user, [
            'monthly' => '35.00',
            'quarterly' => '90.00',
        ]);

        $preview = app(ServiceRenewService::class)->previewForUser($user, (int) $service->id);

        // 本地定价缺省周期按服务当前金额推算，回退集合为完整 4 周期（排序见 CYCLE_SORT_MAP）
        $this->assertSame(
            ['monthly', 'quarterly', 'semiannually', 'annually'],
            array_column($preview['cycles'], 'billing_cycle')
        );
    }

    /**
     * 可续费假驱动：带上游可续周期查询能力（不触网）。
     */
    private function fakeRenewalDriver(): object
    {
        return new class implements ProvidesRenewal, UpstreamDriver
        {
            /** @var list<string>|null */
            public ?array $renewableCyclesResult = null;

            public function key(): string
            {
                return 'fake_renewable';
            }

            public function label(): string
            {
                return '测试续费驱动';
            }

            public function capabilities(): array
            {
                return [ProvidesRenewal::class];
            }

            public function supports(string $capability): bool
            {
                return $capability === ProvidesRenewal::class;
            }

            public function resolve(string $capability): ?object
            {
                return $this->supports($capability) ? $this : null;
            }

            public function renewableCycles(Supplier $supplier, int $hostId): ?array
            {
                return $this->renewableCyclesResult;
            }
        };
    }

    /**
     * 把含 fake 驱动的解析器注入容器：ServiceRenewService 经容器解析 ProviderResolver，
     * 不注入会落入全局真实驱动注册表，fake_renewable 永远解析不到。
     */
    private function bindFakeResolver(object $driver): void
    {
        $this->app->instance(
            ProviderResolver::class,
            new ProviderResolver(new ProviderRegistry([$driver]))
        );
    }

    private function renewFixture(object $driver, User $user, array $pricing): Service
    {
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => $pricing,
        ]);
        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'status' => 1,
            'provision_data' => ['upstream_host_id' => 85845],
            'expires_at' => now()->addDays(20),
        ]);

        // 造"商品→供应商"绑定链，使续费配置能解析出供应商（服务级绑定保持缺失，
        // 与 ServiceRenewUpstreamBindingGuardTest 场景 A 同构）
        $supplier = Supplier::query()->create([
            'name' => '测试供应商-'.uniqid(),
            'code' => 'SUP-CF-'.uniqid(),
            'status' => 1,
        ]);
        $supplierBindingId = (int) DB::table('supplier_plugin_bindings')->insertGetId([
            'supplier_id' => $supplier->id,
            'plugin_id' => $this->pluginId,
            'provider_key' => $driver->key(),
            'status' => 1,
        ]);
        DB::table('product_upstream_bindings')->insert([
            'product_id' => $product->id,
            'supplier_plugin_binding_id' => $supplierBindingId,
            'plugin_id' => $this->pluginId,
            'provider_key' => $driver->key(),
            'upstream_product_id' => 'p-'.uniqid(),
            'status' => 1,
        ]);

        return $service;
    }

    private int $pluginId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // 注册 fake 插件行：provider_key → plugin_id 解析依赖 integration_plugins；
        // 插入发生在测试事务内，由 DatabaseTransactions 统一回滚，不做手动清理
        $existing = DB::table('integration_plugins')
            ->where('domain', PluginDomain::UPSTREAM)
            ->where('plugin_key', self::FAKE_PROVIDER_KEY)
            ->first(['id']);
        $this->pluginId = $existing !== null
            ? (int) $existing->id
            : (int) DB::table('integration_plugins')->insertGetId([
                'domain' => PluginDomain::UPSTREAM,
                'slug' => self::FAKE_PROVIDER_KEY,
                'plugin_key' => self::FAKE_PROVIDER_KEY,
                'name' => '测试续费插件',
                'entry_class' => 'Tests\\FakeRenewable',
                'status' => 1,
            ]);
    }
}
