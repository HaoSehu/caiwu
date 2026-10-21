<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Models\AdminUser;
use App\Models\FinanceAccount;
use App\Models\FinanceFundingLot;
use App\Models\FinanceLedgerEntry;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAccount;
use App\Services\Finance\Core\FinanceAccountService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 管理员建用户：初始余额必须走财务内核入账。
 *
 * 回归的是两条链路断裂：
 * 1. UserService::create 恒传 cash_balance 给 AccountService::updateAccount，而该方法
 *    对 cash_balance 直接抛 InvalidArgumentException —— 建用户必失败（P0-1）；
 * 2. 即便删掉该键，初始余额也没有落到 finance_accounts 真源。
 */
class AdminCreateUserInitialBalanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_create_user_with_initial_balance_writes_ledger_lot_and_hot_standby(): void
    {
        $this->actingAsAdmin([AdminPermissions::USER_MANAGE]);

        $response = $this->postJson('/api/v2/admin/users', [
            'email' => 'init-balance-'.uniqid().'@example.test',
            'password' => 'secret123',
            'phone' => '138'.substr((string) random_int(10000000, 99999999), 0, 8),
            'nickname' => '初始余额用户',
            'balance' => '100.00',
            'credit_limit' => '50.00',
        ]);

        $response->assertOk();

        // 资源字段为驼峰（AdminUserListItemResource），按 id 回查最稳
        $user = User::query()->findOrFail((int) $response->json('data.id'));

        // 余额真源：账户 == 批次剩余合计 == 台账净额
        $account = FinanceAccount::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('100.00', $account->available_amount);

        $entry = FinanceLedgerEntry::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(FinanceLedgerEventType::INITIAL_BALANCE, (string) $entry->event_type);
        $this->assertSame('100.00', $entry->change_amount);
        $this->assertSame('100.00', $entry->balance_after);

        $lot = FinanceFundingLot::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('100.00', $lot->remaining_amount);

        // 切换期热备列必须同步，否则回滚旧内核会读到 0
        $standby = UserAccount::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('100.00', $standby->cash_balance);
        $this->assertSame('50.00', $standby->credit_limit);

        // 读模型：用户余额访问器回源内核
        $this->assertSame('100.00', $user->fresh()->balance);
    }

    public function test_create_user_without_balance_keeps_zero_and_no_ledger(): void
    {
        $this->actingAsAdmin([AdminPermissions::USER_MANAGE]);

        $response = $this->postJson('/api/v2/admin/users', [
            'email' => 'init-zero-'.uniqid().'@example.test',
            'password' => 'secret123',
            'phone' => '139'.substr((string) random_int(10000000, 99999999), 0, 8),
        ]);

        $response->assertOk();

        $user = User::query()->findOrFail((int) $response->json('data.id'));

        $this->assertSame(0, FinanceLedgerEntry::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, FinanceFundingLot::query()->where('user_id', $user->id)->count());
        $this->assertSame('0.00', (string) app(FinanceAccountService::class)->accountFor((int) $user->id)->available_amount);
    }

    public function test_negative_initial_balance_is_rejected_by_validation(): void
    {
        $this->actingAsAdmin([AdminPermissions::USER_MANAGE]);

        $this->postJson('/api/v2/admin/users', [
            'email' => 'init-negative-'.uniqid().'@example.test',
            'password' => 'secret123',
            'phone' => '137'.substr((string) random_int(10000000, 99999999), 0, 8),
            'balance' => '-1',
        ])->assertStatus(422);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function actingAsAdmin(array $permissions): void
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '建用户初始余额测试角色',
            'permissions' => $permissions,
        ]);

        $admin = AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '建用户测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);

        Sanctum::actingAs($admin);
    }
}
