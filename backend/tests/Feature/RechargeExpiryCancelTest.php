<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 充值支付单过期取消的窗口语义回归：
 * 未过期的 PENDING 充值单不得被状态轮询取消（修复前判断反转，
 * 用户刚生成支付码就被首轮轮询置为已取消）；
 * 已过期的支付单必须能被单笔与批量两条清理路径正常取消。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class RechargeExpiryCancelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pending_recharge_within_window_is_not_cancelled_by_poll(): void
    {
        $payment = $this->makePendingRechargePayment();

        $result = app(PaymentService::class)->cancelExpiredPendingRecharge($payment, [
            'reason' => 'payment_window_expired',
            'actor_name' => 'recharge-status-poll',
        ]);

        $this->assertSame(PaymentStatus::PENDING, (int) $result->status);
    }

    public function test_expired_pending_recharge_is_cancelled_by_single_path(): void
    {
        $payment = $this->makePendingRechargePayment();
        $payment->forceFill([
            'created_at' => now()->subSeconds(CheckoutSecurityService::paymentSessionTtlSeconds() + 60),
        ])->save();

        $result = app(PaymentService::class)->cancelExpiredPendingRecharge($payment, [
            'reason' => 'payment_window_expired',
            'actor_name' => 'recharge-status-poll',
        ]);

        $this->assertSame(PaymentStatus::CANCELLED, (int) $result->status);
    }

    public function test_expired_pending_recharge_is_cancelled_by_batch_path(): void
    {
        $payment = $this->makePendingRechargePayment();
        $payment->forceFill([
            'created_at' => now()->subSeconds(CheckoutSecurityService::paymentSessionTtlSeconds() + 60),
        ])->save();

        $count = app(PaymentService::class)->cancelExpiredPendingRechargesForUser((int) $payment->user_id, [
            'reason' => 'payment_window_expired',
            'actor_name' => 'recharge-cleanup',
        ]);

        $this->assertSame(1, $count);
        $this->assertSame(PaymentStatus::CANCELLED, (int) $payment->refresh()->status);
    }

    private function makePendingRechargePayment(): Payment
    {
        $user = User::query()->create([
            'email' => 'recharge-expiry-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'recharge-expiry-tester',
            'total_sales_amount' => 0,
        ]);

        // 充值支付单语义：invoice_id 为空（充值账单在到账时才创建），状态 PENDING。
        return Payment::query()->create([
            'payment_no' => 'PYRECH'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'invoice_id' => null,
            'gateway' => 'alipay',
            'amount' => 50.00,
            'status' => PaymentStatus::PENDING,
            'callback_raw' => ['source' => 'alipay_precreate_recharge'],
        ]);
    }
}
