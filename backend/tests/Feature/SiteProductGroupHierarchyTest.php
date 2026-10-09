<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ProductType;
use App\Models\FirstProductGroup;
use App\Models\Product;
use App\Models\SecondProductGroup;
use App\Models\Service;
use App\Models\Setting;
use App\Models\ThirdProductGroup;
use App\Models\User;
use App\Services\ClientServiceConsole\ClientServiceConsoleService;
use App\Services\ProductCatalog\ProductCategoryService;
use App\Services\ProductCatalog\ProductGroupV2QueryService;
use App\Services\ProductCatalog\ProductSiteService;
use App\Support\ProductGroupHierarchyFields;
use App\Support\SiteProductGroupQuery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * #70 逆向修复回归：
 * 1. 站点可见分组唯一入口 SiteProductGroupQuery 按一级菜单 code 精确命中，
 *    两个一级菜单 code 不同、product_type 同为 cloud_server 时不得被合并；
 * 2. group_level_label / group_path / group_path_text 在资源层与站点目录层输出一致；
 * 3. 控制台概览按一级菜单分组，子卡 key 为「层级:ID」，二级 #57 与三级 #57 不互相覆盖。
 *
 * 使用 DatabaseTransactions：写入在测试结束回滚，不污染共享测试库。
 */
class SiteProductGroupHierarchyTest extends TestCase
{
    use DatabaseTransactions;

    private const CODE_MAINLAND = 'regress_mainland_cloud';

