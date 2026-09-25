<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\RechargeRecord;
use App\Models\User;
use App\Services\Admin\Rbac\PermissionCatalogService;
use App\Services\Admin\V2\AdminManualEntryV2Service;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 管理端补录账单：钱在系统外，系统仅记账。
 * 语义：Invoice(manual) + Payment(manual) 两步入账；不动余额、无台账、无充值记录。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class AdminManualInvoiceEntryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_manual_entry_creates_paid_manual_invoice_and_payment(): void
    {
        $user = $this->makeUser();

        $result = $this->service()->createManualInvoice($user, [
            'amount' => '88.50',
            'paid_at' => now()->format('Y-m-d H:i:s'),
            'trade_no' => 'OFFLINE-'.uniqid(),
            'payment_gateway' => 'alipay',
            'remark' => '线下收款补录',
        ], $this->context());

        $this->assertSame('completed', $result['status']);

        $invoice = Invoice::query()->findOrFail((int) $result['detail']['invoice']['id']);
        $this->assertSame(InvoiceType::MANUAL, (string) $invoice->type);
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertSame('88.50', number_format((float) $invoice->amount, 2, '.', ''));
        $this->assertSame('线下收款补录', (string) $invoice->remark);
        $this->assertNull($invoice->order_id);

        /** @var Payment $payment */
        $payment = Payment::query()->where('invoice_id', $invoice->id)->where('status', PaymentStatus::SUCCESS)->firstOrFail();
        $this->assertSame(PaymentGatewayCode::MANUAL, $payment->gatewayKey());
        $callbackRaw = (array) $payment->callback_raw;
        $this->assertSame('admin_manual_entry', $callbackRaw['source']);
        $this->assertSame('alipay', $callbackRaw['payment_gateway']);
        $this->assertSame(1, $callbackRaw['operator_id']);

        // 操作日志上下文同步记录所选支付方式
        $log = DB::table('activity_logs')->where('action', 'invoice.payment.mark_paid')->where('subject_id', $invoice->id)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('alipay', (string) $log->context);
    }

    public function test_manual_entry_defaults_payment_gateway_to_manual(): void
    {
        $user = $this->makeUser();

        $result = $this->service()->createManualInvoice($user, [
            'amount' => '50.00',
            'remark' => '未选择支付方式时保持旧行为',
        ], $this->context());

        $invoice = Invoice::query()->findOrFail((int) $result['detail']['invoice']['id']);

        /** @var Payment $payment */
        $payment = Payment::query()->where('invoice_id', $invoice->id)->where('status', PaymentStatus::SUCCESS)->firstOrFail();
        $callbackRaw = (array) $payment->callback_raw;
        $this->assertSame('manual', $callbackRaw['payment_gateway']);
    }

    public function test_manual_entry_does_not_touch_balance_or_ledger(): void
    {
        $user = $this->makeUser();

        $result = $this->service()->createManualInvoice($user, [
            'amount' => '100.00',
            'remark' => '不动余额校验',
        ], $this->context());

        $invoiceId = (int) $result['detail']['invoice']['id'];

        $this->assertSame(0, RechargeRecord::query()->where('user_id', (int) $user->id)->count());
        $this->assertNull(Invoice::query()->findOrFail($invoiceId)->order_id);
    }

    public function test_duplicate_trade_no_for_same_user_rejected(): void
    {
        $user = $this->makeUser();
        $tradeNo = 'DUP-'.uniqid();

        $this->service()->createManualInvoice($user, [
            'amount' => '50.00',
            'trade_no' => $tradeNo,
            'remark' => '第一笔',
        ], $this->context());

        $this->expectException(BusinessException::class);
        $this->service()->createManualInvoice($user, [
            'amount' => '60.00',
            'trade_no' => $tradeNo,
            'remark' => '同交易号第二笔',
        ], $this->context());
    }

    /**
     * 空交易号的补录没有业务唯一键：同内容重复提交（双击/网络重试）在短窗内必须被拦截，
     * 只允许产生一笔已付账单。
     */
    public function test_duplicate_submission_without_trade_no_rejected_within_window(): void
    {
        $user = $this->makeUser();
        $payload = [
            'amount' => '66.00',
            'payment_gateway' => 'alipay',
            'remark' => '双击防护校验',
        ];

        $first = $this->service()->createManualInvoice($user, $payload, $this->context());
        $this->assertSame('completed', $first['status']);

        try {
            $this->service()->createManualInvoice($user, $payload, $this->context());
            $this->fail('Expected BusinessException for duplicated manual entry without trade_no');
        } catch (BusinessException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(
            1,
            Invoice::query()
                ->where('user_id', (int) $user->id)
                ->where('type', InvoiceType::MANUAL)
                ->count()
        );
    }

    public function test_same_amount_with_different_remark_is_allowed_without_trade_no(): void
    {
        $user = $this->makeUser();

        $this->service()->createManualInvoice($user, [
            'amount' => '66.00',
            'payment_gateway' => 'alipay',
            'remark' => '第一笔内容',
        ], $this->context());

        $second = $this->service()->createManualInvoice($user, [
            'amount' => '66.00',
            'payment_gateway' => 'alipay',
            'remark' => '第二笔不同备注',
        ], $this->context());

        $this->assertSame('completed', $second['status']);
        $this->assertSame(
            2,
            Invoice::query()
                ->where('user_id', (int) $user->id)
                ->where('type', InvoiceType::MANUAL)
                ->count()
        );
    }

    public function test_non_positive_amount_rejected(): void
    {
        $this->expectException(BusinessException::class);
        $this->service()->createManualInvoice($this->makeUser(), [
            'amount' => '0',
            'remark' => '零金额',
        ], $this->context());
    }

    public function test_manual_entry_permissions_not_defaulted_but_catalogued(): void
    {
        $defaults = AdminPermissions::adminDefaultPermissions();

        $this->assertNotContains('invoice.manual_entry', $defaults);
        $this->assertNotContains('order.manual_entry', $defaults);
        $this->assertNotContains('invoice.manual_entry', AdminPermissions::impliedPermissions('invoice.manage'));
        $this->assertContains('invoice.manual_entry', app(PermissionCatalogService::class)->validKeys());
        $this->assertContains('order.manual_entry', app(PermissionCatalogService::class)->validKeys());
    }

    private function service(): AdminManualEntryV2Service
    {
        return app(AdminManualEntryV2Service::class);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'manual-invoice-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'manual-entry-tester',
            'total_sales_amount' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [
            'operator_id' => 1,
            'operator_name' => 'admin-test',
            'trace_id' => 'test-'.uniqid(),
            'ip_address' => '127.0.0.1',
        ];
    }
}
