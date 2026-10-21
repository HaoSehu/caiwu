<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\FinanceLedgerEntry;
use App\Models\FinanceRefund;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\InvoiceRefundService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsFinanceBalance;
use Tests\TestCase;

/**
 * 拆分支付/分次退款与「系统外收款」退款闸的回归：
 * 1) 账单级累计可退额：2 笔支付 50+50 分两次退满才收口，中间态保持 PAID；
 * 2) 每次退款（含部分）同步 invoices/orders 的退款投影，订单侧不再显示 0；
 * 3) gateway=manual（补录订单/手工入账）的支付单不得作为主退款支付单，凭空入账必须拒绝。
 *
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class RefundSplitPaymentTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsFinanceBalance;

    public function test_split_payment_refund_is_not_closed_after_first_half(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 0.00);

        $order = $this->makeOrder($user, 100.00);
        $invoice = $this->makeInvoice($user, 100.00, ['order_id' => $order->id]);
        $this->makeSuccessPayment($user, $invoice, 50.00);
        $this->makeSuccessPayment($user, $invoice, 50.00);
        $order->forceFill(['status' => OrderStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();

        // 第一次退 50：账单/订单必须保持 PAID，投影累计 50。
        $result = app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice, ['amount' => 50.00]);
        $this->assertFalse((bool) ($result['already_refunded'] ?? true));

        $invoice->refresh();
        $order->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status, '拆分支付退一半不得提前收口');
        $this->assertSame(OrderStatus::PAID, (int) $order->status);
        $this->assertEqualsWithDelta(50.00, (float) $invoice->refund_amount, 0.001);
        $this->assertEqualsWithDelta(50.00, (float) $order->refund_amount, 0.001);
        $this->assertEqualsWithDelta(50.00, $this->financeBalance($user), 0.001);

        // 第二次退剩余 50：累计 100 才收口。
        app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice, ['amount' => 50.00]);

        $invoice->refresh();
        $order->refresh();
        $this->assertSame(InvoiceStatus::REFUNDED, (int) $invoice->status);
        $this->assertSame(OrderStatus::REFUNDED, (int) $order->status);
        $this->assertEqualsWithDelta(100.00, (float) $invoice->refund_amount, 0.001);
        $this->assertEqualsWithDelta(100.00, (float) $order->refund_amount, 0.001);
        $this->assertEqualsWithDelta(100.00, $this->financeBalance($user), 0.001);
        $this->assertSame(2, FinanceRefund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_partial_refund_writes_invoice_and_order_projection(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 0.00);

        $order = $this->makeOrder($user, 100.00);
        $invoice = $this->makeInvoice($user, 100.00, ['order_id' => $order->id]);
        $this->makeSuccessPayment($user, $invoice, 100.00);
        $order->forceFill(['status' => OrderStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();

        app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice, ['amount' => 30.00]);

        $invoice->refresh();
        $order->refresh();
        $this->assertEqualsWithDelta(30.00, (float) $invoice->refund_amount, 0.001, '部分退款必须回写账单投影');
        $this->assertEqualsWithDelta(30.00, (float) $order->refund_amount, 0.001, '部分退款必须回写订单投影');
        $this->assertNotNull($order->refunded_at);
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
    }

    public function test_manual_gateway_payment_cannot_be_refunded_to_balance(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 0.00);

        // 补录订单：系统外线下收款，Payment.gateway 固定 manual。
        $order = $this->makeOrder($user, 100.00);
        $invoice = $this->makeInvoice($user, 100.00, ['order_id' => $order->id]);
        $this->makeManualPayment($user, $invoice, 100.00);
        $order->forceFill(['status' => OrderStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();

        try {
            app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice);
            $this->fail('补录订单（manual 网关）退款必须被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('系统外收款', $exception->getMessage());
        }

        // 余额未变、无退款单、无退款台账。
        $this->assertEqualsWithDelta(0.00, $this->financeBalance($user), 0.001);
        $this->assertSame(0, FinanceRefund::query()->where('invoice_id', (int) $invoice->id)->count());
        $this->assertSame(0, FinanceLedgerEntry::query()
            ->where('user_id', (int) $user->id)
            ->where('event_type', FinanceLedgerEventType::INVOICE_REFUND)
            ->count());
    }

    private function makeUser(string $prefix = 'split'): User
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
            'amount' => $amount,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
        ], $overrides));
    }

    private function makeOrder(User $user, float $amount): Order
    {
        return Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => $amount,
            'status' => OrderStatus::PENDING,
        ]);
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

    private function makeManualPayment(User $user, Invoice $invoice, float $amount): Payment
    {
        $payment = new Payment([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'gateway' => PaymentGatewayCode::MANUAL,
            'amount' => $amount,
            'status' => PaymentStatus::SUCCESS,
            'trade_no' => 'MANUAL'.date('YmdHis').mt_rand(100000, 999999),
            'paid_at' => now(),
            'callback_raw' => ['source' => 'admin_manual_entry', 'payment_gateway' => 'bank_transfer'],
        ]);
        $payment->allowNonThirdPartyGateway = true;
        $payment->save();

        return $payment;
    }
}