    private const CODE_LIGHTWEIGHT = 'regress_lightweight_cloud';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        ProductType::resetCache();
        Setting::forgetCachedGroup(ProductType::SETTING_GROUP);
        // 造两个一级菜单：code 不同，业务 product_type 同为 cloud_server。
        ProductType::saveItems([
            [
                'internal_id' => 101,
                'value' => self::CODE_MAINLAND,
                'label' => '大陆云服',
                'icon' => 'Platform',
                'is_builtin' => true,
                'is_hidden' => false,
                'product_type' => ProductType::CLOUD_SERVER,
            ],
            [
                'internal_id' => 102,
                'value' => self::CODE_LIGHTWEIGHT,
                'label' => '轻量服务器',
                'icon' => 'Platform',
                'is_builtin' => true,
                'is_hidden' => false,
                'product_type' => ProductType::CLOUD_SERVER,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        ProductType::resetCache();
        Setting::forgetCachedGroup(ProductType::SETTING_GROUP);
        Cache::flush();

        parent::tearDown();
    }

    private function categoryService(): ProductCategoryService
    {
        return app(ProductCategoryService::class);
    }

    private function createFirstGroup(string $code, string $name): FirstProductGroup
    {
        $payload = $this->categoryService()->createCategory([
            'level' => 1,
            'name' => $name,
            'code' => $code,
            'product_type' => ProductType::CLOUD_SERVER,
            'is_visible' => 1,
        ]);

        return FirstProductGroup::query()->findOrFail((int) $payload['id']);
    }

    private function createSecondGroup(int $firstGroupId, string $name, string $slug): SecondProductGroup
    {
        $payload = $this->categoryService()->createCategory([
            'level' => 2,
            'name' => $name,
            'slug' => $slug,
            'first_product_group_id' => $firstGroupId,
            'is_visible' => 1,
        ]);

        return SecondProductGroup::query()->findOrFail((int) $payload['id']);
    }

    private function createThirdGroup(int $secondGroupId, string $name, string $slug): ThirdProductGroup
    {
        $payload = $this->categoryService()->createCategory([
            'level' => 3,
            'name' => $name,
            'slug' => $slug,
            'second_product_group_id' => $secondGroupId,
            'is_visible' => 1,
        ]);

        return ThirdProductGroup::query()->findOrFail((int) $payload['id']);
    }

    private function createProductIn(ThirdProductGroup $third, string $name): Product
    {
        return Product::query()->create([
            'product_type' => ProductType::CLOUD_SERVER,
            'service_type_code' => ProductType::CLOUD_SERVER,
            'name' => $name,
            'product_group_id' => (int) $third->id,
            'status' => 1,
            'stock' => -1,
            'pricing' => ['monthly' => '35.00'],
        ]);
    }

    /**
     * 需求 1：两个一级菜单 code 不同、product_type 同为 cloud_server，查询结果必须分开。
     */
    public function test_visible_second_groups_keeps_same_product_type_menus_separate(): void
    {
        $mainland = $this->createFirstGroup(self::CODE_MAINLAND, '大陆云服');
        $lightweight = $this->createFirstGroup(self::CODE_LIGHTWEIGHT, '轻量服务器');
        $this->assertSame(ProductType::CLOUD_SERVER, (string) $mainland->product_type);
        $this->assertSame(ProductType::CLOUD_SERVER, (string) $lightweight->product_type);

        $mainlandSecond = $this->createSecondGroup((int) $mainland->id, '华南地区', 'regress-mainland-south');
        $lightweightSecond = $this->createSecondGroup((int) $lightweight->id, '华南地区', 'regress-lightweight-south');

        $byMainland = SiteProductGroupQuery::visibleSecondGroups(self::CODE_MAINLAND)
            ->pluck('second_product_groups.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $byLightweight = SiteProductGroupQuery::visibleSecondGroups(self::CODE_LIGHTWEIGHT)
            ->pluck('second_product_groups.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertContains((int) $mainlandSecond->id, $byMainland);
        $this->assertNotContains((int) $lightweightSecond->id, $byMainland, '同为 cloud_server 的轻量服务器分组不得被并入大陆云服');
        $this->assertContains((int) $lightweightSecond->id, $byLightweight);
        $this->assertNotContains((int) $mainlandSecond->id, $byLightweight, '同为 cloud_server 的大陆云服分组不得被并入轻量服务器');
    }

    /**
     * 需求 1（接口路径）：paginateSiteRootGroups 传一级菜单 code 时精确命中，不做 product_type 并项。
     */
    public function test_site_root_groups_pagination_does_not_merge_same_product_type_menus(): void
    {
        $mainland = $this->createFirstGroup(self::CODE_MAINLAND, '大陆云服');
        $lightweight = $this->createFirstGroup(self::CODE_LIGHTWEIGHT, '轻量服务器');
        $mainlandSecond = $this->createSecondGroup((int) $mainland->id, '华东地区', 'regress-mainland-east');
        $lightweightSecond = $this->createSecondGroup((int) $lightweight->id, '华东地区', 'regress-lightweight-east');

        $paginator = app(ProductGroupV2QueryService::class)->paginateSiteRootGroups([
            'first_product_group_code' => self::CODE_MAINLAND,
            'page' => 1,
            'page_size' => 50,
        ]);

        $ids = collect($paginator->items())->map(fn (SecondProductGroup $group): int => (int) $group->id)->all();

        $this->assertContains((int) $mainlandSecond->id, $ids);
        $this->assertNotContains((int) $lightweightSecond->id, $ids);
    }

    /**
     * 需求 3：子卡 key 为「层级:ID」，二级 #57 与三级 #57 必须不同。
     */
    public function test_group_node_key_distinguishes_second_and_third_level(): void
    {
        $this->assertSame('2:57', ProductGroupHierarchyFields::groupNodeKey(2, 57));
        $this->assertSame('3:57', ProductGroupHierarchyFields::groupNodeKey(3, 57));
        $this->assertNotSame(
            ProductGroupHierarchyFields::groupNodeKey(2, 57),
            ProductGroupHierarchyFields::groupNodeKey(3, 57),
            '二级 #57 与三级 #57 的 key 不得相同'
        );
        $this->assertSame('ungrouped', ProductGroupHierarchyFields::groupNodeKey(null, null, 'ungrouped'));
    }

    /**
     * 需求 3（接口路径）：控制台概览子卡 key 带真实层级前缀，且二级 / 三级同时存在。
     */
    public function test_console_overview_child_card_key_uses_level_and_id(): void
    {
        $mainland = $this->createFirstGroup(self::CODE_MAINLAND, '大陆云服');
        $second = $this->createSecondGroup((int) $mainland->id, '华南地区', 'regress-card-south');
        $third = $this->createThirdGroup((int) $second->id, '可用区 A', 'regress-card-south-a');
        $product = $this->createProductIn($third, '华南 2C4G');

        $user = User::factory()->create();
        Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'name' => '华南实例',
            'billing_cycle' => 'monthly',
            'amount' => 35,
            'status' => 1,
            'provision_data' => [],
            'auto_renew' => 1,
            'expires_at' => now()->addMonth(),
        ]);

        $overview = app(ClientServiceConsoleService::class)->groupedOverviewForUser($user);
        $group = collect($overview['list'])->first(
            fn (array $item): bool => ($item['key'] ?? '') === self::CODE_MAINLAND
        );

        $this->assertIsArray($group, '控制台概览缺少大陆云服一级菜单卡片');
        $children = $group['children'] ?? [];
        $this->assertNotEmpty($children, '大陆云服卡片下应有分组子卡');

        $childKeys = array_map(fn (array $card): string => (string) ($card['key'] ?? ''), $children);
        $this->assertContains('3:'.(int) $third->id, $childKeys, '三级子卡 key 应为「3:ID」');

        $card = collect($children)->first(fn (array $c): bool => ($c['key'] ?? '') === '3:'.(int) $third->id);
        $this->assertIsArray($card);
        $this->assertSame('可用区', (string) ($card['group_level_label'] ?? ''), '三级分组层级文案应为「可用区」');
        $this->assertSame(['大陆云服', '华南地区', '可用区 A'], $card['group_path'] ?? []);
        $this->assertSame('大陆云服 / 华南地区 / 可用区 A', (string) ($card['group_path_text'] ?? ''));
    }

    /**
     * 需求 4 + 1：控制台概览按一级菜单分组，同 product_type 的两个菜单各成一张卡。
     */
    public function test_console_overview_groups_by_first_level_menu_code(): void
    {
        $mainland = $this->createFirstGroup(self::CODE_MAINLAND, '大陆云服');
        $lightweight = $this->createFirstGroup(self::CODE_LIGHTWEIGHT, '轻量服务器');
        $mainlandSecond = $this->createSecondGroup((int) $mainland->id, '华南地区', 'regress-group-south');
        $lightweightSecond = $this->createSecondGroup((int) $lightweight->id, '华南地区', 'regress-group-lite-south');
        $mainlandThird = $this->createThirdGroup((int) $mainlandSecond->id, '可用区 A', 'regress-group-south-a');
        $lightweightThird = $this->createThirdGroup((int) $lightweightSecond->id, '可用区 A', 'regress-group-lite-south-a');

        $user = User::factory()->create();
        foreach ([$mainlandThird, $lightweightThird] as $index => $third) {
            $product = $this->createProductIn($third, '规格 '.$index);
            Service::query()->create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'name' => '实例 '.$index,
                'billing_cycle' => 'monthly',
                'amount' => 35,
                'status' => 1,
                'provision_data' => [],
                'auto_renew' => 1,
                'expires_at' => now()->addMonth(),
            ]);
        }

        $overview = app(ClientServiceConsoleService::class)->groupedOverviewForUser($user);
        $keys = array_map(fn (array $item): string => (string) ($item['key'] ?? ''), $overview['list']);

        $this->assertContains(self::CODE_MAINLAND, $keys, '大陆云服应作为独立一级菜单卡片');
        $this->assertContains(self::CODE_LIGHTWEIGHT, $keys, '轻量服务器应作为独立一级菜单卡片');

        $mainlandCard = collect($overview['list'])->first(fn (array $i): bool => ($i['key'] ?? '') === self::CODE_MAINLAND);
        $lightweightCard = collect($overview['list'])->first(fn (array $i): bool => ($i['key'] ?? '') === self::CODE_LIGHTWEIGHT);
        $this->assertSame('产品目录', (string) ($mainlandCard['group_level_label'] ?? ''));
        $this->assertSame(['大陆云服'], $mainlandCard['group_path'] ?? []);
        $this->assertSame('大陆云服', (string) ($mainlandCard['group_path_text'] ?? ''));
        $this->assertSame(1, (int) ($lightweightCard['count'] ?? 0));
        $this->assertSame(1, (int) ($mainlandCard['count'] ?? 0));
    }

