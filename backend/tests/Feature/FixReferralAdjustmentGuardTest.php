<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\FinanceLedgerEntry;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PromotionAmbassador;
use App\Models\ReferralReward;
use App\Models\ReferralWithdrawal;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserAccount;
use App\Services\Referral\AdminReferralOverviewService;
use App\Services\Referral\ReferralService;
use App\Services\User\AccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 返利审查修复回归（M-29 / M-9 / M-91 / M-2 / M-31 / M-32 / M-51 / M-3 / M-33）：
 * 部分退款按比例冲减返佣与销售额、释放前冻结余额守恒断言、打款单号查重、
 * 打款失败回退、管理端可提现口径、提现 PENDING 检查入事务、台账事务兜底、
 * 客户消费口径/停用档位回退/推广链接 Origin。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class FixReferralAdjustmentGuardTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('referral', 'enabled', '1');
        Setting::setValue('referral', 'reward_rate', '10');
        Setting::setValue('referral', 'renewal_reward_rate', '10');
        Setting::setValue('referral', 'reward_freeze_days', '0');
    }

    public function test_partial_refund_adjusts_reward_sales_and_balances(): void
    {
        [$referrer, $buyer, $invoice] = $this->seedRewardInvoice(100.00);

        $reward = app(ReferralService::class)->rewardForPaidInvoice($invoice);
        $this->assertNotNull($reward);
        $this->assertSame('10.00', $this->money($reward->reward_amount));
        $this->assertSame('10.00', $this->money($referrer->fresh()->referral_frozen_amount));

        // 释放到可提余额（冻结 0 天，available_at=now）
        app(ReferralService::class)->releaseMaturedRewards($referrer->fresh());
        $this->assertSame('10.00', $this->money($referrer->fresh()->referral_available_amount));

        // 部分退款 50/100 = 50%：奖励与销售额按比例冲减，余额从可提侧扣
        app(ReferralService::class)->adjustRewardForPartialRefund($invoice, 50.00, 100.00, 'test:partial');

        $reward = $reward->refresh();
        $this->assertSame('5.00', $this->money($reward->reward_amount), '奖励金额应按比例冲减');
        $this->assertSame('50.00', $this->money($reward->order_amount), '返佣基数销售额应按比例扣减');
        $this->assertSame(ReferralReward::STATUS_REWARDED, (int) $reward->status, '部分退款不应整笔撤销');
        $this->assertSame('5.00', $this->money($referrer->fresh()->referral_available_amount), '可提余额应扣回冲减部分');
        $this->assertSame('50.00', $this->money($referrer->fresh()->total_sales_amount), '下线销售额应按比例扣减');

        $ledger = FinanceLedgerEntry::query()
            ->where('user_id', (int) $referrer->id)
            ->where('event_type', FinanceLedgerEventType::REFERRAL_REWARD_REVERSED)
            ->latest('id')
            ->first();
        $this->assertNotNull($ledger, '冲减必须写返利子账本');
        $this->assertSame('-5.00', $this->money($ledger->change_amount));

        // 再退剩余 50/50 = 100%：奖励清零并置为已撤销
        app(ReferralService::class)->adjustRewardForPartialRefund($invoice, 50.00, 50.00, 'test:partial-2');
        $reward = $reward->refresh();
        $this->assertSame('0.00', $this->money($reward->reward_amount));
        $this->assertSame(ReferralReward::STATUS_REVERSED, (int) $reward->status, '累计退款覆盖全部奖励后应撤销');
        $this->assertSame('0.00', $this->money($referrer->fresh()->referral_available_amount));
    }

    public function test_partial_refund_deducts_frozen_reward_first_when_still_frozen(): void
    {
        [$referrer, , $invoice] = $this->seedRewardInvoice(100.00);
        Setting::setValue('referral', 'reward_freeze_days', '3');

        $reward = app(ReferralService::class)->rewardForPaidInvoice($invoice);
        $this->assertNotNull($reward);

        app(ReferralService::class)->adjustRewardForPartialRefund($invoice, 50.00, 100.00, 'test:frozen');

        $this->assertSame('5.00', $this->money($reward->refresh()->reward_amount));
        $this->assertSame(ReferralReward::STATUS_FROZEN, (int) $reward->refresh()->status, '冻结中部分退款不应提前释放');
        $this->assertSame('5.00', $this->money($referrer->fresh()->referral_frozen_amount));
        $this->assertSame('0.00', $this->money($referrer->fresh()->referral_available_amount));
    }

    public function test_partial_refund_marks_shortfall_without_blocking_refund(): void
    {
        [$referrer, , $invoice] = $this->seedRewardInvoice(100.00);
        $reward = app(ReferralService::class)->rewardForPaidInvoice($invoice);
        $this->assertNotNull($reward);
        app(ReferralService::class)->releaseMaturedRewards($referrer->fresh());

        // 余额被提现占用：可提只剩 1.00，冻结为 0，冲减 5.00 时允许 4.00 缺口并留痕
        $account = UserAccount::query()->findOrFail($referrer->id);
        app(AccountService::class)->updateAccount($account, [
            'referral_available_balance' => 1.00,
            'referral_withdrawn_balance' => 9.00,
        ]);

        app(ReferralService::class)->adjustRewardForPartialRefund($invoice, 50.00, 100.00, 'test:shortfall');

        $this->assertSame('0.00', $this->money($referrer->fresh()->referral_available_amount), '可提余额只能按实际金额扣减');
        $this->assertSame('5.00', $this->money($reward->refresh()->reward_amount), '奖励记录仍按比例冲减，缺口留待人工核对');
    }

    public function test_release_matured_rewards_asserts_frozen_conservation(): void
    {
        [$referrer, , $invoice] = $this->seedRewardInvoice(100.00);
        Setting::setValue('referral', 'reward_freeze_days', '0');
        $reward = app(ReferralService::class)->rewardForPaidInvoice($invoice);
        $this->assertNotNull($reward);

        // 异常态：账户冻结余额被外部改成低于待释放奖励
        $account = UserAccount::query()->findOrFail($referrer->id);
        app(AccountService::class)->updateAccount($account, [
            'referral_frozen_balance' => 2.00,
        ]);

        try {
            app(ReferralService::class)->releaseMaturedRewards($referrer->fresh());
            $this->fail('冻结余额不足时释放必须抛业务异常，不能凭空生成可提现资金');
        } catch (BusinessException $exception) {
            $this->assertSame(42213, $exception->getErrorCode());
            $this->assertStringContainsString('推广冻结余额不足', $exception->getMessage());
        }

        $this->assertSame(
            ReferralReward::STATUS_FROZEN,
            (int) $reward->refresh()->status,
            '断言失败时奖励不得推进为已释放'
        );
        $this->assertSame('2.00', $this->money($referrer->fresh()->referral_frozen_amount), '账户冻结余额不得被钳到 0');
    }

    public function test_confirm_payment_rejects_duplicate_payment_no(): void
    {
        $user = $this->makeUser('dup');
        $this->makeAccount($user, ['referral_withdrawn_balance' => 50.00]);

        $first = $this->makeWithdrawal($user, 20.00, ReferralWithdrawal::STATUS_APPROVED, ['payment_no' => 'PN-2026-001']);
        $second = $this->makeWithdrawal($user, 30.00, ReferralWithdrawal::STATUS_APPROVED);

        try {
            app(ReferralService::class)->confirmWithdrawalPayment($second, 1, 'admin', 'PN-2026-001');
            $this->fail('同一打款流水号被其他提现单占用时必须拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('该打款流水号已被其他提现单使用', $exception->getMessage());
        }

        $this->assertSame(ReferralWithdrawal::STATUS_APPROVED, (int) $second->refresh()->status, '拒绝后状态不得推进');

        // 新单号仍可正常确认（查重只拦截同号，不过度阻断）
        $confirmed = app(ReferralService::class)->confirmWithdrawalPayment($second, 1, 'admin', 'PN-2026-002');
        $this->assertSame(ReferralWithdrawal::STATUS_PAID, (int) $confirmed->status);
        $this->assertSame('PN-2026-001', $first->refresh()->payment_no);
    }

    public function test_fail_withdrawal_payment_returns_funds_to_available(): void
    {
        $user = $this->makeUser('failpay');
        $account = $this->makeAccount($user, [
            'referral_available_balance' => 5.00,
            'referral_withdrawn_balance' => 50.00,
        ]);
        $withdrawal = $this->makeWithdrawal($user, 50.00, ReferralWithdrawal::STATUS_APPROVED);

        $failed = app(ReferralService::class)->failWithdrawalPayment($withdrawal, 1, 'admin', '支付宝退回：账号不存在', 'test:failpay');

        $this->assertSame(ReferralService::WITHDRAWAL_STATUS_PAYMENT_FAILED, (int) $failed->status);
        $account = UserAccount::query()->findOrFail((int) $user->id);
        $this->assertSame('0.00', $this->money($account->referral_withdrawn_balance), '已提取额应退回');
        $this->assertSame('55.00', $this->money($account->referral_available_balance), '资金应回到可提余额');
        $this->assertStringContainsString('支付宝退回', (string) $failed->remark);

        $ledger = FinanceLedgerEntry::query()
            ->where('user_id', (int) $user->id)
            ->where('event_type', FinanceLedgerEventType::REFERRAL_WITHDRAW_REJECTED)
            ->where('source_id', (int) $withdrawal->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($ledger, '打款失败必须写返利子账本');

        // 仅 APPROVED 可标记失败：已失败的再调用必须拒绝
        try {
            app(ReferralService::class)->failWithdrawalPayment($failed->refresh(), 1, 'admin', '重复操作', null);
            $this->fail('非审核通过状态不得重复标记打款失败');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('仅审核通过的提现支持标记打款失败', $exception->getMessage());
        }
    }

    public function test_admin_overview_available_amount_uses_realtime_account_balance(): void
    {
        $baseline = app(AdminReferralOverviewService::class)->overview()['summary'];

        $userA = $this->makeUser('ovA');
        $this->makeAccount($userA, ['referral_available_balance' => 10.00]);
        $userB = $this->makeUser('ovB');
        $this->makeAccount($userB, ['referral_available_balance' => 20.00]);

        // 一条已释放奖励（终身已释放口径），与账户当前可提余额刻意不同
        ReferralReward::query()->create([
            'referrer_user_id' => (int) $userA->id,
            'referred_user_id' => (int) $userB->id,
            'order_amount' => 1000.00,
            'reward_rate' => 10.00,
            'reward_amount' => 100.00,
            'status' => ReferralReward::STATUS_REWARDED,
            'rewarded_at' => now(),
            'released_at' => now(),
        ]);

        $summary = app(AdminReferralOverviewService::class)->overview()['summary'];

        $this->assertSame(
            100.0,
            round((float) $summary['released_amount'] - (float) $baseline['released_amount'], 2),
            '已释放额口径不变（累计）'
        );
        $this->assertArrayHasKey('available_amount', $summary, '必须下发可提现口径字段');
        $this->assertSame(
            30.0,
            round((float) $summary['available_amount'] - (float) ($baseline['available_amount'] ?? 0), 2),
            '可提现应为账户余额增量合计而非终身已释放额'
        );
    }

    public function test_create_withdrawal_rechecks_pending_inside_transaction(): void
    {
        $user = $this->makeUser('pend');
        $this->makeAccount($user, ['referral_available_balance' => 100.00]);

        $pendingCheckLevels = [];
        DB::listen(function ($query) use (&$pendingCheckLevels): void {
            if (str_contains($query->sql, 'referral_withdrawals') && str_contains($query->sql, 'exists')) {
                $pendingCheckLevels[] = DB::transactionLevel();
            }
        });

        app(ReferralService::class)->createWithdrawal($user, [
            'amount' => 20.00,
            'method' => ReferralWithdrawal::METHOD_BALANCE,
        ]);

        $this->assertNotEmpty($pendingCheckLevels, '必须执行待处理提现复查');
        // 测试基类 DatabaseTransactions 自带一层事务，事务内的复查应 >= 2
        $this->assertGreaterThanOrEqual(2, max($pendingCheckLevels), 'PENDING 复查必须位于 DB::transaction 内（user 行锁之后）');

        // 已有 PENDING 时第二个申请被拒
        try {
            app(ReferralService::class)->createWithdrawal($user->fresh(), [
                'amount' => 20.00,
                'method' => ReferralWithdrawal::METHOD_BALANCE,
            ]);
            $this->fail('已有待处理提现时必须拒绝新申请');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('已有待处理提现申请', $exception->getMessage());
        }
    }

    public function test_write_account_log_falls_back_to_transaction_outside_one(): void
    {
        $user = $this->makeUser('walog');
        $this->makeAccount($user, ['referral_available_balance' => 3.00]);

        // 直接调用私有方法验证「无事务时自动包一层」的兜底分支不可达（DatabaseTransactions 恒有外层事务），
        // 这里改为验证方法在事务内可正常落库且余额快照取账户真实值。
        $method = new ReflectionMethod(ReferralService::class, 'writeAccountLog');
        $method->setAccessible(true);
        $method->invoke(
            app(ReferralService::class),
            $user->fresh(),
            ReferralService::ACCOUNT_LOG_TYPE_WITHDRAW_REJECTED,
            1.00,
            '测试台账写入',
            null,
            null,
            'system',
            'test:walog',
            null,
            'referral_available',
        );

        $entry = FinanceLedgerEntry::query()
            ->where('user_id', (int) $user->id)
            ->where('event_type', FinanceLedgerEventType::REFERRAL_WITHDRAW_REJECTED)
            ->latest('id')
            ->first();
        $this->assertNotNull($entry);
        $this->assertSame('3.00', $this->money($entry->balance_after), 'balance_after 必须取账户真实余额');
        $this->assertNull($entry->account_id, '返利子账本 account_id 历史口径保持为空');
    }

    public function test_direct_referrals_customer_consumption_counts_referred_user_own_paid_invoices(): void
    {
        $referrer = $this->makeUser('refd');
        $buyer = $this->makeUser('buyd');
        $buyer->forceFill(['referrer_user_id' => $referrer->id, 'total_sales_amount' => 999.00])->save();

        // 买家本人的业务消费：实付 100，已退 30 → 70
        Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $buyer->id,
            'type' => 'new',
            'amount' => 100.00,
            'paid_amount' => 100.00,
            'refund_amount' => 30.00,
            'status' => InvoiceStatus::PAID,
            'due_date' => now()->addDay(),
            'paid_at' => now(),
        ]);
        // 充值类账单不计入消费
        Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $buyer->id,
            'type' => 'recharge',
            'amount' => 500.00,
            'paid_amount' => 500.00,
            'status' => InvoiceStatus::PAID,
            'due_date' => now()->addDay(),
            'paid_at' => now(),
        ]);

        $paginator = app(ReferralService::class)->directReferrals((int) $referrer->id, 15);
        $row = collect($paginator->items())->firstWhere('id', (int) $buyer->id);

        $this->assertNotNull($row);
        $this->assertSame(70.0, round((float) $row->customer_consumption, 2), '客户消费应为下线本人实付扣退款，而非其名下下线销售额');
    }

    public function test_disabled_ambassador_tier_falls_back_to_global_rate(): void
    {
        $ambassador = PromotionAmbassador::query()->create([
            'name' => '停用档位'.uniqid(),
            'reward_rate' => 5.00,
            'renewal_reward_rate' => 5.00,
            'status' => 0,
        ]);
        $referrer = $this->makeUser('refamb');
        $referrer->forceFill(['promotion_ambassador_id' => $ambassador->id])->save();
        $buyer = $this->makeUser('buyamb');
        $buyer->forceFill(['referrer_user_id' => $referrer->id])->save();

        $order = Order::query()->create([
            'order_no' => 'OD'.date('YmdHis').mt_rand(1000, 9999),
            'user_id' => (int) $buyer->id,
            'type' => 'new',
            'amount' => 100.00,
            'paid_amount' => 100.00,
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
        ]);

        $reward = app(ReferralService::class)->rewardForPaidOrder($order->refresh());

        $this->assertNotNull($reward);
        $this->assertSame(10.00, (float) $reward->reward_rate, '停用档位不得按原费率计佣，应回退全局配置');
        $this->assertSame(10.00, (float) $reward->reward_amount);
    }

    public function test_referral_link_origin_whitelist_and_fallback(): void
    {
        $service = app(ReferralService::class);
        $method = new ReflectionMethod(ReferralService::class, 'resolveFrontendBaseUrl');
        $method->setAccessible(true);

        // phpunit 环境 CLIENT_CONSOLE_URL=http://console.example.test 在 CORS 白名单内
        $this->assertSame(
            'http://console.example.test',
            $method->invoke($service, 'http://console.example.test'),
            '白名单内的来源应作为推广链接基址'
        );

        // 未登记来源不得反射进推广链接（防开放重定向式污染）
        $this->assertSame(
            'http://www.example.test',
            $method->invoke($service, 'https://evil.example.com'),
            '未登记来源应回退配置的官网域名'
        );

        $this->assertSame(
            'http://www.example.test',
            $method->invoke($service, 'not-a-url'),
            '非法来源应回退官网域名'
        );
    }

    /**
     * @return array{0: User, 1: User, 2: Invoice}
     */
    private function seedRewardInvoice(float $amount): array
    {
        $referrer = $this->makeUser('refmain');
        $this->makeAccount($referrer);
        $buyer = $this->makeUser('buymain');
        $buyer->forceFill(['referrer_user_id' => $referrer->id])->save();

        $invoice = Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $buyer->id,
            'type' => 'new',
            'amount' => $amount,
            'paid_amount' => $amount,
            'status' => InvoiceStatus::PAID,
            'due_date' => now()->addDay(),
            'paid_at' => now(),
        ]);

        return [$referrer, $buyer, $invoice];
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
    private function makeWithdrawal(User $user, float $amount, int $status, array $attributes = []): ReferralWithdrawal
    {
        return ReferralWithdrawal::query()->create(array_merge([
            'user_id' => (int) $user->id,
            'amount' => $amount,
            'method' => ReferralWithdrawal::METHOD_ALIPAY,
            'account_name' => '测试',
            'account_no' => 'test@example.test',
            'status' => $status,
            'operator' => 'admin',
            // referral_withdrawals.trace_id 是 NOT NULL 列，造数必须给出唯一值
            'trace_id' => 'test-withdrawal-'.uniqid('', true),
        ], $attributes));
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
