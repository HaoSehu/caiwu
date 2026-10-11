<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\AdminUser;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketDepartmentStaff;
use App\Models\TicketTransferLog;
use App\Models\User;
use App\Services\Ticket\TicketService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M3 工单转派：转派流水 / 自动分派 / 冗余列同步。
 */
class TicketTransferTest extends TestCase
{
    use DatabaseTransactions;

    public function test_transfer_with_explicit_assignee_writes_log_and_syncs_redundant_column(): void
    {
        $service = app(TicketService::class);
        $operator = $this->makeAdmin([AdminPermissions::TICKET_MANAGE]);
        $targetStaff = $this->makeAdmin([AdminPermissions::TICKET_REPLY]);

        $from = TicketDepartment::create(['name' => '转出部门']);
        $to = TicketDepartment::create([
            'name' => '转入部门',
            'auto_assign_mode' => TicketDepartment::AUTO_ASSIGN_MANUAL,
        ]);

        $ticket = $this->makeTicket((int) $from->id);
        $originalStaff = $this->makeAdmin([]);
        $ticket->update(['assignee_id' => (int) $originalStaff->id]);

        $transferred = $service->transfer($ticket, (int) $to->id, (int) $targetStaff->id, '需要专项处理', $operator);

        $this->assertSame((int) $to->id, (int) $transferred->department_id);
        $this->assertSame('转入部门', (string) $transferred->department);
        $this->assertSame((int) $targetStaff->id, (int) $transferred->assignee_id);

        $log = TicketTransferLog::query()->where('ticket_id', (int) $ticket->id)->sole();
        $this->assertSame((int) $from->id, (int) $log->from_department_id);
        $this->assertSame((int) $to->id, (int) $log->to_department_id);
        $this->assertSame((int) $originalStaff->id, (int) $log->from_assignee_id);
        $this->assertSame((int) $targetStaff->id, (int) $log->to_assignee_id);
        $this->assertSame('需要专项处理', (string) $log->reason);
        $this->assertSame((int) $operator->id, (int) $log->operator_id);
    }

    public function test_transfer_without_assignee_dispatches_by_target_department_mode(): void
    {
        $service = app(TicketService::class);
        $operator = $this->makeAdmin([AdminPermissions::TICKET_MANAGE]);

        $to = TicketDepartment::create([
            'name' => '自动转派部门',
            'auto_assign_mode' => TicketDepartment::AUTO_ASSIGN_LEAST_LOAD,
        ]);
        $staff = $this->makeAdmin([AdminPermissions::TICKET_REPLY]);
        TicketDepartmentStaff::create([
            'department_id' => (int) $to->id,
            'admin_user_id' => (int) $staff->id,
            'is_leader' => 0,
        ]);

        $ticket = $this->makeTicket(null);

        $transferred = $service->transfer($ticket, (int) $to->id, null, '', $operator);

        // 未指定处理人 → 按目标部门模式自动分派到唯一成员
        $this->assertSame((int) $staff->id, (int) $transferred->assignee_id);
        $this->assertSame((int) $to->id, (int) $transferred->department_id);
        $this->assertSame('自动转派部门', (string) $transferred->department);

        // 流水回写自动分派结果
        $log = TicketTransferLog::query()->where('ticket_id', (int) $ticket->id)->sole();
        $this->assertSame((int) $staff->id, (int) $log->to_assignee_id);
        $this->assertNull($log->from_department_id);
    }

    public function test_transfer_rejects_disabled_target_department(): void
    {
        $service = app(TicketService::class);
        $operator = $this->makeAdmin([AdminPermissions::TICKET_MANAGE]);

        $to = TicketDepartment::create(['name' => '停用部门', 'is_enabled' => 0]);
        $ticket = $this->makeTicket(null);

        $this->expectException(BusinessException::class);
        $service->transfer($ticket, (int) $to->id, null, '', $operator);
    }

    public function test_transfer_rejects_assignee_without_ticket_reply_permission(): void
    {
        $service = app(TicketService::class);
        $operator = $this->makeAdmin([AdminPermissions::TICKET_MANAGE]);

        $to = TicketDepartment::create(['name' => '无权限转派部门']);
        $ticket = $this->makeTicket(null);
        $noPermissionStaff = $this->makeAdmin([]);

        $this->expectException(BusinessException::class);
        $service->transfer($ticket, (int) $to->id, (int) $noPermissionStaff->id, '', $operator);
    }

    private function makeTicket(?int $departmentId): Ticket
    {
        return Ticket::create([
            'user_id' => (int) User::query()->create([
                'email' => 'transfer_user_'.uniqid().'@test.local',
                'password' => 'secret123',
                'nickname' => '转派测试用户',
                'status' => 1,
            ])->id,
            'department' => 'transfer_test',
            'department_id' => $departmentId,
            'subject' => '转派测试工单',
            'status' => 0,
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'transfer_role_'.uniqid(),
            'label' => '转派测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'transfer_staff_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '转派测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
