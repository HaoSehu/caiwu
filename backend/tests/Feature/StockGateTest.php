<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Constants\ProductType;
use App\Exceptions\BusinessException;
use App\Models\FirstProductGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\CheckoutService;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\ProductCatalog\ProductSyncService;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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
            $productGroupId = (int) $first->id;
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
