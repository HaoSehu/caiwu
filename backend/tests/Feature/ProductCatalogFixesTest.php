<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ProductType;
use App\Exceptions\BusinessException;
use App\Models\FirstProductGroup;
use App\Models\Product;
use App\Models\SecondProductGroup;
use App\Models\ThirdProductGroup;
use App\Services\ProductCatalog\ProductAdminService;
use App\Services\ProductCatalog\ProductCategoryService;
use App\Services\ProductCatalog\ProductGroupHierarchyService;
use App\Services\ProductCatalog\ProductGroupV2QueryService;
use App\Services\ProductCatalog\ProductTypeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 商品目录域缺陷修复回归（2026-09-23 审查 D2A-01~05 / D2B-05）。
 * 使用 DatabaseTransactions：所有写入在测试结束后回滚，不污染 idc_test 现有数据。
 */
class ProductCatalogFixesTest extends TestCase
{
    use DatabaseTransactions;

    private function categoryService(): ProductCategoryService
    {
        return app(ProductCategoryService::class);
    }

    /**
     * 取一个命中类型清单的一级分组（不存在则创建），保证前台可见性判定链可用。
     */
    private function ensureFirstGroupByCode(string $code): FirstProductGroup
    {
        $group = FirstProductGroup::query()->where('code', $code)->first();
        if ($group instanceof FirstProductGroup) {
            return $group;
        }

        $payload = $this->categoryService()->createCategory([
            'level' => 1,
            'name' => '回归一级-'.uniqid(),
            'code' => $code,
        ]);

        return FirstProductGroup::query()->findOrFail((int) $payload['id']);
    }

    private function createProductInGroup(ThirdProductGroup $group, int $status = 1, array $extra = []): Product
    {
        return Product::query()->create([
            ...$extra,
            'product_group_id' => (int) $group->id,
            'service_type_code' => 'test',
            'product_type' => 'other',
            'pricing' => ['monthly' => 100.00],
            'setup_fee' => 0,
            'stock' => -1,
            'status' => $status,
            'sort_order' => 0,
        ]);
    }

    /**
     * D2A-01：分类显隐级联只改分组可见性，不得翻转商品上架状态。
     */
    public function test_category_visibility_cascade_keeps_product_status(): void
    {
        $first = $this->ensureFirstGroupByCode((string) (ProductType::items()[0]['value'] ?? 'vps'));
        $second = $this->categoryService()->createCategory([
            'level' => 2,
            'name' => '级联二级-'.uniqid(),
            'first_product_group_id' => (int) $first->id,
        ]);
        $third = $this->categoryService()->createCategory([
            'level' => 3,
            'name' => '级联三级-'.uniqid(),
            'second_product_group_id' => (int) $second['id'],
        ]);

        // 管理员人工下架的商品
        $product = $this->createProductInGroup(ThirdProductGroup::query()->findOrFail((int) $third['id']), 0);

        $this->categoryService()->updateCategory((int) $first->id, ['level' => 1, 'is_visible' => 0]);
        $this->assertSame(0, (int) SecondProductGroup::query()->findOrFail((int) $second['id'])->is_visible);
        $this->assertSame(0, (int) ThirdProductGroup::query()->findOrFail((int) $third['id'])->is_visible);
        $this->assertSame(0, (int) $product->refresh()->status, '隐藏分类不得改写商品 status');

        $this->categoryService()->updateCategory((int) $first->id, ['level' => 1, 'is_visible' => 1]);
        $this->assertSame(0, (int) $product->refresh()->status, '取消隐藏不得把人工下架的商品静默重新上架');
        $this->assertSame(1, (int) ThirdProductGroup::query()->findOrFail((int) $third['id'])->is_visible);
    }

