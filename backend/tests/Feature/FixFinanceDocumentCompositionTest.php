<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Models\FinanceLedgerEntry;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\FinanceDocumentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 审查修复回归（M-56）：FinanceDocumentService::assertBusinessPaymentComposition
 * 的余额侧只认 source_id = 账单 ID。
 *
 * 历史兜底会同时匹配 source_id = order_id，但 orders.id 与 invoices.id 是独立自增序列，
 * 同用户名下 id 恰好等于 order_id 的「他人账单」台账会被误算进来，导致断言失败、
 * 整个入账事务回滚。全仓核实没有写入方把 order_id 写进台账 source_id，兜底已删除。
 *
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class FixFinanceDocumentCompositionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_order_id_ledger_row_of_other_invoice_does_not_block_payment_composition(): void
    {
        $user = $this->makeUser();
        $order = $this->makeOrder($user, 100.00);
        $invoice = $this->makeInvoice($user, 100.00, ['order_id' => (int) $order->id]);
        if ((int) $invoice->id === (int) $order->id) {
            // 全新测试库上 orders/invoices 自增序列可能重合；再建一张即可保证 id 不同。
            $invoice = $this->makeInvoice($user, 100.00, ['order_id' => (int) $order->id]);
        }
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();

        $payment = $this->makeSuccessPayment($user, $invoice, 100.00);

        // id 碰撞构造：同用户名下另一张账单的台账行 source_id 恰好等于本单 order_id。
        // orders/invoices 自增序列独立，这种碰撞在真实库中随时可能发生。
        $this->assertNotSame((int) $invoice->id, (int) $order->id, '前置条件：order.id 与 invoice.id 必须不同');
        FinanceLedgerEntry::query()->create([
            'entry_no' => 'FLCOLLIDE'.date('YmdHis').mt_rand(100000, 999999),
            'account_id' => null,
            'user_id' => (int) $user->id,
            'account_type' => 'cash',
            'event_type' => FinanceLedgerEventType::INVOICE_PAYMENT,
            'change_amount' => '-30.00',
            'balance_after' => '0.00',
            'currency' => 'CNY',
            'source_type' => 'invoice',
            'source_id' => (int) $order->id,
            'remark' => 'id 碰撞的他人账单台账',
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);

        // 修复前：余额侧多算 30，断言失败抛「订单、账单与支付金额不一致」，入账被阻断。
        $record = app(FinanceDocumentService::class)->recordThirdPartyPayment($payment, $invoice);

        $this->assertSame((int) $invoice->id, (int) $record->invoice_id);
        $this->assertSame('100.00', (string) $record->amount);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'doc-composition-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'doc-composition-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeOrder(User $user, float $amount): Order
    {
        return Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => $amount,
            'status' => OrderStatus::PAID,
            'paid_amount' => $amount,
            'paid_at' => now(),
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
            'type' => InvoiceType::NEW_PURCHASE,
            'amount' => $amount,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
        ], $overrides));
    }

    private function makeSuccessPayment(User $user, Invoice $invoice, float $amount): Payment
    {
        return Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => $amount,
            'status' => PaymentStatus::SUCCESS,
            'trade_no' => 'TRADE'.date('YmdHis').mt_rand(100000, 999999),
            'paid_at' => now(),
            'callback_raw' => ['source' => 'alipay_precreate'],
        ]);
    }
}
