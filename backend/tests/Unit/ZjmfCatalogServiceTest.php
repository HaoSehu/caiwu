<?php

declare(strict_types=1);

namespace Tests\Unit;

use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfCatalogService;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfProductTypeMapper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once dirname(__DIR__, 2).'/plugins/servers/zjmf_finance/lib/ZjmfProductTypeMapper.php';
require_once dirname(__DIR__, 2).'/plugins/servers/zjmf_finance/lib/ZjmfCatalogService.php';

class ZjmfCatalogServiceTest extends TestCase
{
    /**
     * ZjmfFinanceTransport 为 final 类无法 mock，通过反射注入属性后直接测归一化方法。
     */
    private function normalizeCatalog(array $response): array
    {
        $service = (new ReflectionClass(ZjmfCatalogService::class))->newInstanceWithoutConstructor();

        $mapperProperty = new ReflectionProperty(ZjmfCatalogService::class, 'productTypeMapper');
        $mapperProperty->setAccessible(true);
        $mapperProperty->setValue($service, new ZjmfProductTypeMapper);

        $method = new ReflectionMethod(ZjmfCatalogService::class, 'normalizeProductCatalog');
        $method->setAccessible(true);

        /** @var array $catalog */
        $catalog = $method->invoke($service, $response);

        return $catalog;
    }

    public function test_catalog_reads_standard_price_fields(): void
    {
        $catalog = $this->normalizeCatalog([
            'products' => [
                [
                    'id' => 1,
                    'name' => '入门云服务器',
                    'type' => 'cloud',
                    'description' => '测试商品',
                    'billingcycle' => 'annually',
                    'product_price' => '420.00',
                    'monthly' => '35.00',
                    'setup_fee' => '10.00',
                    'allow_qty' => 1,
                    'stock_control' => 1,
                    'qty' => 5,
                ],
            ],
        ]);

        $product = $catalog['products'][0];

        $this->assertSame('annually', $product['billingcycle']);
        $this->assertSame('420.00', $product['product_price']);
        $this->assertSame('35.00', $product['monthly_price']);
        $this->assertSame('10.00', $product['setup_fee']);
        $this->assertSame(5, $product['stock']);
    }

    public function test_catalog_falls_back_to_upstream_price_fields(): void
    {
        $catalog = $this->normalizeCatalog([
            'products' => [
                [
                    'id' => 2,
                    'name' => '进阶云服务器',
                    'type' => 'cloud',
                    'upstream_cycle' => 'monthly',
                    'upstream_price' => '68.00',
                ],
            ],
        ]);

        $product = $catalog['products'][0];

        $this->assertSame('monthly', $product['billingcycle']);
        $this->assertSame('68.00', $product['product_price']);
        $this->assertNull($product['monthly_price']);
    }

    public function test_catalog_falls_back_to_price_field(): void
    {
        $catalog = $this->normalizeCatalog([
            'products' => [
                [
                    'id' => 3,
                    'name' => '独立服务器',
                    'type' => 'dcim',
                    'billingcycle' => 'monthly',
                    'price' => '199.00',
                ],
            ],
        ]);

        $product = $catalog['products'][0];

        $this->assertSame('199.00', $product['product_price']);
    }

