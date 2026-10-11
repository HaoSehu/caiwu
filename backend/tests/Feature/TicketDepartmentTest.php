<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\AdminUser;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketDepartmentStaff;
use App\Models\User;
use App\Services\Ticket\TicketDepartmentService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M3 工单部门体系：
 * 1. 部门 CRUD（唯一性校验/软删）；
 * 2. 成员全量设置（覆盖/子集 leader）；
 * 3. 存量回填幂等（直接调 Service 回填方法，不跑迁移）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class TicketDepartmentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_department_crud_via_admin_api(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::TICKET_MANAGE]));

        // 创建
        $created = $this->postJson('/api/v2/admin/ticket-departments', [
            'name' => '测试部门A',
            'description' => '用于 CRUD 回归',
            'auto_assign_mode' => TicketDepartment::AUTO_ASSIGN_ROUND_ROBIN,
            'sort_order' => 5,
        ])->assertOk()->assertJsonPath('data.department.name', '测试部门A');

        $departmentId = (int) $created->json('data.department.id');

        // 重名拒绝
        $this->postJson('/api/v2/admin/ticket-departments', ['name' => '测试部门A'])
            ->assertStatus(422);

        // 更新
        $this->putJson('/api/v2/admin/ticket-departments/'.$departmentId, [
            'name' => '测试部门A2',
            'auto_assign_mode' => TicketDepartment::AUTO_ASSIGN_LEAST_LOAD,
            'is_enabled' => false,
        ])->assertOk()->assertJsonPath('data.department.auto_assign_mode', TicketDepartment::AUTO_ASSIGN_LEAST_LOAD)
            ->assertJsonPath('data.department.is_enabled', 0);

        // 软删
        $this->deleteJson('/api/v2/admin/ticket-departments/'.$departmentId)->assertOk();
        $this->assertSoftDeleted('ticket_departments', ['id' => $departmentId]);
    }

    public function test_department_list_is_open_to_ticket_reply_for_transfer_and_preset_usage(): void
    {
        // 2026-10-11 审查裁决：部门只读列表对 ticket.reply 开放（转派对话框/预设回复侧栏
        // 需要，manage 蕴含 reply）；CRUD 与成员设置仍要求 ticket.manage（其余用例覆盖）。
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::TICKET_REPLY]));

        $this->getJson('/api/v2/admin/ticket-departments')->assertOk();
    }

    public function test_staff_full_sync_is_idempotent_and_leader_subset_enforced(): void
    {
        $service = app(TicketDepartmentService::class);
        $department = TicketDepartment::create(['name' => '成员同步部门']);

        $staffA = $this->makeAdmin([]);
        $staffB = $this->makeAdmin([]);
        $staffC = $this->makeAdmin([]);

        // 首轮：A、B 入组，A 为负责人
        $service->syncStaff($department, [(int) $staffA->id, (int) $staffB->id], [(int) $staffA->id]);

        $this->assertSame(2, TicketDepartmentStaff::query()->where('department_id', $department->id)->count());
        $this->assertSame(1, (int) TicketDepartmentStaff::query()
            ->where('department_id', $department->id)
            ->where('admin_user_id', (int) $staffA->id)
            ->value('is_leader'));

        // 全量覆盖：A 移除、C 入组、B 升负责人
        $service->syncStaff($department, [(int) $staffB->id, (int) $staffC->id], [(int) $staffB->id]);

        $memberIds = TicketDepartmentStaff::query()
            ->where('department_id', $department->id)
            ->pluck('admin_user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing([(int) $staffB->id, (int) $staffC->id], $memberIds);

        // 重复执行幂等
        $service->syncStaff($department, [(int) $staffB->id, (int) $staffC->id], [(int) $staffB->id]);
        $this->assertSame(2, TicketDepartmentStaff::query()->where('department_id', $department->id)->count());

        // 无效账号拒绝
        $this->expectException(BusinessException::class);
        $service->syncStaff($department, [999999999]);
    }

    public function test_legacy_department_backfill_is_reentrant(): void
    {
        $user = $this->makeUser();

        Ticket::create(['user_id' => (int) $user->id, 'department' => 'sales', 'subject' => '回填1', 'status' => 0]);
        Ticket::create(['user_id' => (int) $user->id, 'department' => 'sales', 'subject' => '回填2', 'status' => 0]);
        Ticket::create(['user_id' => (int) $user->id, 'department' => 'billing', 'subject' => '回填3', 'status' => 0]);

        $result = TicketDepartmentService::backfillLegacyDepartments();
        $this->assertSame(2, $result['created_departments']);

        $sales = TicketDepartment::query()->where('name', 'sales')->firstOrFail();
        $billing = TicketDepartment::query()->where('name', 'billing')->firstOrFail();

        $this->assertSame(2, Ticket::query()->where('department_id', (int) $sales->id)->count());
        $this->assertSame(1, Ticket::query()->where('department_id', (int) $billing->id)->count());

        // 冗余列保持同步为部门名
        $this->assertSame('sales', (string) Ticket::query()->where('department_id', (int) $sales->id)->firstOrFail()->department);

        // 重跑幂等：不重复建、不重复回填
        $second = TicketDepartmentService::backfillLegacyDepartments();
        $this->assertSame(0, $second['created_departments']);
        $this->assertSame(0, $second['backfilled_tickets']);
        $this->assertSame(0, Ticket::query()->whereNull('department_id')->count());
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions = []): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'dept_role_'.uniqid(),
            'label' => '部门测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'dept_staff_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '部门测试客服',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'dept_user_'.uniqid().'@test.local',
            'password' => 'secret123',
            'nickname' => '部门测试用户',
            'status' => 1,
        ]);
    }
}
