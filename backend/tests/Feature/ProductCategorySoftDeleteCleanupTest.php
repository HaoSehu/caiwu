<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Services\ProductCatalog\ProductCategoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 删除商品分类对软删商品的清理回归。
 *
 * 起因：删除分类的守卫曾用默认作用域查商品——软删商品漏检，且软删商品与其
 * product_upstream_bindings（product_id 为 RESTRICT 外键）不清理，
 * 分组物理 DELETE 直接撞外键被兜底渲染成 500。
 *
 * 语义与商品物理删除守卫（ProductAdminService::forceDeleteProduct）协同：
 * 无服务实例的软删商品随分类删除一并清理；有服务实例的软删商品阻断并给清晰错误。
 * 使用 DatabaseTransactions：所有写入在测试结束后回滚，不污染 idc_test 现有数据。
 */
class ProductCategorySoftDeleteCleanupTest extends TestCase
{
    use DatabaseTransactions;

    private function categoryService(): ProductCategoryService
    {
        return app(ProductCategoryService::class);
    }

    /**
     * 建一棵全新的一/二/三级分组树（一级用唯一编码，不触碰种子数据），
     * 返回各层 id，供三个删除层级分别测试。
     *
     * @return array{first: int, second: int, third: int}
     */
    private function createIsolatedGroupTree(): array
    {
        $suffix = uniqid();
        $first = $this->categoryService()->createCategory([
            'level' => 1,
            'name' => '清理一级-'.$suffix,
            'code' => 'cleanup-'.$suffix,
        ]);
        $second = $this->categoryService()->createCategory([
            'level' => 2,
            'name' => '清理二级-'.$suffix,
            'first_product_group_id' => (int) $first['id'],
        ]);
        $third = $this->categoryService()->createCategory([
            'level' => 3,
            'name' => '清理三级-'.$suffix,
            'second_product_group_id' => (int) $second['id'],
        ]);

        return [
            'first' => (int) $first['id'],
            'second' => (int) $second['id'],
            'third' => (int) $third['id'],
        ];
    }

    private function createProductInThirdGroup(int $thirdGroupId, array $extra = []): Product
    {
        return Product::query()->create([
            ...$extra,
            'product_group_id' => $thirdGroupId,
            'service_type_code' => 'test',
            'product_type' => 'other',
            'pricing' => ['monthly' => 100.00],
            'setup_fee' => 0,
            'stock' => -1,
            'status' => 1,
            'sort_order' => 0,
        ]);
    }

