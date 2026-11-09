<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Models\AdminUser;
use App\Models\Payment;
use App\Models\ReferralWithdrawal;
use App\Models\Role;
use App\Models\User;
use App\Services\User\AccountService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 删除用户资产保护回归（M-94 / P1 AUF-02）：
 *
 * User 是软删，但支付回调与提现审核都用 User::lockForUpdate()->findOrFail()
 * （受 SoftDeletes 全局作用域）。删除前必须拦住「删除后资金/审核链路会永久失败」
 * 的悬空资产：待支付直达充值单、未处理/已通过未打款的提现单、未清零的返利余额。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class FixAdminUserReportsDeleteGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pending_direct_recharge_payment_blocks_delete(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $user = $this->makeUser();

        $this->makePendingRechargePayment($user);

        $this->deleteJson("/api/v2/admin/users/{$user->id}")
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('message', '该用户存在待支付的充值单，请等待支付完成或过期取消后再删除');

        $this->assertNotNull(User::query()->find($user->id));
    }

    public function test_pending_or_approved_withdrawal_blocks_delete(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $user = $this->makeUser();

        $withdrawal = $this->makeWithdrawal($user, ReferralWithdrawal::STATUS_PENDING);

        $this->deleteJson("/api/v2/admin/users/{$user->id}")
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);

        $withdrawal->update(['status' => ReferralWithdrawal::STATUS_APPROVED]);

        $this->deleteJson("/api/v2/admin/users/{$user->id}")
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);

        $this->assertNotNull(User::query()->find($user->id));
    }

    public function test_referral_balance_blocks_delete(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $user = $this->makeUser();

        app(AccountService::class)->updateAccount($user, [
            'referral_available_balance' => '5.00',
        ]);

        $this->deleteJson("/api/v2/admin/users/{$user->id}")
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('message', '该用户仍有返利余额（冻结/可用/提现中），请先清零后再删除');
    }

    public function test_user_can_be_deleted_after_blocking_assets_are_cleared(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $user = $this->makeUser();

        $payment = $this->makePendingRechargePayment($user);
        $withdrawal = $this->makeWithdrawal($user, ReferralWithdrawal::STATUS_PENDING);
        app(AccountService::class)->updateAccount($user, [
            'referral_available_balance' => '3.00',
        ]);

        $payment->update(['status' => PaymentStatus::CANCELLED]);
        $withdrawal->update(['status' => ReferralWithdrawal::STATUS_REJECTED]);
        app(AccountService::class)->updateAccount($user, [
            'referral_available_balance' => '0.00',
        ]);

        // 余额方式审核通过即完结（钱已进现金余额），不应永久阻塞删除；
        // 已打款的支付宝提现同理不属于未结资产。
        $this->makeWithdrawal($user, ReferralWithdrawal::STATUS_APPROVED, ReferralWithdrawal::METHOD_BALANCE);
        $this->makeWithdrawal($user, ReferralWithdrawal::STATUS_PAID, ReferralWithdrawal::METHOD_ALIPAY);

        $this->deleteJson("/api/v2/admin/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertNotNull(User::withTrashed()->find($user->id));
        $this->assertTrue(User::withTrashed()->find($user->id)->trashed());
    }

    private function makePendingRechargePayment(User $user): Payment
    {
        return Payment::query()->create([
            'payment_no' => Payment::generatePaymentNo(),
            'user_id' => $user->id,
            'invoice_id' => null,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => 20.00,
            'currency' => 'CNY',
            'status' => PaymentStatus::PENDING,
        ]);
    }

    private function makeWithdrawal(User $user, int $status, string $method = ReferralWithdrawal::METHOD_ALIPAY): ReferralWithdrawal
    {
        return ReferralWithdrawal::query()->create([
            'user_id' => $user->id,
            'amount' => '10.00',
            'method' => $method,
            'status' => $status,
            'trace_id' => 'withdraw-'.uniqid(),
        ]);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'delete-guard-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'delete-guard-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeAdmin(): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '删除用户保护测试角色',
            'permissions' => [AdminPermissions::USER_MANAGE],
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '删除用户测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
