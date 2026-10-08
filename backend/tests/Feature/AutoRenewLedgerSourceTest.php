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
use App\Services\Finance\BalanceInvoicePaymentService;
use App\Services\Order\PaidOrderBusinessFlowDispatcher;
use App\Services\User\AccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 台账 source 回归：消费-余额支付流的台账行 source_type='invoice' 时必须挂
 * 账单 ID（含 origin_id）——orders/invoices 自增序列独立，挂订单 ID 会让
 * 台账反查到无关账单。订单优先路径已移除（方案 2 职责重划），仅保留主路径
 * payByBalance 的回归保护。
 * 使用 DatabaseTransactions，测试结束回滚；履约派发已 mock 隔离。
 */
class AutoRenewLedgerSourceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pay_by_balance_ledger_source_stays_invoice_id(): void
    {
        $this->mock(PaidOrderBusinessFlowDispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatchPaidInvoice')->andReturnNull();
        });

        $user = $this->makeUser();
        app(AccountService::class)->setCashBalance($user, 100.00);

        $order = $this->makeOrder($user);
        $invoice = $this->makeInvoice($user, $order);

        app(BalanceInvoicePaymentService::class)->payByBalance($invoice, $user);

        $txn = AccountTransaction::query()
            ->where('user_id', $user->id)
            ->where('event_type', FinanceLedgerEventType::INVOICE_PAYMENT)
            ->first();

        $this->assertNotNull($txn);
        $this->assertSame('invoice', (string) $txn->source_type);
        $this->assertSame((int) $invoice->id, (int) $txn->source_id);
        $this->assertSame((int) $invoice->id, (int) $txn->origin_id);
        $this->assertSame('-40.00', (string) $txn->change_amount);
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->fresh()->status);
        $this->assertSame(OrderStatus::PAID, (int) $order->fresh()->status);
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
