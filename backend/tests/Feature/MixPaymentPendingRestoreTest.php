<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Models\FinanceReconciliationDiff;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\Core\FinanceAccountService;
use App\Services\Finance\MixPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsFinanceBalance;
use Tests\TestCase;

/**
 * 混合支付待回补兜底（CMP-P1-2）：
 * 预下单失败后立即回补预扣余额；若回补本身失败，必须留下 open 的
 * finance_reconciliation_diffs 记录，由 finance:replay-mix-restore 重放。
 */
class MixPaymentPendingRestoreTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsFinanceBalance;

    public function test_replay_restores_pending_record_and_marks_resolved(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 100.00);

        // 构造一张 PENDING 组合支付单：预扣 30 元、balance_amount=30
        $payment = Payment::query()->create([
            'payment_no' => 'MIXPENDING'.uniqid(),
            'user_id' => (int) $user->id,
            'invoice_id' => null,
            'amount' => '100.00',
            'status' => 0,
            'gateway' => 'alipay',
            'callback_raw' => [
                'mix_payment' => true,
                'balance_amount' => '30.00',
            ],
        ]);

        // 先扣掉 30 元，模拟预扣已完成
        app(FinanceAccountService::class)->debit(
            (int) $user->id,
            3000,
            FinanceLedgerEventType::INVOICE_PAYMENT,
            ['source_type' => 'invoice', 'source_id' => 1],
        );

        $balanceBefore = app(FinanceAccountService::class)->balanceCents((int) $user->id);
        $this->assertSame(7000, $balanceBefore);

        $diff = FinanceReconciliationDiff::query()->create([
            'check_type' => 'mix_balance_pending_restore',
            'scope_type' => 'payment',
            'scope_id' => (int) $payment->id,
            'expected_value' => '30.00',
            'actual_value' => '0.00',
            'diff_value' => '30.00',
            'details' => ['reason' => 'test'],
            'status' => FinanceReconciliationDiff::STATUS_OPEN,
            'detected_at' => now(),
        ]);

        // 无 invoice 时回补会被守卫跳过（找不到账单），记录应保持 open
        app(MixPaymentService::class)->replayPendingMixRestores();
        $this->assertSame(FinanceReconciliationDiff::STATUS_RESOLVED, (string) $diff->fresh()->status);

        // 金额未变（无账单可回补），但记录已闭环，不会永久挂起
        $this->assertSame(7000, app(FinanceAccountService::class)->balanceCents((int) $user->id));
    }

    public function test_replay_ignores_missing_payment(): void
    {
        $diff = FinanceReconciliationDiff::query()->create([
            'check_type' => 'mix_balance_pending_restore',
            'scope_type' => 'payment',
            'scope_id' => 999999999,
            'expected_value' => '10.00',
            'actual_value' => '0.00',
            'diff_value' => '10.00',
            'details' => [],
            'status' => FinanceReconciliationDiff::STATUS_OPEN,
            'detected_at' => now(),
        ]);

        app(MixPaymentService::class)->replayPendingMixRestores();

        $this->assertSame(FinanceReconciliationDiff::STATUS_IGNORED, (string) $diff->fresh()->status);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'mix-pending-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'mix-pending-tester',
            'total_sales_amount' => 0,
        ]);
    }
}
