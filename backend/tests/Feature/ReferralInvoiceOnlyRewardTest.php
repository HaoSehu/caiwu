<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Models\FinanceLedgerEntry;
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

    public function test_release_writes_frozen_offset_and_ledger_matches_account_balance(): void
    {
        // 冻结→释放是成对变动：两条奖励都释放后，退利子账本（finance_ledger_entries
        // 每个 account_type 的最新 balance_after）必须等于 user_accounts 实际余额，
        // 否则 reconcile:account-balance 会报存量漂移（实测 user 314 referral_frozen 2.75 vs 0.00）。
        Setting::setValue('referral', 'reward_rate', 10);
        Setting::setValue('referral', 'reward_freeze_days', 0);

        $referrer = $this->makeUser('relref');
        $this->makeAccount($referrer->id);
        $buyer = $this->makeUser('relbuy');
        $buyer->forceFill(['referrer_user_id' => $referrer->id])->save();

        $first = app(ReferralService::class)->rewardForPaidInvoice($this->makeInvoice($buyer));
        $second = app(ReferralService::class)->rewardForPaidInvoice($this->makeInvoice($buyer));
        $this->assertNotNull($first);
        $this->assertNotNull($second);

        // 两条都进入冻结期：冻结余额=两条奖励合计，冻结台账最新快照必须等于该值
        $frozen = round((float) $referrer->refresh()->referral_frozen_amount, 2);
        $this->assertGreaterThan(0.00, $frozen);
        $this->assertSame(
            number_format($frozen, 2, '.', ''),
            $this->latestLedgerBalance((int) $referrer->id, 'referral_frozen'),
            '冻结时台账 balance_after 必须等于账户冻结余额'
        );

        $lastLedgerIdBeforeRelease = (int) FinanceLedgerEntry::query()->where('user_id', $referrer->id)->max('id');

        $released = app(ReferralService::class)->releaseMaturedRewards($referrer->refresh());
        $this->assertSame(2, $released, '两条到期奖励都应释放');

        $account = UserAccount::query()->findOrFail($referrer->id);
        $this->assertSame('0.00', number_format((float) $account->referral_frozen_balance, 2, '.', ''), '释放后冻结余额应归零');
        $this->assertSame(number_format($frozen, 2, '.', ''), number_format((float) $account->referral_available_balance, 2, '.', ''));

        // 释放必须补写冻结侧冲减行，最新快照归零，否则与账户余额 0.00 漂移
        $this->assertSame(
            '0.00',
            $this->latestLedgerBalance((int) $referrer->id, 'referral_frozen'),
            '释放后必须写冻结侧冲减台账，最新快照应归零'
        );
        $this->assertSame(
            number_format($frozen, 2, '.', ''),
            $this->latestLedgerBalance((int) $referrer->id, 'referral_available'),
            '可用台账最新快照必须等于账户可提余额'
        );

        // 每条奖励各写一条冻结侧冲减行（金额取负），合计必须等于释放总额，便于对账审计
        $offsets = FinanceLedgerEntry::query()
            ->where('user_id', $referrer->id)
            ->where('account_type', 'referral_frozen')
            ->where('id', '>', $lastLedgerIdBeforeRelease)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $offsets, '两条奖励释放应各写一条冻结冲减行');
        $this->assertSame(
            '-'.number_format($frozen, 2, '.', ''),
            number_format((float) $offsets->sum(fn (FinanceLedgerEntry $entry) => (float) $entry->change_amount), 2, '.', '')
        );
        foreach ($offsets as $offset) {
            $this->assertStringContainsString('release', (string) $offset->remark);
        }
    }

    private function latestLedgerBalance(int $userId, string $accountType): string
    {
        $value = FinanceLedgerEntry::query()
            ->where('user_id', $userId)
            ->where('account_type', $accountType)
            ->orderByDesc('id')
            ->value('balance_after');

        return number_format((float) $value, 2, '.', '');
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
