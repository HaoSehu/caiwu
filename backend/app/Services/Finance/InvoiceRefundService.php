<?php

declare(strict_types=1);

namespace App\Services\Finance;

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
use App\Models\Refund;
use App\Models\User;
use App\Services\Referral\ReferralService;
use App\Services\User\AccountService;
use App\Support\SchemaMetadataCache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 冲正退款流：账单退款到用户余额（系统内唯一保留的自动冲正公共入口，
 * 当前调用方为续费被取代账单的自动退款）。
 * 全款退 -> 余额回加 + INVOICE_REFUND 台账 + Refund 记录 + 红字账单
 * + Payment/Invoice/Order 收口 REFUNDED + 推广奖励回退；
 * 充值/补录账单拒退、已转余额（credited_to_balance）部分扣减口径、部分退款累计。
 */
class InvoiceRefundService
{
    public function __construct(
        private AccountService $accountService,
        private FinanceDocumentService $financeDocumentService,
        private FinanceLedgerWriter $financeLedgerWriter,
        private PaymentCallbackProjector $callbackProjector,
        private MixPaymentService $mixPaymentService,
        private ReferralService $referralService,
        private CouponService $couponService,
    ) {}

    /**
     * 后台发起账单退款到用户余额
     *
     * @return array<string, mixed>
     */
    public function refundInvoiceToBalance(User $user, Invoice $invoice, array $payload = [], array $context = []): array
    {
        $lockKey = "lock:refund:invoice:{$invoice->id}";

        try {
            return Cache::lock($lockKey, 40)->block(5, function () use ($user, $invoice, $payload, $context) {
                return DB::transaction(function () use ($user, $invoice, $payload, $context) {
                    $lockedInvoice = Invoice::query()
                        ->lockForUpdate()
                        ->with(['order', 'payments'])
                        ->findOrFail($invoice->id);
                    $lockedUser = User::query()
                        ->lockForUpdate()
                        ->findOrFail($user->id);

                    throw_if((int) $lockedInvoice->user_id !== (int) $lockedUser->id, new BusinessException('账单与用户不匹配'));
                    throw_if(
                        ! in_array((int) $lockedInvoice->status, [InvoiceStatus::PAID], true),
                        new BusinessException('当前账单状态不支持退款')
                    );

                    // 充值账单到账时余额已同步增加、补录账单为系统外收款凭证：
                    // 两者退回余额都没有对应的资金流出，等于凭空增加余额，必须走原路退款或红字冲抵。
                    throw_if(
                        in_array((string) $lockedInvoice->type, [InvoiceType::RECHARGE, InvoiceType::MANUAL], true),
                        new BusinessException('充值/补录账单不支持退回余额')
                    );

                    $order = $lockedInvoice->order;

                    if ($order && (int) $order->status === OrderStatus::REFUNDED) {
                        return [
                            'already_refunded' => true,
                            'invoice_id' => (int) $lockedInvoice->id,
                            'payment_id' => 0,
                            'refund' => [],
                        ];
                    }

                    throw_if(
                        $order && (int) $order->status !== OrderStatus::PAID,
                        new BusinessException('当前订单状态不支持退款')
                    );

                    // 账单退款与订单退款同规则：奖励已释放且可提余额不足时先阻断，避免退款后无法追回返利
                    $this->referralService->assertInvoiceRewardRefundable($lockedInvoice);

                    $payment = $this->resolvePrimaryRefundablePayment($lockedInvoice);

                    if ($payment instanceof Payment && (int) $payment->status === PaymentStatus::REFUNDED) {
                        $refund = (array) data_get((array) ($payment->callback_raw ?? []), 'refund', []);
                        if ((int) $lockedInvoice->status !== InvoiceStatus::REFUNDED) {
                            $this->markInvoiceRefunded(
                                $lockedInvoice,
                                $refund,
                                (string) ($refund['refund_method'] ?? 'balance'),
                                (float) ($refund['refund_amount'] ?? $refund['refund_fee'] ?? 0),
                                $context
                            );
                        }

                        return [
                            'already_refunded' => true,
                            'invoice_id' => (int) $lockedInvoice->id,
                            'payment_id' => (int) $payment->id,
                            'refund' => $refund,
                        ];
                    }

                    $refundableAmount = $this->remainingRefundableAmount($lockedInvoice, $payment);
                    $refundAmount = round((float) ($payload['amount'] ?? $refundableAmount), 2);
                    throw_if(
                        $refundAmount <= 0,
                        new BusinessException($this->invoiceCreditedToBalanceAmount($lockedInvoice) > 0
                            ? '该账单款项已通过重复支付转入余额，无需再退款'
                            : '退款金额不正确')
                    );
                    throw_if($refundAmount - $refundableAmount > 0.00001, new BusinessException('退款金额超过原单可退金额'));

                    $refundReason = trim((string) ($payload['remark'] ?? ''));
                    if ($refundReason === '') {
                        $refundReason = '后台退回用户余额';
                    }

                    $refundMethod = trim((string) ($payload['refund_method'] ?? 'balance')) ?: 'balance';
                    $refundMethodLabel = trim((string) ($payload['refund_method_label'] ?? ''));

                    if ($refundMethodLabel === '') {
                        $refundMethodLabel = $refundMethod === 'original' ? '原路退款' : '退回余额';
                    }

                    $balanceAfter = $this->setUserBalance($lockedUser, $this->getUserBalance($lockedUser) + $refundAmount);
                    $transaction = $this->financeLedgerWriter->createBalanceLog(
                        (int) $lockedUser->id,
                        FinanceLedgerEventType::INVOICE_REFUND,
                        $refundAmount,
                        $balanceAfter,
                        (int) $lockedInvoice->id,
                        '账单退款 '.(string) $lockedInvoice->invoice_no,
                        [
                            'operator' => (string) ($context['operator_name'] ?? ''),
                            'trace_id' => (string) ($context['trace_id'] ?? ''),
                        ],
                    );

                    $refundRecord = [
                        'refund_method' => $refundMethod,
                        'refund_method_label' => $refundMethodLabel,
                        'refund_amount' => number_format($refundAmount, 2, '.', ''),
                        'refund_reason' => $refundReason,
                        'trade_no' => (string) ($payment?->trade_no ?? ''),
                        'original_gateway' => $payment?->gatewayKey() ?? 'balance',
                        'original_gateway_label' => $payment instanceof Payment
                            ? $this->resolvePaymentGatewayLabel($payment->gatewayKey())
                            : '余额支付',
                        'operator_id' => (int) ($context['operator_id'] ?? 0),
                        'operator_name' => (string) ($context['operator_name'] ?? ''),
                        'trace_id' => (string) ($context['trace_id'] ?? ''),
                        'refunded_at' => now()->format('Y-m-d H:i:s'),
                    ];

                    $documents = $this->financeDocumentService->createBalanceRefundDocuments(
                        $lockedInvoice,
                        $payment,
                        $transaction,
                        $refundAmount,
                        $refundReason,
                        array_merge($context, ['operator_type' => $context['operator_type'] ?? 'admin']),
                    );
                    $isFullyRefunded = abs($refundAmount - $refundableAmount) <= 0.00001;

                    if ($payment instanceof Payment) {
                        $callbackRaw = (array) ($payment->callback_raw ?? []);
                        $callbackRaw['refund'] = $refundRecord;
                        $callbackRaw['refunds'] = array_values(array_merge(
                            (array) ($callbackRaw['refunds'] ?? []),
                            [$refundRecord]
                        ));

                        $paymentPayload = [
                            'callback_raw' => $callbackRaw,
                        ];
                        if ($isFullyRefunded) {
                            $paymentPayload['status'] = PaymentStatus::REFUNDED;
                        }
                        $payment->forceFill($paymentPayload)->save();
                        $this->callbackProjector->syncProjection($payment);
                    }

                    if ($isFullyRefunded) {
                        $this->markInvoiceRefunded($lockedInvoice, $refundRecord, $refundMethod, $refundAmount, $context);

                        // 全款退款回退推广奖励（幂等：订单退款流程已回退则直接跳过）
                        $refundTraceId = trim((string) ($context['trace_id'] ?? ''));
                        $this->referralService->reverseRewardForRefundedInvoice(
                            $lockedInvoice,
                            $refundTraceId !== '' ? "refund:{$refundTraceId}" : "refund:invoice:{$lockedInvoice->id}",
                        );
                    }

                    $scope = (array) ($payload['scope'] ?? ['order', 'payment']);

                    if ($isFullyRefunded && $order && in_array('order', $scope, true)) {
                        $order->forceFill([
                            'status' => OrderStatus::REFUNDED,
                        ])->save();
                    }

                    Log::info('[账单退款] 已退回用户余额', [
                        'invoice_id' => $lockedInvoice->id,
                        'invoice_no' => $lockedInvoice->invoice_no,
                        'payment_id' => $payment?->id,
                        'refund_amount' => $refundRecord['refund_amount'],
                        'user_id' => $lockedUser->id,
                    ]);

                    return [
                        'already_refunded' => false,
                        'invoice_id' => (int) $lockedInvoice->id,
                        'payment_id' => (int) ($payment?->id ?? 0),
                        'refund_id' => (int) $documents['refund']->id,
                        'refund_invoice_id' => (int) $documents['refund_invoice']->id,
                        'recharge_record_id' => $documents['recharge_record']?->id,
                        'refund' => $refundRecord,
                    ];
                });
            });
        } catch (LockTimeoutException) {
            throw new BusinessException('退款处理中，请勿重复提交');
        }
    }

