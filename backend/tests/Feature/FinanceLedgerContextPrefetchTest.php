<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\PaymentGatewayCode;
use App\Models\AccountTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\FinanceLedgerQueryService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 台账上下文解析不得对支付来源流水逐行回查 Invoice：
 * 支付来源流水的 invoice_id 必须并入预取集合一次建 map，
 * 否则每条流水多 4 条查询（单页上限 100 时最坏 400 条）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class FinanceLedgerContextPrefetchTest extends TestCase
{
    use DatabaseTransactions;

    public function test_payment_source_ledger_resolves_invoice_without_per_row_fallback_query(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user);
        $payment = $this->makePayment($user, $invoice);
        $this->makeTransaction($user, 'recharge', '50.00', 'payment', (int) $payment->id);

        $invoiceQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$invoiceQueries): void {
            if (str_starts_with($query->sql, 'select * from `invoices`')) {
                $invoiceQueries++;
            }
        });

        $paginator = app(FinanceLedgerQueryService::class)->paginatorForUser($user, [], 15);

        // 修复前：payment 来源流水的关联账单落到循环内 find()，invoices 主查询出现 2 次
        $this->assertSame(1, $invoiceQueries);

        $row = collect($paginator->items())->first();
        $this->assertNotNull($row);

        $resolved = $row->getRelation('invoice');
        $this->assertNotNull($resolved);
        $this->assertSame((int) $invoice->id, (int) $resolved->id);

        // service/order 关系随预取一并加载，避免逐行懒加载
        $this->assertTrue($resolved->relationLoaded('payments'));
        $this->assertTrue($resolved->relationLoaded('order'));
        $this->assertTrue($resolved->relationLoaded('service'));
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'ledger-prefetch-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'ledger-prefetch-tester',
            'total_sales_amount' => 0,
            'status' => 1,
        ]);
    }

    private function makeInvoice(User $user): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => 100.00,
            'paid_amount' => 100.00,
            'status' => 1,
            'paid_at' => now(),
        ]);
    }

    private function makePayment(User $user, Invoice $invoice): Payment
    {
        return Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => 50.00,
            'status' => 1,
            'paid_at' => now(),
        ]);
    }

    private function makeTransaction(User $user, string $eventType, string $changeAmount, string $sourceType, ?int $sourceId): AccountTransaction
    {
        return AccountTransaction::query()->create([
            'user_id' => $user->id,
            'account_type' => 'cash',
            'event_type' => $eventType,
            'change_amount' => $changeAmount,
            'balance_after' => '0.00',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'origin_type' => $sourceType,
            'origin_id' => $sourceId,
        ]);
    }
}
