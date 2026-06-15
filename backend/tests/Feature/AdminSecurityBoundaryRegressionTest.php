<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Support\AdminPermissions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSecurityBoundaryRegressionTest extends TestCase
{
    public function test_order_list_permission_does_not_allow_order_detail(): void
    {
        Sanctum::actingAs($this->createAdminUser([AdminPermissions::ORDER_LIST]));

        $this->getJson('/api/admin/orders/1')
            ->assertForbidden()
            ->assertJsonPath('code', 40300);
    }

    public function test_log_list_permission_does_not_allow_log_cleanup(): void
    {
        Sanctum::actingAs($this->createAdminUser([AdminPermissions::LOG_LIST]));

        $this->postJson('/api/admin/logs/cleanup', [
            'type' => 'api',
            'keep_days' => 30,
            'confirm_text' => '立即清理',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 40300);
    }

    public function test_supplier_with_bound_products_cannot_be_deleted(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $supplier = Supplier::query()->create([
            'name' => 'Delete Guard Supplier '.$suffix,
            'code' => 'delete_guard_'.$suffix,
            'interface_type' => 'hosting_panel_api',
            'api_url' => 'https://provider.example.test',
            'api_username' => 'provider-user',
            'api_key' => 'provider-secret',
            'status' => 1,
            'sort_order' => 1,
        ]);

        Product::query()->create([
            'name' => 'Delete Guard Product '.$suffix,
            'product_type' => 'cloud',
            'remark' => 'Delete Guard Product',
            'pricing' => ['monthly' => '100.00'],
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => 10,
            'status' => 1,
            'sort_order' => 1,
            'supplier_id' => (int) $supplier->id,
            'supplier_product_id' => 10001,
            'provision_module' => 'hosting_panel_api',
        ]);

        Sanctum::actingAs($this->createAdminUser([AdminPermissions::PRODUCT_MANAGE]));

        $this->deleteJson('/api/admin/suppliers/'.$supplier->id)
            ->assertStatus(409)
            ->assertJsonPath('code', 40900)
            ->assertJsonPath('data.usage.products', 1);

        $this->assertDatabaseHas('suppliers', ['id' => (int) $supplier->id]);
    }

    private function createAdminUser(array $permissions): AdminUser
    {
        $suffix = bin2hex(random_bytes(4));
        $role = Role::query()->create([
            'name' => 'security-boundary-role-'.$suffix,
            'label' => 'Security Boundary Role',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'security-boundary-admin-'.$suffix,
            'password' => 'secret123',
            'nickname' => 'Security Boundary Admin',
            'role_id' => (int) $role->id,
            'status' => 1,
        ]);
    }
}
