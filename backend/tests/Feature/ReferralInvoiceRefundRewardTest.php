<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\ReferralReward;
use App\Models\User;
use App\Models\UserAccount;
use App\Services\Referral\ReferralService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 账单退款回退推广奖励：奖励以 invoice_id 或关联订单 order_id 落库时均可回退；
 * 已释放且可提余额不足时阻断；重复回退幂等不双扣。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class ReferralInvoiceRefundRewardTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $prefix): User
    {
        return User::query()->create([
            'email' => $prefix.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $prefix.'-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeAccount(int $userId, float $frozen = 0.00, float $available = 0.00): UserAccount
    {
        return UserAccount::query()->create([
            'user_id' => $userId,
            'referral_frozen_balance' => $frozen,
            'referral_available_balance' => $available,
        ]);
    }

    private function makeOrder(User $buyer): Order
    {
        return Order::query()->create([
            'order_no' => 'RR'.date('YmdHis').mt_rand(1000, 9999),
            'user_id' => $buyer->id,
            'type' => 'renew',
            'amount' => 100.00,
            'paid_amount' => 100.00,
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
        ]);
    }

    private function makeInvoice(User $buyer, Order $order): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(1000, 9999),
            'user_id' => $buyer->id,
            'order_id' => $order->id,
            'type' => 'renew',
            'amount' => 100.00,
            'paid_amount' => 100.00,
            'status' => InvoiceStatus::PAID,
            'due_date' => now()->addDays(7),
            'paid_at' => now(),
        ]);
    }

    private function makeReward(User $referrer, User $buyer, Order $order, ?Invoice $invoice, int $status): ReferralReward
    {
        return ReferralReward::query()->create([
            'referrer_user_id' => $referrer->id,
            'referred_user_id' => $buyer->id,
            'order_id' => $order->id,
            'invoice_id' => $invoice?->id,
            'order_amount' => 100.00,
            'reward_rate' => 5.00,
            'reward_amount' => 5.00,
            'status' => $status,
            'available_at' => now()->addDays(4),
            'operator' => 'system',
            'remark' => '测试奖励',
            'rewarded_at' => now(),
        ]);
    }

    public function test_invoice_refund_reverses_frozen_reward(): void
    {
        $referrer = $this->makeUser('rref');
        $this->makeAccount($referrer->id, frozen: 5.00);
        $buyer = $this->makeUser('rbuy');
        $order = $this->makeOrder($buyer);
        $invoice = $this->makeInvoice($buyer, $order);
        $reward = $this->makeReward($referrer, $buyer, $order, $invoice, ReferralReward::STATUS_FROZEN);

        $reversed = app(ReferralService::class)->reverseRewardForRefundedInvoice($invoice, 'refund:test');

        $this->assertNotNull($reversed);
        $this->assertSame(ReferralReward::STATUS_REVERSED, (int) $reversed->refresh()->status);
        $this->assertSame(0.00, (float) $referrer->refresh()->referral_frozen_amount);
        $this->assertSame(0.00, (float) $referrer->refresh()->total_sales_amount);
    }

    public function test_invoice_refund_reverses_reward_recorded_by_order(): void
    {
        $referrer = $this->makeUser('oref');
        $this->makeAccount($referrer->id, frozen: 5.00);
        $buyer = $this->makeUser('obuy');
        $order = $this->makeOrder($buyer);
        $invoice = $this->makeInvoice($buyer, $order);
        // 主路径奖励以 order_id 落库、invoice_id 为空
        $reward = $this->makeReward($referrer, $buyer, $order, null, ReferralReward::STATUS_FROZEN);

        $reversed = app(ReferralService::class)->reverseRewardForRefundedInvoice($invoice);

        $this->assertNotNull($reversed);
        $this->assertSame((int) $reward->id, (int) $reversed->id);
        $this->assertSame(ReferralReward::STATUS_REVERSED, (int) $reversed->refresh()->status);
    }

    public function test_invoice_refund_reverse_is_idempotent(): void
    {
        $referrer = $this->makeUser('iref');
        $this->makeAccount($referrer->id, frozen: 5.00);
        $buyer = $this->makeUser('ibuy');
        $order = $this->makeOrder($buyer);
        $invoice = $this->makeInvoice($buyer, $order);
        $this->makeReward($referrer, $buyer, $order, $invoice, ReferralReward::STATUS_FROZEN);

        $service = app(ReferralService::class);
        $first = $service->reverseRewardForRefundedInvoice($invoice);
        $second = $service->reverseRewardForRefundedInvoice($invoice);

        $this->assertSame((int) $first->id, (int) $second?->id);
        $this->assertSame(0.00, (float) $referrer->refresh()->referral_frozen_amount);
    }

    public function test_invoice_refund_blocked_when_reward_released_and_insufficient(): void
    {
        $referrer = $this->makeUser('bref');
        $this->makeAccount($referrer->id, available: 0.00);
        $buyer = $this->makeUser('bbuy');
        $order = $this->makeOrder($buyer);
        $invoice = $this->makeInvoice($buyer, $order);
        $this->makeReward($referrer, $buyer, $order, $invoice, ReferralReward::STATUS_REWARDED);

        $this->expectException(BusinessException::class);

        app(ReferralService::class)->assertInvoiceRewardRefundable($invoice);
    }

    public function test_invoice_refund_allowed_when_reward_frozen(): void
    {
        $referrer = $this->makeUser('fref');
        $this->makeAccount($referrer->id, frozen: 5.00);
        $buyer = $this->makeUser('fbuy');
        $order = $this->makeOrder($buyer);
        $invoice = $this->makeInvoice($buyer, $order);
        $this->makeReward($referrer, $buyer, $order, $invoice, ReferralReward::STATUS_FROZEN);

        app(ReferralService::class)->assertInvoiceRewardRefundable($invoice);

        $this->expectNotToPerformAssertions();
    }
}
