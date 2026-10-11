<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\InvoiceMergeItem;
use App\Models\User;
use App\Services\Finance\InvoiceMergeService;
use App\Services\Finance\TradeLifecycleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 账单合并（M1）拆分测试：仅未支付总账单可拆，拆后子账单可独立支付。
 *
 * 核心不变式：
 * - unmerge 仅 merge_role=1 且 status=0 放行；
 * - 子账单解除 merged_into_id/merged_at，回到独立待支付，可正常 markInvoicePaid；
 * - invoice_merge_items 快照清空；总账单置已取消（status=4，remark 注明拆分）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class InvoiceUnmergeTest extends TestCase
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

    public function test_unmerge_releases_children_and_cancels_master(): void
    {
        $user = $this->makeUser();
        $invoiceA = $this->makeInvoice($user, 100.00);
        $invoiceB = $this->makeInvoice($user, 50.00);
        $master = $this->service->merge((int) $user->id, [(int) $invoiceA->id, (int) $invoiceB->id], 1);

        $result = $this->service->unmerge($master, 1);

        // 总账单：已取消、备注注明拆分。
        $result->refresh();
        $this->assertSame(InvoiceStatus::CANCELLED, (int) $result->status);
        $this->assertStringContainsString('拆分', (string) $result->remark);

        // 子账单：解除关联、恢复独立待支付。
        $invoiceA->refresh();
        $invoiceB->refresh();
        $this->assertNull($invoiceA->merged_into_id);
        $this->assertNull($invoiceB->merged_into_id);
        $this->assertNull($invoiceA->merged_at);
        $this->assertNull($invoiceB->merged_at);
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoiceA->status);
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoiceB->status);

        // 关联快照清空。
        $this->assertSame(0, InvoiceMergeItem::query()->where('master_invoice_id', (int) $master->id)->count());
    }

    public function test_children_can_be_paid_independently_after_unmerge(): void
    {
        $user = $this->makeUser();
        $invoiceA = $this->makeInvoice($user, 100.00);
        $invoiceB = $this->makeInvoice($user, 50.00);
        $master = $this->service->merge((int) $user->id, [(int) $invoiceA->id, (int) $invoiceB->id], 1);
        $this->service->unmerge($master, 1);

        // 拆后子账单走原生支付链路独立结清。
        $this->lifecycle->markInvoicePaid($invoiceA, ['trace_id' => 'unmerge-pay-a']);

        $invoiceA->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoiceA->status);
        $this->assertSame('100.00', (string) $invoiceA->paid_amount);
        $invoiceB->refresh();
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoiceB->status);
    }

    public function test_paid_master_cannot_be_unmerged(): void
    {
        $user = $this->makeUser();
        $invoiceA = $this->makeInvoice($user, 100.00);
        $invoiceB = $this->makeInvoice($user, 50.00);
        $master = $this->service->merge((int) $user->id, [(int) $invoiceA->id, (int) $invoiceB->id], 1);
        $this->lifecycle->markInvoicePaid($master, ['trace_id' => 'unmerge-paid']);

        try {
            $this->service->unmerge($master, 1);
            $this->fail('已支付总账单拆分应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('仅待支付的总账单支持拆分', $exception->getMessage());
        }

        // 拒绝后无状态变化：子账单保持已结清。
        $invoiceA->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoiceA->status);
        $this->assertSame((int) $master->id, (int) $invoiceA->merged_into_id);
    }

    public function test_non_master_invoice_cannot_be_unmerged(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 100.00);

        try {
            $this->service->unmerge($invoice, 1);
            $this->fail('普通账单拆分应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('不是合并总账单', $exception->getMessage());
        }

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->status);
    }

    private function makeUser(string $prefix = 'unmerge'): User
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
