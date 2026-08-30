<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Models\AccountTransaction;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Services\Finance\PaymentService;
use App\Services\Order\PaidOrderBusinessFlowDispatcher;
use App\Services\User\AccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 台账 source 错配回归：payOrderByBalance 曾把订单 ID 写进 source_type='invoice'
 * 的台账行，orders/invoices 自增序列独立会导致台账反查挂上无关账单。
 * 修复后必须写账单 ID（含 origin_id），payByBalance 主路径不受影响。
 * 使用 DatabaseTransactions，测试结束回滚；履约派发已 mock 隔离。
 */
class AutoRenewLedgerSourceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_balance_paid_order_ledger_points_to_invoice(): void
    {
        $this->mock(PaidOrderBusinessFlowDispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatchPaidInvoice')->andReturnNull();
        });

        $user = $this->makeUser();
        app(AccountService::class)->setCashBalance($user, 100.00);

        $order = $this->makeOrder($user);
        $invoice = $this->makeInvoice($user, $order);

        app(PaymentService::class)->payOrderByBalance($order, $user, ['auto_renew' => true]);

        $txn = AccountTransaction::query()
            ->where('user_id', $user->id)
            ->where('event_type', FinanceLedgerEventType::INVOICE_PAYMENT)
            ->first();

        $this->assertNotNull($txn);
        $this->assertSame('invoice', (string) $txn->source_type);
        $this->assertSame((int) $invoice->id, (int) $txn->source_id, '台账 source_id 必须挂账单 ID 而非订单 ID');
        $this->assertSame((int) $invoice->id, (int) $txn->origin_id);
        $this->assertSame('-40.00', (string) $txn->change_amount);
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->fresh()->status);
        $this->assertSame(OrderStatus::PAID, (int) $order->fresh()->status);
    }

    public function test_pay_by_balance_ledger_source_stays_invoice_id(): void
    {
        // 回归保护：主路径 payByBalance 本就写账单 ID，本次改动不得破坏。
        $this->mock(PaidOrderBusinessFlowDispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatchPaidInvoice')->andReturnNull();
        });

        $user = $this->makeUser();
        app(AccountService::class)->setCashBalance($user, 100.00);

        $order = $this->makeOrder($user);
        $invoice = $this->makeInvoice($user, $order);

        app(PaymentService::class)->payByBalance($invoice, $user);

        $txn = AccountTransaction::query()
            ->where('user_id', $user->id)
            ->where('event_type', FinanceLedgerEventType::INVOICE_PAYMENT)
            ->first();

        $this->assertSame('invoice', (string) $txn->source_type);
        $this->assertSame((int) $invoice->id, (int) $txn->source_id);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'ledger'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'renew-ledger-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeOrder(User $user): Order
    {
        return Order::query()->create([
            'order_no' => 'OT'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'renew',
            'amount' => 40.00,
            'status' => OrderStatus::PENDING,
        ]);
    }

    private function makeInvoice(User $user, Order $order): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'renew',
            'amount' => 40.00,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
        ]);
    }
}
