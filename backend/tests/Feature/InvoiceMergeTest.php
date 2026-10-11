<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\InvoiceMergeItem;
use App\Models\User;
use App\Services\Finance\InvoiceMergeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 账单合并（M1）合并口径测试。
 *
 * 核心不变式：
 * - 总账单口径：amount/discount/member_discount_amount=Σ子账单、paid_amount=0、
 *   due_date=MIN(子账单)、type/currency 取首张子账单、merge_role=1、status=0；
 * - 子账单写 merged_into_id/merged_at，自身金额列不变；
 * - invoice_merge_items 快照行：child 唯一、amount_snapshot=合并时金额；
 * - 守卫：空/单张拒绝、跨用户/跨币种/含已支付拒绝、已合并/总账单重复合并拒绝。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class InvoiceMergeTest extends TestCase
{
    use DatabaseTransactions;

    private InvoiceMergeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvoiceMergeService::class);
    }

    public function test_merge_builds_master_with_summed_amounts_and_snapshots(): void
    {
        $user = $this->makeUser();
        $invoiceA = $this->makeInvoice($user, 100.00, [
            'discount' => 10.00,
            'member_discount_amount' => 5.00,
            'due_date' => '2030-01-01',
            'type' => 'new',
        ]);
        $invoiceB = $this->makeInvoice($user, 50.00, [
            'discount' => 2.50,
            'member_discount_amount' => 1.00,
            'due_date' => '2030-03-01',
            'type' => 'renew',
        ]);

        $master = $this->service->merge((int) $user->id, [(int) $invoiceA->id, (int) $invoiceB->id], 1);

        // 总账单口径：Σ金额 / Σ优惠 / MIN 到期日 / 首张 type / merge_role=1 / 待支付。
        $master->refresh();
        $this->assertSame(1, (int) $master->merge_role);
        $this->assertSame(InvoiceStatus::UNPAID, (int) $master->status);
        $this->assertSame('150.00', (string) $master->amount);
        $this->assertSame('12.50', (string) $master->discount);
        $this->assertSame('6.00', (string) $master->member_discount_amount);
        $this->assertSame('0.00', (string) $master->paid_amount);
        $this->assertSame('2030-01-01', $master->due_date?->toDateString());
        $this->assertSame('new', (string) $master->type);
        $this->assertSame('CNY', (string) $master->currency);
        $this->assertSame('合并账单，含 2 张子账单', (string) $master->remark);
        $this->assertNull($master->merged_into_id);

        // 子账单：写关联、金额列不变。
        $invoiceA->refresh();
        $invoiceB->refresh();
        $this->assertSame((int) $master->id, (int) $invoiceA->merged_into_id);
        $this->assertSame((int) $master->id, (int) $invoiceB->merged_into_id);
        $this->assertNotNull($invoiceA->merged_at);
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoiceA->status);
        $this->assertSame('100.00', (string) $invoiceA->amount);

        // 关联快照：child 唯一、金额快照。
        $items = InvoiceMergeItem::query()->where('master_invoice_id', (int) $master->id)->orderBy('child_invoice_id')->get();
        $this->assertSame(2, $items->count());
        $this->assertSame('100.00', (string) $items->firstWhere('child_invoice_id', (int) $invoiceA->id)?->amount_snapshot);
        $this->assertSame('50.00', (string) $items->firstWhere('child_invoice_id', (int) $invoiceB->id)?->amount_snapshot);
        $this->assertSame((string) $invoiceA->invoice_no, (string) $items->firstWhere('child_invoice_id', (int) $invoiceA->id)?->child_invoice_no);
        $this->assertSame((string) $invoiceB->invoice_no, (string) $items->firstWhere('child_invoice_id', (int) $invoiceB->id)?->child_invoice_no);
    }

    public function test_single_or_empty_selection_is_rejected(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 100.00);

        try {
            $this->service->merge((int) $user->id, [(int) $invoice->id], 1);
            $this->fail('单张账单合并应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('至少选择两张', $exception->getMessage());
        }

        try {
            $this->service->merge((int) $user->id, [], 1);
            $this->fail('空选择合并应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('至少选择两张', $exception->getMessage());
        }

        $this->assertSame(0, InvoiceMergeItem::query()->count());
    }

    public function test_cross_user_invoices_are_rejected(): void
    {
        $userA = $this->makeUser('user-a');
        $userB = $this->makeUser('user-b');
        $invoiceA = $this->makeInvoice($userA, 100.00);
        $invoiceB = $this->makeInvoice($userB, 50.00);

        try {
            $this->service->merge((int) $userA->id, [(int) $invoiceA->id, (int) $invoiceB->id], 1);
            $this->fail('跨用户合并应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('不属于该用户', $exception->getMessage());
        }

        $invoiceA->refresh();
        $this->assertNull($invoiceA->merged_into_id);
    }

    public function test_cross_currency_invoices_are_rejected(): void
    {
        $user = $this->makeUser();
        $invoiceA = $this->makeInvoice($user, 100.00, ['currency' => 'CNY']);
        $invoiceB = $this->makeInvoice($user, 50.00, ['currency' => 'USD']);

        try {
            $this->service->merge((int) $user->id, [(int) $invoiceA->id, (int) $invoiceB->id], 1);
            $this->fail('跨币种合并应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('币种不一致', $exception->getMessage());
        }

        $invoiceA->refresh();
        $invoiceB->refresh();
        $this->assertNull($invoiceA->merged_into_id);
        $this->assertNull($invoiceB->merged_into_id);
    }

    public function test_paid_invoice_in_selection_is_rejected(): void
    {
        $user = $this->makeUser();
        $unpaid = $this->makeInvoice($user, 100.00);
        $paid = $this->makeInvoice($user, 50.00, ['status' => InvoiceStatus::PAID, 'paid_amount' => 50.00]);

        try {
            $this->service->merge((int) $user->id, [(int) $unpaid->id, (int) $paid->id], 1);
            $this->fail('含已支付账单的合并应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('不是待支付状态', $exception->getMessage());
        }

        $unpaid->refresh();
        $this->assertNull($unpaid->merged_into_id);
    }

    public function test_already_merged_invoice_is_rejected_again(): void
    {
        $user = $this->makeUser();
        $invoiceA = $this->makeInvoice($user, 100.00);
        $invoiceB = $this->makeInvoice($user, 50.00);
        $invoiceC = $this->makeInvoice($user, 30.00);

        $this->service->merge((int) $user->id, [(int) $invoiceA->id, (int) $invoiceB->id], 1);

        try {
            $this->service->merge((int) $user->id, [(int) $invoiceA->id, (int) $invoiceC->id], 1);
            $this->fail('已合并账单重复合并应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('已被合并', $exception->getMessage());
        }
    }

    public function test_master_invoice_cannot_be_merged_as_child(): void
    {
        $user = $this->makeUser();
        $invoiceA = $this->makeInvoice($user, 100.00);
        $invoiceB = $this->makeInvoice($user, 50.00);
        $invoiceC = $this->makeInvoice($user, 30.00);

        $master = $this->service->merge((int) $user->id, [(int) $invoiceA->id, (int) $invoiceB->id], 1);

        try {
            $this->service->merge((int) $user->id, [(int) $master->id, (int) $invoiceC->id], 1);
            $this->fail('总账单作为子账单合并应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('不支持再次合并', $exception->getMessage());
        }
    }

    public function test_nonexistent_invoice_is_rejected(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 100.00);

        try {
            $this->service->merge((int) $user->id, [(int) $invoice->id, 999999999], 1);
            $this->fail('不存在的账单应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('不存在', $exception->getMessage());
        }
    }

    private function makeUser(string $prefix = 'merge'): User
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
