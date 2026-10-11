<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\AdminUser;
use App\Models\Role;
use App\Models\SalesAttributionLog;
use App\Models\User;
use App\Services\SalesCommission\SalesCommissionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M5 客户归属（sales_owner）管理行为快照。
 *
 * 核心不变式：
 * - 设置归属写 users.sales_owner_admin_id + sales_owner_assigned_at；
 * - 每次实际变更写一条 sales_attribution_logs（from/to/operator/reason）；
 * - 同一归属幂等跳过（不产生新日志、不刷新时间）；
 * - 归属销售必须存在于 admin_users（否则拒绝）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class SalesAttributionTest extends TestCase
{
    use DatabaseTransactions;

    private SalesCommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SalesCommissionService::class);
    }

    public function test_assign_writes_user_field_and_attribution_log(): void
    {
        $user = $this->makeUser();
        $sales = $this->makeAdmin();

        $changed = $this->service->setSalesOwner($user, (int) $sales->id, '新客分配', 9);

        $this->assertTrue($changed);

        $user->refresh();
        $this->assertSame((int) $sales->id, (int) $user->sales_owner_admin_id);
        $this->assertNotNull($user->sales_owner_assigned_at);

        $log = SalesAttributionLog::query()->where('user_id', (int) $user->id)->latest('id')->firstOrFail();
        $this->assertNull($log->from_admin_id);
        $this->assertSame((int) $sales->id, (int) $log->to_admin_id);
        $this->assertSame(9, (int) $log->operator_id);
        $this->assertSame('新客分配', (string) $log->reason);
    }

    public function test_same_owner_assignment_is_idempotent(): void
    {
        $user = $this->makeUser();
        $sales = $this->makeAdmin();

        $this->service->setSalesOwner($user, (int) $sales->id, '首次分配', 9);
        $assignedAt = $user->refresh()->sales_owner_assigned_at;

        $changed = $this->service->setSalesOwner($user, (int) $sales->id, '重复分配', 9);

        $this->assertFalse($changed);
        $this->assertSame(1, SalesAttributionLog::query()->where('user_id', (int) $user->id)->count());
        $user->refresh();
        $this->assertSame(
            $assignedAt?->format('Y-m-d H:i:s'),
            $user->sales_owner_assigned_at?->format('Y-m-d H:i:s'),
        );
    }

    public function test_change_owner_writes_from_to_log(): void
    {
        $user = $this->makeUser();
        $salesA = $this->makeAdmin('a');
        $salesB = $this->makeAdmin('b');

        $this->service->setSalesOwner($user, (int) $salesA->id, '首次', 9);
        $this->service->setSalesOwner($user, (int) $salesB->id, '转保护航', 9);

        $user->refresh();
        $this->assertSame((int) $salesB->id, (int) $user->sales_owner_admin_id);

        $log = SalesAttributionLog::query()
            ->where('user_id', (int) $user->id)
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame((int) $salesA->id, (int) $log->from_admin_id);
        $this->assertSame((int) $salesB->id, (int) $log->to_admin_id);
        $this->assertSame('转保护航', (string) $log->reason);
        $this->assertSame(2, SalesAttributionLog::query()->where('user_id', (int) $user->id)->count());
    }

    public function test_clear_owner_writes_log_with_null_to(): void
    {
        $user = $this->makeUser();
        $sales = $this->makeAdmin();

        $this->service->setSalesOwner($user, (int) $sales->id, '首次', 9);
        $this->service->setSalesOwner($user, null, '客户主动解绑', 9);

        $user->refresh();
        $this->assertNull($user->sales_owner_admin_id);

        $log = SalesAttributionLog::query()
            ->where('user_id', (int) $user->id)
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame((int) $sales->id, (int) $log->from_admin_id);
        $this->assertNull($log->to_admin_id);
    }

    public function test_nonexistent_admin_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('不存在');

        $this->service->setSalesOwner($user, PHP_INT_MAX, '无效归属', 9);
    }

    private function makeUser(string $prefix = 'm5attr'): User
    {
        return User::query()->create([
            'email' => $prefix.'-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $prefix.'-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeAdmin(string $prefix = 'm5sales'): AdminUser
    {
        // admin_users.role_id NOT NULL 且 FK→roles，必须先造角色父行。
        $role = Role::query()->create([
            'name' => $prefix.'-role-'.uniqid(),
            'permissions' => [],
        ]);

        return AdminUser::query()->create([
            'username' => $prefix.'-'.uniqid(),
            'password' => 'x',
            'role_id' => (int) $role->id,
            'status' => 1,
        ]);
    }
}
