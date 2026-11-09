<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Models\FinanceLedgerEntry;
use App\Models\User;
use App\Services\Finance\Core\FinanceAccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 审查修复回归（M-52）：FinanceLedgerService::restore 必须把入参
 * $sourceType/$sourceId 并入台账行，否则回补台账 source_type/source_id 落 NULL，
 * 按来源过滤的对账（账单结清断言）看不到这笔回补，账单永久无法结清。
 *
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class FixFinanceLedgerRestoreSourceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_restore_ledger_entry_carries_source_type_and_id(): void
    {
        $user = $this->makeUser();
        $accounts = app(FinanceAccountService::class);
        $accounts->credit((int) $user->id, 10000, FinanceLedgerEventType::RECHARGE, [
            'source_type' => 'payment',
            'source_id' => 11,
        ]);
        $accounts->debit((int) $user->id, 4000, FinanceLedgerEventType::INVOICE_PAYMENT, [
            'source_type' => 'invoice',
            'source_id' => 321,
        ]);

        $restored = $accounts->restore(
            (int) $user->id,
            4000,
            FinanceLedgerEventType::INVOICE_REFUND,
            'invoice',
            321,
            ['remark' => '组合支付取消退回'],
        );

        $entry = FinanceLedgerEntry::query()->findOrFail($restored->entryId);
        $this->assertSame('invoice', (string) $entry->source_type);
        $this->assertSame(321, (int) $entry->source_id);
        // origin 未显式给时由 source 回填，跨域溯源字段不空。
        $this->assertSame('invoice', (string) $entry->origin_type);
        $this->assertSame(321, (int) $entry->origin_id);
    }

    public function test_restore_meta_keys_override_positional_source(): void
    {
        $user = $this->makeUser();
        $accounts = app(FinanceAccountService::class);
        $accounts->credit((int) $user->id, 10000, FinanceLedgerEventType::RECHARGE);
        $accounts->debit((int) $user->id, 4000, FinanceLedgerEventType::INVOICE_PAYMENT, [
            'source_type' => 'invoice',
            'source_id' => 321,
        ]);

        $restored = $accounts->restore(
            (int) $user->id,
            4000,
            FinanceLedgerEventType::INVOICE_REFUND,
            'invoice',
            321,
            ['source_type' => 'invoice_override', 'source_id' => 999],
        );

        $entry = FinanceLedgerEntry::query()->findOrFail($restored->entryId);
        $this->assertSame('invoice_override', (string) $entry->source_type);
        $this->assertSame(999, (int) $entry->source_id);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'ledger-restore-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'ledger-restore-tester',
            'total_sales_amount' => 0,
        ]);
    }
}