    /**
     * 需求 2：group_level_label / group_path / group_path_text 在资源层与站点目录层一致。
     */
    public function test_group_hierarchy_fields_consistent_across_resource_and_catalog(): void
    {
        $mainland = $this->createFirstGroup(self::CODE_MAINLAND, '大陆云服');
        $second = $this->createSecondGroup((int) $mainland->id, '华南地区', 'regress-consist-south');
        $third = $this->createThirdGroup((int) $second->id, '可用区 A', 'regress-consist-south-a');
        $product = $this->createProductIn($third, '一致性规格');
        $product->loadMissing('productGroup.secondProductGroup.firstProductGroup');

        $hierarchy = ProductGroupHierarchyFields::fromProduct($product);
        $this->assertSame('可用区', (string) $hierarchy['group_level_label']);
        $this->assertSame(['大陆云服', '华南地区', '可用区 A'], $hierarchy['group_path']);
        $this->assertSame('大陆云服 / 华南地区 / 可用区 A', (string) $hierarchy['group_path_text']);

        // 站点目录层（ProductSiteService）与商品资源层必须输出同一套层级字段
        $catalog = app(ProductSiteService::class)->siteGroupCatalog((int) $second->id);
        $catalogItem = collect($catalog['items_by_group'] ?? [])
            ->flatMap(fn (array $bucket): array => (array) ($bucket['products'] ?? []))
            ->first(fn (array $item): bool => (int) ($item['id'] ?? 0) === (int) $product->id);

        $this->assertIsArray($catalogItem, '站点目录应包含该商品');
        $this->assertSame($hierarchy['group_level_label'], $catalogItem['group_level_label'] ?? null);
        $this->assertSame($hierarchy['group_path'], $catalogItem['group_path'] ?? null);
        $this->assertSame($hierarchy['group_path_text'], $catalogItem['group_path_text'] ?? null);
    }
}
