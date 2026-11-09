<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\Core\FinanceAccountService;
use App\Services\Finance\MixPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsFinanceBalance;
use Tests\TestCase;

/**
 * M-45 回归：预扣余额回补的锁序必须与入账引擎一致（Invoice → Payment），
 * 消除 ABBA 死锁窗口；单路径回补行为保持不变（余额退回、支付单取消、幂等闸）。
 */
class Fix2MixRestoreLockOrderTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsFinanceBalance;

    public function test_restore_locks_invoice_before_payment_and_keeps_restore_behavior(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 100.00);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => 100.00,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
        ]);
        $payment = Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => 70.00,
            'status' => PaymentStatus::PENDING,
            'callback_raw' => [
                'source' => 'alipay_precreate_mix',
                'mix_payment' => true,
                'balance_amount' => '30.00',
            ],
        ]);
        app(FinanceAccountService::class)->debit((int) $user->id, 3000, FinanceLedgerEventType::INVOICE_PAYMENT, [
            'source_type' => 'invoice',
            'source_id' => (int) $invoice->id,
            'remark' => '组合支付预扣余额',
            'operator_type' => 'member',
        ]);
        $invoice->forceFill(['paid_amount' => 30.00])->save();
        $this->assertEqualsWithDelta(70.00, $this->financeBalance($user), 0.001);

        $connection = DB::connection();
        $wasLogging = $connection->logging();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        $queries = [];

        try {
            $restored = app(MixPaymentService::class)->restoreReservedMixBalance($payment, [
                'closed_reason' => 'fix2_lock_order',
                'trace_id' => 'fix2-lock-order',
            ]);
            $queries = array_values(array_map(
                static fn (array $query): string => (string) ($query['query'] ?? ''),
                (array) $connection->getQueryLog(),
            ));
        } finally {
            $connection->flushQueryLog();
            if (! $wasLogging) {
                $connection->disableQueryLog();
            }
        }

        $this->assertTrue($restored);

        // 锁序断言：事务内先出现账单行锁，后出现支付单行锁。
        $invoiceLock = $this->firstLockQueryIndex($queries, 'invoices');
        $paymentLock = $this->firstLockQueryIndex($queries, 'payments');
        $this->assertNotNull($invoiceLock, '回补必须在事务内锁定账单行');
        $this->assertNotNull($paymentLock, '回补必须在事务内锁定支付单行');
        $this->assertLessThan($paymentLock, $invoiceLock, '回补锁序必须为 Invoice → Payment，避免与入账引擎 ABBA 死锁');

        // 单路径行为不变：余额退回一次、支付单取消、幂等标志落盘。
        $payment->refresh();
        $this->assertSame(PaymentStatus::CANCELLED, (int) $payment->status);
        $this->assertEqualsWithDelta(100.00, $this->financeBalance($user), 0.001);
        $raw = (array) $payment->callback_raw;
        $this->assertTrue((bool) ($raw['balance_restored'] ?? false));
        $this->assertEqualsWithDelta(0.00, (float) $invoice->refresh()->paid_amount, 0.001);

        // 幂等闸保持不变：二次回补返回 false 且余额不再变化。
        $this->assertFalse(app(MixPaymentService::class)->restoreReservedMixBalance($payment, [
            'closed_reason' => 'fix2_lock_order',
        ]));
        $this->assertEqualsWithDelta(100.00, $this->financeBalance($user), 0.001);
    }

    /**
     * @param  list<string>  $queries
     */
    private function firstLockQueryIndex(array $queries, string $table): ?int
    {
        foreach ($queries as $index => $sql) {
            if (stripos($sql, 'for update') === false) {
                continue;
            }

            if (preg_match('/\bfrom\s+`?'.preg_quote($table, '/').'`?(?:\s|$)/i', $sql) === 1) {
                return $index;
            }
        }

        return null;
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'fix2-lock-order-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'fix2-lock-order-tester',
            'total_sales_amount' => 0,
        ]);
    }
}
