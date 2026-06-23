<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\FirstProductGroup;
use App\Models\Product;
use App\Models\Role;
use App\Models\SecondProductGroup;
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

        $firstGroup = $this->firstGroupForType('domain', 'Filter root '.$suffix, 'admin-filter-root-'.$suffix);
        $group = $this->createSecondGroup($firstGroup, 'Filter group '.$suffix, 'admin-filter-group-'.$suffix);

        Product::query()->create($this->productPayload($group, 'Domain product '.$suffix, '10.00', 0));

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/products?keyword=&second_product_group_id='.$group->id.'&product_type=domain&status=&page=1&page_size=20')
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

        $root = $this->firstGroupForType('vps', 'Batch category root '.$suffix, 'admin-batch-category-root-'.$suffix);
        $sourceCategory = $this->createSecondGroup($root, 'Batch category source '.$suffix, 'admin-batch-category-source-'.$suffix, 1);
        $targetCategory = $this->createSecondGroup($root, 'Batch category target '.$suffix, 'admin-batch-category-target-'.$suffix, 2);

        $existingTargetProduct = Product::query()->create($this->productPayload($targetCategory, 'Target product '.$suffix, '30.00', 1));
        $firstSourceProduct = Product::query()->create($this->productPayload($sourceCategory, 'Source product A '.$suffix, '10.00', 1));
        $secondSourceProduct = Product::query()->create($this->productPayload($sourceCategory, 'Source product B '.$suffix, '20.00', 2));

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/products/category/batch', [
            'product_ids' => [(int) $firstSourceProduct->id, (int) $secondSourceProduct->id],
            'target_second_product_group_id' => (int) $targetCategory->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.updated_count', 2)
            ->assertJsonPath('data.target_effective_product_group_id', (int) $targetCategory->id);

        $this->assertDatabaseHas('products', [
            'id' => (int) $firstSourceProduct->id,
            'second_product_group_id' => (int) $targetCategory->id,
            'product_type' => 'vps',
        ]);
        $this->assertDatabaseHas('products', [
            'id' => (int) $secondSourceProduct->id,
            'second_product_group_id' => (int) $targetCategory->id,
            'product_type' => 'vps',
        ]);

        $targetOrder = Product::query()
            ->where('second_product_group_id', (int) $targetCategory->id)
            ->whereNull('third_product_group_id')
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

        $root = $this->firstGroupForType('vps', 'Drag reorder root '.$suffix, 'admin-drag-reorder-root-'.$suffix);
        $category = $this->createSecondGroup($root, 'Drag reorder category '.$suffix, 'admin-drag-reorder-category-'.$suffix);

        $firstProduct = Product::query()->create($this->productPayload($category, 'Drag product A '.$suffix, '10.00', 0));
        $secondProduct = Product::query()->create($this->productPayload($category, 'Drag product B '.$suffix, '20.00', 0));
        $thirdProduct = Product::query()->create($this->productPayload($category, 'Drag product C '.$suffix, '30.00', 0));

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/products/reorder', [
            'product_id' => (int) $thirdProduct->id,
            'target_second_product_group_id' => (int) $category->id,
            'reference_product_id' => (int) $secondProduct->id,
            'position' => 'after',
        ])
            ->assertOk()
            ->assertJsonPath('data.product_id', (int) $thirdProduct->id)
            ->assertJsonPath('data.target_effective_product_group_id', (int) $category->id)
            ->assertJsonPath('data.position', 'after');

        $orderedIds = Product::query()
            ->where('second_product_group_id', (int) $category->id)
            ->whereNull('third_product_group_id')
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

        $root = $this->firstGroupForType('vps', 'Product detail root '.$suffix, 'admin-product-detail-root-'.$suffix);
        $category = $this->createSecondGroup($root, 'Product detail category '.$suffix, 'admin-product-detail-category-'.$suffix);

        $product = Product::query()->create($this->productPayload($category, 'Product detail item '.$suffix, '299.00', 1));

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

        $root = $this->firstGroupForType('vps', 'Product remark root '.$suffix, 'admin-product-remark-root-'.$suffix);
        $category = $this->createSecondGroup($root, 'Product remark category '.$suffix, 'admin-product-remark-category-'.$suffix);

        $product = Product::query()->create($this->productPayload($category, 'Product remark item '.$suffix, '299.00', 1));

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.id', (int) $product->id);

        $this->assertArrayNotHasKey('description', $response->json('data'));
        $this->assertArrayHasKey('remark', $response->json('data'));
    }

    private function firstGroupForType(string $code, string $name, string $slug): FirstProductGroup
    {
        $group = FirstProductGroup::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'slug' => $slug,
                'sort_order' => 0,
                'is_visible' => 1,
                'is_system' => 0,
                'legacy_product_type' => $code,
            ]
        );

        if ((int) $group->is_visible !== 1) {
            $group->update(['is_visible' => 1]);
        }

        return $group->refresh();
    }

    private function createSecondGroup(FirstProductGroup $firstGroup, string $name, string $slug, int $sortOrder = 0): SecondProductGroup
    {
        return SecondProductGroup::query()->create([
            'first_product_group_id' => (int) $firstGroup->id,
            'name' => $name,
            'slug' => $slug,
            'sort_order' => $sortOrder,
            'is_visible' => 1,
        ]);
    }

    private function productPayload(SecondProductGroup $group, string $name, string $monthlyPrice, int $sortOrder): array
    {
        $firstGroup = $group->firstProductGroup ?: FirstProductGroup::query()->findOrFail((int) $group->first_product_group_id);
        $code = (string) $firstGroup->code;

        return [
            'first_product_group_id' => (int) $firstGroup->id,
            'second_product_group_id' => (int) $group->id,
            'third_product_group_id' => null,
            'service_type_code' => $code,
            'name' => $name,
            'custom_display_name' => $name,
            'product_type' => $code,
            'pricing' => ['monthly' => $monthlyPrice],
            'purchase_requires' => [],
            'config_options' => [],
            'stock' => -1,
            'status' => 1,
            'sort_order' => $sortOrder,
            'provision_module' => null,
            'auto_setup' => 0,
        ];
    }
}
