<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Support\AdminPermissions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCatalogRegressionTest extends TestCase
{
    public function test_admin_catalog_and_coupon_pages_do_not_require_removed_tables(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $role = Role::query()->create([
            'name' => 'admin-catalog-'.$suffix,
            'label' => 'Admin Catalog',
            'permissions' => [AdminPermissions::ALL],
        ]);

        $admin = AdminUser::query()->create([
            'username' => 'admin-catalog-'.$suffix,
            'password' => 'Temp@123456',
            'role_id' => (int) $role->id,
            'nickname' => 'Admin Catalog',
            'email' => 'admin-catalog-'.$suffix.'@example.com',
            'status' => 1,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/services?page=1&page_size=20')
            ->assertOk()
            ->assertJsonStructure(['code', 'message', 'data', 'timestamp']);

        $this->getJson('/api/admin/products/summary')
            ->assertOk()
            ->assertJsonStructure(['code', 'message', 'data', 'timestamp']);

        $this->getJson('/api/admin/product-types')
            ->assertOk()
            ->assertJsonStructure(['code', 'message', 'data', 'timestamp']);

        $this->getJson('/api/admin/coupons/product-tree')
            ->assertOk()
            ->assertJsonStructure(['code', 'message', 'data' => ['tree'], 'timestamp']);

        $this->getJson('/api/admin/coupon-campaigns?page=1&page_size=20')
            ->assertOk()
            ->assertJsonStructure(['code', 'message', 'data', 'timestamp']);

        $this->getJson('/api/admin/coupon-campaigns/summary')
            ->assertOk()
            ->assertJsonStructure(['code', 'message', 'data', 'timestamp']);
    }

    public function test_admin_product_list_filters_by_current_product_group_structure(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $role = Role::query()->create([
            'name' => 'admin-product-filter-'.$suffix,
            'label' => 'Admin Product Filter',
            'permissions' => [AdminPermissions::ALL],
        ]);

        $admin = AdminUser::query()->create([
            'username' => 'admin-product-filter-'.$suffix,
            'password' => 'Temp@123456',
            'role_id' => (int) $role->id,
            'nickname' => 'Admin Product Filter',
            'email' => 'admin-product-filter-'.$suffix.'@example.com',
            'status' => 1,
        ]);

        $group = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'domain',
            'name' => 'Filter root '.$suffix,
            'slug' => 'admin-filter-group-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        Product::query()->create([
            'product_group_id' => (int) $group->id,
            'name' => 'Domain product '.$suffix,
            'product_type' => 'domain',
            'pricing' => ['monthly' => '10.00'],
            'purchase_requires' => [],
            'config_options' => [],
            'stock' => -1,
            'status' => 1,
            'sort_order' => 0,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/products?keyword=&category_id='.$group->id.'&product_type=domain&status=&page=1&page_size=20')
            ->assertOk()
            ->assertJsonStructure(['code', 'message', 'data', 'timestamp']);
    }

    public function test_admin_can_batch_move_products_to_another_category(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $role = Role::query()->create([
            'name' => 'admin-product-batch-category-'.$suffix,
            'label' => 'Admin Product Batch Category',
            'permissions' => [AdminPermissions::ALL],
        ]);

        $admin = AdminUser::query()->create([
            'username' => 'admin-product-batch-category-'.$suffix,
            'password' => 'Temp@123456',
            'role_id' => (int) $role->id,
            'nickname' => 'Admin Product Batch Category',
            'email' => 'admin-product-batch-category-'.$suffix.'@example.com',
            'status' => 1,
        ]);

        $root = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'vps',
            'name' => 'Batch category root '.$suffix,
            'slug' => 'admin-batch-category-root-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $sourceCategory = ProductCategory::query()->create([
            'parent_id' => (int) $root->id,
            'product_type' => 'vps',
            'name' => 'Batch category source '.$suffix,
            'slug' => 'admin-batch-category-source-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 1,
        ]);

        $targetCategory = ProductCategory::query()->create([
            'parent_id' => (int) $root->id,
            'product_type' => 'vps',
            'name' => 'Batch category target '.$suffix,
            'slug' => 'admin-batch-category-target-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 2,
        ]);

        $existingTargetProduct = Product::query()->create([
            'product_group_id' => (int) $targetCategory->id,
            'name' => 'Target product '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '30.00'],
            'purchase_requires' => [],
            'config_options' => [],
            'stock' => -1,
            'status' => 1,
            'sort_order' => 1,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);

        $firstSourceProduct = Product::query()->create([
            'product_group_id' => (int) $sourceCategory->id,
            'name' => 'Source product A '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '10.00'],
            'purchase_requires' => [],
            'config_options' => [],
            'stock' => -1,
            'status' => 1,
            'sort_order' => 1,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);

        $secondSourceProduct = Product::query()->create([
            'product_group_id' => (int) $sourceCategory->id,
            'name' => 'Source product B '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '20.00'],
            'purchase_requires' => [],
            'config_options' => [],
            'stock' => -1,
            'status' => 1,
            'sort_order' => 2,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/products/category/batch', [
            'product_ids' => [(int) $firstSourceProduct->id, (int) $secondSourceProduct->id],
            'target_category_id' => (int) $targetCategory->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.updated_count', 2)
            ->assertJsonPath('data.target_category_id', (int) $targetCategory->id);

        $this->assertDatabaseHas('products', [
            'id' => (int) $firstSourceProduct->id,
            'product_group_id' => (int) $targetCategory->id,
            'product_type' => 'vps',
        ]);
        $this->assertDatabaseHas('products', [
            'id' => (int) $secondSourceProduct->id,
            'product_group_id' => (int) $targetCategory->id,
            'product_type' => 'vps',
        ]);

        $targetOrder = Product::query()
            ->where('product_group_id', (int) $targetCategory->id)
            ->orderBy('sort_order')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame([
            (int) $existingTargetProduct->id,
            (int) $firstSourceProduct->id,
            (int) $secondSourceProduct->id,
        ], $targetOrder);
    }

    public function test_admin_product_drag_reorder_uses_visible_list_order_when_sort_values_match(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $role = Role::query()->create([
            'name' => 'admin-product-drag-reorder-'.$suffix,
            'label' => 'Admin Product Drag Reorder',
            'permissions' => [AdminPermissions::ALL],
        ]);

        $admin = AdminUser::query()->create([
            'username' => 'admin-product-drag-reorder-'.$suffix,
            'password' => 'Temp@123456',
            'role_id' => (int) $role->id,
            'nickname' => 'Admin Product Drag Reorder',
            'email' => 'admin-product-drag-reorder-'.$suffix.'@example.com',
            'status' => 1,
        ]);

        $root = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'vps',
            'name' => 'Drag reorder root '.$suffix,
            'slug' => 'admin-drag-reorder-root-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $category = ProductCategory::query()->create([
            'parent_id' => (int) $root->id,
            'product_type' => 'vps',
            'name' => 'Drag reorder category '.$suffix,
            'slug' => 'admin-drag-reorder-category-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 1,
        ]);

        $firstProduct = Product::query()->create([
            'product_group_id' => (int) $category->id,
            'name' => 'Drag product A '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '10.00'],
            'purchase_requires' => [],
            'config_options' => [],
            'stock' => -1,
            'status' => 1,
            'sort_order' => 0,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);

        $secondProduct = Product::query()->create([
            'product_group_id' => (int) $category->id,
            'name' => 'Drag product B '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '20.00'],
            'purchase_requires' => [],
            'config_options' => [],
            'stock' => -1,
            'status' => 1,
            'sort_order' => 0,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);

        $thirdProduct = Product::query()->create([
            'product_group_id' => (int) $category->id,
            'name' => 'Drag product C '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '30.00'],
            'purchase_requires' => [],
            'config_options' => [],
            'stock' => -1,
            'status' => 1,
            'sort_order' => 0,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/products/reorder', [
            'product_id' => (int) $thirdProduct->id,
            'target_category_id' => (int) $category->id,
            'reference_product_id' => (int) $secondProduct->id,
            'position' => 'after',
        ])
            ->assertOk()
            ->assertJsonPath('data.product_id', (int) $thirdProduct->id)
            ->assertJsonPath('data.target_category_id', (int) $category->id)
            ->assertJsonPath('data.position', 'after');

        $orderedIds = Product::query()
            ->where('product_group_id', (int) $category->id)
            ->orderBy('sort_order')
            ->orderByDesc('status')
            ->orderByDesc('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame([
            (int) $secondProduct->id,
            (int) $thirdProduct->id,
            (int) $firstProduct->id,
        ], $orderedIds);
    }

    public function test_product_detail_payloads_do_not_depend_on_removed_description_column(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $role = Role::query()->create([
            'name' => 'admin-product-detail-'.$suffix,
            'label' => 'Admin Product Detail',
            'permissions' => [AdminPermissions::ALL],
        ]);

        $admin = AdminUser::query()->create([
            'username' => 'admin-product-detail-'.$suffix,
            'password' => 'Temp@123456',
            'role_id' => (int) $role->id,
            'nickname' => 'Admin Product Detail',
            'email' => 'admin-product-detail-'.$suffix.'@example.com',
            'status' => 1,
        ]);

        $root = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'vps',
            'name' => 'Product detail root '.$suffix,
            'slug' => 'admin-product-detail-root-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $category = ProductCategory::query()->create([
            'parent_id' => (int) $root->id,
            'product_type' => 'vps',
            'name' => 'Product detail category '.$suffix,
            'slug' => 'admin-product-detail-category-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 1,
        ]);

        $product = Product::query()->create([
            'product_group_id' => (int) $category->id,
            'name' => 'Product detail item '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '299.00'],
            'purchase_requires' => [],
            'config_options' => [],
            'stock' => -1,
            'status' => 1,
            'sort_order' => 1,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);

        Sanctum::actingAs($admin);

        $adminResponse = $this->getJson('/api/admin/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.id', (int) $product->id)
            ->assertJsonPath('data.name', (string) $product->name);

        $siteResponse = $this->getJson('/api/site/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.product.id', (int) $product->id)
            ->assertJsonPath('data.product.name', (string) $product->name);

        $this->assertArrayNotHasKey('description', $adminResponse->json('data'));
        $this->assertArrayNotHasKey('description', $siteResponse->json('data.product'));
    }

    public function test_admin_product_detail_keeps_description_removed_when_remark_support_is_added(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $role = Role::query()->create([
            'name' => 'admin-product-remark-'.$suffix,
            'label' => 'Admin Product Remark',
            'permissions' => [AdminPermissions::ALL],
        ]);

        $admin = AdminUser::query()->create([
            'username' => 'admin-product-remark-'.$suffix,
            'password' => 'Temp@123456',
            'role_id' => (int) $role->id,
            'nickname' => 'Admin Product Remark',
            'email' => 'admin-product-remark-'.$suffix.'@example.com',
            'status' => 1,
        ]);

        $root = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'vps',
            'name' => 'Product remark root '.$suffix,
            'slug' => 'admin-product-remark-root-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $category = ProductCategory::query()->create([
            'parent_id' => (int) $root->id,
            'product_type' => 'vps',
            'name' => 'Product remark category '.$suffix,
            'slug' => 'admin-product-remark-category-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 1,
        ]);

        $product = Product::query()->create([
            'product_group_id' => (int) $category->id,
            'name' => 'Product remark item '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '299.00'],
            'purchase_requires' => [],
            'config_options' => [],
            'stock' => -1,
            'status' => 1,
            'sort_order' => 1,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.id', (int) $product->id);

        $this->assertArrayNotHasKey('description', $response->json('data'));
        $this->assertArrayHasKey('remark', $response->json('data'));
    }

}