    /**
     * 给软删商品挂一条完整的上游绑定链（plugin → supplier → binding → product binding）。
     */
    private function attachUpstreamBinding(Product $product): void
    {
        $suffix = uniqid();
        $pluginId = (int) DB::table('integration_plugins')->insertGetId([
            'domain' => 'upstream',
            'slug' => 'slug-'.$suffix,
            'plugin_key' => 'plugin-'.$suffix,
            'name' => '测试插件-'.$suffix,
            'entry_class' => 'Tests\\Fixtures\\DummyPlugin',
        ]);
        $supplierId = (int) DB::table('suppliers')->insertGetId([
            'name' => '测试供应商-'.$suffix,
            'code' => 'sup-'.$suffix,
        ]);
        $bindingId = (int) DB::table('supplier_plugin_bindings')->insertGetId([
            'supplier_id' => $supplierId,
            'plugin_id' => $pluginId,
            'provider_key' => 'plugin-'.$suffix,
        ]);
        DB::table('product_upstream_bindings')->insert([
            'product_id' => (int) $product->id,
            'supplier_plugin_binding_id' => $bindingId,
            'plugin_id' => $pluginId,
            'provider_key' => 'plugin-'.$suffix,
            'upstream_product_id' => (string) random_int(10000, 99999),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 给商品挂一条服务实例（含已软删的服务行，其外键引用仍会阻塞商品物理删除）。
     */
    private function attachServiceInstance(Product $product, bool $trashed = false): int
    {
        $userId = (int) DB::table('users')->insertGetId([
            'email' => 'cat-cleanup-'.uniqid().'@test.local',
            'password' => 'test',
        ]);

        return (int) DB::table('services')->insertGetId([
            'user_id' => $userId,
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 100,
            'deleted_at' => $trashed ? now() : null,
        ]);
    }

    /**
     * 无服务实例的软删商品：随三级分类删除一并物理清理，上游绑定行同步清除。
     */
    public function test_delete_third_group_cleans_serviceless_soft_deleted_products(): void
    {
        $groupIds = $this->createIsolatedGroupTree();
        $product = $this->createProductInThirdGroup($groupIds['third']);
        $this->attachUpstreamBinding($product);
        $product->delete();

        $productId = (int) $product->id;
        $this->assertTrue(
            DB::table('product_upstream_bindings')->where('product_id', $productId)->exists(),
            '前置条件：软删商品应持有上游绑定行'
        );

        $this->categoryService()->deleteCategory($groupIds['third'], 3);

        $this->assertDatabaseMissing('products', ['id' => $productId]);
        $this->assertDatabaseMissing('product_upstream_bindings', ['product_id' => $productId]);
        $this->assertDatabaseMissing('third_product_groups', ['id' => $groupIds['third']]);
    }

    /**
     * 有服务实例的软删商品：分类删除被阻断并给清晰错误，商品与分组原样保留。
     * 已软删的服务行同样算服务实例——它的外键引用仍会阻塞商品物理删除。
     */
    public function test_delete_third_group_blocked_by_soft_deleted_product_with_service(): void
    {
        $groupIds = $this->createIsolatedGroupTree();
        $product = $this->createProductInThirdGroup($groupIds['third']);
        $this->attachServiceInstance($product, trashed: true);
        $product->delete();

        try {
            $this->categoryService()->deleteCategory($groupIds['third'], 3);
            $this->fail('期待业务异常');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('服务实例', $exception->getMessage());
        }

        $this->assertSoftDeleted('products', ['id' => (int) $product->id]);
        $this->assertDatabaseHas('third_product_groups', ['id' => $groupIds['third']]);
    }

    /**
     * 未删除（活跃）商品仍按既有语义阻断分类删除。
     */
    public function test_delete_third_group_with_active_product_is_blocked(): void
    {
        $groupIds = $this->createIsolatedGroupTree();
        $this->createProductInThirdGroup($groupIds['third']);

        try {
            $this->categoryService()->deleteCategory($groupIds['third'], 3);
            $this->fail('期待业务异常');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('请先迁移或删除该分类下的商品', $exception->getMessage());
        }

        $this->assertDatabaseHas('third_product_groups', ['id' => $groupIds['third']]);
    }

    /**
     * 二级分类删除覆盖子树内三级分组（inSecondProductGroup 作用域路径）：
     * 层级守卫要求先删下级分类，按「三级 → 二级」的自底向上流程验证链路。
     */
    public function test_delete_second_group_after_third_group_in_subtree(): void
    {
        $groupIds = $this->createIsolatedGroupTree();
        $product = $this->createProductInThirdGroup($groupIds['third']);
        $product->delete();

        $this->categoryService()->deleteCategory($groupIds['third'], 3);
        $this->assertDatabaseMissing('products', ['id' => (int) $product->id]);

        $this->categoryService()->deleteCategory($groupIds['second'], 2);
        $this->assertDatabaseMissing('second_product_groups', ['id' => $groupIds['second']]);
    }

    /**
     * 一级分类删除覆盖整棵子树（inFirstProductGroup 作用域路径）：
     * 层级守卫要求先删下级分类，按「三级 → 二级 → 一级」的自底向上流程验证链路。
     */
    public function test_delete_first_group_after_subtree_in_tree(): void
    {
        $groupIds = $this->createIsolatedGroupTree();
        $product = $this->createProductInThirdGroup($groupIds['third']);
        $product->delete();

        $this->categoryService()->deleteCategory($groupIds['third'], 3);
        $this->categoryService()->deleteCategory($groupIds['second'], 2);
        $this->categoryService()->deleteCategory($groupIds['first'], 1);

        $this->assertDatabaseMissing('products', ['id' => (int) $product->id]);
        $this->assertDatabaseMissing('third_product_groups', ['id' => $groupIds['third']]);
        $this->assertDatabaseMissing('second_product_groups', ['id' => $groupIds['second']]);
        $this->assertDatabaseMissing('first_product_groups', ['id' => $groupIds['first']]);
    }
}