    public function test_catalog_flattens_nested_groups_and_ignores_invalid_prices(): void
    {
        $catalog = $this->normalizeCatalog([
            'data' => [
                'products' => [
                    [
                        'name' => '香港节点',
                        'products' => [
                            [
                                'id' => 4,
                                'name' => '香港 A',
                                'type' => 'cloud',
                                'billingcycle' => 'monthly',
                                'product_price' => '50.00',
                            ],
                            [
                                'id' => 5,
                                'name' => '香港 B',
                                'type' => 'cloud',
                                'billingcycle' => 'monthly',
                                'product_price' => 'abc',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertCount(2, $catalog['products']);
        $this->assertSame('香港节点', $catalog['products'][0]['group_label']);
        $this->assertSame('50.00', $catalog['products'][0]['product_price']);
        $this->assertNull($catalog['products'][1]['product_price']);
        $this->assertCount(1, $catalog['groups']);
    }

    public function test_catalog_reads_monthly_price_field_fallback(): void
    {
        $catalog = $this->normalizeCatalog([
            'products' => [
                [
                    'id' => 6,
                    'name' => '按量计费',
                    'type' => 'cloud',
                    'billingcycle' => 'monthly',
                    'product_price' => '30.00',
                    'monthly_price' => '28.00',
                ],
            ],
        ]);

        $product = $catalog['products'][0];

        $this->assertSame('28.00', $product['monthly_price']);
    }

    public function test_product_detail_merges_prodetail_pricing_and_stock(): void
    {
        $service = $this->serviceWithoutTransport();
        $method = new ReflectionMethod(ZjmfCatalogService::class, 'applyUpstreamProductDetail');
        $method->setAccessible(true);

        /** @var array $item */
        $item = $method->invoke($service, [
            'id' => 7,
            'name' => '合并价格',
            'type' => 'cloud',
            'billingcycle' => '',
            'product_price' => null,
            'monthly_price' => null,
            'setup_fee' => null,
            'stock_control' => 0,
            'qty' => null,
            'stock' => -1,
        ], [
            'stock_control' => 1,
            'qty' => 8,
            'product_pricings' => [
                [
                    'currency' => 1,
                    'monthly' => 35.00,
                    'quarterly' => 105.00,
                    'annually' => 420.00,
                    'msetupfee' => 10.00,
                    'asetupfee' => 0.00,
                ],
            ],
        ]);

        $this->assertSame('monthly', $item['billingcycle']);
        $this->assertSame('35.00', $item['product_price']);
        $this->assertSame('35.00', $item['monthly_price']);
        $this->assertSame('10.00', $item['setup_fee']);
        $this->assertSame(1, $item['stock_control']);
        $this->assertSame(8, $item['qty']);
        $this->assertSame(8, $item['stock']);
    }

    public function test_product_detail_skips_unconfigured_cycles(): void
    {
        $service = $this->serviceWithoutTransport();
        $method = new ReflectionMethod(ZjmfCatalogService::class, 'applyUpstreamProductDetail');
        $method->setAccessible(true);

        /** @var array $item */
        $item = $method->invoke($service, [
            'id' => 8,
            'name' => '未配置月付',
            'type' => 'cloud',
            'billingcycle' => '',
            'product_price' => null,
            'monthly_price' => null,
            'setup_fee' => null,
            'stock_control' => 0,
            'qty' => null,
            'stock' => -1,
        ], [
            'product_pricings' => [
                [
                    'monthly' => -1,
                    'quarterly' => -1,
                    'annually' => 120.00,
                    'msetupfee' => -1,
                    'asetupfee' => '15.00',
                ],
            ],
        ]);

        $this->assertSame('annually', $item['billingcycle']);
        $this->assertSame('120.00', $item['product_price']);
        $this->assertNull($item['monthly_price']);
        $this->assertSame('15.00', $item['setup_fee']);
    }

    public function test_product_detail_merges_nothing_without_pricing_row(): void
    {
        $service = $this->serviceWithoutTransport();
        $method = new ReflectionMethod(ZjmfCatalogService::class, 'applyUpstreamProductDetail');
        $method->setAccessible(true);

        /** @var array $item */
        $item = $method->invoke($service, [
            'id' => 9,
            'name' => '无价格',
            'type' => 'cloud',
            'billingcycle' => '',
            'product_price' => null,
            'monthly_price' => null,
            'setup_fee' => null,
            'stock_control' => 0,
            'qty' => null,
            'stock' => -1,
        ], [
            'product_pricings' => [
                ['monthly' => -1, 'quarterly' => -1, 'annually' => -1],
            ],
        ]);

        $this->assertSame('', $item['billingcycle']);
        $this->assertNull($item['product_price']);
        $this->assertSame(-1, $item['stock']);
    }

    private function serviceWithoutTransport(): ZjmfCatalogService
    {
        $service = (new ReflectionClass(ZjmfCatalogService::class))->newInstanceWithoutConstructor();

        $mapperProperty = new ReflectionProperty(ZjmfCatalogService::class, 'productTypeMapper');
        $mapperProperty->setAccessible(true);
        $mapperProperty->setValue($service, new ZjmfProductTypeMapper);

        return $service;
    }
}
