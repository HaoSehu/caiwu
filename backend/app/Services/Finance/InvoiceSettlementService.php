<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 统一入账引擎（对应 zjmf orderPayHandle 的角色）：
 * 网关回调与主动轮询两条通道共用的入账事务体——已付/已取消/过期/金额不符四分支
 * 与「置 SUCCESS -> 账单 PAID -> 订单 PAID -> 关其他支付 -> 凭证」主流程。
 *
 * 两条通道的既有差异全部由入口参数传入，不在此抹平：
 * - 锁键（notify 用原始 gateway，query 用消毒后 safeGateway）
 * - 日志标签（回调 / 主动查询）
 * - closeOtherPendingPayments 的关闭原因（invoice_paid_by_gateway / ..._query）
 * - 入账 payload（回调参数 / 主动查询组装体）
 * - 状态异常文案与锁超时文案
 * 消毒逻辑留在 query 入口，保证 notify 通道锁键与并发行为不变。
 *
 * 本类自身不直写余额与台账：异常款转余额/预扣回补经 MixPaymentService
 * （其内部经 AccountService 与 FinanceLedgerWriter），第三方实付凭证经
 * FinanceDocumentService 落库。
 */
class InvoiceSettlementService
{
    public function __construct(
        private MixPaymentService $mixPaymentService,
        private PaymentCallbackProjector $callbackProjector,
        private FinanceDocumentService $financeDocumentService,
        private CheckoutSecurityService $checkoutSecurityService,
        private TradeLifecycleService $tradeLifecycleService,
    ) {}

