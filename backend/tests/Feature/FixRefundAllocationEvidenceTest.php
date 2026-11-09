<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Models\FinanceConsumption;
use App\Models\FinanceRefundAllocation;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\Core\FinanceAccountService;
use App\Services\Finance\InvoiceRefundService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsFinanceBalance;
use Tests\TestCase;

/**
 * 审查修复回归（M-69）：退款原消费证据表不得静默截断到 20 条。
 * 一次退款跨 25 笔消费时，证据表必须写出 25 行，且金额合计等于退款额。
 *
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class FixRefundAllocationEvidenceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsFinanceBalance;

    public function test_refund_across_25_consumptions_writes_25_evidence_rows(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 100.00);
        $accounts = app(FinanceAccountService::class);

        $invoice = $this->makeInvoice($user, 25.00);
        for ($i = 0; $i < 25; $i++) {
            $accounts->debit((int) $user->id, 100, FinanceLedgerEventType::INVOICE_PAYMENT, [
                'source_type' => 'invoice',
                'source_id' => (int) $invoice->id,
                'remark' => '测试余额支付账单',
            ]);
        }

        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 25.00, 'paid_at' => now()])->save();
        $this->assertSame(25, FinanceConsumption::query()
            ->where('source_type', 'invoice')
            ->where('source_id', (int) $invoice->id)
            ->count(), '前置条件：该账单名下必须有 25 条消费映射');

        $result = app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice);
        $this->assertFalse((bool) ($result['already_refunded'] ?? true));

        $marks = FinanceRefundAllocation::query()
            ->where('refund_id', (int) ($result['refund_id'] ?? 0))
            ->where('allocation_type', FinanceRefundAllocation::TYPE_CONSUMPTION_MARK)
            ->get();

        $this->assertCount(25, $marks, '超出 20 条的消费分摊必须全量落证据表，不得静默截断');
        $this->assertEqualsWithDelta(25.00, (float) $marks->sum(fn ($row): float => (float) $row->amount), 0.001);
        // 预置 100 − 支付 25 + 退款 25 = 100
        $this->assertEqualsWithDelta(100.00, $this->financeBalance($user), 0.001);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'refund-evidence-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'refund-evidence-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeInvoice(User $user, float $amount): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => $amount,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
        ]);
    }
}
