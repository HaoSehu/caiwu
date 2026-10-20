<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Exceptions\BusinessException;
use App\Models\FinanceAccount;
use App\Models\FinanceConsumption;
use App\Models\FinanceFundingLot;
use App\Models\FinanceLedgerEntry;
use App\Models\FinanceOperation;
use App\Models\User;
use App\Models\UserAccount;
use App\Services\Finance\Core\FinanceAccountService;
use App\Services\Finance\Core\FinanceIdempotencyService;
use App\Support\Money;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 财务内核不变式：余额、台账、批次、消费映射四者守恒。
 *
 * 覆盖 G2/G3/G4 闸门的运行时等价物：
 * - 入账必建批次且 1:1；
 * - 出账按 FIFO 跨批次扣减；
 * - 撤销出账只回补原批次，不产生新批次；
 * - 余额不足或批次不足时整体回滚，不留半条记录；
 * - 幂等同键同体重放、同键异体拒绝。
 */
class FinanceCoreTest extends TestCase
{
    use DatabaseTransactions;

    public function test_credit_creates_account_entry_and_lot_with_hot_standby(): void
    {
        $user = $this->makeUser();

        $mutation = $this->accounts()->credit(
            (int) $user->id,
            10000,
            FinanceLedgerEventType::RECHARGE,
            ['source_type' => 'payment', 'source_id' => 11, 'remark' => '测试充值'],
        );

        $this->assertSame(10000, $mutation->balanceCents);
        $this->assertSame(10000, $this->accounts()->balanceCents((int) $user->id));
        $this->assertSame(1, FinanceLedgerEntry::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, FinanceFundingLot::query()->where('user_id', $user->id)->count());

        $entry = FinanceLedgerEntry::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('100.00', $entry->change_amount);
        $this->assertSame('100.00', $entry->balance_after);
        $this->assertNotNull($entry->funding_lot_id);

        $lot = FinanceFundingLot::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame((int) $entry->id, (int) $lot->entry_id);
        $this->assertSame('100.00', $lot->remaining_amount);

        // 热备列必须与账户一致，否则切换后回滚会丢账。
        $standby = UserAccount::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('100.00', $standby->cash_balance);

        $this->assertAccountInvariant((int) $user->id);
    }

    public function test_debit_consumes_lots_in_fifo_order(): void
    {
        $user = $this->makeUser();
        $this->accounts()->credit((int) $user->id, 5000, FinanceLedgerEventType::RECHARGE);
        $this->accounts()->credit((int) $user->id, 3000, FinanceLedgerEventType::RECHARGE);

        $mutation = $this->accounts()->debit(
            (int) $user->id,
            6000,
            FinanceLedgerEventType::INVOICE_PAYMENT,
            ['source_type' => 'invoice', 'source_id' => 900],
        );

        $this->assertSame(2000, $mutation->balanceCents);
        $this->assertCount(2, $mutation->allocations);
        $this->assertSame(5000, $mutation->allocations[0]['amount_cents']);
        $this->assertSame(1000, $mutation->allocations[1]['amount_cents']);

        $lots = FinanceFundingLot::query()->where('user_id', $user->id)->orderBy('id')->get();
        $this->assertSame('0.00', $lots[0]->remaining_amount);
        $this->assertSame('20.00', $lots[1]->remaining_amount);

        $this->assertSame(2, FinanceConsumption::query()->where('user_id', $user->id)->count());
        $this->assertAccountInvariant((int) $user->id);
    }

    public function test_debit_rejects_when_balance_is_insufficient_without_partial_write(): void
    {
        $user = $this->makeUser();
        $this->accounts()->credit((int) $user->id, 1000, FinanceLedgerEventType::RECHARGE);

        try {
            $this->accounts()->debit((int) $user->id, 2000, FinanceLedgerEventType::INVOICE_PAYMENT);
            $this->fail('余额不足应抛出业务异常');
        } catch (BusinessException $error) {
            $this->assertSame(40900, $error->getErrorCode());
        }

        $this->assertSame(1000, $this->accounts()->balanceCents((int) $user->id));
        $this->assertSame(1, FinanceLedgerEntry::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, FinanceConsumption::query()->where('user_id', $user->id)->count());
        $this->assertAccountInvariant((int) $user->id);
    }

