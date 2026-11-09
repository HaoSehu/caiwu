<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\ReferralWithdrawal;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAccount;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 打款失败入口的 HTTP 级回归：审批通过后线下打款失败，管理员必须能通过
 * 管理端接口把资金从「已提取」退回「可提现」，否则资金永久卡死。
 */
class WithdrawalPaymentFailureRouteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_mark_approved_withdrawal_as_payment_failed_via_http(): void
    {
        $user = $this->makeUser();
        $this->makeAccount($user, [
            'referral_available_balance' => 5.00,
            'referral_withdrawn_balance' => 50.00,
        ]);
        $withdrawal = $this->makeWithdrawal($user, 50.00);
        $this->actingAsAdmin();

        $this->postJson('/api/v2/admin/referral-withdrawals/'.$withdrawal->id.'/payment-failures', [
            'reason' => '支付宝退回：账号不存在',
        ])->assertOk();

        $withdrawal->refresh();
        $this->assertSame(ReferralWithdrawal::STATUS_PAYMENT_FAILED, (int) $withdrawal->status);
        $this->assertStringContainsString('账号不存在', (string) $withdrawal->remark);

        $account = UserAccount::query()->findOrFail((int) $user->id);
        $this->assertSame('0.00', $this->money($account->referral_withdrawn_balance));
        $this->assertSame('55.00', $this->money($account->referral_available_balance));
    }

    public function test_payment_failure_requires_reason(): void
    {
        $user = $this->makeUser();
        $this->makeAccount($user, ['referral_withdrawn_balance' => 50.00]);
        $withdrawal = $this->makeWithdrawal($user, 50.00);
        $this->actingAsAdmin();

        $this->postJson('/api/v2/admin/referral-withdrawals/'.$withdrawal->id.'/payment-failures', [])
            ->assertStatus(422);

        $this->assertSame(ReferralWithdrawal::STATUS_APPROVED, (int) $withdrawal->refresh()->status);
    }

    private function actingAsAdmin(): void
    {
        $role = Role::query()->create([
            'name' => 'withdraw_fail_role_'.uniqid(),
            'label' => '打款失败测试角色',
            'permissions' => [AdminPermissions::ALL],
        ]);

        $admin = AdminUser::query()->create([
            'username' => 'withdraw_fail_admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '打款失败测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);

        Sanctum::actingAs($admin);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'withdraw-fail-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'withdraw-fail-tester',
            'total_sales_amount' => 0,
        ]);
    }

    /**
     * @param  array<string, float|string>  $attributes
     */
    private function makeAccount(User $user, array $attributes = []): UserAccount
    {
        return UserAccount::query()->create(array_merge([
            'user_id' => (int) $user->id,
            'referral_frozen_balance' => 0.00,
            'referral_available_balance' => 0.00,
            'referral_pending_withdrawal_balance' => 0.00,
            'referral_withdrawn_balance' => 0.00,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeWithdrawal(User $user, float $amount, array $attributes = []): ReferralWithdrawal
    {
        return ReferralWithdrawal::query()->create(array_merge([
            'user_id' => (int) $user->id,
            'amount' => $amount,
            'method' => ReferralWithdrawal::METHOD_ALIPAY,
            'account_name' => '测试',
            'account_no' => 'test@example.test',
            'status' => ReferralWithdrawal::STATUS_APPROVED,
            'operator' => 'admin',
            'trace_id' => 'test-withdrawal-fail-'.uniqid('', true),
        ], $attributes));
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