    /**
     * D2A-02：编辑商品不得清空 purchase_requires 中的同步态键。
     */
    public function test_update_product_preserves_purchase_requires(): void
    {
        $first = $this->ensureFirstGroupByCode((string) (ProductType::items()[0]['value'] ?? 'vps'));
        $second = $this->categoryService()->createCategory([
            'level' => 2,
            'name' => '保留键二级-'.uniqid(),
            'first_product_group_id' => (int) $first->id,
        ]);
        $third = $this->categoryService()->createCategory([
            'level' => 3,
            'name' => '保留键三级-'.uniqid(),
            'second_product_group_id' => (int) $second['id'],
        ]);

        $service = app(ProductAdminService::class);
        $product = $service->createProduct([
            'third_product_group_id' => (int) $third['id'],
            'pricing' => ['monthly' => 100.00],
        ]);

        // 模拟同步/拆分逻辑写入的同步态键（客户端无法提交这些键）
        $original = [
            'upstream_default_config' => ['cpu' => '8', 'memory' => '16'],
            'upstream_split' => ['source_product_id' => 123, 'source_product_name' => '源商品', 'variant_key' => 'cpu=8;memory=16'],
            'require_verification' => true,
            'provision_hostname' => ['mode' => 'prefix', 'value' => 'host', 'length' => 12],
        ];
        Product::withoutEvents(fn () => $product->forceFill(['purchase_requires' => $original])->save());

        // 请求整体未提交 purchase_requires：原值原样保留（MySQL JSON 列会重排对象键，比较不依赖顺序）
        $service->updateProduct($product->refresh(), [
            'third_product_group_id' => (int) $third['id'],
            'pricing' => ['monthly' => 120.00],
        ]);
        $this->assertEquals($original, $product->refresh()->purchase_requires);

        // 提交部分白名单键：白名单键以本次提交为准，同步态键沿用旧值
        $service->updateProduct($product->refresh(), [
            'third_product_group_id' => (int) $third['id'],
            'pricing' => ['monthly' => 130.00],
            'purchase_requires' => ['require_phone' => true],
        ]);
        $merged = $product->refresh()->purchase_requires;
        $this->assertTrue($merged['require_phone']);
        $this->assertEquals($original['upstream_default_config'], $merged['upstream_default_config']);
        $this->assertEquals($original['upstream_split'], $merged['upstream_split']);
        // 白名单键沿用「整段以本次提交为准」的既有语义：未提交 provision_hostname 即移除
        $this->assertArrayNotHasKey('provision_hostname', $merged);
        $this->assertArrayNotHasKey('require_verification', $merged, '未提交的白名单键随本次提交被移除');
    }

    /**
     * D2A-03：前台 level=2 分组商品列表必须过滤隐藏的三级分组。
     */
    public function test_site_level2_product_list_excludes_hidden_third_groups(): void
    {
        $first = $this->ensureFirstGroupByCode((string) (ProductType::visibleValues()[0] ?? 'vps'));
        $second = $this->categoryService()->createCategory([
            'level' => 2,
            'name' => '前台二级-'.uniqid(),
            'first_product_group_id' => (int) $first->id,
        ]);
        $visibleThird = $this->categoryService()->createCategory([
            'level' => 3,
            'name' => '前台三级可见-'.uniqid(),
            'second_product_group_id' => (int) $second['id'],
        ]);
        $hiddenThird = $this->categoryService()->createCategory([
            'level' => 3,
            'name' => '前台三级隐藏-'.uniqid(),
            'second_product_group_id' => (int) $second['id'],
        ]);
        ThirdProductGroup::query()->whereKey((int) $hiddenThird['id'])->update(['is_visible' => 0]);

        $visibleProduct = $this->createProductInGroup(ThirdProductGroup::query()->findOrFail((int) $visibleThird['id']));
        $hiddenProduct = $this->createProductInGroup(ThirdProductGroup::query()->findOrFail((int) $hiddenThird['id']));

        $paginator = app(ProductGroupV2QueryService::class)->paginateSiteProducts((int) $second['id'], 2, []);
        $listedIds = collect($paginator->items())->map(fn (Product $item) => (int) $item->id)->all();

        $this->assertContains((int) $visibleProduct->id, $listedIds);
        $this->assertNotContains((int) $hiddenProduct->id, $listedIds, '隐藏三级分组下的商品不得通过 level=2 列表暴露');
    }

    /**
     * D2A-04：三级分组内商品全部软删时，删除分组给出业务提示而非 500；彻底删除商品后可删分组。
     */
    public function test_delete_third_group_with_soft_deleted_products_reports_business_error(): void
    {
        $first = $this->ensureFirstGroupByCode((string) (ProductType::items()[0]['value'] ?? 'vps'));
        $second = $this->categoryService()->createCategory([
            'level' => 2,
            'name' => '软删分组二级-'.uniqid(),
            'first_product_group_id' => (int) $first->id,
        ]);
        $third = $this->categoryService()->createCategory([
            'level' => 3,
            'name' => '软删分组三级-'.uniqid(),
            'second_product_group_id' => (int) $second['id'],
        ]);

        $product = $this->createProductInGroup(ThirdProductGroup::query()->findOrFail((int) $third['id']));
        $product->delete();

        try {
            $this->categoryService()->deleteCategory((int) $third['id'], 3);
            $this->fail('期待业务异常');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('彻底删除', $exception->getMessage());
        }

        $this->assertDatabaseHas('third_product_groups', ['id' => (int) $third['id']]);

        $product->forceDelete();
        $this->categoryService()->deleteCategory((int) $third['id'], 3);
        $this->assertDatabaseMissing('third_product_groups', ['id' => (int) $third['id']]);
    }