    public function test_restore_returns_money_to_original_lot_without_creating_new_lot(): void
    {
        $user = $this->makeUser();
        $this->accounts()->credit((int) $user->id, 10000, FinanceLedgerEventType::RECHARGE);
        $this->accounts()->debit(
            (int) $user->id,
            4000,
            FinanceLedgerEventType::INVOICE_PAYMENT,
            ['source_type' => 'invoice', 'source_id' => 321],
        );

        $restored = $this->accounts()->restore(
            (int) $user->id,
            4000,
            FinanceLedgerEventType::INVOICE_REFUND,
            'invoice',
            321,
            ['remark' => '组合支付取消退回'],
        );

        $this->assertSame(10000, $restored->balanceCents);
        $this->assertSame(1, FinanceFundingLot::query()->where('user_id', $user->id)->count());

        $consumption = FinanceConsumption::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('40.00', $consumption->restored_amount);
        $this->assertNotNull($consumption->restored_entry_id);

        // 恢复台账不绑定新批次，靠 consumption.restored_entry_id 反向指认。
        $restoreEntry = FinanceLedgerEntry::query()->whereKey((int) $restored->entryId)->firstOrFail();
        $this->assertNull($restoreEntry->funding_lot_id);
        $this->assertSame('40.00', $restoreEntry->change_amount);

        $this->assertAccountInvariant((int) $user->id);
    }

    public function test_idempotency_replays_same_key_and_same_payload(): void
    {
        $user = $this->makeUser();
        $calls = 0;

        $callback = function () use ($user, &$calls): array {
            $calls++;
            $mutation = $this->accounts()->credit(
                (int) $user->id,
                2500,
                FinanceLedgerEventType::MANUAL_RECHARGE,
                ['source_type' => 'manual_adjustment'],
            );

            return ['entry_id' => $mutation->entryId];
        };

        $first = $this->idempotency()->execute(
            'admin_balance_adjust',
            'admin',
            7,
            'idem-key-0001',
            ['user_id' => (int) $user->id, 'amount' => 25.0, 'direction' => 'credit'],
            $callback,
        );
        $second = $this->idempotency()->execute(
            'admin_balance_adjust',
            'admin',
            7,
            'idem-key-0001',
            ['direction' => 'credit', 'amount' => 25.00, 'user_id' => (int) $user->id],
            $callback,
        );

        $this->assertFalse($first->replayed);
        $this->assertTrue($second->replayed);
        $this->assertSame(1, $calls);
        $this->assertSame($first->payload, $second->payload);
        $this->assertSame(1, FinanceLedgerEntry::query()->where('user_id', $user->id)->count());
        $this->assertSame(FinanceOperation::STATUS_SUCCEEDED, FinanceOperation::query()->where('request_key', 'idem-key-0001')->value('status'));
    }

    public function test_idempotency_rejects_same_key_with_different_payload(): void
    {
        $user = $this->makeUser();
        $calls = 0;
        $callback = function () use (&$calls): array {
            $calls++;

            return ['ok' => true];
        };

        $this->idempotency()->execute('admin_balance_adjust', 'admin', 7, 'idem-key-0002', ['amount' => 10.0], $callback);

        try {
            $this->idempotency()->execute('admin_balance_adjust', 'admin', 7, 'idem-key-0002', ['amount' => 99.0], $callback);
            $this->fail('同键异体应拒绝');
        } catch (BusinessException $error) {
            $this->assertSame(40900, $error->getErrorCode());
        }

        $this->assertSame(1, $calls);
        $this->assertSame(1, FinanceOperation::query()->where('request_key', 'idem-key-0002')->count());
    }

    public function test_negative_balance_is_rejected_unless_explicitly_allowed(): void
    {
        $user = $this->makeUser();

        try {
            $this->accounts()->debit((int) $user->id, 100, FinanceLedgerEventType::VERIFICATION_FEE);
            $this->fail('零余额扣款应拒绝');
        } catch (BusinessException $error) {
            $this->assertSame(40900, $error->getErrorCode());
        }

        $this->assertSame(0, $this->accounts()->balanceCents((int) $user->id));
        $this->assertSame(0, FinanceLedgerEntry::query()->where('user_id', $user->id)->count());
    }

    private function accounts(): FinanceAccountService
    {
        return app(FinanceAccountService::class);
    }

    private function idempotency(): FinanceIdempotencyService
    {
        return app(FinanceIdempotencyService::class);
    }

    private function assertAccountInvariant(int $userId): void
    {
        $account = FinanceAccount::query()->where('user_id', $userId)->firstOrFail();
        $lots = Money::toCents(
            FinanceFundingLot::query()->where('account_id', $account->id)->sum('remaining_amount')
        );

        $this->assertSame(Money::toCents($account->available_amount), $lots, '批次剩余合计必须等于账户余额');
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'finance-core-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'finance-core-tester',
            'total_sales_amount' => 0,
        ]);
    }
}
