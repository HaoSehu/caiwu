<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FinanceAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * M-73 回归：finance:rebuild 复制台账时必须遵守「返利子账户 account_id 为空」的表契约。
 *
 * 缺陷背景：copyLedger 曾对每一行（含 account_type=referral_*）都写
 * account_id=$accountMap[$userId]，把返利流水的 account_id 回填成现金账户 id；
 * 与新写入口径（ReferralService::writeAccountLog 显式 account_id=null）分裂，
 * 任何按 account_id 聚合/导出的代码都会把返利流水混入现金账户视图。
 */
class FixRebuildFinanceLedgerAccountTypeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // --fresh 是资金红线唯一违例路径，测试内按演练环境开启。
        config(['finance.rebuild.allow_fresh' => true]);
    }

    #[Test]
    public function rebuild_keeps_referral_rows_account_id_null_and_cash_rows_linked(): void
    {
        $user = $this->makeUser();

        $cashRowId = $this->makeLedgerRow($user, 'recharge', '100.00', '100.00', 'payment', 1, 'cash');
        $referralRowId = $this->makeLedgerRow($user, 'reward_frozen', '5.00', '5.00', 'referral', 2, 'referral_frozen');

        $exit = Artisan::call('finance:rebuild', ['--execute' => true, '--fresh' => true]);
        $this->assertSame(0, $exit, Artisan::output());

        $account = FinanceAccount::query()->where('user_id', $user->id)->firstOrFail();

        $cashEntry = DB::table('finance_ledger_entries')->where('id', $cashRowId)->first();
        $this->assertNotNull($cashEntry);
        $this->assertSame((int) $account->id, (int) $cashEntry->account_id, '现金台账行必须挂到现金账户');

        $referralEntry = DB::table('finance_ledger_entries')->where('id', $referralRowId)->first();
        $this->assertNotNull($referralEntry);
        $this->assertNull(
            $referralEntry->account_id,
            '返利子账户台账行的 account_id 必须为空（表契约），不得回填成现金账户 id（M-73）',
        );
        $this->assertSame('referral_frozen', $referralEntry->account_type, '返利行的 account_type 应原样保留');
    }

    private function makeUser(): User
    {
        // 不预置 user_accounts 行：copyAccounts 会按现金台账合计为无账户用户建户，
        // 既覆盖该分支，也避免用例依赖热备列 cash_balance（T+7 后不存在）。
        return User::query()->create([
            'email' => 'rebuild-account-id-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'rebuild-account-id-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeLedgerRow(
        User $user,
        string $eventType,
        string $change,
        string $balanceAfter,
        string $sourceType,
        int $sourceId,
        string $accountType,
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
            'remark' => 'M-73 rebuild fixture',
            'operator' => null,
            'trace_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
