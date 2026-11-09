<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\CheckoutService;
use App\Services\Finance\Core\FinanceAccountService;
use App\Services\Finance\InvoiceOrderReconciliationService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\InvoiceSettlementService;
use App\Services\Finance\PaymentCallbackProjector;
use App\Services\Finance\PaymentCallbackRaw;
use App\Services\Finance\TradeLifecycleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Support\SeedsFinanceBalance;
use Tests\TestCase;

/**
 * 财务审查修复批次（2026-11-09）之一：入账/取消/对账/展示口径。
 * 覆盖 M-55（已退款账单迟到回调转余额）、M-65（回调保留混付标记）、
 * M-54（partial 账单拒绝直接取消）、M-58（PAID+CANCELLED 对账不抛）、
 * M-59（详情 refund_amount 口径）、M-63（管理端已退款筛选）、M-5（0 元账单清理豁免）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class FixInvoiceSettlementReviewTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsFinanceBalance;

    public function test_refunded_invoice_late_capture_is_credited_to_balance(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 10.00);
        $invoice = $this->makeInvoice($user, [
            'status' => InvoiceStatus::REFUNDED,
            'paid_amount' => '100.00',
            'refund_amount' => '100.00',
            'refunded_at' => now()->subHour(),
            'paid_at' => now()->subDay(),
        ]);
        $payment = $this->makePendingPayment($user, $invoice, 100.00);

        $result = $this->settle($payment, ['trade_no' => 'LATE-TRADE-1', 'total_amount' => '100.00']);

        $this->assertFalse((bool) ($result['dispatch'] ?? true), '已退款账单不得再推进入账');

        $payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, (int) $payment->status);
        $raw = (array) ($payment->callback_raw ?? []);
        $this->assertTrue((bool) ($raw['credited_to_balance'] ?? false), '捕获款转余额必须带 credited_to_balance 标记');
        $this->assertSame('invoice_refunded_late_capture', (string) ($raw['credit_reason'] ?? ''));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::REFUNDED, (int) $invoice->status, '账单状态不得被回调改写');

        // 余额 10 + 迟到捕获 100 = 110：钱到客户余额而不是悬空。
        $this->assertEqualsWithDelta(110.00, $this->financeBalance($user), 0.001);
        $this->assertSame(1, DB::table('finance_ledger_entries')
            ->where('user_id', (int) $user->id)
            ->where('event_type', FinanceLedgerEventType::RECHARGE)
            ->count());
    }

    public function test_success_settlement_preserves_mix_payment_callback_markers(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 200.00);
        $invoice = $this->makeInvoice($user, ['amount' => 100.00]);
        $payment = $this->makePendingPayment($user, $invoice, 70.00, [
            'mix_payment' => true,
            'balance_amount' => 30.0,
            'source' => 'alipay_precreate_mix',
        ]);
        // 预扣投影已计入 paid_amount，且余额侧有真实扣款台账（组成校验要求账单金额 = 网关实收 + 余额扣款）。
        $invoice->forceFill(['paid_amount' => '30.00'])->save();
        app(FinanceAccountService::class)->debit(
            (int) $user->id,
            3000,
            FinanceLedgerEventType::INVOICE_PAYMENT,
            [
                'source_type' => 'invoice',
                'source_id' => (int) $invoice->id,
                'remark' => '组合支付预扣余额',
                'operator_type' => 'member',
            ],
        );

        // 与真实回调入口一致：先写 payment_callbacks 投影，再进入统一入账事务体。
        // 回归点：投影里只有原始通知载荷，若入账从访问器读 callback_raw 会丢掉 mix 标记。
        $payload = ['trade_no' => 'MIX-TRADE-1', 'total_amount' => '70.00', 'trade_status' => 'TRADE_SUCCESS'];
        app(PaymentCallbackProjector::class)->recordPaymentCallback($payment, 'payment', $payload, true, 'MIX-TRADE-1');

        $result = $this->settle($payment, $payload);
        $this->assertTrue((bool) ($result['dispatch'] ?? false), '混付尾款全额回调应正常入账');

        $payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, (int) $payment->status);
        $raw = PaymentCallbackRaw::rawDecode($payment, true);
        $this->assertTrue((bool) ($raw['mix_payment'] ?? false), '成功回调不得抹掉 mix_payment 标记（M-65）');
        $this->assertEqualsWithDelta(30.00, (float) ($raw['balance_amount'] ?? 0), 0.001, '成功回调不得抹掉 balance_amount');
        $this->assertSame('MIX-TRADE-1', (string) ($raw['trade_no'] ?? ''), '原始通知字段仍须写入');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertEqualsWithDelta(100.00, (float) $invoice->paid_amount, 0.001);
    }

    public function test_cancel_trade_rejects_unpaid_invoice_with_recorded_payment(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, ['amount' => 100.00]);
        $invoice->forceFill(['paid_amount' => '30.00'])->save();

        try {
            app(TradeLifecycleService::class)->cancelTrade($invoice);
            $this->fail('部分入账的待支付账单不得直接取消');
        } catch (BusinessException $exception) {
            $this->assertSame('账单已有入账款项，不能直接取消', $exception->getMessage());
        }

        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->fresh()->status);
    }

    public function test_checkout_cancel_still_works_after_mix_reservation_restored(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 100.00);
        $invoice = $this->makeInvoice($user, ['amount' => 100.00]);
        // 预扣投影 30（组合支付预扣后取消前）
        $invoice->forceFill(['paid_amount' => '30.00'])->save();
        $payment = $this->makePendingPayment($user, $invoice, 70.00, [
            'mix_payment' => true,
            'balance_amount' => 30.0,
        ]);
        // 余额预扣真实出账，取消时才会经 restoreReservedMixBalance 回补并递减 paid_amount
        app(FinanceAccountService::class)->debit(
            (int) $user->id,
            3000,
            FinanceLedgerEventType::INVOICE_PAYMENT,
            [
                'source_type' => 'invoice',
                'source_id' => (int) $invoice->id,
                'remark' => '组合支付预扣余额',
                'operator_type' => 'member',
            ],
        );

        $cancelled = app(CheckoutService::class)->cancel($invoice->fresh());

        $this->assertSame(InvoiceStatus::CANCELLED, (int) $cancelled->status, '预扣回补后 paid_amount 归零，取消应成功');
        $this->assertEqualsWithDelta(0.00, (float) $cancelled->paid_amount, 0.001);
        $payment->refresh();
        $this->assertSame(PaymentStatus::CANCELLED, (int) $payment->status);
        $this->assertEqualsWithDelta(100.00, $this->financeBalance($user), 0.001);
    }

    public function test_reconcile_paid_invoice_cancelled_order_does_not_throw_or_fake_refund(): void
    {
        $user = $this->makeUser();
        $order = Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => 100.00,
            'status' => OrderStatus::CANCELLED,
        ]);
        $invoice = $this->makeInvoice($user, [
            'order_id' => (int) $order->id,
            'status' => InvoiceStatus::PAID,
            'paid_amount' => '100.00',
            'paid_at' => now()->subDay(),
        ]);

        $service = app(InvoiceOrderReconciliationService::class);
        $pair = (object) [
            'order_id' => (int) $order->id,
            'order_no' => (string) $order->order_no,
            'order_status' => OrderStatus::CANCELLED,
            'order_paid_amount' => '100.00',
            'order_paid_at' => now()->toDateTimeString(),
            'invoice_id' => (int) $invoice->id,
            'invoice_no' => (string) $invoice->invoice_no,
            'invoice_status' => InvoiceStatus::PAID,
            'invoice_amount' => '100.00',
            'invoice_paid_amount' => '100.00',
            'invoice_paid_at' => now()->subDay()->toDateTimeString(),
        ];

        $payloadMethod = new ReflectionMethod($service, 'statusMismatchPayload');
        $payloadMethod->setAccessible(true);
        $this->assertSame('manual_review', $payloadMethod->invoke($service, $pair)['suggested_action']);

        $repairMethod = new ReflectionMethod($service, 'repairStatusMismatch');
        $repairMethod->setAccessible(true);
        $this->assertSame(0, $repairMethod->invoke($service, $pair), 'PAID+CANCELLED 组合只进人工审查，不做自动修复');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status, '账单不得被取消或伪造退款');
        $this->assertSame(0, DB::table('finance_refunds')->where('invoice_id', (int) $invoice->id)->count(), '不得伪造退款记录');
    }

    public function test_reconcile_batch_continues_after_paid_invoice_cancelled_order_pair(): void
    {
        // 同一批里既有「PAID+CANCELLED」无法修复项，也有「UNPAID 账单 + PAID 订单」可修复项：
        // 前者不得抛异常回滚整批（M-58），后者仍应被修复。
        $user = $this->makeUser();
        $cancelledOrder = Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => 100.00,
            'status' => OrderStatus::CANCELLED,
        ]);
        $paidInvoice = $this->makeInvoice($user, [
            'order_id' => (int) $cancelledOrder->id,
            'status' => InvoiceStatus::PAID,
            'paid_amount' => '100.00',
            'paid_at' => now()->subDay(),
        ]);
        $paidOrder = Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => 50.00,
            'status' => OrderStatus::PAID,
            'paid_amount' => 50.00,
            'paid_at' => now()->subHour(),
        ]);
        $unpaidInvoice = $this->makeInvoice($user, [
            'order_id' => (int) $paidOrder->id,
            'amount' => 50.00,
            'status' => InvoiceStatus::UNPAID,
        ]);

        $service = app(InvoiceOrderReconciliationService::class);
        $repairMethod = new ReflectionMethod($service, 'repairStatusMismatch');
        $repairMethod->setAccessible(true);

        $blockedPair = (object) [
            'order_id' => (int) $cancelledOrder->id,
            'order_no' => (string) $cancelledOrder->order_no,
            'order_status' => OrderStatus::CANCELLED,
            'order_paid_amount' => '100.00',
            'order_paid_at' => now()->toDateTimeString(),
            'invoice_id' => (int) $paidInvoice->id,
            'invoice_no' => (string) $paidInvoice->invoice_no,
            'invoice_status' => InvoiceStatus::PAID,
            'invoice_amount' => '100.00',
            'invoice_paid_amount' => '100.00',
            'invoice_paid_at' => now()->subDay()->toDateTimeString(),
        ];
        $repairablePair = (object) [
            'order_id' => (int) $paidOrder->id,
            'order_no' => (string) $paidOrder->order_no,
            'order_status' => OrderStatus::PAID,
            'order_paid_amount' => '50.00',
            'order_paid_at' => now()->toDateTimeString(),
            'invoice_id' => (int) $unpaidInvoice->id,
            'invoice_no' => (string) $unpaidInvoice->invoice_no,
            'invoice_status' => InvoiceStatus::UNPAID,
            'invoice_amount' => '50.00',
            'invoice_paid_amount' => null,
            'invoice_paid_at' => null,
        ];

        $this->assertSame(0, $repairMethod->invoke($service, $blockedPair));
        $this->assertSame(1, $repairMethod->invoke($service, $repairablePair), '同批其他可修复项必须继续修复');

        $this->assertSame(InvoiceStatus::PAID, (int) $paidInvoice->fresh()->status);
        $this->assertSame(InvoiceStatus::PAID, (int) $unpaidInvoice->fresh()->status);
    }

    public function test_invoice_detail_refund_amount_matches_list_for_historical_gateway_refund(): void
    {
        $user = $this->makeUser();
        // 历史原路退款：payment 已 REFUNDED，finance_refunds 无记录，仅 invoices.refund_amount 有值。
        $invoice = $this->makeInvoice($user, [
            'status' => InvoiceStatus::PAID,
            'paid_amount' => '80.00',
            'refund_amount' => '80.00',
            'refund_method' => 'balance',
            'paid_at' => now()->subDay(),
        ]);
        $this->makePendingPayment($user, $invoice, 80.00, [], PaymentStatus::REFUNDED);

        $service = app(InvoiceService::class);
        $detail = $service->adminDetail((int) $invoice->id);
        $listItem = $service->adminListItem($invoice->fresh());

        $this->assertSame('80.00', $detail['refund_amount'], '详情 refund_amount 不得回退为 ¥0.00（M-59）');
        $this->assertSame($listItem['refund_amount'], $detail['refund_amount'], '详情与列表 refund_amount 口径必须一致');
    }

    public function test_admin_refunded_filter_matches_client_scope_for_gateway_refund(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, [
            'status' => InvoiceStatus::PAID,
            'paid_amount' => '80.00',
            'refund_amount' => '0.00',
            'paid_at' => now()->subDay(),
        ]);
        // 仅第三方位网关原路退款（payment REFUNDED），无 finance_refunds 行。
        $this->makePendingPayment($user, $invoice, 80.00, [], PaymentStatus::REFUNDED);

        $paginator = app(InvoiceService::class)->adminList([
            'keyword' => (string) $user->email,
            'status' => InvoiceStatus::REFUNDED,
        ]);

        $this->assertSame(1, $paginator->total(), '管理端「已退款」筛选必须命中原路退款账单（M-63）');
        $this->assertSame((int) $invoice->id, (int) $paginator->getCollection()->first()['id']);
    }

    public function test_zero_amount_unpaid_invoice_is_exempt_from_payment_window_cleanup(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, [
            'amount' => 0.00,
            'paid_amount' => 0.00,
            'due_date' => now()->addDays(7),
        ]);
        // 超出 5 分钟支付会话窗口
        Invoice::query()->whereKey((int) $invoice->id)->update(['created_at' => now()->subHour()]);

        $updated = app(CheckoutService::class)->cancelExpiredUnpaidInvoice($invoice->fresh());

        $this->assertSame(InvoiceStatus::UNPAID, (int) $updated->status, '0 元账单不得被支付窗口清理取消（M-5）');
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeInvoice(User $user, array $overrides = []): Invoice
    {
        return Invoice::query()->create(array_merge([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(1000000, 9999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => 100.00,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(7),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $callbackRaw
     */
    private function makePendingPayment(
        User $user,
        Invoice $invoice,
        float $amount,
        array $callbackRaw = [],
        int $status = PaymentStatus::PENDING,
    ): Payment {
        return Payment::query()->create([
            'payment_no' => Payment::generatePaymentNo(),
            'user_id' => (int) $user->id,
            'invoice_id' => (int) $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => number_format($amount, 2, '.', ''),
            'status' => $status,
            'callback_raw' => $callbackRaw ?: ['source' => 'alipay_precreate'],
        ]);
    }

    /**
     * 直接驱动统一入账事务体（与回调/轮询同一入口），锁键与日志标签取测试值。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function settle(Payment $payment, array $payload): array
    {
        return app(InvoiceSettlementService::class)->settleCapturedGatewayPayment(
            $payment,
            (string) ($payload['trade_no'] ?? ''),
            $payload,
            'test:lock:payment:'.$payment->id,
            '测试回调',
            'invoice_paid_by_gateway',
            '账单状态异常，无法处理支付回调',
            '支付回调处理中，请稍后重试',
        );
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'fix-review-settlement-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'fix-review-settlement-tester',
            'total_sales_amount' => 0,
        ]);
    }
}
