<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\AccountTransaction;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ReferralReward;
use App\Models\Refund;
use App\Models\User;
use App\Services\Finance\BalanceInvoicePaymentService;
use App\Services\Finance\InvoiceRefundService;
use App\Services\User\AccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 账单退款到余额主流程的行为快照（refundInvoiceToBalance 此前零覆盖，
 * 是系统内唯一保留的自动冲正能力的公共入口）：
 * 全款/部分退款 -> 余额回加 + INVOICE_REFUND 台账 + Refund 记录 + 红字账单
 * + 状态机收口（Payment/Invoice/Order -> REFUNDED）+ 推广奖励回退；
 * 充值/补录账单拒退、已转余额账单拒退文案区分、重放幂等。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class RefundInvoiceToBalanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_full_refund_balance_paid_invoice_writes_red_documents(): void
    {
        $user = $this->makeUser();
        $accounts = app(AccountService::class);
        $accounts->setCashBalance($user, 100.00);

        $invoice = $this->makeInvoice($user, 40.00);
        app(BalanceInvoicePaymentService::class)->payByBalance($invoice, $user);
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->refresh()->status);
        $this->assertEqualsWithDelta(60.00, $accounts->cashBalance($user), 0.001);
        $this->assertSame(1, $this->countLedger($user, FinanceLedgerEventType::INVOICE_PAYMENT));

        $result = app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice);

        $this->assertFalse((bool) ($result['already_refunded'] ?? true));
        $this->assertSame(0, (int) ($result['payment_id'] ?? -1));
        $this->assertGreaterThan(0, (int) ($result['refund_id'] ?? 0));

        // 余额回到支付前水平，台账一行精确挂账单。
        $this->assertEqualsWithDelta(100.00, $accounts->cashBalance($user), 0.001);
        $refundLog = AccountTransaction::query()
            ->where('user_id', (int) $user->id)
            ->where('event_type', FinanceLedgerEventType::INVOICE_REFUND)
            ->firstOrFail();
        $this->assertSame('invoice', (string) $refundLog->source_type);
        $this->assertSame((int) $invoice->id, (int) $refundLog->source_id);
        $this->assertEqualsWithDelta(40.00, (float) $refundLog->change_amount, 0.001);
        $this->assertEqualsWithDelta(100.00, (float) $refundLog->balance_after, 0.001);

        // Refund 记录 + 红字账单（负数金额、指向原单）。
        $refund = Refund::query()->where('invoice_id', (int) $invoice->id)->firstOrFail();
        $this->assertSame(Refund::STATUS_COMPLETED, (int) $refund->status);
        $this->assertSame('40.00', (string) $refund->amount);
        $this->assertSame('balance', (string) $refund->refund_method);
        $redInvoice = Invoice::query()->where('origin_invoice_id', (int) $invoice->id)->firstOrFail();
        $this->assertSame(InvoiceType::REFUND, (string) $redInvoice->type);
        $this->assertEqualsWithDelta(-40.00, (float) $redInvoice->amount, 0.001);
        $this->assertSame(InvoiceStatus::PAID, (int) $redInvoice->status);

        // 原账单收口为已退款。
        $this->assertSame(InvoiceStatus::REFUNDED, (int) $invoice->refresh()->status);
    }

    public function test_full_refund_gateway_paid_invoice_with_order_reverses_referral_reward(): void
    {
        $referrer = $this->makeUser('referrer');
        $payer = $this->makeUser('payer');
        $accounts = app(AccountService::class);
        $accounts->setCashBalance($payer, 0.00);
        $referrerAccount = $accounts->ensureAccount($referrer);
        $accounts->updateAccount($referrerAccount, ['referral_frozen_balance' => 10.00]);

        // 直接构造网关支付完成的账单/订单状态（入账主链路由 GatewayNotifySettlementTest 固化）。
        $order = $this->makeOrder($payer, 100.00);
        $invoice = $this->makeInvoice($payer, 100.00, ['order_id' => $order->id]);
        $payment = $this->makeSuccessPayment($payer, $invoice, 100.00);
        $order->forceFill(['status' => OrderStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();

        ReferralReward::query()->create([
            'referrer_user_id' => $referrer->id,
            'referred_user_id' => $payer->id,
            'invoice_id' => $invoice->id,
            'order_amount' => 100.00,
            'reward_rate' => 0.10,
            'reward_amount' => 10.00,
            'status' => ReferralReward::STATUS_FROZEN,
        ]);

        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->refresh()->status);
        $this->assertSame(OrderStatus::PAID, (int) $order->refresh()->status);

        $result = app(InvoiceRefundService::class)->refundInvoiceToBalance($payer, $invoice, [], [
            'operator_name' => '退款测试',
            'operator_id' => 1,
            'trace_id' => 'refund-test',
        ]);

        $this->assertFalse((bool) ($result['already_refunded'] ?? true));
        $this->assertSame((int) $payment->id, (int) ($result['payment_id'] ?? 0));

        // 三单收口：支付单/账单/订单全部 REFUNDED。
        $this->assertSame(PaymentStatus::REFUNDED, (int) $payment->refresh()->status);
        $this->assertSame(InvoiceStatus::REFUNDED, (int) $invoice->refresh()->status);
        $this->assertSame(OrderStatus::REFUNDED, (int) $order->refresh()->status);

        // 资金与红字单据。
        $this->assertEqualsWithDelta(100.00, $accounts->cashBalance($payer), 0.001);
        $this->assertSame(1, Refund::query()->where('invoice_id', (int) $invoice->id)->count());
        $redInvoice = Invoice::query()->where('origin_invoice_id', (int) $invoice->id)->firstOrFail();
        $this->assertEqualsWithDelta(-100.00, (float) $redInvoice->amount, 0.001);

        // 推广奖励回退：FROZEN -> REVERSED，推荐人冻结余额扣回。
        $reward = ReferralReward::query()->where('invoice_id', (int) $invoice->id)->firstOrFail();
        $this->assertSame(ReferralReward::STATUS_REVERSED, (int) $reward->status);
        $this->assertEqualsWithDelta(0.00, (float) $accounts->ensureAccount($referrer)->referral_frozen_balance, 0.001);

        // 重放：账单已 REFUNDED，入口状态闸直接拒绝（already_refunded 短路仅覆盖
        // 「订单已退款但账单未标记」的修复场景），不会产生新单据。
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('当前账单状态不支持退款');
        app(InvoiceRefundService::class)->refundInvoiceToBalance($payer, $invoice);
    }

    public function test_partial_refund_keeps_statuses_then_full_refund_closes(): void
    {
        $user = $this->makeUser();
        $accounts = app(AccountService::class);
        $accounts->setCashBalance($user, 0.00);

        $invoice = $this->makeInvoice($user, 100.00);
        $payment = $this->makeSuccessPayment($user, $invoice, 100.00);
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();

        // 部分退款 30：账单与支付单保持已支付，只落红字单据。
        app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice, ['amount' => 30.00]);
        $this->assertEqualsWithDelta(30.00, $accounts->cashBalance($user), 0.001);
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->refresh()->status);
        $this->assertSame(PaymentStatus::SUCCESS, (int) $payment->refresh()->status);
        $this->assertSame(1, Refund::query()->where('invoice_id', (int) $invoice->id)->count());

        // 剩余 70 全款退：账单/支付单收口 REFUNDED。
        app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice, ['amount' => 70.00]);
        $this->assertEqualsWithDelta(100.00, $accounts->cashBalance($user), 0.001);
        $this->assertSame(InvoiceStatus::REFUNDED, (int) $invoice->refresh()->status);
        $this->assertSame(PaymentStatus::REFUNDED, (int) $payment->refresh()->status);
        $this->assertSame(2, Refund::query()->where('invoice_id', (int) $invoice->id)->count());
        $this->assertSame(2, Invoice::query()->where('origin_invoice_id', (int) $invoice->id)->count());
    }

    public function test_recharge_invoice_refund_is_refused(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 50.00, ['type' => InvoiceType::RECHARGE]);
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 50.00, 'paid_at' => now()])->save();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('充值/补录账单不支持退回余额');
        app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice);
    }

    public function test_manual_invoice_refund_is_refused(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 50.00, ['type' => InvoiceType::MANUAL]);
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 50.00, 'paid_at' => now()])->save();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('充值/补录账单不支持退回余额');
        app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice);
    }

    public function test_unpaid_invoice_refund_is_refused(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 50.00);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('当前账单状态不支持退款');
        app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice);
    }

    public function test_credited_to_balance_invoice_refund_is_refused_with_distinct_message(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 100.00);
        $this->makeSuccessPayment($user, $invoice, 100.00, [
            'credited_to_balance' => true,
            'credited_amount' => '100.00',
            'credit_reason' => 'invoice_already_paid',
        ]);
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();

        // 已转余额的异常支付不可再退：口径必须与普通余额不足区分。
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('该账单款项已通过重复支付转入余额，无需再退款');
        app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice);
    }

    private function countLedger(User $user, string $eventType): int
    {
        return AccountTransaction::query()
            ->where('user_id', (int) $user->id)
            ->where('event_type', $eventType)
            ->count();
    }

    private function makeUser(string $prefix = 'refund'): User
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

    /**
     * @param  array<string, mixed>  $rawOverrides
     */
    private function makeSuccessPayment(User $user, Invoice $invoice, float $amount, array $rawOverrides = []): Payment
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
            'callback_raw' => array_merge(['source' => 'alipay_precreate'], $rawOverrides),
        ]);
    }
}
