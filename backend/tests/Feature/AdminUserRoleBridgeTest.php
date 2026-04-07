<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use App\Services\AdminRoleBridgeService;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUserRoleBridgeTest extends TestCase
{
    public function test_admin_role_bridge_service_syncs_bridge_table_and_preserves_permissions(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $role = Role::query()->create([
            'name' => 'bridge-role-' . $suffix,
            'label' => '桥表角色',
            'permissions' => [AdminPermissions::TICKET_REPLY],
        ]);

        $admin = AdminUser::query()->create([
            'username' => 'bridge-admin-' . $suffix,
            'password' => 'secret123',
            'role_id' => (int) $role->id,
            'nickname' => '桥表管理员',
            'email' => "bridge-admin-{$suffix}@example.com",
            'status' => 1,
        ]);

        $service = app(AdminRoleBridgeService::class);
        $service->syncPrimaryRole($admin);

        $bridgeRow = DB::table('admin_user_roles')
            ->where('admin_user_id', (int) $admin->id)
            ->first();

        $this->assertNotNull($bridgeRow);
        $this->assertSame((int) $role->id, (int) ($bridgeRow->role_id ?? 0));

        $reloadedAdmin = AdminUser::query()
            ->withResolvedPermissionRelations()
            ->findOrFail((int) $admin->id);

        $this->assertTrue($reloadedAdmin->hasPermission(AdminPermissions::TICKET_REPLY));

        $service->syncPrimaryRole($admin, 0);

        $this->assertDatabaseMissing('admin_user_roles', [
            'admin_user_id' => (int) $admin->id,
        ]);
    }

    public function test_admin_user_model_no_longer_uses_saved_hook_for_role_bridge(): void
    {
        $content = file_get_contents(base_path('app/Models/AdminUser.php'));

        $this->assertIsString($content);
        $this->assertStringNotContainsString('static::saved', $content);
    }
}
