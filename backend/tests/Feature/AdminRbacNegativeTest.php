<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use App\Services\Admin\Rbac\PermissionCatalogService;
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
     * 退款标记权限（finance.refund）：资金域敏感操作，必须在权限目录中登记
     * （资金操作组 / high 风险级），且默认不下发任何内置角色。
     */
    public function test_refund_mark_permission_is_catalogued_but_not_defaulted(): void
    {
        $catalog = app(PermissionCatalogService::class);
        $item = null;
        foreach ($catalog->list() as $entry) {
            if (($entry['key'] ?? '') === AdminPermissions::FINANCE_REFUND) {
                $item = $entry;
                break;
            }
        }

        $this->assertNotNull($item, 'finance.refund 必须出现在权限目录');
        $this->assertSame('finance_funds', $item['group']);
        $this->assertSame('high', $item['risk_level']);
        $this->assertSame('标记账单退款', $item['name']);
        $this->assertSame('退款标记', $item['action_label']);
        $this->assertTrue((bool) $item['is_dangerous']);

        $this->assertNotContains(AdminPermissions::FINANCE_REFUND, AdminPermissions::visitorPermissions());
        $this->assertNotContains(AdminPermissions::FINANCE_REFUND, AdminPermissions::adminDefaultPermissions());
        $this->assertNotContains(
            AdminPermissions::FINANCE_REFUND,
            AdminPermissions::impliedPermissions(AdminPermissions::INVOICE_MANAGE)
        );
        $this->assertNotContains(
            AdminPermissions::FINANCE_REFUND,
            AdminPermissions::impliedPermissions(AdminPermissions::ORDER_MANAGE)
        );
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
