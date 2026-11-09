<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Models\User;
use App\Services\Finance\FinanceLedgerQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * M-7 回归：台账查询必须同时兼容 account_type='cash' 与 'cash_balance'。
 *
 * 2026-11-09 归一迁移把历史 'cash_balance' 行改为 'cash'，但迁移执行前后都
 * 必须能读到历史行：列表（paginatorForUser）与汇总（summaryForClient）都要
 * 把两种命名计入现金流水。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class Fix2LedgerAccountTypeCompatTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ledger_list_and_summary_count_both_cash_naming_rows(): void
    {
        $user = $this->makeUser();
        $this->insertLedgerRow($user, 'cash', '20.00');
        $this->insertLedgerRow($user, 'cash_balance', '30.00');

        $service = app(FinanceLedgerQueryService::class);

        // 列表：迁移前后两种命名的历史行都要可读。
        $paginator = $service->paginatorForUser($user, [], 20);
        $accountTypes = collect($paginator->items())
            ->pluck('account_type')
            ->sort()
            ->values()
            ->all();
        $this->assertSame(['cash', 'cash_balance'], $accountTypes);
        $this->assertSame(2, $paginator->total());

        // 汇总：两种命名的入金都要计入总额与充值口径。
        $summary = $service->summaryForClient($user, []);
        $this->assertSame(2, (int) $summary['total_count']);
        $this->assertSame('50.00', (string) $summary['total_in']);
        $this->assertSame('50.00', (string) $summary['recharge_in']);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'ledger-account-type-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'ledger-account-type-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function insertLedgerRow(User $user, string $accountType, string $changeAmount): void
    {
        DB::table('finance_ledger_entries')->insert([
            'entry_no' => 'LGFIX2'.strtoupper(bin2hex(random_bytes(8))),
            'account_id' => null,
            'user_id' => (int) $user->id,
            'account_type' => $accountType,
            'event_type' => FinanceLedgerEventType::RECHARGE,
            'change_amount' => $changeAmount,
            'balance_after' => $changeAmount,
            'currency' => 'CNY',
            'source_type' => 'test_seed',
            'source_id' => null,
            'origin_type' => null,
            'origin_id' => null,
            'operator_type' => 'system',
            'operator_id' => null,
            'operator_name' => 'test',
            'remark' => 'account_type 兼容查询用例',
            'trace_id' => null,
            'created_at' => now(),
        ]);
    }
}
