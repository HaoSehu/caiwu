<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Constants\FinanceLedgerEventType;
use App\Models\User;
use App\Services\Finance\Core\FinanceAccountService;
use App\Support\Money;

/**
 * 测试预置现金余额。
 *
 * 现金真源已迁到 finance_accounts，且要求「余额 == 批次剩余合计」。
 * 因此非零预置必须走内核入账（同时产生一条 system_adjustment 台账与对应批次），
 * 不能直接改余额列，否则后续扣款会因批次不足被拒绝。
 */
trait SeedsFinanceBalance
{
    protected function seedFinanceBalance(User $user, float $balance): void
    {
        $accounts = app(FinanceAccountService::class);
        $accounts->accountFor((int) $user->id);

        $cents = Money::toCents($balance);
        if ($cents <= 0) {
            return;
        }

        $accounts->credit((int) $user->id, $cents, FinanceLedgerEventType::SYSTEM_ADJUSTMENT, [
            'source_type' => 'test_seed',
            'remark' => '测试预置余额',
            'operator_type' => 'system',
            'operator_name' => 'test',
        ]);
    }

    /** 读取当前现金余额（元）。 */
    protected function financeBalance(User $user, bool $lock = false): float
    {
        return Money::fromCents(app(FinanceAccountService::class)->balanceCents((int) $user->id, $lock));
    }
}
