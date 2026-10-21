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
use App\Models\FinanceConsumption;
use App\Models\FinanceLedgerEntry;
use App\Models\FinanceRefund;
use App\Models\FinanceRefundAllocation;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ReferralReward;
use App\Models\User;
use App\Services\Finance\BalanceInvoicePaymentService;
use App\Services\Finance\Core\FinanceAccountService;
use App\Services\Finance\InvoiceRefundService;
use App\Services\Finance\InvoiceService;
use App\Services\User\AccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsFinanceBalance;
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
    use SeedsFinanceBalance;

    public function test_full_refund_balance_paid_invoice_writes_red_documents(): void
    {
        $user = $this->makeUser();
        $accounts = app(AccountService::class);
        $this->seedFinanceBalance($user, 100.00);

        $invoice = $this->makeInvoice($user, 40.00);
        app(BalanceInvoicePaymentService::class)->payByBalance($invoice, $user);
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->refresh()->status);
        $this->assertEqualsWithDelta(60.00, $this->financeBalance($user), 0.001);
        $this->assertSame(1, $this->countLedger($user, FinanceLedgerEventType::INVOICE_PAYMENT));

        $result = app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice);

        $this->assertFalse((bool) ($result['already_refunded'] ?? true));
        $this->assertSame(0, (int) ($result['payment_id'] ?? -1));
        $this->assertGreaterThan(0, (int) ($result['refund_id'] ?? 0));

        // 余额回到支付前水平，台账一行精确挂账单。
        $this->assertEqualsWithDelta(100.00, $this->financeBalance($user), 0.001);
        $refundLog = FinanceLedgerEntry::query()
            ->where('user_id', (int) $user->id)
            ->where('event_type', FinanceLedgerEventType::INVOICE_REFUND)
            ->firstOrFail();
        $this->assertSame('invoice', (string) $refundLog->source_type);
        $this->assertSame((int) $invoice->id, (int) $refundLog->source_id);
        $this->assertEqualsWithDelta(40.00, (float) $refundLog->change_amount, 0.001);
        $this->assertEqualsWithDelta(100.00, (float) $refundLog->balance_after, 0.001);

        // Refund 记录 + 红字账单（负数金额、指向原单）。
        $refund = FinanceRefund::query()->where('invoice_id', (int) $invoice->id)->firstOrFail();
        $this->assertSame(FinanceRefund::STATUS_COMPLETED, (int) $refund->status);
        $this->assertSame('40.00', (string) $refund->amount);
        $this->assertTrue((bool) $refund->funds_moved);
        $this->assertSame(FinanceRefund::ORIGIN_NATIVE, (string) $refund->data_origin);
        $redInvoice = Invoice::query()->where('origin_invoice_id', (int) $invoice->id)->firstOrFail();
        $this->assertSame(InvoiceType::REFUND, (string) $redInvoice->type);
        $this->assertEqualsWithDelta(-40.00, (float) $redInvoice->amount, 0.001);
        $this->assertSame(InvoiceStatus::PAID, (int) $redInvoice->status);

        // 原账单收口为已退款。
        $this->assertSame(InvoiceStatus::REFUNDED, (int) $invoice->refresh()->status);

        // 退款批次证据（P1-3）：new_lot 指向退款入账产生的新批次，一笔退款至少一条。
        $allocation = FinanceRefundAllocation::query()
            ->where('refund_id', (int) $refund->id)
            ->where('allocation_type', FinanceRefundAllocation::TYPE_NEW_LOT)
            ->first();
        $this->assertNotNull($allocation, '退款必须写批次证据（否则无法回答「退的是哪一笔钱」）');
        $this->assertSame('40.00', (string) $allocation->amount);
    }

    public function test_full_refund_gateway_paid_invoice_with_order_reverses_referral_reward(): void
    {
        $referrer = $this->makeUser('referrer');
        $payer = $this->makeUser('payer');
        $accounts = app(AccountService::class);
        $this->seedFinanceBalance($payer, 0.00);
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
        $this->assertEqualsWithDelta(100.00, $this->financeBalance($payer), 0.001);
        $this->assertSame(1, FinanceRefund::query()->where('invoice_id', (int) $invoice->id)->count());
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
        $this->seedFinanceBalance($user, 0.00);

        $invoice = $this->makeInvoice($user, 100.00);
        $payment = $this->makeSuccessPayment($user, $invoice, 100.00);
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();

        // 部分退款 30：账单与支付单保持已支付，只落红字单据。
        app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice, ['amount' => 30.00]);
        $this->assertEqualsWithDelta(30.00, $this->financeBalance($user), 0.001);
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->refresh()->status);
        $this->assertSame(PaymentStatus::SUCCESS, (int) $payment->refresh()->status);
        $this->assertSame(1, FinanceRefund::query()->where('invoice_id', (int) $invoice->id)->count());

        // 剩余 70 全款退：账单/支付单收口 REFUNDED。
        app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice, ['amount' => 70.00]);
        $this->assertEqualsWithDelta(100.00, $this->financeBalance($user), 0.001);
        $this->assertSame(InvoiceStatus::REFUNDED, (int) $invoice->refresh()->status);
        $this->assertSame(PaymentStatus::REFUNDED, (int) $payment->refresh()->status);
        $this->assertSame(2, FinanceRefund::query()->where('invoice_id', (int) $invoice->id)->count());
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

    public function test_refund_allocations_record_per_consumption_amount_across_two_lots(): void
    {
        $user = $this->makeUser();
        $accounts = app(FinanceAccountService::class);
        $this->seedFinanceBalance($user, 100.00);
        // 第二个批次：首批次被第一笔消费吃满后，第二笔消费只能落到后一批次，
        // 这样同一账单名下会留下两条指向不同 lot 的消费映射。
        $accounts->credit((int) $user->id, 5000, FinanceLedgerEventType::SYSTEM_ADJUSTMENT, [
            'source_type' => 'test_seed',
            'remark' => '测试预置余额（第二批）',
        ]);

        $invoice = $this->makeInvoice($user, 150.00);

        foreach ([10000, 5000] as $cents) {
            $accounts->debit((int) $user->id, $cents, FinanceLedgerEventType::INVOICE_PAYMENT, [
                'source_type' => 'invoice',
                'source_id' => (int) $invoice->id,
                'remark' => '测试余额支付账单',
            ]);
        }

        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 150.00, 'paid_at' => now()])->save();
        $this->assertSame(2, FinanceConsumption::query()
            ->where('source_type', 'invoice')
            ->where('source_id', (int) $invoice->id)
            ->count(), '前置条件：该账单名下必须有两条消费映射');
        $this->assertEqualsWithDelta(0.00, $this->financeBalance($user), 0.001);

        $result = app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice);

        $this->assertFalse((bool) ($result['already_refunded'] ?? true));
        $refundId = (int) ($result['refund_id'] ?? 0);
        $this->assertGreaterThan(0, $refundId);

        $marks = FinanceRefundAllocation::query()
            ->where('refund_id', $refundId)
            ->where('allocation_type', FinanceRefundAllocation::TYPE_CONSUMPTION_MARK)
            ->get();

        $this->assertCount(2, $marks, '跨两批次退款：每条被追认消费都要有自己的证据行');
        $amounts = $marks->map(fn (FinanceRefundAllocation $row): float => round((float) $row->amount, 2))->all();
        sort($amounts);
        // 金额必须是该条消费的分摊额（100 / 50），而不是每条都写本次退款总额 150。
        $this->assertSame([50.0, 100.0], $amounts);
        $this->assertEqualsWithDelta(150.00, array_sum($amounts), 0.001);
        $this->assertEqualsWithDelta(150.00, $this->financeBalance($user), 0.001);
    }

    public function test_refundable_amount_single_source_deducts_credited_to_balance_and_completed_refunds(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 0.00);

        $order = $this->makeOrder($user, 100.00);
        $invoice = $this->makeInvoice($user, 100.00, ['order_id' => $order->id]);
        // 主支付单 100 为正常收款；另有一笔 30 的重复支付已转入余额，必须从可退额里扣掉，
        // 否则展示值虚高、运营会以为这 30 还能再退一次。
        $this->makeSuccessPayment($user, $invoice, 100.00);
        $this->makeSuccessPayment($user, $invoice, 30.00, [
            'credited_to_balance' => true,
            'credited_amount' => '30.00',
            'credit_reason' => 'invoice_already_paid',
        ]);
        // 已完成退款 20（纯标记 funds_moved=0 与真退款同表同口径，一并扣减）。
        FinanceRefund::query()->create([
            'refund_no' => 'RFD'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => (int) $user->id,
            'invoice_id' => (int) $invoice->id,
            'amount' => '20.00',
            'status' => FinanceRefund::STATUS_COMPLETED,
            'funds_moved' => 0,
            'data_origin' => FinanceRefund::ORIGIN_NATIVE,
            'currency' => 'CNY',
            'reason' => '已部分退款',
            'refunded_at' => now(),
        ]);

        $order->forceFill(['status' => OrderStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();

        // 唯一真源口径：100 − 30（已转余额）− 20（已完成退款） = 50。
        $this->assertEqualsWithDelta(
            50.00,
            app(InvoiceRefundService::class)->markRefundableAmountOf($invoice->refresh()),
            0.001,
        );

        // 详情页三个金额字段必须与真源一致（改造前会显示 80 的虚高值）。
        $detail = app(InvoiceService::class)->adminDetail((int) $invoice->id);
        $this->assertSame('50.00', $detail['refundable_amount']);
        $this->assertSame('50.00', $detail['refundable_amount_for_balance']);
    }

    private function countLedger(User $user, string $eventType): int
    {
        return FinanceLedgerEntry::query()
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