    private function resolvePrimaryRefundablePayment(Invoice $invoice, ?array $gateways = null): ?Payment
    {
        $payments = $invoice->relationLoaded('payments')
            ? $invoice->payments
            : Payment::query()
                ->where('invoice_id', $invoice->id)
                ->whereIn('status', [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED])
                ->orderByDesc('id')
                ->get();

        if (is_array($gateways) && $gateways !== []) {
            $payments = $payments
                ->filter(fn (Payment $payment) => in_array($payment->gatewayKey(), $gateways, true))
                ->values();
        }

        // 已转入余额的异常支付（重复支付/超额支付）不得作为主退款支付单：
        // 其金额已通过 creditCapturedPaymentToBalance 退回用户余额，再按其原路/全额退款会造成双重退款。
        $isRefundablePayment = fn (Payment $payment): bool => in_array((int) $payment->status, [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED], true)
            && ! PaymentCallbackRaw::isCreditedToBalance($payment);

        return $payments
            ->first(fn (Payment $payment) => $isRefundablePayment($payment)
                && ! PaymentCallbackRaw::isDuplicatePaid($payment))
            ?? $payments->first($isRefundablePayment);
    }

    private function resolveBalanceRefundableAmount(Invoice $invoice, ?Payment $payment): float
    {
        $mixBalanceAmount = $payment instanceof Payment ? $this->mixPaymentService->resolveMixBalanceAmount($payment) : 0.0;
        if ($mixBalanceAmount > 0 || $this->invoiceHasBalancePayment($invoice)) {
            $grossRefundable = round(max((float) ($invoice->paid_amount ?? 0), (float) ($invoice->amount ?? 0)), 2);

            // 扣除已转入余额的异常支付（重复支付/超额支付）金额：
            // 该部分已退回用户余额，若退款时未扣除会造成与"转入余额"重复返还。
            return round(max($grossRefundable - $this->invoiceCreditedToBalanceAmount($invoice), 0), 2);
        }

        return $payment instanceof Payment ? round((float) $payment->amount, 2) : 0.0;
    }

