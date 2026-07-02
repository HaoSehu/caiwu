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

class AdminProductDeletedVisibilityTest extends TestCase
{
    public function test_admin_can_list_restore_and_force_delete_soft_deleted_products(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $admin = $this->createAdmin($suffix);
        $root = $this->firstGroupForType('vps', 'Deleted visibility root '.$suffix, 'deleted-visibility-root-'.$suffix);
        $category = $this->createSecondGroup($root, 'Deleted visibility category '.$suffix, 'deleted-visibility-category-'.$suffix);

        $product = Product::query()->create($this->productPayload($category, 'Deleted visibility product '.$suffix, '66.00', 1));
        $product->delete();

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/product-categories?product_type=vps')
            ->assertOk()
            ->assertJsonFragment([
                'id' => (int) $category->id,
                'products_count' => 0,
                'products_with_trashed_count' => 1,
            ]);

        $this->getJson('/api/admin/products?keyword=&second_product_group_id='.$category->id.'&product_type=vps&lifecycle_status=deleted&status=&page=1&page_size=20')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.id', (int) $product->id)
            ->assertJsonPath('data.list.0.is_deleted', true)
            ->assertJsonPath('data.list.0.lifecycle_status', 'deleted');

        $this->postJson('/api/admin/products/'.$product->id.'/restore')
            ->assertOk()
            ->assertJsonPath('data.id', (int) $product->id)
            ->assertJsonPath('data.is_deleted', false)
            ->assertJsonPath('data.lifecycle_status', 'active');

        $this->assertDatabaseHas('products', [
            'id' => (int) $product->id,
            'deleted_at' => null,
        ]);

        $product->refresh()->delete();

        $this->deleteJson('/api/admin/products/'.$product->id.'/force')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('products', [
            'id' => (int) $product->id,
        ]);
    }

    private function createAdmin(string $suffix): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'admin-product-deleted-'.$suffix,
            'label' => 'Admin Product Deleted',
            'permissions' => [AdminPermissions::ALL],
        ]);

        return AdminUser::query()->create([
            'username' => 'admin-product-deleted-'.$suffix,
            'password' => 'Temp@123456',
            'role_id' => (int) $role->id,
            'nickname' => 'Admin Product Deleted',
            'email' => 'admin-product-deleted-'.$suffix.'@example.com',
            'status' => 1,
        ]);
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

    private function createSecondGroup(FirstProductGroup $firstGroup, string $name, string $slug): SecondProductGroup
    {
        return SecondProductGroup::query()->create([
            'first_product_group_id' => (int) $firstGroup->id,
            'name' => $name,
            'slug' => $slug,
            'sort_order' => 0,
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
