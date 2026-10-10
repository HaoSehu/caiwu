<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 管理端 RBAC 负向回归（B5：V7-08 零覆盖缺口，BACKEND §9「权限码缺失、水平越权、用户资源归属」）：
 * 1. 权限码缺失 → 403（40300），不得放行；
 * 2. 最小权限边界：仅授予仪表盘权限的账号访问订单接口仍被拒；
 * 3. 账号被禁用 → 403（40300），即使持有权限码也不放行。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class AdminRbacNegativeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_missing_permission_is_rejected_with_403(): void
    {
        Sanctum::actingAs($this->makeAdmin([]));

        $this->getJson('/api/v2/admin/dashboard/stats')
            ->assertStatus(403)
            ->assertJsonPath('code', 40300)
            ->assertJsonPath('message', '无操作权限');
    }

    public function test_minimum_permission_scope_is_enforced(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::DASHBOARD_VIEW]));

        // 已授权端点放行
        $this->getJson('/api/v2/admin/dashboard/stats')->assertOk();

        // 未授权端点仍 403：权限不得横向扩散
        $this->getJson('/api/v2/admin/orders')
            ->assertStatus(403)
            ->assertJsonPath('code', 40300);
    }

    public function test_disabled_admin_is_rejected_even_with_permission(): void
    {
        $admin = $this->makeAdmin([AdminPermissions::DASHBOARD_VIEW]);
        $admin->update(['status' => 0]);

        Sanctum::actingAs($admin->refresh());

        $this->getJson('/api/v2/admin/dashboard/stats')
            ->assertStatus(403)
            ->assertJsonPath('code', 40300)
            ->assertJsonPath('message', '管理员账号已被禁用');
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => 'RBAC 负向测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => 'RBAC 测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