    /**
     * 账单名下累计已转入余额的异常支付金额（creditCapturedPaymentToBalance 产生）。
     */
    private function invoiceCreditedToBalanceAmount(Invoice $invoice): float
    {
        $payments = $invoice->relationLoaded('payments')
            ? $invoice->payments
            : Payment::query()
                ->where('invoice_id', (int) $invoice->id)
                ->where('status', PaymentStatus::SUCCESS)
                ->get();

        return round((float) $payments
            ->filter(fn (Payment $payment) => PaymentCallbackRaw::isCreditedToBalance($payment))
            ->sum(fn (Payment $payment) => (float) (
                PaymentCallbackRaw::creditedAmount($payment) > 0
                    ? PaymentCallbackRaw::creditedAmount($payment)
                    : (float) ($payment->amount ?? 0)
            )), 2);
    }

    private function invoiceHasBalancePayment(Invoice $invoice): bool
    {
        // 台账 INVOICE_PAYMENT 行的 source_id 统一为账单 ID（历史错挂订单 ID 的
        // 存量已由订正迁移 2026_08_31_000001 归位），此处只需精确匹配账单本身。
        return AccountTransaction::query()
            ->where('user_id', (int) $invoice->user_id)
            ->where('event_type', FinanceLedgerEventType::INVOICE_PAYMENT)
            ->where('source_type', 'invoice')
            ->where('source_id', (int) $invoice->id)
            ->exists();
    }

