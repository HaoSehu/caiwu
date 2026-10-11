<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Constants\ProductType;
use App\Exceptions\BusinessException;
use App\Exceptions\UpstreamStockShortageException;
use App\Models\FirstProductGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\SecondProductGroup;
use App\Models\Supplier;
use App\Models\ThirdProductGroup;
use App\Models\User;
use App\Services\ClientServiceConsole\ServiceUpgradeService;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\CheckoutService;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\ProductCatalog\ProductSyncService;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use App\Support\CacheKey;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfProvisionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 新购库存双闸门与预留释放回归：
 * - 第一遍（结账）：结账事务内按上游快照库存减开放预留校验，快照为 0
 *   或已被在途预留占满时直接拦截"库存不足"；负值视为不限库存放行。
 * - 第二遍（履约）：提交上游购物车结算前按上游实时库存复核；上游库存被在途
 *   预留占满时拦截，且必须排除本单自身预留额度（否则已付款单会把自己挡死）。
 * - 库存占用统一由开放预留统计承担（products.stock 为纯上游快照，无业务增减），
 *   取消/退款/过期通过订单状态流转释放预留。
 * - 浏览覆盖：目录/详情拉上游实时库存展示并回写快照，失败降级快照不阻塞页面。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class StockGateTest extends TestCase
{
    use DatabaseTransactions;

    private const FAKE_PROVIDER_KEY = 'fake_stock_gate';

    private int $pluginId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // redis_volatile 切 array store：覆盖标记与前台库存缓存不落真实 redis，
        // array store 随用例的应用实例重建，天然隔离无残留
        config(['cache.stores.redis_volatile' => ['driver' => 'array']]);

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
                'name' => '测试库存闸门插件',
                'entry_class' => 'Tests\\FakeStockGate',
                'status' => 1,
            ]);
    }

    public function test_checkout_rejects_when_snapshot_stock_is_zero(): void
    {
        $user = User::factory()->create(['is_verified' => 1]);
        $product = $this->makeProduct(stock: 0);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('库存不足');

        app(CheckoutService::class)->create((int) $user->id, $this->checkoutPayload($product, 'stock-gate-placeholder'), [
            'idempotency_key' => 'stock-gate-zero-'.uniqid(),
        ]);
    }

    public function test_checkout_rejects_when_snapshot_stock_fully_reserved(): void
    {
        $user = User::factory()->create(['is_verified' => 1]);
        $product = $this->makeProduct(stock: 1);
        $this->makePaidNewOrder($user, $product);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('库存不足');

        app(CheckoutService::class)->create((int) $user->id, $this->checkoutPayload($product, 'stock-gate-placeholder'), [
            'idempotency_key' => 'stock-gate-reserved-'.uniqid(),
        ]);
    }

    public function test_snapshot_gate_allows_unlimited_stock(): void
    {
        $product = $this->makeProduct(stock: -1);

        app(ProductSyncService::class)->assertSnapshotStockAvailable($product, 1);

        $this->assertTrue(true);
    }

    public function test_checkout_allows_successive_orders_within_snapshot_stock(): void
    {
        // 双计回归：库存占用统一由开放预留统计承担（下单不再增减快照），
        // 快照库存 2 时连续两单（不同用户规避账单指纹复用）都必须放行；
        // 若库存增减机制回归（下单扣减快照 + 预留统计并存），第二单会被双重扣减误拦。
        $checkout = app(CheckoutService::class);
        $product = $this->makeProduct(stock: 2, withVisibleGroup: true);

        foreach ([0, 1] as $index) {
            $user = User::factory()->create(['is_verified' => 1]);
            $invoice = $checkout->create((int) $user->id, $this->checkoutPayload($product, $this->issueQuoteToken($checkout, $user, $product)), [
                'idempotency_key' => 'stock-gate-success-'.$index.'-'.uniqid(),
            ]);

            $this->assertSame(1, (int) $invoice->quantity);
        }
    }

    public function test_cancelled_order_releases_stock_reservation(): void
    {
        // 预留释放回归：快照库存 1，第一单结账占用预留；取消后订单退出预留统计，同用户可再次下单。
        $checkout = app(CheckoutService::class);
        $user = User::factory()->create(['is_verified' => 1]);
        $product = $this->makeProduct(stock: 1, withVisibleGroup: true);

        $first = $checkout->create((int) $user->id, $this->checkoutPayload($product, $this->issueQuoteToken($checkout, $user, $product)), [
            'idempotency_key' => 'stock-gate-cancel-1-'.uniqid(),
        ]);
        $checkout->cancel($first);

        $second = $checkout->create((int) $user->id, $this->checkoutPayload($product, $this->issueQuoteToken($checkout, $user, $product)), [
            'idempotency_key' => 'stock-gate-cancel-2-'.uniqid(),
        ]);

        $this->assertSame(1, (int) $second->quantity);
    }

    public function test_refunded_order_releases_stock_reservation(): void
    {
        // 预留释放回归：快照库存 1，PAID 在途订单占用唯一名额时结账被拦（闸门按预留计算 1-1=0）；
        // 订单退款（REFUNDED）退出预留统计后释放名额（1-0=1）放行。
        $checkout = app(CheckoutService::class);
        $user = User::factory()->create(['is_verified' => 1]);
        $product = $this->makeProduct(stock: 1, withVisibleGroup: true);
        $order = $this->makePaidNewOrder($user, $product);

        try {
            $checkout->create((int) $user->id, $this->checkoutPayload($product, $this->issueQuoteToken($checkout, $user, $product)), [
                'idempotency_key' => 'stock-gate-refund-1-'.uniqid(),
            ]);
            $this->fail('在途预留占满时结账应当被拦截');
        } catch (BusinessException $exception) {
            $this->assertSame('库存不足', $exception->getMessage());
        }

        $order->forceFill(['status' => OrderStatus::REFUNDED])->save();

        $invoice = $checkout->create((int) $user->id, $this->checkoutPayload($product, $this->issueQuoteToken($checkout, $user, $product)), [
            'idempotency_key' => 'stock-gate-refund-2-'.uniqid(),
        ]);

        $this->assertSame(1, (int) $invoice->quantity);
    }

    public function test_upstream_gate_allows_when_upstream_unreachable(): void
    {
        // 履约闸门降级口径：上游拉取异常时放行，交由上游购物车结算最终把关。
        [$product, $syncService] = $this->makeBoundProduct(remoteStock: 7, localStock: 3, throwOnFetch: true);

        $syncService->assertUpstreamStockForProvision($product, 0, 1);

        $this->assertTrue(true);
    }

    public function test_upstream_gate_allows_product_without_upstream_binding(): void
    {
        $product = $this->makeProduct(stock: 0);

        app(ProductSyncService::class)->assertUpstreamStockForProvision($product, 0, 1);

        $this->assertTrue(true);
    }

    public function test_upstream_gate_blocks_when_remote_stock_exhausted(): void
    {
        [$product, $syncService] = $this->makeBoundProduct(remoteStock: 1);
        $this->makePaidNewOrder(User::factory()->create(), $product);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('库存不足');

        $syncService->assertUpstreamStockForProvision($product, 0, 1);
    }

    public function test_upstream_gate_excludes_own_order_reservation(): void
    {
        [$product, $syncService] = $this->makeBoundProduct(remoteStock: 2);
        $this->makePaidNewOrder(User::factory()->create(), $product);
        $ownOrder = $this->makePaidNewOrder(User::factory()->create(), $product);

        // 上游库存 2，在途预留含本单共 2：排除本单后 2-1=1 ≥ 1 应放行；
        // 未排除本单时 2-2=0 会把自己误拦，此用例即该回归的守卫。
        $syncService->assertUpstreamStockForProvision($product, (int) $ownOrder->id, 1);

        $this->assertTrue(true);
    }

    public function test_browse_overlay_persists_upstream_snapshot_to_product_stock(): void
    {
        [$product, $syncService] = $this->makeBoundProduct(remoteStock: 7, localStock: 3);

        $overlaid = $syncService->applyLiveStockToProducts(new Collection([$product]), true);

        // 展示值取上游实时，且本地快照被覆盖为上游原始值（与 15 分钟同步任务同口径）
        $this->assertSame(7, (int) $overlaid->first()->getAttribute('live_stock'));
        $this->assertSame(7, (int) $product->fresh()->stock);
    }

    public function test_browse_overlay_skips_write_when_upstream_unchanged(): void
    {
        [$product, $syncService] = $this->makeBoundProduct(remoteStock: 3, localStock: 3);
        $updatedAtBefore = $product->refresh()->updated_at?->toISOString();

        $syncService->applyLiveStockToProducts(new Collection([$product]), true);

        // 上游值与快照一致时不产生写放大（updated_at 不变）
        $this->assertSame($updatedAtBefore, $product->fresh()->updated_at?->toISOString());
    }

    public function test_browse_overlay_degrades_to_snapshot_when_upstream_fails(): void
    {
        [$product, $syncService] = $this->makeBoundProduct(remoteStock: 7, localStock: 3, throwOnFetch: true);
        $stockBefore = (int) $product->refresh()->stock;

        $overlaid = $syncService->applyLiveStockToProducts(new Collection([$product]), true);

        // 上游不可达时浏览不炸：展示降级为本地快照，快照不被误写
        $this->assertSame($stockBefore, (int) $product->fresh()->stock);
        $this->assertSame($stockBefore, (int) $overlaid->first()->getAttribute('live_stock'));
    }

    public function test_provision_shortage_marks_override_zeroes_snapshot_and_clears_site_cache(): void
    {
        // 售罄校准回归（订单 3202 事故）：上游加购以「库存不足」拒绝后，本地快照
        // 必须立即归零（快照闸门拦新单）、写覆盖标记（阻断浏览回写把 qty 假值写回）、
        // 清前台新鲜/陈旧双键（售罄立即可见）。
        [$product, $syncService] = $this->makeBoundProduct(remoteStock: 20, localStock: 20);
        $supplierId = $this->boundSupplierId($product);

        $store = Cache::store('redis_volatile');
        $siteKey = CacheKey::siteProductStock((int) $product->id);
        $store->put($siteKey, ['product_id' => (int) $product->id, 'stock' => 20], 60);
        $store->put($siteKey.':stale', ['product_id' => (int) $product->id, 'stock' => 20], 60);

        $syncService->markUpstreamStockShortage((int) $product->id);

        $this->assertSame(0, (int) $product->fresh()->stock);
        $this->assertTrue($store->has(CacheKey::upstreamStockShortage($supplierId, 11)));
        $this->assertFalse($store->has($siteKey));
        $this->assertFalse($store->has($siteKey.':stale'));
    }

    public function test_shortage_override_forces_remote_stock_reads_to_zero(): void
    {
        // 覆盖期读取回归：上游 qty 仍返回 20（展示口径假值）时，覆盖标记内
        // 库存读取一律按 0 —— 展示归零，浏览回写不再把假值写回快照。
        [$product, $syncService] = $this->makeBoundProduct(remoteStock: 20, localStock: 20);
        $supplierId = $this->boundSupplierId($product);

        Cache::store('redis_volatile')->put(
            CacheKey::upstreamStockShortage($supplierId, 11),
            true,
            now()->addSeconds(60)
        );

        $overlaid = $syncService->applyLiveStockToProducts(new Collection([$product]), true);

        $this->assertSame(0, (int) $overlaid->first()->getAttribute('live_stock'));
        $this->assertSame(0, (int) $product->fresh()->stock);
    }

    public function test_shortage_override_is_scoped_to_bound_supplier_product(): void
    {
        // 覆盖隔离回归：售罄覆盖只作用于绑定的供应商+上游商品，其他商品不受牵连。
        [$marked, $markedSync] = $this->makeBoundProduct(remoteStock: 20, localStock: 20);
        [$other, $otherSync] = $this->makeBoundProduct(remoteStock: 5, localStock: 5);

        $markedSync->markUpstreamStockShortage((int) $marked->id);

        $this->assertSame(0, (int) $marked->fresh()->stock);

        $overlaidOther = $otherSync->applyLiveStockToProducts(new Collection([$other]), true);
        $this->assertSame(5, (int) $overlaidOther->first()->getAttribute('live_stock'));
        $this->assertSame(5, (int) $other->fresh()->stock);
    }

    public function test_zjmf_upstream_stock_shortage_message_is_classified(): void
    {
        // 上游「库存不足」文案分类回归：命中售罄词抛 UpstreamStockShortageException
        // （供开通编排层校准库存），其他失败仍为普通 BusinessException。
        // 插件类由运行时的 PluginFileLoader 装载，测试中手动引入（与 ZjmfProvisionServiceTest 同款）。
        require_once __DIR__.'/../../plugins/servers/zjmf_finance/lib/ZjmfFinanceTransport.php';
        require_once __DIR__.'/../../plugins/servers/zjmf_finance/lib/ZjmfProvisionService.php';

        $provisionClass = ZjmfProvisionService::class;
        $service = (new ReflectionClass($provisionClass))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($provisionClass, 'throwUpstreamFailure');
        $method->setAccessible(true);

        try {
            $method->invoke($service, ['status' => 400, 'msg' => '产品美国轻量云 2H2G库存不足'], '加入上游购物车');
            $this->fail('库存不足文案应抛售罄分类异常');
        } catch (UpstreamStockShortageException $exception) {
            $this->assertSame('加入上游购物车失败，请稍后重试', $exception->getMessage());
        }

        try {
            $method->invoke($service, ['status' => 400, 'msg' => '登录信息错误'], '加入上游购物车');
            $this->fail('非库存文案应抛普通业务异常');
        } catch (BusinessException $exception) {
            $this->assertNotInstanceOf(UpstreamStockShortageException::class, $exception);
        }
    }

    public function test_upgrade_rejection_calibrates_target_product_from_snapshot_meta(): void
    {
        // 升级校准接线回归：升级单 product_id 是服务当前旧商品，目标商品只在
        // config_pricing_snapshot.meta.product_id——校准必须归零目标商品，
        // 误归零旧商品会错杀在售库存（P0 修正回归）。
        $serviceClass = ServiceUpgradeService::class;

        $syncMock = $this->createMock(ProductSyncService::class);
        $syncMock->expects($this->once())
            ->method('markUpstreamStockShortage')
            ->with($this->identicalTo(991));
        $this->app->instance(ProductSyncService::class, $syncMock);

        $order = new Order;
        $order->config_pricing_snapshot = ['meta' => ['product_id' => '991', 'kind' => 'host_upgrade']];

        $method = new ReflectionMethod($serviceClass, 'calibrateTargetStockForUpgrade');
        $method->setAccessible(true);
        $method->invoke(app($serviceClass), $order, new UpstreamStockShortageException('超出库存'));

        // 非库存类失败不触发校准
        $idleMock = $this->createMock(ProductSyncService::class);
        $idleMock->expects($this->never())->method('markUpstreamStockShortage');
        $this->app->instance(ProductSyncService::class, $idleMock);
        $method->invoke(app($serviceClass), $order, new BusinessException('参数错误'));

        // meta 缺失时静默跳过（>0 守卫）
        $guardMock = $this->createMock(ProductSyncService::class);
        $guardMock->expects($this->never())->method('markUpstreamStockShortage');
        $this->app->instance(ProductSyncService::class, $guardMock);
        $orderWithoutMeta = new Order;
        $orderWithoutMeta->config_pricing_snapshot = ['meta' => []];
        $method->invoke(app($serviceClass), $orderWithoutMeta, new UpstreamStockShortageException('超出库存'));
    }

    private function boundSupplierId(Product $product): int
    {
        $binding = DB::table('product_upstream_bindings')
            ->where('product_id', $product->id)
            ->firstOrFail(['supplier_plugin_binding_id']);
        $supplierBinding = DB::table('supplier_plugin_bindings')
            ->where('id', $binding->supplier_plugin_binding_id)
            ->firstOrFail(['supplier_id']);

        return (int) $supplierBinding->supplier_id;
    }

    private function checkoutPayload(Product $product, string $quoteToken): array
    {
        return [
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'quantity' => 1,
            'config' => [],
            'quote_token' => $quoteToken,
        ];
    }

    private function issueQuoteToken(CheckoutService $checkout, User $user, Product $product): string
    {
        $quote = $checkout->quote($product, 'monthly', [], 1);
        $issued = app(CheckoutSecurityService::class)->issueQuoteToken(
            (int) $product->id,
            'monthly',
            [],
            $quote,
            ['user_id' => (int) $user->id],
        );

        return (string) $issued['quote_token'];
    }

    private function makeProduct(int $stock, bool $withVisibleGroup = false): Product
    {
        $productGroupId = null;
        if ($withVisibleGroup) {
            // 结账事务内的分组可见性复核要求商品挂可见分组；一级分组 code 受唯一约束，复用既有行
            $first = FirstProductGroup::query()->firstOrCreate(
                ['code' => ProductType::VPS],
                [
                    'product_type' => 'cloud_server',
                    'name' => '库存闸门测试一级分组',
                    'slug' => 'stock-gate-first-'.uniqid(),
                    'sort_order' => 999,
                    'is_visible' => 1,
                    'is_system' => 0,
                ]
            );
            if ((int) $first->is_visible !== 1) {
                $first->forceFill(['is_visible' => 1])->save();
            }

            // 商品挂「可见三级分组」（products.product_group_id 外键 → third_product_groups），
            // 逐级造 可见二级 + 可见三级；原实现直接把一级分组 id 塞给 product_group_id，
            // 仅因历史库恰有同 id 的三级行才通过（重建库即触发外键失败）。
            $second = SecondProductGroup::query()->create([
                'first_product_group_id' => (int) $first->id,
                'name' => '库存闸门测试二级分组',
                'slug' => 'stock-gate-second-'.uniqid(),
                'sort_order' => 999,
                'is_visible' => 1,
            ]);
            $third = ThirdProductGroup::query()->create([
                'second_product_group_id' => (int) $second->id,
                'name' => '库存闸门测试三级分组',
                'slug' => 'stock-gate-third-'.uniqid(),
                'sort_order' => 999,
                'is_visible' => 1,
            ]);
            $productGroupId = (int) $third->id;
        }

        return Product::query()->create([
            'product_type' => 'cloud_host',
            'name' => '库存闸门测试商品-'.uniqid(),
            'product_group_id' => $productGroupId,
            'status' => 1,
            'stock' => $stock,
            'pricing' => ['monthly' => '35.00'],
        ]);
    }

    private function makePaidNewOrder(User $buyer, Product $product): Order
    {
        return Order::query()->create([
            'order_no' => 'SG'.date('YmdHis').mt_rand(1000, 9999),
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'type' => OrderType::NEW,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'paid_amount' => 35.00,
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
        ]);
    }

    /**
     * 造「商品 → 供应商 → fake 目录驱动」绑定链，驱动返回指定上游库存。
     *
     * @return array{0: Product, 1: ProductSyncService}
     */
    private function makeBoundProduct(int $remoteStock, ?int $localStock = null, bool $throwOnFetch = false): array
    {
        $product = $this->makeProduct(stock: $localStock ?? $remoteStock);
        $supplier = Supplier::query()->create([
            'name' => '测试供应商-'.uniqid(),
            'code' => 'SUP-SG-'.uniqid(),
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
            'upstream_product_id' => '11',
            'status' => 1,
        ]);

        $driver = new class($remoteStock, $throwOnFetch) implements ProvidesConsoleCatalog, UpstreamDriver
        {
            public function __construct(
                private readonly int $remoteStock,
                private readonly bool $throwOnFetch = false,
            ) {}

            public function key(): string
            {
                return 'fake_stock_gate';
            }

            public function label(): string
            {
                return '测试库存闸门驱动';
            }

            public function capabilities(): array
            {
                return [ProvidesConsoleCatalog::class];
            }

            public function supports(string $capability): bool
            {
                return $capability === ProvidesConsoleCatalog::class;
            }

            public function resolve(string $capability): ?object
            {
                return $this->supports($capability) ? $this : null;
            }

            public function fetchBatchProductStocks(Supplier $supplier, array $productIds, int $chunkSize = 8): array
            {
                if ($this->throwOnFetch) {
                    throw new \RuntimeException('模拟上游库存接口不可达');
                }

                return array_fill_keys(array_map(intval(...), $productIds), ['stock' => $this->remoteStock]);
            }
        };

        $syncService = new ProductSyncService(
            new ProviderResolver(new ProviderRegistry([$driver])),
        );

        return [$product, $syncService];
    }
}
