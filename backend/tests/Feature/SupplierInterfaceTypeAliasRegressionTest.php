<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\Supplier;
use App\Support\AdminPermissions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierInterfaceTypeAliasRegressionTest extends TestCase
{
    public function test_admin_can_create_supplier_with_mofang_finance_alias(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $role = Role::query()->create([
            'name' => 'supplier-alias-'.$suffix,
            'label' => 'Supplier Alias',
            'permissions' => [AdminPermissions::PRODUCT_MANAGE],
        ]);

        $admin = AdminUser::query()->create([
            'username' => 'supplier-alias-'.$suffix,
            'password' => 'Temp@123456',
            'role_id' => (int) $role->id,
            'nickname' => 'Supplier Alias',
            'email' => 'supplier-alias-'.$suffix.'@example.com',
            'status' => 1,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/suppliers', [
            'name' => 'Mofang Alias '.$suffix,
            'interface_type' => 'mofang_finance_api',
            'api_url' => 'https://supplier-'.$suffix.'.example.com',
            'api_username' => 'demo',
            'api_key' => 'secret',
            'status' => 1,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.interface_type', 'hosting_panel_api');

        $supplier = Supplier::query()
            ->where('name', 'Mofang Alias '.$suffix)
            ->first();

        $this->assertNotNull($supplier);
        $this->assertSame('hosting_panel_api', $supplier->interface_type);
    }
}