    private function remainingRefundableAmount(Invoice $invoice, ?Payment $payment): float
    {
        $refundedAmount = $this->completedRefundAmount($invoice);

        return round(max($this->resolveBalanceRefundableAmount($invoice, $payment) - $refundedAmount, 0), 2);
    }

    private function completedRefundAmount(Invoice $invoice): float
    {
        return round((float) Refund::query()
            ->where('invoice_id', (int) $invoice->id)
            ->where('status', Refund::STATUS_COMPLETED)
            ->sum('amount'), 2);
    }

    private function resolvePaymentGatewayLabel(string $gateway): string
    {
        return match ($gateway) {
            // 与 PaymentGatewayCode::LABELS 一致的词条统一走集中定义，避免多处文案漂移。
            PaymentGatewayCode::ALIPAY,
            PaymentGatewayCode::YIPAY,
            PaymentGatewayCode::WECHAT,
            PaymentGatewayCode::BALANCE => PaymentGatewayCode::label($gateway),
            // 业务侧扩展口径与本地历史文案不属于集中 LABELS，保留原样。
            'bank_transfer' => '银行转账',
            'offline' => '线下支付',
            default => '手动入账',
        };
    }

    private function getUserBalance(User $user): float
    {
        return $this->accountService->cashBalance($user, true);
    }

    private function setUserBalance(User $user, float $balance): string
    {
        return $this->accountService->setCashBalance($user, $balance);
    }

    private function markInvoiceRefunded(
        Invoice $invoice,
        array $refundRecord,
        string $refundMethod,
        float $refundAmount,
        array $context = [],
    ): void {
        $traceId = trim((string) ($refundRecord['trace_id'] ?? $context['trace_id'] ?? ''));
        $refundedAt = trim((string) ($refundRecord['refunded_at'] ?? $refundRecord['gmt_refund_pay'] ?? ''));
        $normalizedRefundAmount = number_format(round(max(
            $refundAmount,
            (float) ($refundRecord['refund_amount'] ?? $refundRecord['refund_fee'] ?? 0)
        ), 2), 2, '.', '');

        $payload = [
            'status' => InvoiceStatus::REFUNDED,
        ];

        if (SchemaMetadataCache::hasColumn('invoices', 'refunded_at')) {
            $payload['refunded_at'] = $refundedAt !== '' ? $refundedAt : now();
        }

        if (SchemaMetadataCache::hasColumn('invoices', 'refund_amount')) {
            $payload['refund_amount'] = $normalizedRefundAmount;
        }

        if (SchemaMetadataCache::hasColumn('invoices', 'refund_method')) {
            $payload['refund_method'] = trim($refundMethod) !== '' ? trim($refundMethod) : 'balance';
        }

        if (SchemaMetadataCache::hasColumn('invoices', 'refund_trace_id')) {
            $payload['refund_trace_id'] = $traceId !== '' ? $traceId : null;
        } elseif ($traceId !== '' && SchemaMetadataCache::hasColumn('invoices', 'trace_id')) {
            $payload['trace_id'] = $traceId;
        }

        $invoice->forceFill($payload)->save();

        // 退款成功后与取消路径（CheckoutService/OrderService）保持同一口径：
        // 重算优惠券用量与状态，无有效已付账单时把用户券回落为可复用。方法本身幂等。
        $this->couponService->syncInvoiceCouponUsage($invoice);
    }
}
