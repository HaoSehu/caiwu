<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AccountTransaction;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\FinanceLedgerQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 台账汇总缓存键必须覆盖全部参与聚合的过滤字段：此前指纹只含 tab/日期等
 * 6 个字段，keyword/invoice_no/service_id/payment_no 缺失，30s TTL 内不同
 * 筛选共享同一份汇总金额。使用 DatabaseTransactions，测试结束回滚。
 */
class FinanceLedgerSummaryCacheTest extends TestCase
{
    use DatabaseTransactions;

    public function test_summary_varies_with_keyword_filter_within_ttl(): void
    {
        [$userA, $userB] = $this->makeUsersWithLedger();

        $service = app(FinanceLedgerQueryService::class);

        $summaryA = $service->summaryForAdmin(['keyword' => (string) $userA->id]);
        $this->assertSame('100.00', $summaryA['total_in']);
        $this->assertSame('30.00', $summaryA['total_out']);
        $this->assertSame(2, $summaryA['total_count']);

        // 修复前：与上一条共用缓存键，此处会错误返回 A 的汇总数字
        $summaryB = $service->summaryForAdmin(['keyword' => (string) $userB->id]);
        $this->assertSame('0.00', $summaryB['total_in']);
        $this->assertSame('50.00', $summaryB['total_out']);
        $this->assertSame(1, $summaryB['total_count']);
    }

    public function test_summary_varies_with_invoice_no_filter_within_ttl(): void
    {
        [$userA] = $this->makeUsersWithLedger();
        $userC = $this->makeUser('c');
        $this->makeTransaction($userC, 'recharge', 88.00, 'payment', null);

        $service = app(FinanceLedgerQueryService::class);
        $invoiceNo = 'ZZFIX'.date('YmdHis').mt_rand(100000, 999999);
        $invoice = $this->makeInvoice((int) $userA->id, $invoiceNo);
        $this->makeTransaction($userA, 'invoice_payment', -30.00, 'invoice', $invoice->id);

        $hit = $service->summaryForAdmin(['invoice_no' => $invoiceNo]);
        $this->assertSame(1, $hit['total_count']);
        $this->assertSame('30.00', $hit['total_out']);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function makeUsersWithLedger(): array
    {
        $userA = $this->makeUser('a');
        $userB = $this->makeUser('b');

        $this->makeTransaction($userA, 'recharge', 100.00, 'payment', null);
        $invoiceA = $this->makeInvoice((int) $userA->id, 'ZKEYA'.date('YmdHis').mt_rand(100000, 999999));
        $this->makeTransaction($userA, 'invoice_payment', -30.00, 'invoice', $invoiceA->id);
        $this->makeTransaction($userB, 'invoice_payment', -50.00, 'invoice', null);

        return [$userA, $userB];
    }

    private function makeUser(string $prefix): User
    {
        return User::query()->create([
            'email' => $prefix.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $prefix.'-ledger-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeTransaction(User $user, string $eventType, float $changeAmount, string $sourceType, ?int $sourceId): AccountTransaction
    {
        return AccountTransaction::query()->create([
            'user_id' => $user->id,
            'account_type' => 'cash',
            'event_type' => $eventType,
            'change_amount' => number_format($changeAmount, 2, '.', ''),
            'balance_after' => '0.00',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'origin_type' => $sourceType,
            'origin_id' => $sourceId,
        ]);
    }

    private function makeInvoice(int $userId, string $invoiceNo): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => $invoiceNo,
            'user_id' => $userId,
            'type' => 'new',
            'amount' => 100.00,
            'paid_amount' => 100.00,
            'status' => 1,
            'paid_at' => now(),
        ]);
    }
}
