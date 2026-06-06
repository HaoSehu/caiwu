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
    public function test_admin_can_create_supplier_with_mofang_finance_provider(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $this->actingAsProductManager($suffix);

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
            ->assertJsonPath('data.interface_type', 'mofang_finance_api');

        $supplier = Supplier::query()
            ->where('name', 'Mofang Alias '.$suffix)
            ->first();

        $this->assertNotNull($supplier);
        $this->assertSame('mofang_finance_api', $supplier->interface_type);
    }

    public function test_admin_can_fetch_provider_type_options_from_registered_drivers(): void
    {
        $this->actingAsProductManager(bin2hex(random_bytes(4)));

        $this->getJson('/api/admin/suppliers/provider-types')
            ->assertOk()
            ->assertJsonPath('data.list.0.value', 'hosting_panel_api')
            ->assertJsonPath('data.list.0.label', '主机面板接口')
            ->assertJsonPath('data.list.1.value', 'mofang_finance_api')
            ->assertJsonPath('data.list.1.label', '魔方财务接口');
    }

    private function actingAsProductManager(string $suffix): void
    {
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
    }
}
