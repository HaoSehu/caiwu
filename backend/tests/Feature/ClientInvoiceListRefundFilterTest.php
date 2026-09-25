<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 客户端账单列表「已退款」筛选必须经 HTTP 层生效：
 * HTTP 查询串带来的是字符串（?status=5），修复前与 InvoiceStatus::REFUNDED(int)
 * 严格比较失败，「账单已退款 或 支付已退款」的设计分支在真实请求路径上永不执行。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class ClientInvoiceListRefundFilterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_http_refund_filter_matches_refunded_invoice_and_refunded_payment(): void
    {
        $user = $this->makeUser();
        $refundedInvoice = $this->makeInvoice($user, InvoiceStatus::REFUNDED);
        $paidInvoiceWithRefundedPayment = $this->makeInvoice($user, InvoiceStatus::PAID);
        $this->makePayment($user, $paidInvoiceWithRefundedPayment, PaymentStatus::REFUNDED);

        Sanctum::actingAs($user);

        $ids = collect($this->getJson('/api/v2/client/invoices?status=5')->assertOk()->json('data.list'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $this->assertContains((int) $refundedInvoice->id, $ids);
        // 展示口径中「支付已退款」的账单也标记为已退款，筛选必须能查到它
        $this->assertContains((int) $paidInvoiceWithRefundedPayment->id, $ids);
    }

    public function test_http_paid_filter_stays_exact(): void
    {
        $user = $this->makeUser();
        $paidInvoice = $this->makeInvoice($user, InvoiceStatus::PAID);
        $refundedInvoice = $this->makeInvoice($user, InvoiceStatus::REFUNDED);

        Sanctum::actingAs($user);

        $ids = collect($this->getJson('/api/v2/client/invoices?status=1')->assertOk()->json('data.list'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $this->assertContains((int) $paidInvoice->id, $ids);
        $this->assertNotContains((int) $refundedInvoice->id, $ids);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'refund-filter-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'refund-filter-tester',
            'total_sales_amount' => 0,
            'status' => 1,
        ]);
    }

    private function makeInvoice(User $user, int $status): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => 100.00,
            'paid_amount' => $status === InvoiceStatus::UNPAID ? 0 : 100.00,
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::UNPAID ? null : now(),
        ]);
    }

    private function makePayment(User $user, Invoice $invoice, int $status): Payment
    {
        return Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => 100.00,
            'status' => $status,
            'paid_at' => now(),
        ]);
    }
}
