<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Supplier;
use App\Services\ProductCatalog\ProductSyncService;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfAuthManager;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfCatalogService;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfCloudConfigTemplate;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfFinanceTransport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * ZJMF 批量对接缺价商品补价回归（TuraIDC 3381388 同源）。
 *
 * 背景：ZJMF 的 /cart/all 列表与 prodetail 详情都可能不返回价格，
 * ProductSyncService::buildImportedPricing 拿不到可导入价格时会把商品整批跳过。
 * 修复：ZjmfCatalogService::hydrateSelectedPricing 按选中商品串行拉取
 * /cart/get_product_config 的 product_pricings 补充价格，导入前回填目录。
 *
 * 使用 DatabaseTransactions：Supplier 写入在测试结束后回滚，不污染 idc_test 现有数据。
 */
class ZjmfCatalogPricingHydrationTest extends TestCase
{
    use DatabaseTransactions;

    private const JWT = 'unit-test-jwt-token';

    #[Test]
    public function hydrate_selected_pricing_fills_missing_pricing_from_product_config(): void
    {
        $supplier = $this->createSupplier();
        $products = [
            $this->product(101, '缺价商品'),
            $this->product(102, '已有月价商品', ['monthly_price' => '25.50', 'billingcycle' => 'monthly']),
            $this->product(103, '未选中商品'),
        ];

        // 只有缺价且被选中的商品（101）会触发 /cart/get_product_config 补价；
        // 上游按周期顺序取第一个有效价格，monthly=19.99 命中月价。
        $configResponses = [
            101 => [
                'status' => 200,
                'data' => [
                    'product_pricings' => [
                        ['monthly' => '19.99', 'quarterly' => '55.00', 'msetupfee' => '10.00'],
                    ],
                ],
            ],
        ];

        $service = $this->makeCatalogService($configResponses);
        $hydrated = $service->hydrateSelectedPricing($supplier, $products, [101, 102]);

        $this->assertSame('19.99', $hydrated[0]['monthly_price'], '缺价商品的月价应从购买配置接口补齐');
        $this->assertSame('19.99', $hydrated[0]['product_price'], '缺价商品的单价应随周期价格一并补齐');
        $this->assertSame('monthly', $hydrated[0]['billingcycle']);
        $this->assertSame('10.00', $hydrated[0]['setup_fee'], '开空费应随价格一并补齐');
        $this->assertSame('25.50', $hydrated[1]['monthly_price'], '已有价格的商品不得被上游数据覆盖');
        $this->assertNull($hydrated[2]['monthly_price'], '未选中的商品不参与补价');
    }

    #[Test]
    public function hydrate_selected_pricing_skips_upstream_calls_when_all_selected_have_pricing(): void
    {
        $supplier = $this->createSupplier();
        $products = [
            $this->product(201, '已有月价商品', ['monthly_price' => '25.50', 'billingcycle' => 'monthly']),
            $this->product(202, '已有周期价商品', ['product_price' => '98.00', 'billingcycle' => 'quarterly']),
        ];

        // 全部选中商品已有可导入价格：不应登录上游，也不应拉取购买配置。
        $service = $this->makeCatalogService([], expectUpstreamCalls: false);
        $hydrated = $service->hydrateSelectedPricing($supplier, $products, [201, 202]);

        $this->assertSame($products, $hydrated, '无缺价商品时目录应原样返回');
    }

    #[Test]
    public function hydrate_selected_pricing_keeps_products_when_upstream_has_no_pricing(): void
    {
        $supplier = $this->createSupplier();
        $products = [$this->product(301, '上游也无价格商品')];

        // 上游配置接口不返回 product_pricings：补价失败不抛错，商品保持缺价
        // （由 ProductSyncService 的跳过逻辑兜底，不阻塞其余商品导入）。
        $service = $this->makeCatalogService([
            301 => ['status' => 200, 'data' => ['products' => ['id' => 301]]],
        ]);
        $hydrated = $service->hydrateSelectedPricing($supplier, $products, [301]);

        $this->assertSame($products, $hydrated, '上游无价格数据时不得篡改目录条目');
    }

    /**
     * 导入价格周期换算必须走整数分运算（TuraIDC cc4364d 同源）：
     * 先转分再乘，避免浮点乘法在边界产生一分钱误差。
     */
    #[Test]
    public function build_imported_pricing_expands_cycles_with_cents_math(): void
    {
        $service = app(ProductSyncService::class);
        $method = new ReflectionMethod(ProductSyncService::class, 'buildImportedPricing');

        $this->assertSame([
            'monthly' => '19.99',
            'quarterly' => '59.97',
            'semiannually' => '119.94',
            'annually' => '239.88',
        ], $method->invoke($service, ['monthly_price' => '19.99']), '月价基准按分位整数展开各周期');

        // 周期价折算月价（100/3=33.333→33.33）后再展开，反向不得放大舍入误差。
        $this->assertSame([
            'monthly' => '33.33',
            'quarterly' => '99.99',
            'semiannually' => '199.98',
            'annually' => '399.96',
        ], $method->invoke($service, ['product_price' => '100.00', 'billingcycle' => 'quarterly']));

        $this->assertSame([], $method->invoke($service, ['name' => '无价格商品']), '缺价时返回空定价供上层跳过');
    }

    /**
     * @param  array<int, array<string, mixed>>  $configResponses  pid → /cart/get_product_config 响应
     */
    private function makeCatalogService(array $configResponses, bool $expectUpstreamCalls = true): ZjmfCatalogService
    {
        config(['idc.hosting_panel_api.jwt_cache_store' => 'array']);

        $hostingTransport = Mockery::mock(HostingPanelApiTransport::class);
        if ($expectUpstreamCalls) {
            $hostingTransport->shouldReceive('request')->andReturnUsing(
                function (
                    Supplier $supplier,
                    string $method,
                    string $uri,
                    array|string $payload = [],
                    ?string $jwt = null,
                    array $headers = [],
                    array $query = [],
                ) use ($configResponses): array {
                    if ($uri === '/zjmf_api_login') {
                        return ['jwt' => self::JWT];
                    }

                    if ($uri === '/cart/get_product_config') {
                        $productId = (int) ($query['pid'] ?? 0);

                        return $configResponses[$productId] ?? ['status' => 200, 'data' => []];
                    }

                    throw new RuntimeException('意外的上游请求：'.$uri);
                }
            );
        } else {
            $hostingTransport->shouldReceive('request')->never();
        }

        return new ZjmfCatalogService(
            new ZjmfFinanceTransport($hostingTransport, new ZjmfAuthManager($hostingTransport)),
            new ZjmfCloudConfigTemplate,
        );
    }

    private function createSupplier(): Supplier
    {
        return Supplier::query()->create([
            'name' => 'ZJMF-补价回归-'.uniqid(),
            'code' => 'SUP-ZJMF-'.uniqid(),
            'status' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function product(int $id, string $name, array $overrides = []): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'type' => 'vps',
            'billingcycle' => '',
            'monthly_price' => null,
            'product_price' => null,
            'setup_fee' => null,
            ...$overrides,
        ];
    }
}
