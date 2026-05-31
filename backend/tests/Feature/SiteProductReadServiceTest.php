<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Models\User;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\CheckoutService;
use App\Services\Finance\CouponService;
use App\Services\ProductCatalog\CpuModelCatalogService;
use App\Services\ProductCatalog\InstanceSpecCatalogService;
use App\Services\ProductCatalog\ProductCatalogService;
use App\Services\ProductCatalog\ProductSiteService;
use App\Services\Site\SiteProductQuoteService;
use App\Services\Site\SiteProductReadService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SiteProductReadServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        app('db')->setDefaultConnection('sqlite');

        $this->resetModelCaches();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlite')->dropIfExists('personal_access_tokens');
        Schema::connection('sqlite')->dropIfExists('users');
        Schema::connection('sqlite')->dropIfExists('products');
        Schema::connection('sqlite')->dropIfExists('product_groups');
        Schema::connection('sqlite')->dropIfExists('settings');

        parent::tearDown();
    }

    public function test_site_group_catalog_returns_monthly_pricing_entries_for_product_cards(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $rootGroup = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'vps',
            'name' => 'Catalog Root '.$suffix,
            'slug' => 'catalog-root-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $childGroup = ProductCategory::query()->create([
            'parent_id' => (int) $rootGroup->id,
            'product_type' => 'vps',
            'name' => 'Catalog Child '.$suffix,
            'slug' => 'catalog-child-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $product = Product::query()->create([
            'product_group_id' => (int) $childGroup->id,
            'name' => '香港轻量云 2H2G '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '20.00', 'quarterly' => '60.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => 12,
            'status' => 1,
            'sort_order' => 0,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);

        $response = $this->getJson('/api/site/product-categories/'.$rootGroup->id.'/catalog')
            ->assertOk();

        $products = collect((array) $response->json('data.items_by_group'))
            ->flatMap(fn (array $group): array => (array) ($group['products'] ?? []))
            ->values();

        $matchedProduct = $products->firstWhere('id', (int) $product->id);

        $this->assertIsArray($matchedProduct);
        $this->assertSame('monthly', data_get($matchedProduct, 'pricing_entries.0.cycle'));
        $this->assertSame('20.00', data_get($matchedProduct, 'pricing_entries.0.amount'));
    }

    public function test_site_group_catalog_checks_product_columns_once_per_request(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $rootGroup = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'vps',
            'name' => 'Column Cache Root '.$suffix,
            'slug' => 'column-cache-root-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $childGroup = ProductCategory::query()->create([
            'parent_id' => (int) $rootGroup->id,
            'product_type' => 'vps',
            'name' => 'Column Cache Child '.$suffix,
            'slug' => 'column-cache-child-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        foreach (range(1, 5) as $index) {
            Product::query()->create([
                'product_group_id' => (int) $childGroup->id,
                'name' => 'Column Cache Product '.$index.' '.$suffix,
                'product_type' => 'vps',
                'pricing' => ['monthly' => '20.00'],
                'setup_fee' => '0.00',
                'config_options' => [],
                'purchase_requires' => [],
                'stock' => 12,
                'status' => 1,
                'sort_order' => $index,
                'provision_module' => null,
                'auto_setup' => 0,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/site/product-categories/'.$rootGroup->id.'/catalog')
            ->assertOk();

        $productColumnLookups = collect(DB::getQueryLog())
            ->filter(function (array $query): bool {
                $sql = (string) ($query['query'] ?? '');

                return str_contains($sql, 'pragma_table_xinfo')
                    && str_contains($sql, 'products');
            })
            ->count();

        $this->assertLessThanOrEqual(1, $productColumnLookups);
    }

    public function test_site_product_cards_read_cpu_model_catalog_once_per_batch(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $group = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'vps',
            'name' => 'CPU Catalog Cache '.$suffix,
            'slug' => 'cpu-catalog-cache-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        foreach (range(1, 4) as $index) {
            Product::query()->create([
                'product_group_id' => (int) $group->id,
                'name' => 'CPU Catalog Cache Product '.$index.' '.$suffix,
                'product_type' => 'vps',
                'pricing' => ['monthly' => '20.00'],
                'setup_fee' => '0.00',
                'config_options' => [],
                'purchase_requires' => [],
                'stock' => 12,
                'status' => 1,
                'sort_order' => $index,
                'provision_module' => null,
                'auto_setup' => 0,
            ]);
        }

        $cpuModelCatalogService = $this->getMockBuilder(CpuModelCatalogService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCatalog'])
            ->getMock();
        $cpuModelCatalogService->expects($this->once())
            ->method('getCatalog')
            ->willReturn([]);

        $service = new ProductSiteService(
            $cpuModelCatalogService,
            new InstanceSpecCatalogService
        );

        $payload = $service->siteProductsByGroupIds([(int) $group->id]);
        $products = collect($payload)
            ->flatMap(fn (array $item): array => (array) ($item['products'] ?? []))
            ->values();

        $this->assertCount(4, $products);
    }

    public function test_site_product_cards_reuse_batch_instance_spec_lookup(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $group = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'vps',
            'name' => 'Spec Batch Cache '.$suffix,
            'slug' => 'spec-batch-cache-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $products = collect(range(1, 4))
            ->map(fn (int $index): Product => Product::query()->create([
                'product_group_id' => (int) $group->id,
                'name' => 'Spec Batch Product '.$index.' '.$suffix,
                'product_type' => 'vps',
                'pricing' => ['monthly' => '20.00'],
                'setup_fee' => '0.00',
                'config_options' => [],
                'purchase_requires' => [],
                'stock' => 12,
                'status' => 1,
                'sort_order' => $index,
                'provision_module' => null,
                'auto_setup' => 0,
            ]))
            ->values();

        $instanceSpecCatalogService = $this->getMockBuilder(InstanceSpecCatalogService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolveProductSpecMap'])
            ->getMock();
        $instanceSpecCatalogService->expects($this->once())
            ->method('resolveProductSpecMap')
            ->willReturn([
                (int) $products[0]->id => [
                    'instance_spec_id' => 'spec_cached',
                    'instance_spec_value' => 'spec_cached',
                    'instance_spec_text' => 'ecs.cached.2c2g',
                    'instance_spec_alias' => '2 核 2G',
                    'instance_spec_note' => '',
                    'instance_spec_status' => '展示中',
                ],
            ]);

        $service = new ProductSiteService(
            new CpuModelCatalogService,
            $instanceSpecCatalogService
        );

        $payload = $service->siteProductsByGroupIds([(int) $group->id]);
        $catalogProducts = collect($payload)
            ->flatMap(fn (array $item): array => (array) ($item['products'] ?? []))
            ->values();

        $this->assertCount(4, $catalogProducts);
        $this->assertSame('ecs.cached.2c2g', data_get($catalogProducts->first(), 'display_name'));
    }

    public function test_site_group_catalog_and_product_detail_include_bound_cpu_model_name(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $rootGroup = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'vps',
            'name' => 'CPU Root '.$suffix,
            'slug' => 'cpu-root-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $childGroup = ProductCategory::query()->create([
            'parent_id' => (int) $rootGroup->id,
            'product_type' => 'vps',
            'name' => 'CPU Child '.$suffix,
            'slug' => 'cpu-child-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $product = Product::query()->create([
            'product_group_id' => (int) $childGroup->id,
            'name' => '襄阳高防大带宽 4H4G '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '90.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => 10,
            'status' => 1,
            'sort_order' => 0,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);

        Setting::setValue('product', 'cpu_model_catalog', json_encode([
            [
                'id' => 'group_intel',
                'value' => 'intel_xeon',
                'name' => 'Intel Xeon',
                'models' => [
                    [
                        'id' => 'model_6133',
                        'value' => 'intel_xeon_gold_6133',
                        'name' => 'Intel Xeon Gold 6133',
                        'base_frequency' => '2.40GHz',
                        'turbo_frequency' => '3.10GHz',
                        'bindings' => [
                            [
                                'product_id' => (int) $product->id,
                                'product_name' => (string) $product->name,
                                'category_full_name' => '云服务器 / 襄阳高防',
                                'primary_price' => [
                                    'cycle' => 'monthly',
                                    'amount' => '90.00',
                                ],
                                'status' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $catalogResponse = $this->getJson('/api/site/product-categories/'.$rootGroup->id.'/catalog')
            ->assertOk();

        $catalogProduct = collect((array) $catalogResponse->json('data.items_by_group'))
            ->flatMap(fn (array $group): array => (array) ($group['products'] ?? []))
            ->firstWhere('id', (int) $product->id);

        $this->assertSame('Intel Xeon Gold 6133', data_get($catalogProduct, 'cpu_model_name'));
        $this->assertSame('2.40GHz', data_get($catalogProduct, 'cpu_base_frequency'));
        $this->assertSame('3.10GHz', data_get($catalogProduct, 'cpu_turbo_frequency'));

        $this->getJson('/api/site/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.product.cpu_model_name', 'Intel Xeon Gold 6133')
            ->assertJsonPath('data.product.cpu_base_frequency', '2.40GHz')
            ->assertJsonPath('data.product.cpu_turbo_frequency', '3.10GHz');
    }

    public function test_site_group_catalog_and_product_detail_include_bound_instance_spec_text(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $rootGroup = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'vps',
            'name' => 'Spec Root '.$suffix,
            'slug' => 'spec-root-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $childGroup = ProductCategory::query()->create([
            'parent_id' => (int) $rootGroup->id,
            'product_type' => 'vps',
            'name' => 'Spec Child '.$suffix,
            'slug' => 'spec-child-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $product = Product::query()->create([
            'product_group_id' => (int) $childGroup->id,
            'name' => '杭州通用云 2H2G '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '45.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => 8,
            'status' => 1,
            'sort_order' => 0,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);

        Setting::setValue('product', 'instance_spec_catalog', json_encode([
            [
                'id' => 'spec_2c2g',
                'value' => 'ecs_g9i_2c2g',
                'text' => 'ecs.g9i.2c2g',
                'alias' => '2 核 2G',
                'note' => '展示规格',
                'status' => '展示中',
                'bindings' => [
                    [
                        'product_id' => (int) $product->id,
                        'display_name' => 'ecs.g9i.2c2g',
                        'cpu_memory_display' => '2 vCPU 2G',
                        'category_full_name' => '云服务器 / 杭州节点',
                        'primary_price' => [
                            'cycle' => 'monthly',
                            'amount' => '45.00',
                        ],
                        'status' => 1,
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $catalogResponse = $this->getJson('/api/site/product-categories/'.$rootGroup->id.'/catalog')
            ->assertOk();

        $catalogProduct = collect((array) $catalogResponse->json('data.items_by_group'))
            ->flatMap(fn (array $group): array => (array) ($group['products'] ?? []))
            ->firstWhere('id', (int) $product->id);

        $this->assertSame('ecs.g9i.2c2g', data_get($catalogProduct, 'display_name'));
        $this->assertSame('ecs.g9i.2c2g', data_get($catalogProduct, 'instance_spec_text'));
        $this->assertSame('2 核 2G', data_get($catalogProduct, 'instance_spec_alias'));

        $this->getJson('/api/site/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.product.display_name', 'ecs.g9i.2c2g')
            ->assertJsonPath('data.product.instance_spec_text', 'ecs.g9i.2c2g')
            ->assertJsonPath('data.product.instance_spec_alias', '2 核 2G')
            ->assertJsonPath('data.product.group.display_name', 'ecs.g9i.2c2g');
    }

    public function test_site_product_read_service_normalizes_group_filters_before_query(): void
    {
        $catalogService = $this->createMock(ProductCatalogService::class);
        $catalogService->expects($this->once())
            ->method('siteProductsByGroupIds')
            ->with([11, 12, 13, 14, 15, 16])
            ->willReturn([
                ['group_id' => 11, 'products' => []],
            ]);

        $service = new SiteProductReadService($catalogService);

        $payload = $service->products([
            'category_id' => 11,
            'category_ids' => [12, 12],
            'group_id' => 13,
            'group_ids' => [14],
            'product_group_id' => 15,
            'product_group_ids' => [16, 15],
        ]);

        $this->assertSame([
            ['group_id' => 11, 'products' => []],
        ], $payload['items_by_group']);
    }

    public function test_site_product_read_service_builds_group_catalog_map_with_single_product_query(): void
    {
        $catalogService = $this->createMock(ProductCatalogService::class);
        $catalogService->expects($this->once())
            ->method('siteRootGroups')
            ->willReturn([
                ['id' => 11, 'name' => 'Cloud Servers', 'product_type_id' => 1],
                ['id' => 12, 'name' => 'Databases', 'product_type_id' => 1],
            ]);
        $catalogService->expects($this->exactly(2))
            ->method('siteChildGroups')
            ->willReturnCallback(function (int $groupId): array {
                return match ($groupId) {
                    11 => [
                        ['id' => 21, 'name' => 'High Frequency Nodes', 'product_count' => 2],
                    ],
                    12 => [],
                    default => [],
                };
            });
        $catalogService->expects($this->once())
            ->method('siteProductsByGroupIds')
            ->with([11, 12, 21])
            ->willReturn([
                [
                    'group_id' => 11,
                    'products' => [
                        ['id' => 101, 'group_id' => 11, 'name' => '轻量云服务器 2H2G', 'primary_price' => '68.00'],
                    ],
                ],
                [
                    'group_id' => 21,
                    'products' => [
                        ['id' => 201, 'group_id' => 21, 'name' => '轻量云高配 4H4G', 'primary_price' => '188.00'],
                    ],
                ],
            ]);

        $service = new SiteProductReadService($catalogService);

        $payload = $service->groupCatalogMap([11, 12]);

        $this->assertSame('轻量云服务器 2H2G', $payload[11]['featured_product']['name'] ?? null);
        $this->assertSame('轻量云服务器 2H2G', $payload[11]['featured_product']['display_name'] ?? null);
        $this->assertSame(11, $payload[11]['featured_product']['group_id'] ?? null);
        $this->assertSame('轻量云高配 4H4G', $payload[11]['preview_products'][1]['name'] ?? null);
        $this->assertSame('轻量云高配 4H4G', $payload[11]['preview_products'][1]['display_name'] ?? null);
        $this->assertArrayNotHasKey('children', $payload[11]);
        $this->assertArrayNotHasKey('children', $payload[12]);
    }

    public function test_site_product_quote_service_builds_quote_payload_and_security_token(): void
    {
        $product = new Product([
            'pricing' => ['monthly' => '99.00'],
            'setup_fee' => '10.00',
            'config_options' => [],
            'purchase_requires' => [],
        ]);
        $product->setAttribute('id', 501);
        $product->exists = true;

        $checkoutService = $this->createMock(CheckoutService::class);
        $checkoutService->expects($this->once())
            ->method('normalizeConfig')
            ->with($product, ['region' => 'cn-hk'])
            ->willReturn(['region' => 'cn-hk']);
        $checkoutService->expects($this->once())
            ->method('quote')
            ->with($product, 'monthly', ['region' => 'cn-hk'], 2)
            ->willReturn([
                'base_amount' => '99.00',
                'setup_fee' => '10.00',
                'total_amount' => '208.00',
                'items' => [['label' => 'Base Price', 'amount' => '198.00']],
            ]);

        $couponService = $this->createMock(CouponService::class);
        $couponService->expects($this->once())
            ->method('previewOwnedCoupon')
            ->with(9, 88, $product, 'monthly', 208.0, 'new')
            ->willReturn([
                'user_coupon_id' => 9,
                'discount_amount' => '8.00',
                'name' => 'Test Coupon',
            ]);
        $couponService->expects($this->once())
            ->method('availableCouponsForCheckout')
            ->with(88, $product, 'monthly', 208.0, 'new')
            ->willReturn([
                ['id' => 9, 'name' => 'Test Coupon'],
            ]);

        $securityService = $this->createMock(CheckoutSecurityService::class);
        $securityService->expects($this->once())
            ->method('issueQuoteToken')
            ->with(
                501,
                'monthly',
                ['region' => 'cn-hk'],
                $this->callback(function (array $quote): bool {
                    return ($quote['subtotal_amount'] ?? '') === '208.00'
                        && ($quote['discount_amount'] ?? '') === '8.00'
                        && ($quote['total_amount'] ?? '') === '200.00'
                        && (int) ($quote['user_coupon_id'] ?? 0) === 9;
                }),
                [
                    'request_id' => 'req-site-001',
                    'ip_address' => '127.0.0.1',
                ]
            )
            ->willReturn([
                'quote_token' => 'quote-token-001',
                'fingerprint' => 'fp-001',
            ]);

        $service = new SiteProductQuoteService($checkoutService, $securityService, $couponService);
        $payload = $service->quote($product, [
            'billing_cycle' => 'monthly',
            'config' => ['region' => 'cn-hk'],
            'quantity' => 2,
            'user_coupon_id' => 9,
        ], [
            'user_id' => 88,
            'request_id' => 'req-site-001',
            'ip_address' => '127.0.0.1',
        ]);

        $this->assertSame('208.00', $payload['subtotal_amount']);
        $this->assertSame('8.00', $payload['discount_amount']);
        $this->assertSame('200.00', $payload['total_amount']);
        $this->assertSame('quote-token-001', $payload['quote_token']);
        $this->assertSame(9, $payload['user_coupon_id']);
    }

    public function test_site_product_controller_delegates_to_read_and_quote_services(): void
    {
        $readService = $this->createMock(SiteProductReadService::class);
        $readService->expects($this->once())
            ->method('productTypes')
            ->willReturn([
                'list' => [['id' => 1, 'label' => '云服务器']],
            ]);
        $readService->expects($this->once())
            ->method('products')
            ->with([
                'category_id' => 12,
                'group_ids' => [13, 14],
            ])
            ->willReturn([
                'items_by_group' => [['group_id' => 12, 'products' => []]],
            ]);

        $quoteService = $this->createMock(SiteProductQuoteService::class);
        $quoteService->expects($this->once())
            ->method('resolveQuotePayload')
            ->with(
                501,
                [
                    'billing_cycle' => 'monthly',
                    'config' => ['region' => 'cn-hk'],
                    'quantity' => 2,
                    'user_coupon_id' => 9,
                ],
                $this->callback(fn (array $context) => (int) ($context['user_id'] ?? 0) === 0)
            )
            ->willReturn([
                'total_amount' => '200.00',
                'quote_token' => 'quote-token-501',
            ]);

        $this->app->instance(SiteProductReadService::class, $readService);
        $this->app->instance(SiteProductQuoteService::class, $quoteService);

        $this->getJson('/api/site/product-types')
            ->assertOk()
            ->assertJsonPath('data.list.0.label', '云服务器');

        $this->getJson('/api/site/products?category_id=12&group_ids[0]=13&group_ids[1]=14')
            ->assertOk()
            ->assertJsonPath('data.items_by_group.0.group_id', 12);

        $this->postJson('/api/site/products/501/quote', [
            'billing_cycle' => 'monthly',
            'config' => ['region' => 'cn-hk'],
            'quantity' => 2,
            'user_coupon_id' => 9,
        ])
            ->assertOk()
            ->assertJsonPath('data.quote_token', 'quote-token-501');
    }

    public function test_site_product_controller_resolves_client_user_from_bearer_token_on_quote(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $user = User::query()->create([
            'email' => 'site-quote-token-'.$suffix.'@example.com',
            'password' => 'secret123',
            'phone' => '139'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => '',
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);
        $token = $user->createToken('site-quote-test')->plainTextToken;

        $quoteService = $this->createMock(SiteProductQuoteService::class);
        $quoteService->expects($this->once())
            ->method('resolveQuotePayload')
            ->with(
                501,
                [
                    'billing_cycle' => 'monthly',
                    'config' => ['region' => 'cn-hk'],
                    'quantity' => 2,
                    'user_coupon_id' => 9,
                ],
                $this->callback(fn (array $context) => (int) ($context['user_id'] ?? 0) === (int) $user->id)
            )
            ->willReturn([
                'total_amount' => '200.00',
                'quote_token' => 'quote-token-authenticated',
            ]);

        $this->app->instance(SiteProductQuoteService::class, $quoteService);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/site/products/501/quote', [
            'billing_cycle' => 'monthly',
            'config' => ['region' => 'cn-hk'],
            'quantity' => 2,
            'user_coupon_id' => 9,
        ])
            ->assertOk()
            ->assertJsonPath('data.quote_token', 'quote-token-authenticated');
    }

    public function test_site_product_controller_no_longer_contains_quote_and_read_helpers(): void
    {
        $content = file_get_contents(base_path('app/Http/Controllers/SiteProductController.php'));

        $this->assertIsString($content);
        $this->assertStringNotContainsString('private function saleProductQuery', $content);
        $this->assertStringNotContainsString('private function findSaleProductForQuote', $content);
        $this->assertStringNotContainsString('private function normalizeCategoryIds', $content);
        $this->assertStringNotContainsString('private function transformProduct', $content);
    }

    private function createSchema(): void
    {
        Schema::connection('sqlite')->create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group_key', 100);
            $table->string('item_key', 100);
            $table->text('item_value')->nullable();
            $table->unique(['group_key', 'item_key'], 'settings_group_item_unique');
        });

        Schema::connection('sqlite')->create('product_groups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_group_id')->nullable();
            $table->string('product_type', 50);
            $table->string('name', 190);
            $table->string('slug', 190)->nullable();
            $table->text('slogan')->nullable();
            $table->unsignedTinyInteger('is_visible')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_group_id')->nullable();
            $table->string('name', 190);
            $table->string('product_type', 50)->nullable();
            $table->json('pricing')->nullable();
            $table->decimal('setup_fee', 12, 2)->default(0);
            $table->json('config_options')->nullable();
            $table->json('purchase_requires')->nullable();
            $table->integer('stock')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->integer('sort_order')->default(0);
            $table->string('provision_module', 100)->nullable();
            $table->unsignedTinyInteger('auto_setup')->default(0);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('supplier_product_id')->nullable();
            $table->string('supplier_product_name', 190)->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 191)->nullable();
            $table->string('password', 255);
            $table->string('phone', 50)->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('nickname', 100)->nullable();
            $table->string('company', 100)->nullable();
            $table->string('qq', 50)->nullable();
            $table->text('admin_note')->nullable();
            $table->string('referral_code', 50)->nullable();
            $table->unsignedBigInteger('referrer_user_id')->nullable();
            $table->timestamp('referred_at')->nullable();
            $table->unsignedBigInteger('member_level_id')->nullable();
            $table->decimal('total_sales_amount', 12, 2)->default(0);
            $table->unsignedTinyInteger('is_verified')->default(0);
            $table->string('real_name', 100)->nullable();
            $table->text('id_card')->nullable();
            $table->unsignedTinyInteger('verification_status')->default(0);
            $table->text('verification_message')->nullable();
            $table->string('verification_certify_id', 100)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('alipay_real_name', 100)->nullable();
            $table->string('alipay_account', 190)->nullable();
            $table->unsignedTinyInteger('login_email_alert')->default(0);
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    private function resetModelCaches(): void
    {
        $this->resetStaticProperty(Setting::class, 'groupValueCache', []);
        $this->resetStaticProperty(Product::class, 'physicalColumnExistsCache', []);
        $this->resetStaticProperty(User::class, 'profileTableAvailable', null);
        $this->resetStaticProperty(User::class, 'accountTableAvailable', null);
    }

    private function resetStaticProperty(string $className, string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionClass($className);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }
}
