<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Services\Finance\InvoiceOrderReconciliationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 对账修复的状态回退护栏：账单已退款/已取消时，即使订单仍为已付，
 * 也不能被自动改回已支付（资金已退回或已作废），只进人工审查报告；
 * 「订单已付 + 账单待支付」的自动回填行为保持不变。
 *
 * 说明：测试库存在大量历史 mismatch 残留，直接跑全量 reconcile/inspect
 * 会触碰无关数据且样本被 limit 截断，这里按单行 pair 精确驱动修复逻辑。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class InvoiceReconcileRefundGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_refunded_invoice_pair_is_reported_manual_review_and_not_repaired(): void
    {
        $user = $this->makeUser();
        [$order, $invoice] = $this->makePaidOrderWithInvoice($user, InvoiceStatus::REFUNDED);
        $pair = $this->makePair($order, $invoice, InvoiceStatus::REFUNDED);
        $service = app(InvoiceOrderReconciliationService::class);

        $payload = $this->invokePayload($service, $pair);
        $this->assertSame('manual_review', $payload['suggested_action']);
        $this->assertSame(0, $this->invokeRepair($service, $pair));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::REFUNDED, (int) $invoice->status);
    }

    public function test_cancelled_invoice_pair_is_not_repaired(): void
    {
        $user = $this->makeUser();
        [$order, $invoice] = $this->makePaidOrderWithInvoice($user, InvoiceStatus::CANCELLED);
        $pair = $this->makePair($order, $invoice, InvoiceStatus::CANCELLED);
        $service = app(InvoiceOrderReconciliationService::class);

        $this->assertSame('manual_review', $this->invokePayload($service, $pair)['suggested_action']);
        $this->assertSame(0, $this->invokeRepair($service, $pair));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::CANCELLED, (int) $invoice->status);
    }

    public function test_unpaid_invoice_pair_still_repaired_from_paid_order(): void
    {
        $user = $this->makeUser();
        [$order, $invoice] = $this->makePaidOrderWithInvoice($user, InvoiceStatus::UNPAID);
        $pair = $this->makePair($order, $invoice, InvoiceStatus::UNPAID);
        $service = app(InvoiceOrderReconciliationService::class);

        $this->assertSame('sync_invoice_status_from_paid_order', $this->invokePayload($service, $pair)['suggested_action']);
        $this->assertSame(1, $this->invokeRepair($service, $pair));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertSame('100.00', number_format((float) $invoice->paid_amount, 2, '.', ''));
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame(OrderStatus::PAID, (int) $order->status);
    }

    /**
     * @return array{0: Order, 1: Invoice}
     */
    private function makePaidOrderWithInvoice(User $user, int $invoiceStatus): array
    {
        $order = Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => 100.00,
            'status' => OrderStatus::PAID,
            'paid_amount' => 100.00,
            'paid_at' => now(),
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'new',
            'amount' => 100.00,
            'paid_amount' => $invoiceStatus === InvoiceStatus::PAID ? 100.00 : 0.00,
            'status' => $invoiceStatus,
            'paid_at' => $invoiceStatus === InvoiceStatus::PAID ? now() : null,
        ]);

        return [$order, $invoice];
    }

    /**
     * 构造与 statusMismatches select 同构的行对象。
     */
    private function makePair(Order $order, Invoice $invoice, int $invoiceStatus): object
    {
        return (object) [
            'order_id' => (int) $order->id,
            'order_no' => (string) $order->order_no,
            'order_status' => OrderStatus::PAID,
            'order_paid_amount' => '100.00',
            'order_paid_at' => now()->toDateTimeString(),
            'invoice_id' => (int) $invoice->id,
            'invoice_no' => (string) $invoice->invoice_no,
            'invoice_status' => $invoiceStatus,
            'invoice_amount' => '100.00',
            'invoice_paid_amount' => $invoiceStatus === InvoiceStatus::UNPAID ? null : '100.00',
            'invoice_paid_at' => $invoiceStatus === InvoiceStatus::UNPAID ? null : now()->toDateTimeString(),
        ];
    }

    private function invokePayload(InvoiceOrderReconciliationService $service, object $pair): array
    {
        $method = new ReflectionMethod($service, 'statusMismatchPayload');
        $method->setAccessible(true);

        return (array) $method->invoke($service, $pair);
    }

    private function invokeRepair(InvoiceOrderReconciliationService $service, object $pair): int
    {
        $method = new ReflectionMethod($service, 'repairStatusMismatch');
        $method->setAccessible(true);

        return (int) $method->invoke($service, $pair);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'reconcile-guard-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'reconcile-guard-tester',
            'total_sales_amount' => 0,
            'status' => 1,
        ]);
    }
}
