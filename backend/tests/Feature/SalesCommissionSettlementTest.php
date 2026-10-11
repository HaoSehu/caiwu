<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Exceptions\BusinessException;
use App\Models\AccountTransaction;
use App\Models\AdminUser;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\SalesCommission;
use App\Models\SalesCommissionPlan;
use App\Models\SalesCommissionSettlement;
use App\Models\User;
use App\Models\UserAccount;
use App\Services\SalesCommission\SalesCommissionService;
use App\Services\SalesCommission\SalesCommissionSettlementService;
use App\Services\User\AccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M5 结算批次行为快照（线下发放的管理登记制）。
 *
 * 核心不变式：
 * - 资金红线：create/approve/reject 全程不写 AccountTransaction、
 *   不改 user_accounts.cash_balance——结算发放只是登记事实；
 * - create 聚合该销售期间内 status=0 计提（status=1 + settlement_id + 批次总额/笔数）；
 * - approve：批次 → 已发放，计提逐条 → 已发放 + settled_at；
 * - reject：批次 → 已驳回，计提回 status=0 并解绑批次；
 * - 重叠期校验：同销售存在未闭环（待审核）且期间重叠的批次时拒绝。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class SalesCommissionSettlementTest extends TestCase
{
    use DatabaseTransactions;

    private SalesCommissionSettlementService $service;

    private SalesCommissionService $commissionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SalesCommissionSettlementService::class);
        $this->commissionService = app(SalesCommissionService::class);
    }

    public function test_create_aggregates_pending_commissions_into_batch(): void
    {
        $sales = $this->makeAdmin();
        $user = $this->makeUser();
        $user->forceFill(['sales_owner_admin_id' => $sales->id])->save();

        $commissionA = $this->makePendingCommission($sales, $user, 200.00, 10.00);
        $commissionB = $this->makePendingCommission($sales, $user, 50.00, 10.00);

        $settlement = $this->service->create(
            adminUserId: (int) $sales->id,
            periodStart: now()->subDay()->toDateString(),
            periodEnd: now()->toDateString(),
            operatorId: 1,
        );

        $this->assertSame(SalesCommissionSettlement::STATUS_PENDING, (int) $settlement->status);
        $this->assertSame('25.00', (string) $settlement->total_amount);
        $this->assertSame(2, (int) $settlement->commission_count);

        $commissionA->refresh();
        $commissionB->refresh();
        $this->assertSame(SalesCommission::STATUS_IN_BATCH, (int) $commissionA->status);
        $this->assertSame((int) $settlement->id, (int) $commissionA->settlement_id);
        $this->assertSame(SalesCommission::STATUS_IN_BATCH, (int) $commissionB->status);
    }

    public function test_approve_marks_batch_and_commissions_settled(): void
    {
        $sales = $this->makeAdmin();
        $user = $this->makeUser();
        $user->forceFill(['sales_owner_admin_id' => $sales->id])->save();

        $commission = $this->makePendingCommission($sales, $user, 200.00, 10.00);
        $settlement = $this->service->create((int) $sales->id, now()->subDay()->toDateString(), now()->toDateString(), 1);

        $result = $this->service->approve($settlement, 1);

        $this->assertSame(SalesCommissionSettlement::STATUS_PAID, (int) $result->status);
        $this->assertNotNull($result->settled_at);

        $commission->refresh();
        $this->assertSame(SalesCommission::STATUS_SETTLED, (int) $commission->status);
        $this->assertNotNull($commission->settled_at);
    }

    public function test_reject_returns_commissions_to_pending(): void
    {
        $sales = $this->makeAdmin();
        $user = $this->makeUser();
        $user->forceFill(['sales_owner_admin_id' => $sales->id])->save();

        $commission = $this->makePendingCommission($sales, $user, 200.00, 10.00);
        $settlement = $this->service->create((int) $sales->id, now()->subDay()->toDateString(), now()->toDateString(), 1);

        $result = $this->service->reject($settlement, 1, '金额复核不符');

        $this->assertSame(SalesCommissionSettlement::STATUS_REJECTED, (int) $result->status);

        $commission->refresh();
        $this->assertSame(SalesCommission::STATUS_PENDING, (int) $commission->status);
        $this->assertNull($commission->settlement_id);
    }

    public function test_overlapping_period_with_open_batch_is_rejected(): void
    {
        $sales = $this->makeAdmin();
        $user = $this->makeUser();
        $user->forceFill(['sales_owner_admin_id' => $sales->id])->save();

        $this->makePendingCommission($sales, $user, 200.00, 10.00);
        $this->service->create((int) $sales->id, now()->subDays(10)->toDateString(), now()->toDateString(), 1);

        // 同销售期间重叠的未闭环批次存在 → 拒绝
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('重叠');

        $this->service->create((int) $sales->id, now()->subDays(5)->toDateString(), now()->addDay()->toDateString(), 1);
    }

    public function test_period_without_commissions_is_rejected(): void
    {
        $sales = $this->makeAdmin();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('没有可结算');

        $this->service->create((int) $sales->id, now()->subDay()->toDateString(), now()->toDateString(), 1);
    }

    public function test_settlement_lifecycle_touches_no_money_records(): void
    {
        $sales = $this->makeAdmin();
        $user = $this->makeUser();
        $user->forceFill(['sales_owner_admin_id' => $sales->id])->save();

        $this->makePendingCommission($sales, $user, 200.00, 10.00);

        // 确保资金账户存在，使现金余额断言有真值
        app(AccountService::class)->ensureAccount($user);
        $accountBefore = UserAccount::query()->where('user_id', (int) $user->id)->firstOrFail();
        $cashBefore = (string) $accountBefore->cash_balance;
        $ledgerBefore = AccountTransaction::query()->where('user_id', (int) $user->id)->count();

        $settlement = $this->service->create((int) $sales->id, now()->subDay()->toDateString(), now()->toDateString(), 1);
        $this->service->approve($settlement, 1);

        // 资金红线：台账零变化、现金余额不动
        $this->assertSame($ledgerBefore, AccountTransaction::query()->where('user_id', (int) $user->id)->count());

        $accountAfter = UserAccount::query()->where('user_id', (int) $user->id)->firstOrFail();
        $this->assertSame($cashBefore, (string) $accountAfter->cash_balance);
    }

    public function test_non_pending_batch_cannot_be_processed(): void
    {
        $sales = $this->makeAdmin();
        $user = $this->makeUser();
        $user->forceFill(['sales_owner_admin_id' => $sales->id])->save();

        $this->makePendingCommission($sales, $user, 200.00, 10.00);
        $settlement = $this->service->create((int) $sales->id, now()->subDay()->toDateString(), now()->toDateString(), 1);
        $this->service->approve($settlement, 1);

        // 已发放批次重复审核 / 驳回均拒绝
        $this->expectException(BusinessException::class);
        $this->service->approve($settlement, 1);
    }

    public function test_reversed_commission_decrements_open_batch(): void
    {
        $sales = $this->makeAdmin();
        $user = $this->makeUser();
        $user->forceFill(['sales_owner_admin_id' => $sales->id])->save();

        $invoice = Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => 200.00,
            'paid_amount' => 200.00,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);

        SalesCommissionPlan::query()->create([
            'name' => '冲正测试方案-'.uniqid(),
            'basis' => SalesCommissionPlan::BASIS_PAID_AMOUNT,
            'is_enabled' => 1,
        ]);
        $commission = SalesCommission::query()->create([
            'admin_user_id' => (int) $sales->id,
            'user_id' => (int) $user->id,
            'invoice_id' => (int) $invoice->id,
            'plan_snapshot' => ['rate' => 10.0],
            'base_amount' => '200.00',
            'rate' => '10.00',
            'commission_amount' => '20.00',
            'status' => SalesCommission::STATUS_PENDING,
        ]);

        $settlement = $this->service->create((int) $sales->id, now()->subDay()->toDateString(), now()->toDateString(), 1);

        // 退款冲正：已入批次未发放的计提自动取消并扣减批次登记
        $this->commissionService->markReversed($invoice, '账单退款标记');

        $commission->refresh();
        $this->assertSame(SalesCommission::STATUS_CANCELLED, (int) $commission->status);

        $settlement->refresh();
        $this->assertSame('0.00', (string) $settlement->total_amount);
        $this->assertSame(0, (int) $settlement->commission_count);
    }

    private function makePendingCommission(AdminUser $sales, User $buyer, float $baseAmount, float $rate): SalesCommission
    {
        $invoice = Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $buyer->id,
            'type' => 'new',
            'amount' => $baseAmount,
            'paid_amount' => $baseAmount,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);

        return SalesCommission::query()->create([
            'admin_user_id' => (int) $sales->id,
            'user_id' => (int) $buyer->id,
            'invoice_id' => (int) $invoice->id,
            'plan_snapshot' => ['rate' => $rate],
            'base_amount' => number_format($baseAmount, 2, '.', ''),
            'rate' => number_format($rate, 2, '.', ''),
            'commission_amount' => number_format(round($baseAmount * $rate / 100, 2), 2, '.', ''),
            'status' => SalesCommission::STATUS_PENDING,
        ]);
    }

    private function makeUser(string $prefix = 'm5settle'): User
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