    /**
     * D2A-05：一级分组齐备时，GET 商品种类列表不得回写人工设置的 name/is_visible/sort_order。
     */
    public function test_product_type_list_does_not_overwrite_manual_first_group_settings(): void
    {
        $typeCode = (string) (ProductType::items()[0]['value'] ?? 'vps');
        $first = $this->ensureFirstGroupByCode($typeCode);

        // 显式维护动作保证类型清单对应的一级分组齐备，之后读接口应零写库
        app(ProductGroupHierarchyService::class)->syncProductTypes();

        $manualName = '人工命名-'.uniqid();
        $first->forceFill(['name' => $manualName, 'is_visible' => 0, 'sort_order' => 77])->save();

        app(ProductTypeService::class)->list();

        $first->refresh();
        $this->assertSame($manualName, (string) $first->name, '种类列表不得回滚一级分组人工命名');
        $this->assertSame(0, (int) $first->is_visible, '种类列表不得重置一级分组可见性');
        $this->assertSame(77, (int) $first->sort_order, '种类列表不得重置一级分组排序');
    }

    /**
     * D2B-05 回归：拆分候选索引重构后，预览与执行拆分的行为保持不变（幂等更新既有变体）。
     */
    public function test_split_products_find_existing_variants_via_index(): void
    {
        $first = $this->ensureFirstGroupByCode((string) (ProductType::items()[0]['value'] ?? 'vps'));
        $second = $this->categoryService()->createCategory([
            'level' => 2,
            'name' => '拆分二级-'.uniqid(),
            'first_product_group_id' => (int) $first->id,
        ]);
        $third = $this->categoryService()->createCategory([
            'level' => 3,
            'name' => '拆分三级-'.uniqid(),
            'second_product_group_id' => (int) $second['id'],
        ]);

        $service = app(ProductAdminService::class);
        $source = $service->createProduct([
            'third_product_group_id' => (int) $third['id'],
            'pricing' => ['monthly' => 100.00],
            'config_options' => [
                [
                    'field' => 'cpu',
                    'name' => 'CPU',
                    'sub' => [
                        ['option_name' => '2核', 'option_name_first' => '2', 'pricing' => []],
                        ['option_name' => '4核', 'option_name_first' => '4', 'pricing' => []],
                    ],
                ],
                [
                    'field' => 'memory',
                    'name' => '内存',
                    'sub' => [
                        ['option_name' => '2G', 'option_name_first' => '2', 'pricing' => []],
                        ['option_name' => '4G', 'option_name_first' => '4', 'pricing' => []],
                    ],
                ],
            ],
        ]);

        $preview = $service->previewSplitProducts(['product_ids' => [(int) $source->id]]);
        $this->assertSame(4, $preview['preview_count'], 'cpu 2 × memory 2 应生成 4 个变体预览');

        // 预置一个既有拆分变体（cpu=4;memory=4），索引查找应命中它并转为 update
        $existingVariant = $this->createProductInGroup(ThirdProductGroup::query()->findOrFail((int) $third['id']), 1, [
            'purchase_requires' => [
                'upstream_split' => [
                    'source_product_id' => (int) $source->id,
                    'source_product_name' => '源商品',
                    'variant_key' => 'cpu=4;memory=4',
                ],
            ],
        ]);

        // 预览路径的索引查找：既有变体的 product_id 非空、action=update
        $previewAfter = $service->previewSplitProducts(['product_ids' => [(int) $source->id]]);
        $matched = collect($previewAfter['items'][0]['variants'] ?? [])
            ->firstWhere('variant_key', 'cpu=4;memory=4');
        $this->assertNotNull($matched);
        $this->assertSame((int) $existingVariant->id, (int) $matched['product_id']);
        $this->assertSame('update', $matched['action']);

        $run = $service->splitProducts(['product_ids' => [(int) $source->id]]);
        $this->assertSame(2, $run['created_count'], '仅缺失的两个变体新建');
        $this->assertSame(2, $run['updated_count'], '源商品变体与预置既有变体应复用更新');

        $updatedIds = collect($run['items'])
            ->filter(fn (array $item) => $item['action'] === 'updated')
            ->map(fn (array $item) => (int) $item['product_id'])
            ->values()
            ->all();
        $this->assertContains((int) $source->id, $updatedIds, '源商品自身变体应命中源商品');
        $this->assertContains((int) $existingVariant->id, $updatedIds, '既有拆分变体应通过索引命中');
    }
}
