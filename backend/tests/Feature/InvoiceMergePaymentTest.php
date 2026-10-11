<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Models\AccountTransaction;
use App\Models\Invoice;
use App\Models\InvoiceMergeItem;
use App\Models\Refund;
use App\Models\User;
use App\Models\UserAccount;
use App\Services\Finance\InvoiceMergeService;
use App\Services\Finance\TradeLifecycleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 账单合并（M1）支付联动测试：总账单支付成功 → 同事务联动结清子账单。
 *
 * 核心不变式：
 * - 联动经 markInvoicePaid（唯一写者）触发：merge_role=1 → settleMergedChildren；
 * - 子账单结清口径：status=1、paid_amount=amount、paid_at=总账单 paid_at；
 * - 幂等重放：markInvoicePaid 重复调用不重写资金列、子账单不重复写；
 * - 资金红线：无 AccountTransaction 新记录、无 UserAccount 余额变动、
 *   无 refunds 记录、无红字账单（origin_invoice_id 指向）、Payment 不动。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class InvoiceMergePaymentTest extends TestCase
{
    use DatabaseTransactions;

    private InvoiceMergeService $service;

    private TradeLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvoiceMergeService::class);
        $this->lifecycle = app(TradeLifecycleService::class);
    }

    public function test_master_payment_settles_children_with_amount_and_paid_at(): void
    {
        $user = $this->makeUser();
        $invoiceA = $this->makeInvoice($user, 100.00);
        $invoiceB = $this->makeInvoice($user, 50.00);
        $master = $this->service->merge((int) $user->id, [(int) $invoiceA->id, (int) $invoiceB->id], 1);

        $this->lifecycle->markInvoicePaid($master, ['trace_id' => 'merge-pay-test']);

        $master->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $master->status);
        $this->assertSame('150.00', (string) $master->paid_amount);
        $this->assertNotNull($master->paid_at);

        $invoiceA->refresh();
        $invoiceB->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoiceA->status);
        $this->assertSame(InvoiceStatus::PAID, (int) $invoiceB->status);
        $this->assertSame('100.00', (string) $invoiceA->paid_amount);
        $this->assertSame('50.00', (string) $invoiceB->paid_amount);
        $this->assertSame($master->paid_at?->format('YmdHis'), $invoiceA->paid_at?->format('YmdHis'));
        $this->assertSame($master->paid_at?->format('YmdHis'), $invoiceB->paid_at?->format('YmdHis'));
    }

    public function test_replay_of_master_payment_is_idempotent(): void
    {
        $user = $this->makeUser();
        $invoiceA = $this->makeInvoice($user, 100.00);
        $invoiceB = $this->makeInvoice($user, 50.00);
        $master = $this->service->merge((int) $user->id, [(int) $invoiceA->id, (int) $invoiceB->id], 1);

        $this->lifecycle->markInvoicePaid($master, ['trace_id' => 'merge-pay-first']);
        $paidAtA = $invoiceA->refresh()->paid_at;
        $this->assertNotNull($paidAtA);

        // 幂等重放：状态/资金列不重写，无异常。
        $this->lifecycle->markInvoicePaid($master, ['trace_id' => 'merge-pay-replay']);
        $this->lifecycle->markInvoicePaid($master, ['trace_id' => 'merge-pay-replay-2']);

        $invoiceA->refresh();
        $invoiceB->refresh();
        $this->assertSame($paidAtA?->format('YmdHis'), $invoiceA->paid_at?->format('YmdHis'));
        $this->assertSame('100.00', (string) $invoiceA->paid_amount);
        $this->assertSame('50.00', (string) $invoiceB->paid_amount);
        $this->assertSame(2, InvoiceMergeItem::query()->where('master_invoice_id', (int) $master->id)->count());
    }

    public function test_settle_skips_already_paid_child(): void
    {
        $user = $this->makeUser();
        $invoiceA = $this->makeInvoice($user, 100.00);
        $invoiceB = $this->makeInvoice($user, 50.00);
        $master = $this->service->merge((int) $user->id, [(int) $invoiceA->id, (int) $invoiceB->id], 1);

        // 子账单 B 在合并后、总账单支付前被单独结清（对账修复等入口重放 markInvoicePaid）。
        $this->lifecycle->markInvoicePaid($invoiceB, ['paid_amount' => 50.00, 'paid_at' => now()->subHour()]);

        $settled = $this->service->settleMergedChildren($master);

        // 仅待支付子账单被结清；已支付子账单跳过（幂等）。
        $this->assertSame(1, $settled);
        $invoiceA->refresh();
        $invoiceB->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoiceA->status);
        $this->assertSame(InvoiceStatus::PAID, (int) $invoiceB->status);
        $this->assertSame('50.00', (string) $invoiceB->paid_amount);
    }

    public function test_payment_lands_no_money_records_red_lines(): void
    {
        $user = $this->makeUser();
        $invoiceA = $this->makeInvoice($user, 100.00);
        $invoiceB = $this->makeInvoice($user, 50.00);
        $master = $this->service->merge((int) $user->id, [(int) $invoiceA->id, (int) $invoiceB->id], 1);

        $ledgerBefore = AccountTransaction::query()->where('user_id', (int) $user->id)->count();
        $balanceBefore = $this->userCashBalance((int) $user->id);
        $redInvoiceBefore = Invoice::query()->whereIn('origin_invoice_id', [(int) $master->id, (int) $invoiceA->id, (int) $invoiceB->id])->count();
        $refundBefore = Refund::query()->whereIn('invoice_id', [(int) $master->id, (int) $invoiceA->id, (int) $invoiceB->id])->count();

        $this->lifecycle->markInvoicePaid($master, ['trace_id' => 'merge-pay-redline']);

        // 红线：台账零新增、余额零变动、无红字账单、无退款记录。
        $this->assertSame($ledgerBefore, AccountTransaction::query()->where('user_id', (int) $user->id)->count());
        $this->assertSame($balanceBefore, $this->userCashBalance((int) $user->id));
        $this->assertSame($redInvoiceBefore, Invoice::query()->whereIn('origin_invoice_id', [(int) $master->id, (int) $invoiceA->id, (int) $invoiceB->id])->count());
        $this->assertSame($refundBefore, Refund::query()->whereIn('invoice_id', [(int) $master->id, (int) $invoiceA->id, (int) $invoiceB->id])->count());
    }

    public function test_normal_invoice_payment_does_not_trigger_settle(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 80.00);

        // 非总账单（merge_role=0）支付走原路径，无副作用。
        $this->lifecycle->markInvoicePaid($invoice, ['trace_id' => 'normal-pay']);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertSame(0, (int) $invoice->merge_role);
        $this->assertSame(0, InvoiceMergeItem::query()->count());
    }

    private function userCashBalance(int $userId): string
    {
        $account = UserAccount::query()->where('user_id', $userId)->first();

        return $account === null ? '' : (string) $account->cash_balance;
    }

    private function makeUser(string $prefix = 'merge-pay'): User
    {
        return User::query()->create([
            'email' => $prefix.'-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $prefix.'-tester',
            'total_sales_amount' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeInvoice(User $user, float $amount, array $overrides = []): Invoice
    {
        return Invoice::query()->create(array_merge([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'currency' => 'CNY',
            'amount' => $amount,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
        ], $overrides));
    }
}
