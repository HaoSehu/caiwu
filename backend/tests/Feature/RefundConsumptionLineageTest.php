<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Models\FinanceConsumption;
use App\Models\FinanceFundingLot;
use App\Models\User;
use App\Services\Finance\Core\FinanceAccountService;
use App\Services\Finance\Core\FundingLotAllocator;
use App\Support\Money;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 退款消费血缘：退款入账后必须能回答「退的是哪笔原消费」。
 *
 * 回归 P1-5：FundingLotAllocator::markRefunded 此前全库零调用，退款金额只能当新钱
 * 按 FIFO 使用，finance_consumptions.refunded_amount 永远为 0。
 */
class RefundConsumptionLineageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_refund_backfills_consumption_lineage_without_touching_lot_remaining(): void
    {
        $user = $this->makeUser();
        $accounts = app(FinanceAccountService::class);

        // 充值 100 → 消费 30（产生消费映射）→ 再退款 30
        $accounts->credit((int) $user->id, 10000, FinanceLedgerEventType::RECHARGE, [
            'source_type' => 'payment',
            'source_id' => 1,
        ]);

        $accounts->debit((int) $user->id, 3000, FinanceLedgerEventType::INVOICE_PAYMENT, [
            'source_type' => 'invoice',
            'source_id' => 9001,
        ]);

        $consumption = FinanceConsumption::query()
            ->where('source_type', 'invoice')
            ->where('source_id', 9001)
            ->firstOrFail();

        $this->assertSame('0.00', (string) $consumption->refunded_amount);

        $lotRemainingBefore = $this->lotRemainingCents((int) $user->id);

        $accounts->markRefundedConsumptions((int) $user->id, 'invoice', 9001, 3000);

        $consumption->refresh();
        $this->assertSame('30.00', (string) $consumption->refunded_amount);

        // 只写证据链：批次剩余不得被这笔追认改动
        $this->assertSame($lotRemainingBefore, $this->lotRemainingCents((int) $user->id));
    }

    public function test_lineage_cannot_exceed_consumed_amount(): void
    {
        $user = $this->makeUser();
        $accounts = app(FinanceAccountService::class);

        $accounts->credit((int) $user->id, 10000, FinanceLedgerEventType::RECHARGE, ['source_type' => 'payment', 'source_id' => 1]);
        $accounts->debit((int) $user->id, 3000, FinanceLedgerEventType::INVOICE_PAYMENT, ['source_type' => 'invoice', 'source_id' => 9002]);

        // 超额的追认只按可追认额度截断，不抛错、不做假账
        $marked = $accounts->markRefundedConsumptions((int) $user->id, 'invoice', 9002, 5000);
        $this->assertSame(3000, $marked);

        $this->assertSame(0, $accounts->markRefundedConsumptions((int) $user->id, 'invoice', 9002, 1000));
    }

    public function test_unknown_source_returns_zero(): void
    {
        $user = $this->makeUser();
        $accounts = app(FinanceAccountService::class);
        $accounts->accountFor((int) $user->id);

        $this->assertSame(0, $accounts->markRefundedConsumptions((int) $user->id, 'invoice', 123456, 1000));
    }

    private function lotRemainingCents(int $userId): int
    {
        $sum = FinanceFundingLot::query()->where('user_id', $userId)->sum('remaining_amount');

        return Money::toCents($sum);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'refund-lineage-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'refund-lineage-tester',
            'total_sales_amount' => 0,
        ]);
    }
}
