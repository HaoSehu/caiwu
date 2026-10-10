<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\InvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 财务 B2 批次 1：手工补录部分核销（G3-8 收敛）。
 * 覆盖：多次入账累加 paid_amount、独立 payments 行、超额/非正金额拒绝、
 * 结清后走终态、金额守恒，以及派生展示「部分已付」的命中/未命中边界。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class InvoicePartialSettlementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_manual_partial_entry_accumulates_and_keeps_unpaid(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 100.00);
        $service = app(InvoiceService::class);

        $service->markPaidManually($invoice, ['amount' => '30.00', 'remark' => '定金'], $this->context());

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->status, '部分核销后账单仍为待支付');
        $this->assertSame('30.00', number_format((float) $invoice->paid_amount, 2, '.', ''));
        $this->assertSame(1, Payment::query()->where('invoice_id', $invoice->id)->where('status', PaymentStatus::SUCCESS)->count());
        $this->assertSame('30.00', number_format((float) Payment::query()->where('invoice_id', $invoice->id)->value('amount'), 2, '.', ''));

        // 第二次部分入账：累加而非覆盖。
        $service->markPaidManually($invoice->refresh(), ['amount' => '20.00', 'remark' => '尾款一'], $this->context());

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->status);
        $this->assertSame('50.00', number_format((float) $invoice->paid_amount, 2, '.', ''));
        $this->assertSame(2, Payment::query()->where('invoice_id', $invoice->id)->where('status', PaymentStatus::SUCCESS)->count());
    }

    public function test_partial_entry_rejects_amount_over_payable(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 100.00);
        $service = app(InvoiceService::class);
        $service->markPaidManually($invoice, ['amount' => '60.00'], $this->context());

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('入账金额不能超过账单应付金额');
        $service->markPaidManually($invoice->refresh(), ['amount' => '50.00'], $this->context());
    }

    public function test_partial_entry_rejects_non_positive_amount(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 100.00);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('入账金额必须大于 0');
        app(InvoiceService::class)->markPaidManually($invoice, ['amount' => '0'], $this->context());
    }

    public function test_partial_then_full_topup_marks_invoice_paid_and_conserves_amount(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 100.00);
        $service = app(InvoiceService::class);

        $service->markPaidManually($invoice, ['amount' => '40.00'], $this->context());
        $service->markPaidManually($invoice->refresh(), ['amount' => '60.00'], $this->context());

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertSame('100.00', number_format((float) $invoice->paid_amount, 2, '.', ''));

        // 金额守恒：各笔入账 payments 之和 = 账单金额。
        $sum = (float) Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', PaymentStatus::SUCCESS)
            ->sum('amount');
        $this->assertEqualsWithDelta(100.00, $sum, 0.001);
    }

    public function test_partial_paid_display_status_hit_and_miss_boundaries(): void
    {
        $user = $this->makeUser();
        $service = app(InvoiceService::class);

        // 命中：0 < paid_amount < amount -> 部分已付。
        $partial = $this->makeInvoice($user, 100.00, ['paid_amount' => 30.00]);
        $detail = $service->adminDetail((int) $partial->id);
        $this->assertSame(InvoiceStatus::PARTIAL_PAID, $detail['status']);
        $this->assertSame('部分已付', $detail['status_label']);
        $this->assertTrue($detail['is_partial_paid']);
        $this->assertSame('30.00', $detail['paid_amount']);
        $this->assertSame('70.00', $detail['payable_amount']);

        // 未命中：paid_amount = 0 -> 待支付。
        $unpaid = $this->makeInvoice($user, 100.00, ['paid_amount' => 0]);
        $unpaidDetail = $service->adminDetail((int) $unpaid->id);
        $this->assertSame(InvoiceStatus::UNPAID, $unpaidDetail['status']);
        $this->assertSame('待支付', $unpaidDetail['status_label']);
        $this->assertFalse($unpaidDetail['is_partial_paid']);

        // 未命中：paid_amount = amount（已支付）-> 已支付。
        $paid = $this->makeInvoice($user, 100.00, [
            'paid_amount' => 100.00,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);
        $paidDetail = $service->adminDetail((int) $paid->id);
        $this->assertSame(InvoiceStatus::PAID, $paidDetail['status']);
        $this->assertFalse($paidDetail['is_partial_paid']);

        // 列表路径同样派生「部分已付」。
        $listItem = $service->adminListItem($partial->refresh());
        $this->assertSame(InvoiceStatus::PARTIAL_PAID, $listItem['status']);
        $this->assertSame('部分已付', $listItem['status_label']);
        $this->assertTrue($listItem['is_partial_paid']);

        // 客户端详情同样派生。
        $clientDetail = $service->clientDetail($partial->refresh());
        $this->assertSame(InvoiceStatus::PARTIAL_PAID, $clientDetail['status']);
        $this->assertTrue($clientDetail['is_partial_paid']);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'partial-settle-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'partial-settle-tester',
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
            'type' => 'manual',
            'amount' => $amount,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [
            'operator_id' => 1,
            'operator_name' => 'admin-test',
            'trace_id' => 'partial-'.uniqid(),
            'ip_address' => '127.0.0.1',
        ];
    }
}
