<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketDepartmentStaff;
use App\Models\User;
use App\Services\Ticket\TicketAutomationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M3 部门自动分派：三种 auto_assign_mode / 无成员置空 / 轮询游标循环。
 */
class TicketAutoAssignTest extends TestCase
{
    use DatabaseTransactions;

    private TicketAutomationService $automation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->automation = app(TicketAutomationService::class);
    }

    public function test_manual_mode_keeps_assignee_untouched(): void
    {
        $department = TicketDepartment::create([
            'name' => '手动模式部门',
            'auto_assign_mode' => TicketDepartment::AUTO_ASSIGN_MANUAL,
        ]);
        // tickets.assignee_id FK → admin_users：须用真实管理员 ID（88888 会违反外键）。
        $assignee = $this->makeAdmin();

        $ticket = $this->makeTicket((int) $department->id, (int) $assignee->id);

        $this->automation->dispatch($ticket, $department);

        $this->assertSame((int) $assignee->id, (int) $ticket->refresh()->assignee_id);
    }

    public function test_round_robin_cycles_through_staff_in_id_order(): void
    {
        $department = TicketDepartment::create([
            'name' => '轮询部门',
            'auto_assign_mode' => TicketDepartment::AUTO_ASSIGN_ROUND_ROBIN,
        ]);

        $staffA = $this->makeAdmin();
        $staffB = $this->makeAdmin();
        $this->attachStaff($department, $staffA);
        $this->attachStaff($department, $staffB);

        // 成员 id 升序队列：a < b（按创建顺序保证）
        $ids = collect([(int) $staffA->id, (int) $staffB->id])->sort()->values()->all();

        $first = $this->automation->dispatch($this->makeTicket((int) $department->id), $department);
        $this->assertSame($ids[0], (int) $first->assignee_id);

        $second = $this->automation->dispatch($this->makeTicket((int) $department->id), $department);
        $this->assertSame($ids[1], (int) $second->assignee_id);

        // 游标指向末尾后回到队首（循环）
        $third = $this->automation->dispatch($this->makeTicket((int) $department->id), $department);
        $this->assertSame($ids[0], (int) $third->assignee_id);

        $this->assertSame($ids[0], $this->automation->dispatchCursorSnapshot()[(int) $department->id]);
    }

    public function test_least_load_picks_staff_with_fewest_open_tickets(): void
    {
        $department = TicketDepartment::create([
            'name' => '负载部门',
            'auto_assign_mode' => TicketDepartment::AUTO_ASSIGN_LEAST_LOAD,
        ]);

        $staffA = $this->makeAdmin();
        $staffB = $this->makeAdmin();
        $this->attachStaff($department, $staffA);
        $this->attachStaff($department, $staffB);

        $user = $this->makeUser();

        // A 名下 2 张未结单，B 名下 1 张未结单 + 1 张已结单（不计负载）
        // tickets.subject NOT NULL，造负载单也必须带主题。
        Ticket::create(['user_id' => (int) $user->id, 'department' => 'x', 'subject' => '负载单A1', 'status' => 0, 'assignee_id' => (int) $staffA->id]);
        Ticket::create(['user_id' => (int) $user->id, 'department' => 'x', 'subject' => '负载单A2', 'status' => 1, 'assignee_id' => (int) $staffA->id]);
        Ticket::create(['user_id' => (int) $user->id, 'department' => 'x', 'subject' => '负载单B1', 'status' => 3, 'assignee_id' => (int) $staffB->id]);

        $ticket = $this->automation->dispatch($this->makeTicket((int) $department->id), $department);

        $this->assertSame((int) $staffB->id, (int) $ticket->assignee_id);
    }

    public function test_department_without_enabled_staff_clears_assignee(): void
    {
        $department = TicketDepartment::create([
            'name' => '无成员部门',
            'auto_assign_mode' => TicketDepartment::AUTO_ASSIGN_ROUND_ROBIN,
        ]);

        // 仅落部门，assignee 置 NULL（tickets.assignee_id FK → admin_users，须用真实 ID 验证被清空）
        $ticket = $this->automation->dispatch($this->makeTicket((int) $department->id, (int) $this->makeAdmin()->id), $department);
        $this->assertNull($ticket->refresh()->assignee_id);

        // 成员被禁用后同样视为无可用成员
        $staff = $this->makeAdmin();
        $staff->update(['status' => 0]);
        $this->attachStaff($department, $staff);

        $ticket2 = $this->automation->dispatch($this->makeTicket((int) $department->id), $department);
        $this->assertNull($ticket2->refresh()->assignee_id);
    }

    private function attachStaff(TicketDepartment $department, AdminUser $admin): void
    {
        TicketDepartmentStaff::create([
            'department_id' => (int) $department->id,
            'admin_user_id' => (int) $admin->id,
            'is_leader' => 0,
        ]);
    }

    private function makeTicket(int $departmentId, ?int $assigneeId = null): Ticket
    {
        return Ticket::create([
            'user_id' => (int) $this->makeUser()->id,
            'department' => 'auto_assign_test',
            'department_id' => $departmentId,
            'subject' => '自动分派测试工单',
            'status' => 0,
            'assignee_id' => $assigneeId,
        ]);
    }

    private function makeAdmin(): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'assign_role_'.uniqid(),
            'label' => '自动分派测试角色',
            'permissions' => [],
        ]);

        return AdminUser::query()->create([
            'username' => 'assign_staff_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '自动分派测试客服',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'assign_user_'.uniqid().'@test.local',
            'password' => 'secret123',
            'nickname' => '自动分派测试用户',
            'status' => 1,
        ]);
    }
}
