<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\User\AccountService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 消费-余额支付流：账单全额余额支付（不产生 Payment 记录）与订单余额支付
 * （含自动续费 0 元特例与台账 source 挂账单的口径）。
 * 支付成功后的履约/返利编排交给 InvoicePaidOrchestrator；账单维度关其他
 * PENDING 支付单复用统一入账引擎的 closeOtherPendingPayments。
 */
class BalanceInvoicePaymentService
{
    public function __construct(
        private AccountService $accountService,
        private FinanceLedgerWriter $financeLedgerWriter,
        private PaymentCallbackProjector $callbackProjector,
        private InvoiceSettlementService $invoiceSettlementService,
        private InvoicePaidOrchestrator $paidInvoiceOrchestrator,
    ) {}

    /**
     * 余额支付（不产生 Payment 记录）
     */
    public function payByBalance(Invoice $invoice, User $user, array $context = []): Invoice
    {
        $traceId = trim((string) ($context['trace_id'] ?? ''));
        $lockKey = "lock:pay:invoice:{$invoice->id}";

        $paidInvoice = $this->withLock($lockKey, 30, function () use ($invoice, $user, $traceId, $context) {
            return DB::transaction(function () use ($invoice, $user, $traceId, $context) {
                $lockedInvoice = Invoice::query()
                    ->lockForUpdate()
                    ->with('order')
                    ->findOrFail($invoice->id);
                $lockedUser = User::query()
                    ->lockForUpdate()
                    ->findOrFail($user->id);

                throw_if(
                    ! in_array((int) $lockedInvoice->status, [InvoiceStatus::UNPAID], true),
                    new BusinessException('账单状态异常，无法支付')
                );

                $amount = round((float) $lockedInvoice->amount - (float) ($lockedInvoice->paid_amount ?? 0), 2);
                $currentBalance = $this->getUserBalance($lockedUser);
                throw_if($amount <= 0, new BusinessException('当前账单无需支付'));
                throw_if($currentBalance < $amount, new BusinessException('余额不足'));

                $balanceAfter = $this->setUserBalance($lockedUser, $currentBalance - $amount);
                $this->financeLedgerWriter->createBalanceLog(
                    (int) $lockedUser->id,
                    FinanceLedgerEventType::INVOICE_PAYMENT,
                    -$amount,
                    $balanceAfter,
                    (int) $lockedInvoice->id,
                    '支付账单 '.(string) $lockedInvoice->invoice_no,
                    [
                        'operator' => trim((string) ($context['operator'] ?? $context['operator_name'] ?? '')),
                        'trace_id' => $traceId,
                    ]
                );

                $lockedInvoice->forceFill([
                    'status' => InvoiceStatus::PAID,
                    'paid_amount' => $lockedInvoice->amount,
                    'paid_at' => now(),
                    'trace_id' => $traceId !== '' ? $traceId : $lockedInvoice->trace_id,
                ])->save();

                // 订单 paid_amount 投影账单累计实收额，补尾款不能覆盖此前已付金额。
                $lockedInvoice->order?->forceFill([
                    'status' => OrderStatus::PAID,
                    'paid_amount' => $lockedInvoice->amount,
                    'paid_at' => now(),
                ])->save();

                $this->invoiceSettlementService->closeOtherPendingPayments($lockedInvoice, 0, 'invoice_paid_by_balance');

                return $lockedInvoice;
            });
        }, '支付请求处理中，请勿重复提交');

        $this->paidInvoiceOrchestrator->handlePaidInvoice($invoice, $traceId !== '' ? 'balance:'.$traceId : 'balance:'.$invoice->id);

        return $paidInvoice->fresh() ?? $paidInvoice;
    }

