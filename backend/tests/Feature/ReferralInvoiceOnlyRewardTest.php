<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\ReferralReward;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserAccount;
use App\Services\Referral\ReferralService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 推荐奖励 order_id 写路径：referral_rewards.order_id 已迁移为可空后，
 * 无订单账单（invoice 路径）的奖励必须正常入账且 order_id 落 NULL；
 * 订单路径仍以 order_id 落库（唯一索引对 NULL 不去重，订单侧约束不变）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class ReferralInvoiceOnlyRewardTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // 测试库种子数据可能关闭推荐功能（referral.enabled=0），奖励写入用例显式开启
        Setting::setValue('referral', 'enabled', 1);
    }

    public function test_invoice_only_reward_creates_record_with_null_order_id(): void
    {
        $referrer = $this->makeUser('invref');
        $this->makeAccount($referrer->id);
        $buyer = $this->makeUser('invbuy');
        $buyer->forceFill(['referrer_user_id' => $referrer->id])->save();
        $invoice = $this->makeInvoice($buyer);

        $reward = app(ReferralService::class)->rewardForPaidInvoice($invoice);

        $this->assertNotNull($reward, '无订单账单的推荐奖励应正常入账');
        $this->assertNull($reward->refresh()->order_id, 'invoice 路径的奖励 order_id 应落 NULL');
        $this->assertSame((int) $invoice->id, (int) $reward->invoice_id);
        $this->assertSame(ReferralReward::STATUS_FROZEN, (int) $reward->status);
        $this->assertGreaterThan(0.00, (float) $reward->reward_amount);
        $this->assertGreaterThan(0.00, (float) $referrer->refresh()->referral_frozen_amount);
    }

    public function test_order_reward_still_records_order_id_with_null_invoice_id(): void
    {
        $referrer = $this->makeUser('ordref');
        $this->makeAccount($referrer->id);
        $buyer = $this->makeUser('ordbuy');
        $buyer->forceFill(['referrer_user_id' => $referrer->id])->save();
        $order = $this->makeOrder($buyer);

        $reward = app(ReferralService::class)->rewardForPaidOrder($order);

        $this->assertNotNull($reward);
        $this->assertSame((int) $order->id, (int) $reward->refresh()->order_id);
        $this->assertNull($reward->invoice_id, 'order 路径的奖励 invoice_id 应为 NULL');
        $this->assertSame(ReferralReward::STATUS_FROZEN, (int) $reward->status);
    }

    public function test_order_reward_base_amount_reads_invoice_not_order_snapshot(): void
    {
        // 固定费率 10%，使奖励金额可精确反推基数（80 → 8.00；若误用订单快照 100 → 10.00）
        Setting::setValue('referral', 'reward_rate', 10);

        $referrer = $this->makeUser('baseinf');
        $this->makeAccount($referrer->id);
        $buyer = $this->makeUser('basebuy');
        $buyer->forceFill(['referrer_user_id' => $referrer->id])->save();

        // 订单金额列是创建时快照，账单才是资金真源：订单快照 100 / 账单实收 80 时基数必须取账单
        $order = $this->makeOrder($buyer, 100.00);
        Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(1000, 9999),
            'user_id' => $buyer->id,
            'order_id' => $order->id,
            'type' => 'new',
            'amount' => 80.00,
            'paid_amount' => 80.00,
            'status' => InvoiceStatus::PAID,
            'due_date' => now()->addDays(7),
            'paid_at' => now(),
        ]);

        $reward = app(ReferralService::class)->rewardForPaidOrder($order->fresh(['user', 'product', 'invoice']));

        $this->assertNotNull($reward);
        $this->assertSame('8.00', number_format((float) $reward->refresh()->reward_amount, 2, '.', ''), '奖励基数必须读账单实收而非订单快照');
    }

    private function makeUser(string $prefix): User
    {
        return User::query()->create([
            'email' => $prefix.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $prefix.'-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeAccount(int $userId): UserAccount
    {
        return UserAccount::query()->create([
            'user_id' => $userId,
            'referral_frozen_balance' => 0.00,
            'referral_available_balance' => 0.00,
        ]);
    }

    private function makeInvoice(User $buyer): Invoice
    {
        // 无订单账单：order_id 不设值（管理员开服建账单不建订单的场景）
        return Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(1000, 9999),
            'user_id' => $buyer->id,
            'type' => 'new',
            'amount' => 100.00,
            'paid_amount' => 100.00,
            'status' => InvoiceStatus::PAID,
            'due_date' => now()->addDays(7),
            'paid_at' => now(),
        ]);
    }

    private function makeOrder(User $buyer, float $amount = 100.00): Order
    {
        return Order::query()->create([
            'order_no' => 'RR'.date('YmdHis').mt_rand(1000, 9999),
            'user_id' => $buyer->id,
            'type' => 'new',
            'amount' => $amount,
            'paid_amount' => $amount,
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
        ]);
    }
}
