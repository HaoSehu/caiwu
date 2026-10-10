<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Exceptions\BusinessException;
use App\Models\AccountTransaction;
use App\Models\Invoice;
use App\Models\RechargeRecord;
use App\Models\User;
use App\Services\Admin\Rbac\PermissionCatalogService;
use App\Services\Admin\V2\AdminManualEntryV2Service;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 财务 B2 批次 2：补录账单红字冲抵（已入账补录的唯一纠错通道）。
 * 语义：复用 refund 类型生成负数红字账单并关联原单；同原单至多一张（幂等重放拦截）；
 * 不触碰原单、不动余额与台账；生成/执行各记一条 activity_logs。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class AdminManualReversalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reverse_manual_invoice_creates_negative_refund_invoice_with_audit(): void
    {
        $user = $this->makeUser();
        $original = $this->makeManualEntry($user, '88.50');

        $result = $this->service()->reverseManualInvoice($user, (int) $original->id, [
            'remark' => '线上收款登记错误，需红字冲抵',
        ], $this->context());

        $this->assertSame('completed', $result['status']);
        $this->assertFalse((bool) $result['detail']['already_reversed']);

        $reversal = Invoice::query()
            ->where('origin_invoice_id', (int) $original->id)
            ->firstOrFail();
        $this->assertSame(InvoiceType::REFUND, (string) $reversal->type);
        $this->assertSame(InvoiceStatus::PAID, (int) $reversal->status);
        $this->assertSame('-88.50', number_format((float) $reversal->amount, 2, '.', ''));
        $this->assertSame('-88.50', number_format((float) $reversal->paid_amount, 2, '.', ''));
        $this->assertSame('线上收款登记错误，需红字冲抵', (string) $reversal->remark);

        // 金额守恒：原单 + 红字单净值归零；原单不被触碰。
        $this->assertEqualsWithDelta(
            0.00,
            (float) $original->refresh()->amount + (float) $reversal->amount,
            0.001
        );
        $this->assertSame(InvoiceStatus::PAID, (int) $original->refresh()->status);
        $this->assertSame('88.50', number_format((float) $original->paid_amount, 2, '.', ''));

        // 补录账单为系统外收款的纯记账凭证：不产生任何余额流水或充值记录。
        $this->assertSame(0, AccountTransaction::query()->where('user_id', (int) $user->id)->count());
        $this->assertSame(0, RechargeRecord::query()->where('user_id', (int) $user->id)->count());

        // 审计：生成与执行各一条。
        $this->assertSame(1, DB::table('activity_logs')
            ->where('action', 'finance.manual_reversal.created')
            ->where('subject_id', (int) $reversal->id)
            ->count());
        $this->assertSame(1, DB::table('activity_logs')
            ->where('action', 'finance.manual_reversal.executed')
            ->where('subject_id', (int) $original->id)
            ->count());
    }

    public function test_reverse_manual_invoice_is_idempotent_on_replay(): void
    {
        $user = $this->makeUser();
        $original = $this->makeManualEntry($user, '60.00');

        $first = $this->service()->reverseManualInvoice($user, (int) $original->id, ['remark' => '首次冲抵'], $this->context());
        $this->assertFalse((bool) $first['detail']['already_reversed']);

        $replay = $this->service()->reverseManualInvoice($user, (int) $original->id, ['remark' => '重放冲抵'], $this->context());
        $this->assertTrue((bool) $replay['detail']['already_reversed']);
        $this->assertSame((int) $first['id'], (int) $replay['id']);

        // 重放不产生第二张红字单。
        $this->assertSame(1, Invoice::query()
            ->where('origin_invoice_id', (int) $original->id)
            ->where('type', InvoiceType::REFUND)
            ->count());
    }

    public function test_reverse_rejects_non_manual_invoice(): void
    {
        $user = $this->makeUser();
        $invoice = Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => InvoiceType::NEW_PURCHASE,
            'amount' => 50.00,
            'paid_amount' => 50.00,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('仅支持对补录账单发起红字冲抵');
        $this->service()->reverseManualInvoice($user, (int) $invoice->id, ['remark' => '非补录单'], $this->context());
    }

    public function test_reverse_rejects_unpaid_invoice(): void
    {
        $user = $this->makeUser();
        $invoice = Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => InvoiceType::MANUAL,
            'amount' => 50.00,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('当前账单状态不支持红字冲抵');
        $this->service()->reverseManualInvoice($user, (int) $invoice->id, ['remark' => '未入账'], $this->context());
    }

    public function test_reverse_permission_is_catalogued_but_not_defaulted(): void
    {
        $defaults = AdminPermissions::adminDefaultPermissions();

        $this->assertNotContains(AdminPermissions::FINANCE_MANUAL_REVERSAL, $defaults);
        $this->assertNotContains(
            AdminPermissions::FINANCE_MANUAL_REVERSAL,
            AdminPermissions::impliedPermissions(AdminPermissions::INVOICE_MANAGE)
        );
        $this->assertContains(
            AdminPermissions::FINANCE_MANUAL_REVERSAL,
            app(PermissionCatalogService::class)->validKeys()
        );
    }

    private function service(): AdminManualEntryV2Service
    {
        return app(AdminManualEntryV2Service::class);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'manual-reversal-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'manual-reversal-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeManualEntry(User $user, string $amount): Invoice
    {
        $result = $this->service()->createManualInvoice($user, [
            'amount' => $amount,
            'remark' => '线下收款补录',
        ], $this->context());

        return Invoice::query()->findOrFail((int) $result['detail']['invoice']['id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [
            'operator_id' => 1,
            'operator_name' => 'admin-test',
            'trace_id' => 'reversal-'.uniqid(),
            'ip_address' => '127.0.0.1',
        ];
    }
}
