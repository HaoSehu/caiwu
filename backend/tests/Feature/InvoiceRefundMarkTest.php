<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Constants\RefundStatus;
use App\Exceptions\BusinessException;
use App\Models\AccountTransaction;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RechargeRecord;
use App\Models\ReferralReward;
use App\Models\Refund;
use App\Models\User;
use App\Services\Finance\InvoiceRefundMarkService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 退款标记（纯标记通道）行为快照：登记线下已退款事实，支持部分退款。
 *
 * 核心不变式：
 * - 资金红线：不动余额（无 AccountTransaction 变化）、不写台账、不生成红字账单、
 *   不改 Payment 状态、不回退推广奖励（仅 referral_reward_pending 留痕）；
 * - refunds 行 refund_method=manual_mark、payment_id/refund_invoice_id 为 NULL；
 * - invoices/orders.refund_amount 逐次累加（非覆盖），仅全额收口 REFUNDED 并级联订单；
 * - 超额拒绝、已退完重放幂等（already_marked）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class InvoiceRefundMarkTest extends TestCase
{
    use DatabaseTransactions;

    private InvoiceRefundMarkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvoiceRefundMarkService::class);
    }

    public function test_partial_mark_writes_refund_row_and_accumulates_projection(): void
    {
        $invoice = $this->makePaidInvoiceWithOrder(100.00);
        $context = $this->operatorContext();

        $result = $this->service->markInvoiceRefunded($invoice, ['amount' => 30.00, 'remark' => '线下已退 30 元'], $context);

        $this->assertFalse((bool) $result['detail']['already_marked']);
        $this->assertSame('部分退款', RefundStatus::$labels[$result['detail']['invoice']['refund_status']]);

        // refunds 明细行：manual_mark、无支付/红字挂载。
        $refund = Refund::query()->where('invoice_id', (int) $invoice->id)->firstOrFail();
        $this->assertSame(Refund::METHOD_MANUAL_MARK, (string) $refund->refund_method);
        $this->assertNull($refund->payment_id);
        $this->assertNull($refund->refund_invoice_id);
        $this->assertSame('30.00', (string) $refund->amount);
        $this->assertSame(Refund::STATUS_COMPLETED, (int) $refund->status);
        $this->assertSame('线下已退 30 元', (string) $refund->reason);

        // 投影累加：账单/订单 refund_amount 同步 30，状态均保持 PAID。
        $invoice->refresh();
        $this->assertSame('30.00', (string) $invoice->refund_amount);
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertSame(Refund::METHOD_MANUAL_MARK, (string) $invoice->refund_method);
        $order = $invoice->order;
        $this->assertInstanceOf(Order::class, $order);
        $order->refresh();
        $this->assertSame('30.00', (string) $order->refund_amount);
        $this->assertSame(OrderStatus::PAID, (int) $order->status);
    }

    public function test_second_mark_accumulates_instead_of_overwriting(): void
    {
        $invoice = $this->makePaidInvoiceWithOrder(100.00);
        $context = $this->operatorContext();

        $this->service->markInvoiceRefunded($invoice, ['amount' => 30.00, 'remark' => '第一次部分退款'], $context);
        $result = $this->service->markInvoiceRefunded($invoice, ['amount' => 20.00, 'remark' => '第二次部分退款'], $context);

        $this->assertSame('50.00', (string) $result['detail']['invoice']['refund_amount']);
        $this->assertSame(2, Refund::query()->where('invoice_id', (int) $invoice->id)->count());

        $invoice->refresh();
        $this->assertSame('50.00', (string) $invoice->refund_amount);
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
    }

    public function test_split_payments_can_be_marked_accumulating_to_full_refund(): void
    {
        // 回归：订单拆分支付（50 + 50）场景下，可退上限必须是整笔实收而非
        // 「主支付单金额」——否则第一次标记 50 后可退归零，剩余 50 永远标不了、
        // 账单无法收口 REFUNDED（真退款的单次口径不适用于累计登记）。
        $invoice = $this->makePaidInvoiceWithOrder(100.00);
        $user = $invoice->user;
        $this->assertInstanceOf(User::class, $user);
        $this->makeSuccessPayment($user, $invoice, 50.00, ['source' => 'alipay_split_second']);

        $context = $this->operatorContext();
        $this->service->markInvoiceRefunded($invoice, ['amount' => 50.00, 'remark' => '拆分支付第一笔退款'], $context);
        $result = $this->service->markInvoiceRefunded($invoice, ['amount' => 50.00, 'remark' => '拆分支付第二笔退款'], $context);

        $invoice->refresh();
        $this->assertSame('100.00', (string) $invoice->refund_amount);
        $this->assertSame(InvoiceStatus::REFUNDED, (int) $invoice->status);
        $this->assertTrue($result['detail']['invoice']['refund_status'] === RefundStatus::FULL);
        $this->assertSame(2, Refund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_full_mark_closes_invoice_and_order_to_refunded(): void
    {
        $invoice = $this->makePaidInvoiceWithOrder(100.00);

        $result = $this->service->markInvoiceRefunded($invoice, ['amount' => 100.00, 'remark' => '线下全额退款'], $this->operatorContext());

        $this->assertTrue($result['detail']['invoice']['refund_status'] === RefundStatus::FULL);
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::REFUNDED, (int) $invoice->status);
        $this->assertSame('100.00', (string) $invoice->refund_amount);
        $order = $invoice->order;
        $this->assertInstanceOf(Order::class, $order);
        $order->refresh();
        $this->assertSame(OrderStatus::REFUNDED, (int) $order->status);
    }

    public function test_over_amount_mark_is_rejected(): void
    {
        $invoice = $this->makePaidInvoiceWithOrder(100.00);

        try {
            $this->service->markInvoiceRefunded($invoice, ['amount' => 100.01, 'remark' => '超额退款'], $this->operatorContext());
            $this->fail('超额标记应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('剩余可退金额', $exception->getMessage());
        }

        $this->assertSame(0, Refund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_recharge_invoice_type_is_rejected(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 100.00, ['type' => InvoiceType::RECHARGE, 'status' => InvoiceStatus::PAID, 'paid_amount' => 100.00]);

        try {
            $this->service->markInvoiceRefunded($invoice, ['amount' => 50.00, 'remark' => '测试'], $this->operatorContext());
            $this->fail('充值账单应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('充值账单不支持退款标记', $exception->getMessage());
        }
    }

    public function test_manual_invoice_type_is_rejected(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 100.00, ['type' => InvoiceType::MANUAL, 'status' => InvoiceStatus::PAID, 'paid_amount' => 100.00]);

        try {
            $this->service->markInvoiceRefunded($invoice, ['amount' => 50.00, 'remark' => '测试'], $this->operatorContext());
            $this->fail('补录账单应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('红字冲抵', $exception->getMessage());
        }
    }

    public function test_non_sales_invoice_types_are_rejected(): void
    {
        $user = $this->makeUser();
        foreach ([InvoiceType::REFUND, InvoiceType::DEDUCTION, InvoiceType::REFERRAL_CREDIT] as $type) {
            $invoice = $this->makeInvoice($user, 100.00, ['type' => $type, 'status' => InvoiceStatus::PAID, 'paid_amount' => 100.00]);

            try {
                $this->service->markInvoiceRefunded($invoice, ['amount' => 50.00, 'remark' => '测试'], $this->operatorContext());
                $this->fail("账单类型 {$type} 应当被拒绝");
            } catch (BusinessException $exception) {
                $this->assertStringContainsString('不支持退款标记', $exception->getMessage());
            }

            $invoice->delete();
        }
    }

    public function test_non_paid_invoice_status_is_rejected(): void
    {
        $user = $this->makeUser();

        $unpaid = $this->makeInvoice($user, 100.00);
        try {
            $this->service->markInvoiceRefunded($unpaid, ['amount' => 50.00, 'remark' => '测试'], $this->operatorContext());
            $this->fail('待支付账单应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('仅已支付账单支持退款标记', $exception->getMessage());
        }

        $cancelled = $this->makeInvoice($user, 100.00, ['status' => InvoiceStatus::CANCELLED]);
        try {
            $this->service->markInvoiceRefunded($cancelled, ['amount' => 50.00, 'remark' => '测试'], $this->operatorContext());
            $this->fail('已取消账单应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('仅已支付账单支持退款标记', $exception->getMessage());
        }

        $refunded = $this->makeInvoice($user, 100.00, ['status' => InvoiceStatus::REFUNDED, 'paid_amount' => 100.00]);
        try {
            $this->service->markInvoiceRefunded($refunded, ['amount' => 50.00, 'remark' => '测试'], $this->operatorContext());
            $this->fail('已退款账单应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('该账单已全额退款', $exception->getMessage());
        }
    }

    public function test_order_status_mismatch_is_rejected(): void
    {
        $user = $this->makeUser();

        $cancelledOrder = $this->makeOrder($user, 100.00);
        $cancelledOrder->forceFill(['status' => OrderStatus::CANCELLED])->save();
        $withCancelledOrder = $this->makeInvoice($user, 100.00, ['order_id' => $cancelledOrder->id, 'status' => InvoiceStatus::PAID, 'paid_amount' => 100.00]);
        $this->makeSuccessPayment($user, $withCancelledOrder, 100.00);
        try {
            $this->service->markInvoiceRefunded($withCancelledOrder, ['amount' => 50.00, 'remark' => '测试'], $this->operatorContext());
            $this->fail('已取消订单应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('关联订单已取消', $exception->getMessage());
        }

        $refundedOrder = $this->makeOrder($user, 100.00);
        $refundedOrder->forceFill(['status' => OrderStatus::REFUNDED])->save();
        $withRefundedOrder = $this->makeInvoice($user, 100.00, ['order_id' => $refundedOrder->id, 'status' => InvoiceStatus::PAID, 'paid_amount' => 100.00]);
        $this->makeSuccessPayment($user, $withRefundedOrder, 100.00);
        try {
            $this->service->markInvoiceRefunded($withRefundedOrder, ['amount' => 50.00, 'remark' => '测试'], $this->operatorContext());
            $this->fail('已退款订单应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('关联订单已退款', $exception->getMessage());
        }

        $pendingOrder = $this->makeOrder($user, 100.00);
        $withPendingOrder = $this->makeInvoice($user, 100.00, ['order_id' => $pendingOrder->id, 'status' => InvoiceStatus::PAID, 'paid_amount' => 100.00]);
        $this->makeSuccessPayment($user, $withPendingOrder, 100.00);
        try {
            $this->service->markInvoiceRefunded($withPendingOrder, ['amount' => 50.00, 'remark' => '测试'], $this->operatorContext());
            $this->fail('待支付订单应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('关联订单状态与账单不一致', $exception->getMessage());
        }
    }

    public function test_invoice_without_order_can_be_marked(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 100.00, ['status' => InvoiceStatus::PAID, 'paid_amount' => 100.00]);
        $this->makeSuccessPayment($user, $invoice, 100.00);

        $result = $this->service->markInvoiceRefunded($invoice, ['amount' => 40.00, 'remark' => '无订单账单部分退款'], $this->operatorContext());

        $this->assertNull($result['detail']['order']);
        $invoice->refresh();
        $this->assertSame('40.00', (string) $invoice->refund_amount);
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
    }

    public function test_mark_touches_no_money_related_records(): void
    {
        $invoice = $this->makePaidInvoiceWithOrder(100.00);

        $ledgerBefore = AccountTransaction::query()->where('user_id', (int) $invoice->user_id)->count();
        $rechargeBefore = RechargeRecord::query()->where('user_id', (int) $invoice->user_id)->count();
        $redInvoiceBefore = Invoice::query()->where('origin_invoice_id', (int) $invoice->id)->count();
        $payment = Payment::query()->where('invoice_id', (int) $invoice->id)->firstOrFail();

        $this->service->markInvoiceRefunded($invoice, ['amount' => 50.00, 'remark' => '资金红线验证'], $this->operatorContext());

        // 红线：台账/充值记录/红字账单零变化，支付单状态与 callback_raw 不动。
        $this->assertSame($ledgerBefore, AccountTransaction::query()->where('user_id', (int) $invoice->user_id)->count());
        $this->assertSame($rechargeBefore, RechargeRecord::query()->where('user_id', (int) $invoice->user_id)->count());
        $this->assertSame($redInvoiceBefore, Invoice::query()->where('origin_invoice_id', (int) $invoice->id)->count());
        $payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, (int) $payment->status);
        $this->assertNull(data_get($payment->callback_raw, 'refund'));
    }

    public function test_referral_reward_is_not_reversed_but_flagged(): void
    {
        $referrer = $this->makeUser('referrer');
        $invoice = $this->makePaidInvoiceWithOrder(100.00);

        $reward = ReferralReward::query()->create([
            'referrer_user_id' => $referrer->id,
            'referred_user_id' => (int) $invoice->user_id,
            'invoice_id' => (int) $invoice->id,
            'order_amount' => 100.00,
            'reward_rate' => 0.10,
            'reward_amount' => 10.00,
            'status' => ReferralReward::STATUS_FROZEN,
        ]);

        $result = $this->service->markInvoiceRefunded($invoice, ['amount' => 100.00, 'remark' => '奖励敞口验证'], $this->operatorContext());

        $this->assertTrue((bool) $result['detail']['referral_reward_pending']);
        $reward->refresh();
        $this->assertSame(ReferralReward::STATUS_FROZEN, (int) $reward->status);
    }

    public function test_replay_after_full_mark_is_idempotent(): void
    {
        $invoice = $this->makePaidInvoiceWithOrder(100.00);
        $context = $this->operatorContext();

        $this->service->markInvoiceRefunded($invoice, ['amount' => 100.00, 'remark' => '全额退款'], $context);
        $replay = $this->service->markInvoiceRefunded($invoice, ['amount' => 100.00, 'remark' => '重复提交'], $context);

        $this->assertTrue((bool) $replay['detail']['already_marked']);
        $this->assertNull($replay['detail']['refund']);
        $this->assertSame(1, Refund::query()->where('invoice_id', (int) $invoice->id)->count());
        $invoice->refresh();
        $this->assertSame('100.00', (string) $invoice->refund_amount);
        $this->assertSame(InvoiceStatus::REFUNDED, (int) $invoice->status);
    }

    public function test_concurrent_mark_is_guarded_by_naming_lock(): void
    {
        $invoice = $this->makePaidInvoiceWithOrder(100.00);
        $lock = Cache::lock("lock:admin:refund-mark:invoice:{$invoice->id}", 15);
        $this->assertTrue($lock->get());

        try {
            $this->service->markInvoiceRefunded($invoice, ['amount' => 50.00, 'remark' => '并发测试'], $this->operatorContext());
            $this->fail('持锁并发应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('退款标记正在处理中', $exception->getMessage());
        } finally {
            $lock->release();
        }

        $this->assertSame(0, Refund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_second_over_amount_mark_keeps_first_accumulation(): void
    {
        $invoice = $this->makePaidInvoiceWithOrder(100.00);
        $context = $this->operatorContext();

        $this->service->markInvoiceRefunded($invoice, ['amount' => 30.00, 'remark' => '首次部分退款'], $context);
        try {
            $this->service->markInvoiceRefunded($invoice, ['amount' => 70.01, 'remark' => '超限退款'], $context);
            $this->fail('二次超限应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('剩余可退金额', $exception->getMessage());
        }

        $invoice->refresh();
        $this->assertSame('30.00', (string) $invoice->refund_amount);
        $this->assertSame(1, Refund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_order_entry_resolves_invoice_and_marks(): void
    {
        $invoice = $this->makePaidInvoiceWithOrder(100.00);
        $order = $invoice->order;
        $this->assertInstanceOf(Order::class, $order);

        $result = $this->service->markOrderRefunded($order, ['amount' => 30.00, 'remark' => '订单侧入口'], $this->operatorContext());

        $this->assertFalse((bool) $result['detail']['already_marked']);
        $this->assertSame((int) $invoice->id, (int) $result['detail']['invoice']['id']);
        $invoice->refresh();
        $this->assertSame('30.00', (string) $invoice->refund_amount);
    }

    public function test_order_without_invoice_is_rejected(): void
    {
        $user = $this->makeUser();
        $order = $this->makeOrder($user, 100.00);

        try {
            $this->service->markOrderRefunded($order, ['amount' => 50.00, 'remark' => '测试'], $this->operatorContext());
            $this->fail('无关联账单的订单应当被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('该订单未关联账单，无法标记退款', $exception->getMessage());
        }
    }

    /**
     * 造「账单 PAID + 关联订单 PAID + 可退基数 100」的完整场景
     * （主支付单口径：resolvePrimaryRefundablePayment 命中 SUCCESS 支付单）。
     */
    private function makePaidInvoiceWithOrder(float $amount): Invoice
    {
        $user = $this->makeUser();
        $order = $this->makeOrder($user, $amount);
        $invoice = $this->makeInvoice($user, $amount, ['order_id' => $order->id]);
        $this->makeSuccessPayment($user, $invoice, $amount);
        $order->forceFill(['status' => OrderStatus::PAID, 'paid_amount' => $amount, 'paid_at' => now()])->save();
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => $amount, 'paid_at' => now()])->save();

        return $invoice->refresh();
    }

    private function makeUser(string $prefix = 'mark'): User
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

    /**
     * @return array{operator_id: int, operator_name: string, trace_id: string}
     */
    private function operatorContext(): array
    {
        return [
            'operator_id' => 1,
            'operator_name' => '退款标记测试员',
            'trace_id' => 'mark-'.uniqid(),
        ];
    }
}
