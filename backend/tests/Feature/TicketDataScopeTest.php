<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketDepartmentStaff;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M3 部门级数据范围：
 * 1. 有部门归属的客服仅见本部门工单；
 * 2. 无归属客服保持全量（向后兼容裁决）；
 * 3. super_admin(*) 恒全量；
 * 4. 无 ticket.list 权限 403。
 */
class TicketDataScopeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_department_scoped_staff_sees_only_own_department_tickets(): void
    {
        $deptA = TicketDepartment::create(['name' => '范围部门A']);
        $deptB = TicketDepartment::create(['name' => '范围部门B']);

        $staff = $this->makeAdmin([AdminPermissions::TICKET_LIST]);
        TicketDepartmentStaff::create([
            'department_id' => (int) $deptA->id,
            'admin_user_id' => (int) $staff->id,
            'is_leader' => 0,
        ]);

        $own = $this->makeTicket($deptA);
        $other = $this->makeTicket($deptB);

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v2/admin/tickets')->assertOk();
        $ids = collect($response->json('data.list'))->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $own->id, $ids);
        $this->assertNotContains((int) $other->id, $ids);
    }

    public function test_staff_without_department_membership_sees_all_tickets(): void
    {
        $deptA = TicketDepartment::create(['name' => '兼容部门A']);
        $deptB = TicketDepartment::create(['name' => '兼容部门B']);

        $staff = $this->makeAdmin([AdminPermissions::TICKET_LIST]);

        $ticketA = $this->makeTicket($deptA);
        $ticketB = $this->makeTicket($deptB);

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v2/admin/tickets')->assertOk();
        $ids = collect($response->json('data.list'))->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $ticketA->id, $ids);
        $this->assertContains((int) $ticketB->id, $ids);
    }

    public function test_super_admin_sees_all_tickets_even_with_department_membership(): void
    {
        $dept = TicketDepartment::create(['name' => '超管范围部门']);
        $other = TicketDepartment::create(['name' => '超管范围部门B']);

        $superAdmin = $this->makeSuperAdmin();
        TicketDepartmentStaff::create([
            'department_id' => (int) $dept->id,
            'admin_user_id' => (int) $superAdmin->id,
            'is_leader' => 0,
        ]);

        $own = $this->makeTicket($dept);
        $foreign = $this->makeTicket($other);

        Sanctum::actingAs($superAdmin);

        $response = $this->getJson('/api/v2/admin/tickets')->assertOk();
        $ids = collect($response->json('data.list'))->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $own->id, $ids);
        $this->assertContains((int) $foreign->id, $ids);
    }

    public function test_missing_ticket_list_permission_is_rejected(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::DASHBOARD_VIEW]));

        $this->getJson('/api/v2/admin/tickets')
            ->assertStatus(403)
            ->assertJsonPath('code', 40300);
    }

    private function makeTicket(TicketDepartment $department): Ticket
    {
        return Ticket::create([
            'user_id' => (int) User::query()->create([
                'email' => 'scope_user_'.uniqid().'@test.local',
                'password' => 'secret123',
                'nickname' => '数据范围测试用户',
                'status' => 1,
            ])->id,
            'department' => (string) $department->name,
            'department_id' => (int) $department->id,
            'subject' => '数据范围测试工单 '.uniqid(),
            'status' => 0,
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'scope_role_'.uniqid(),
            'label' => '数据范围测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'scope_staff_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '数据范围测试客服',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }

    private function makeSuperAdmin(): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'scope_super_'.uniqid(),
            'label' => '数据范围超管角色',
            'permissions' => [AdminPermissions::ALL],
        ]);

        return AdminUser::query()->create([
            'username' => 'scope_super_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '数据范围超管',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
