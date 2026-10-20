<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FinanceAccount;
use App\Models\FinanceFundingLot;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 重建命令：旧台账 → 新账本的回放、校准与缺口表达。
 *
 * 覆盖三类存量形态：
 * - 台账合计小于账户余额（历史虚增余额）→ 追加 calibration 行；
 * - 台账合计大于账户余额（历史虚高支出）→ 负值缺口批次 + 对账差异；
 * - 返利子账户行只复制、不建批次。
 */
class FinanceRebuildCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // --fresh 是资金红线唯一违例路径，需要显式环境开关；测试内按演练环境开启。
        config(['finance.rebuild.allow_fresh' => true]);
    }

    public function test_rebuild_copies_ledger_and_calibrates_balance_mismatch(): void
    {
        $user = $this->makeUser(100.00);
        $creditId = $this->makeLedgerRow($user, 'recharge', '150.00', '150.00', 'payment', 1);
        $this->makeLedgerRow($user, 'invoice_payment', '-70.00', '80.00', 'invoice', 2);

        $exit = Artisan::call('finance:rebuild', ['--execute' => true, '--fresh' => true]);
        $this->assertSame(0, $exit, Artisan::output());

        $account = FinanceAccount::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('100.00', $account->available_amount);

        $entries = DB::table('finance_ledger_entries')->where('user_id', $user->id)->orderBy('id')->get();
        $this->assertCount(3, $entries);
        $this->assertSame('LG'.$creditId, $entries[0]->entry_no);
        $this->assertSame('calibration', $entries[2]->event_type);
        $this->assertSame('20.00', $entries[2]->change_amount);
        $this->assertSame('100.00', $entries[2]->balance_after);

        $lots = FinanceFundingLot::query()->where('account_id', $account->id)->get();
        $this->assertSame(2, $lots->count());
        $this->assertSame(10000, Money::toCents($lots->sum('remaining_amount')));

        $consumption = DB::table('finance_consumptions')->where('user_id', $user->id)->first();
        $this->assertNotNull($consumption);
        $this->assertSame('70.00', $consumption->amount);
    }

    public function test_rebuild_marks_unfunded_debit_as_shortfall_lot_and_diff(): void
    {
        $user = $this->makeUser(-30.00);
        $this->makeLedgerRow($user, 'recharge', '50.00', '50.00', 'payment', 11);
        $debitId = $this->makeLedgerRow($user, 'invoice_payment', '-80.00', '-30.00', 'invoice', 12);

        $exit = Artisan::call('finance:rebuild', ['--execute' => true, '--fresh' => true]);
        $this->assertSame(0, $exit, Artisan::output());

        $account = FinanceAccount::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('-30.00', $account->available_amount);

        $shortfall = FinanceFundingLot::query()
            ->where('account_id', $account->id)
            ->where('data_quality', 'calibration_shortfall')
            ->first();
        $this->assertNotNull($shortfall);
        $this->assertSame('-30.00', $shortfall->amount);

        $this->assertSame(
            1,
            DB::table('finance_reconciliation_diffs')
                ->where('check_type', 'unallocated_debit')
                ->where('scope_type', 'ledger_entry')
                ->where('scope_id', $debitId)
                ->count()
        );
    }

    public function test_rebuild_copies_referral_rows_without_creating_lots(): void
    {
        $user = $this->makeUser(0.00);
        $this->makeLedgerRow($user, 'reward_frozen', '5.00', '5.00', 'referral', 21, 'referral_frozen');

        $exit = Artisan::call('finance:rebuild', ['--execute' => true, '--fresh' => true]);
        $this->assertSame(0, $exit, Artisan::output());

        $this->assertSame(1, DB::table('finance_ledger_entries')->where('user_id', $user->id)->where('account_type', 'referral_frozen')->count());
        $this->assertSame(0, DB::table('finance_funding_lots')->where('user_id', $user->id)->count());
    }

    private function makeUser(float $balance): User
    {
        $user = User::query()->create([
            'email' => 'rebuild-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'rebuild-tester',
            'total_sales_amount' => 0,
        ]);

        DB::table('user_accounts')->insert([
            'user_id' => $user->id,
            'cash_balance' => number_format($balance, 2, '.', ''),
            'credit_limit' => '0.00',
            'referral_frozen_balance' => '0.00',
            'referral_available_balance' => '0.00',
            'referral_pending_withdrawal_balance' => '0.00',
            'referral_withdrawn_balance' => '0.00',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function makeLedgerRow(
        User $user,
        string $eventType,
        string $change,
        string $balanceAfter,
        string $sourceType,
        int $sourceId,
        string $accountType = 'cash',
    ): int {
        return (int) DB::table('account_transactions')->insertGetId([
            'user_id' => $user->id,
            'account_type' => $accountType,
            'event_type' => $eventType,
            'change_amount' => $change,
            'currency' => 'CNY',
            'balance_after' => $balanceAfter,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'origin_type' => $sourceType,
            'origin_id' => $sourceId,
            'remark' => 'rebuild fixture',
            'operator' => null,
            'trace_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_fresh_is_rejected_without_explicit_switch(): void
    {
        config(['finance.rebuild.allow_fresh' => false]);

        $user = $this->makeUser(100.00);
        $this->makeLedgerRow($user, 'recharge', '100.00', '100.00', 'payment', 1);

        $exit = Artisan::call('finance:rebuild', ['--execute' => true, '--fresh' => true]);

        $this->assertSame(1, $exit, '--fresh 未显式开启时必须拒绝执行');
        $this->assertStringContainsString('FINANCE_REBUILD_ALLOW_FRESH', Artisan::output());
    }
}
