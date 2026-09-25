<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserAccount;
use App\Services\Finance\ClientFinanceQueryService;
use App\Services\Finance\FinanceLedgerQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 客户端余额汇总口径：
 * 1. /balance-logs/summary 不得用外层整包缓存把 cash_balance 冻住——内层 30s
 *    缓存命中时专门重读用户余额，两个汇总端点（balance-logs/summary 与
 *    finance/ledger/summary）同一时刻必须返回相同余额；
 * 2. 未付金额聚合统一为「待支付」单一谓词（E1-01，历史 IN (0,3) 已收敛）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class ClientFinanceSummaryBalanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_balance_log_summary_reflects_balance_change_immediately(): void
    {
        $user = $this->makeUser();
        $this->makeAccount($user, '0.00');
        $service = app(ClientFinanceQueryService::class);

        $this->assertSame('0.00', $service->balanceLogSummary($user, [])['cash_balance']);

        // 模拟充值到账：余额更新后立即重查，内层缓存命中也必须重读余额
        UserAccount::query()->where('user_id', (int) $user->id)->update(['cash_balance' => '123.45']);
        $user->unsetRelation('account');

        $after = $service->balanceLogSummary($user, []);
        $this->assertSame('123.45', $after['cash_balance']);

        // 两个汇总端点共享同一份 summary，同一时刻余额一致
        $ledger = app(FinanceLedgerQueryService::class)->summaryForClient($user, []);
        $this->assertSame($after['cash_balance'], $ledger['cash_balance']);
    }

    public function test_summary_unpaid_amount_counts_unpaid_invoices_only(): void
    {
        $user = $this->makeUser();
        $this->makeAccount($user, '0.00');
        $this->makeInvoice((int) $user->id, InvoiceStatus::UNPAID, '100.00', '0.00');
        $this->makeInvoice((int) $user->id, InvoiceStatus::PAID, '50.00', '50.00');
        $this->makeInvoice((int) $user->id, InvoiceStatus::CANCELLED, '70.00', '0.00');

        $summary = app(ClientFinanceQueryService::class)->balanceLogSummary($user, []);

        $this->assertSame('100.00', $summary['unpaid_amount']);
        $this->assertSame(1, $summary['unpaid_count']);
        $this->assertSame(3, $summary['total_invoices']);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'balance-summary-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'balance-summary-tester',
            'total_sales_amount' => 0,
            'status' => 1,
        ]);
    }

    private function makeAccount(User $user, string $cashBalance): UserAccount
    {
        return UserAccount::query()->create([
            'user_id' => $user->id,
            'cash_balance' => $cashBalance,
        ]);
    }

    private function makeInvoice(int $userId, int $status, string $amount, string $paidAmount): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $userId,
            'type' => 'new',
            'amount' => $amount,
            'paid_amount' => $paidAmount,
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::UNPAID ? null : now(),
        ]);
    }
}