    public function payOrderByBalance(Order $order, User $user, array $context = []): Invoice
    {
        $traceId = trim((string) ($context['trace_id'] ?? ''));
        $lockKey = "lock:pay:order:{$order->id}";

        $paidInvoice = $this->withLock($lockKey, 30, function () use ($order, $user, $traceId, $context) {
            return DB::transaction(function () use ($order, $user, $traceId, $context) {
                $lockedOrder = Order::query()
                    ->lockForUpdate()
                    ->with('invoice')
                    ->findOrFail($order->id);
                $lockedUser = User::query()
                    ->lockForUpdate()
                    ->findOrFail($user->id);

                throw_if(
                    (int) $lockedOrder->status !== OrderStatus::PENDING,
                    new BusinessException('当前订单状态不支持支付')
                );

                $amount = $this->resolveOrderPayableAmount($lockedOrder);
                throw_if(
                    $amount <= 0 && $this->isAutoRenewBalancePayment($lockedOrder, $context),
                    new BusinessException('自动续费金额异常，已拦截本次续费')
                );

                if ($amount > 0) {
                    $currentBalance = $this->getUserBalance($lockedUser);
                    throw_if($currentBalance < $amount, new BusinessException('余额不足'));

                    $balanceAfter = $this->setUserBalance($lockedUser, $currentBalance - $amount);
                    $this->financeLedgerWriter->createBalanceLog(
                        (int) $lockedUser->id,
                        FinanceLedgerEventType::INVOICE_PAYMENT,
                        -$amount,
                        $balanceAfter,
                        // 台账 source_type 固定为 invoice，这里必须挂账单 ID：
                        // 订单/账单自增序列独立，挂订单 ID 会让台账反查到无关账单。
                        $lockedOrder->invoice instanceof Invoice ? (int) $lockedOrder->invoice->id : null,
                        '支付订单 '.(string) $lockedOrder->order_no,
                        [
                            'trace_id' => $traceId,
                        ]
                    );
                }

                // 订单 paid_amount 投影账单累计实收额（与 payByBalance 一致）：
                // 先期部分支付后再补尾款时，不能把累计实收覆盖为本次剩余应付。
                $lockedOrder->forceFill([
                    'status' => OrderStatus::PAID,
                    'paid_amount' => $lockedOrder->invoice instanceof Invoice
                        ? $lockedOrder->invoice->amount
                        : $lockedOrder->amount,
                    'paid_at' => now(),
                ])->save();

                if ($lockedOrder->invoice instanceof Invoice) {
                    $configSnapshot = is_array($lockedOrder->invoice->config_snapshot ?? null)
                        ? $lockedOrder->invoice->config_snapshot : [];
                    $configSnapshot['fulfillment_pending'] = true;
                    $configSnapshot['fulfillment_type'] = (string) $lockedOrder->type;

                    $lockedOrder->invoice->forceFill([
                        'status' => InvoiceStatus::PAID,
                        'paid_amount' => $lockedOrder->invoice->amount,
                        'paid_at' => now(),
                        'trace_id' => $traceId !== '' ? $traceId : $lockedOrder->invoice->trace_id,
                        'config_snapshot' => $configSnapshot,
                    ])->save();
                }

                $this->closeOtherPendingPaymentsForOrder($lockedOrder, 0, 'order_paid_by_balance');

                return $lockedOrder->invoice;
            });
        }, '支付请求处理中，请勿重复提交');

        $order = $order->fresh(['invoice']) ?? $order;
        if ($order->invoice instanceof Invoice) {
            $this->paidInvoiceOrchestrator->handlePaidInvoice($order->invoice, $traceId !== '' ? 'balance:'.$traceId : 'balance:'.$order->id);
        }

        return $paidInvoice?->fresh() ?? $paidInvoice ?? $order->invoice;
    }

    private function resolveOrderPayableAmount(Order $order): float
    {
        return round(
            max(
                (float) ($order->amount ?? 0)
                - (float) ($order->discount ?? 0)
                - (float) ($order->member_discount_amount ?? 0)
                - (float) ($order->paid_amount ?? 0),
                0
            ),
            2
        );
    }

    private function isAutoRenewBalancePayment(Order $order, array $context = []): bool
    {
        if (! empty($context['auto_renew'])) {
            return true;
        }

        $configSnapshot = is_array($order->config_snapshot ?? null) ? $order->config_snapshot : [];

        return ! empty($configSnapshot['auto_renew']);
    }

    private function closeOtherPendingPaymentsForOrder(Order $order, int $excludePaymentId, string $reason): void
    {
        $invoiceId = (int) ($order->invoice?->id ?? 0);

        $query = Payment::query()
            ->where('status', PaymentStatus::PENDING)
            ->where(function ($query) use ($order, $invoiceId) {
                $query->where('order_id', $order->id);

                if ($invoiceId > 0) {
                    $query->orWhere('invoice_id', $invoiceId);
                }
            });

        if ($excludePaymentId > 0) {
            $query->where('id', '!=', $excludePaymentId);
        }

        $pendingPayments = $query->lockForUpdate()->get();

        foreach ($pendingPayments as $pendingPayment) {
            $callbackRaw = (array) ($pendingPayment->callback_raw ?? []);
            $callbackRaw['closed_reason'] = $reason;

            $pendingPayment->forceFill([
                'status' => PaymentStatus::CANCELLED,
                'callback_raw' => $callbackRaw,
            ])->save();
            $this->callbackProjector->syncProjection($pendingPayment);
        }
    }

    private function getUserBalance(User $user): float
    {
        return $this->accountService->cashBalance($user, true);
    }

    private function setUserBalance(User $user, float $balance): string
    {
        return $this->accountService->setCashBalance($user, $balance);
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