    /**
     * 捕获款入账（notify 与主动查询唯一共用入口）。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function settleCapturedGatewayPayment(
        Payment $payment,
        string $tradeNo,
        array $payload,
        string $lockKey,
        string $logLabel,
        string $closeReason,
        string $abnormalStatusMessage,
        string $timeoutMessage,
    ): array {
        return $this->withLock($lockKey, 30, function () use ($payment, $tradeNo, $payload, $logLabel, $closeReason, $abnormalStatusMessage) {
            return DB::transaction(function () use ($payment, $tradeNo, $payload, $logLabel, $closeReason, $abnormalStatusMessage) {
                $lockedPayment = Payment::query()
                    ->lockForUpdate()
                    ->with(['invoice.order'])
                    ->find($payment->id);

                if (! $lockedPayment || (int) $lockedPayment->status === PaymentStatus::SUCCESS) {
                    return [
                        'dispatch' => false,
                        'invoice' => $lockedPayment?->invoice,
                        'payment_no' => (string) ($lockedPayment?->payment_no ?? ''),
                    ];
                }

                $invoice = $lockedPayment->invoice_id
                    ? Invoice::query()->lockForUpdate()->with('order')->find($lockedPayment->invoice_id)
                    : null;

                if (! $invoice) {
                    return ['dispatch' => false, 'invoice' => null, 'payment_no' => (string) $lockedPayment->payment_no];
                }

                if ((int) $invoice->status === InvoiceStatus::PAID) {
                    $this->mixPaymentService->creditCapturedPaymentToBalance($lockedPayment, $invoice, $tradeNo, $payload, 'invoice_already_paid');

                    Log::warning("[{$logLabel}] 检测到重复支付，已拦截二次入账", [
                        'payment_no' => $lockedPayment->payment_no,
                        'invoice_id' => $invoice->id,
                        'order_id' => $invoice->order?->id,
                    ]);

                    return ['dispatch' => false, 'invoice' => $invoice, 'payment_no' => (string) $lockedPayment->payment_no];
                }

                if ((int) $invoice->status === InvoiceStatus::CANCELLED) {
                    $this->mixPaymentService->restoreReservedMixBalance($lockedPayment, [
                        'closed_reason' => 'cancelled_invoice_captured',
                        'mark_payment_failed' => false,
                        'trace_id' => (string) ($payload['trace_id'] ?? ''),
                    ]);
                    $lockedPayment->refresh();
                    $this->mixPaymentService->creditCapturedPaymentToBalance($lockedPayment, $invoice, $tradeNo, $payload, 'cancelled_invoice');

                    Log::warning("[{$logLabel}] 已取消账单收到支付回调，已拦截入账", [
                        'payment_no' => $lockedPayment->payment_no,
                        'invoice_id' => $invoice->id,
                        'order_id' => $invoice->order?->id,
                    ]);

                    return ['dispatch' => false, 'invoice' => $invoice, 'payment_no' => (string) $lockedPayment->payment_no];
                }

                if ($this->checkoutSecurityService->isPaymentSessionExpired($invoice)) {
                    $this->mixPaymentService->restoreReservedMixBalance($lockedPayment, [
                        'closed_reason' => 'payment_window_expired_captured',
                        'mark_payment_failed' => false,
                        'trace_id' => (string) ($payload['trace_id'] ?? ''),
                    ]);
                    $lockedPayment->refresh();
                    $this->mixPaymentService->creditCapturedPaymentToBalance($lockedPayment, $invoice, $tradeNo, $payload, 'payment_window_expired');
                    $this->cancelExpiredInvoiceAfterCapturedPayment($invoice, $lockedPayment);

                    Log::warning("[{$logLabel}] 账单支付窗口已过期，已取消账单并拦截入账", [
                        'payment_no' => $lockedPayment->payment_no,
                        'invoice_id' => $invoice->id,
                        'order_id' => $invoice->order?->id,
                    ]);

                    return ['dispatch' => false, 'invoice' => $invoice, 'payment_no' => (string) $lockedPayment->payment_no];
                }

                throw_if(
                    ! in_array((int) $invoice->status, [InvoiceStatus::UNPAID], true),
                    new BusinessException($abnormalStatusMessage)
                );

                $invoice = $this->mixPaymentService->restoreConflictingMixBalancesForCapturedPayment(
                    $invoice,
                    $lockedPayment,
                    'captured_payment_superseded_mix_balance',
                    $payload
                );

                if (abs(round((float) $lockedPayment->amount, 2) - $this->invoicePayableAmount($invoice)) > 0.0001) {
                    $this->mixPaymentService->creditCapturedPaymentToBalance($lockedPayment, $invoice, $tradeNo, $payload, 'payable_amount_mismatch');

                    Log::warning("[{$logLabel}] 支付金额与账单当前应付不匹配，已转入余额", [
                        'payment_no' => $lockedPayment->payment_no,
                        'invoice_id' => $invoice->id,
                        'payment_amount' => number_format((float) $lockedPayment->amount, 2, '.', ''),
                        'payable_amount' => number_format($this->invoicePayableAmount($invoice), 2, '.', ''),
                    ]);

                    return ['dispatch' => false, 'invoice' => $invoice, 'payment_no' => (string) $lockedPayment->payment_no];
                }

                $lockedPayment->forceFill([
                    'trade_no' => $tradeNo,
                    'status' => PaymentStatus::SUCCESS,
                    'callback_raw' => $payload,
                    'paid_at' => now(),
                    'trace_id' => (string) ($payload['trace_id'] ?? $lockedPayment->trace_id),
                ])->save();
                $this->callbackProjector->syncProjection($lockedPayment);

                $invoice = $this->tradeLifecycleService->markInvoicePaid($invoice, [
                    'trace_id' => (string) ($payload['trace_id'] ?? ''),
                ]);

                $this->closeOtherPendingPayments($invoice, (int) $lockedPayment->id, $closeReason);
                $this->recordSuccessfulInvoicePayment($lockedPayment, $invoice);

                return [
                    'dispatch' => true,
                    'invoice' => $invoice,
                    'payment_no' => (string) $lockedPayment->payment_no,
                ];
            });
        }, $timeoutMessage);
    }

    public function closeOtherPendingPayments(Invoice $invoice, int $excludePaymentId, string $reason, bool $restoreReservedBalance = false): void
    {
        $query = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', PaymentStatus::PENDING);

        if ($excludePaymentId > 0) {
            $query->where('id', '!=', $excludePaymentId);
        }

        $pendingPayments = $query->lockForUpdate()->get();

        foreach ($pendingPayments as $pendingPayment) {
            if ($restoreReservedBalance) {
                $this->mixPaymentService->restoreReservedMixBalance($pendingPayment, [
                    'closed_reason' => $reason,
                ]);
                $pendingPayment->refresh();
            }

            $callbackRaw = (array) ($pendingPayment->callback_raw ?? []);
            $callbackRaw['closed_reason'] = $reason;

            $pendingPayment->forceFill([
                'status' => PaymentStatus::CANCELLED,
                'callback_raw' => $callbackRaw,
            ])->save();
            $this->callbackProjector->syncProjection($pendingPayment);
        }
    }

    private function cancelExpiredInvoiceAfterCapturedPayment(Invoice $invoice, Payment $payment): void
    {
        $this->tradeLifecycleService->cancelTrade($invoice);
        $this->closeOtherPendingPayments($invoice, (int) $payment->id, 'payment_window_expired', true);
    }

    private function recordSuccessfulInvoicePayment(Payment $payment, Invoice $invoice): void
    {
        if (! in_array(InvoiceType::normalize((string) $invoice->type), [InvoiceType::NEW_PURCHASE, InvoiceType::RENEW], true)) {
            return;
        }

        $this->financeDocumentService->recordThirdPartyPayment($payment, $invoice);
    }

    /**
     * 与 MixPaymentService 各持一份的私有副本（3 行纯计算），避免引擎↔Mix 互依成环。
     */
    private function invoicePayableAmount(Invoice $invoice): float
    {
        return round(max((float) $invoice->amount - (float) ($invoice->paid_amount ?? 0), 0), 2);
    }

    private function withLock(string $lockKey, int $seconds, callable $callback, string $timeoutMessage): mixed
    {
        try {
            return Cache::lock($lockKey, $seconds)->block(5, $callback);
        } catch (LockTimeoutException) {
            throw new BusinessException($timeoutMessage);
        }
    }
}
