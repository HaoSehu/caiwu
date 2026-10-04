<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use App\Services\Integrations\Payments\PaymentGatewayOperationService;
use App\Services\Integrations\Plugins\PaymentGatewayBindingResolver;
use App\Services\User\AccountService;
use Illuminate\Support\Facades\DB;

/**
 * 混合支付流 + 预扣余额回补/异常网关款转余额群：
 * payByBalanceAndGateway 单事务内先扣余额入 paid_amount，余款建 mix Payment 预下单；
 * 回调失败/取消/过期路径经 restoreReservedMixBalance 回补（balance_restored 幂等闸）；
 * 重复/超额/取消/过期回调捕获款经 creditCapturedPaymentToBalance 转余额
 * （credited_to_balance 幂等闸）。
 * callback_raw 幂等标志读写口径统一走 PaymentCallbackRaw。
 */
class MixPaymentService
{
    use PaymentGatewayFlow;

    private ?PaymentGatewayOperationService $gatewayOperations = null;

    public function __construct(
        private PaymentGatewayManager $paymentGatewayManager,
        private CheckoutSecurityService $checkoutSecurityService,
        private AccountService $accountService,
        private FinanceLedgerWriter $financeLedgerWriter,
        private PaymentCallbackProjector $callbackProjector,
        private ?PaymentGatewayBindingResolver $paymentGatewayBindingResolver = null,
    ) {}

    /**
     * 先扣余额，再为剩余金额通过指定第三方网关生成二维码（通用入口）
     *
     * @return array<string, mixed>
     */
    public function payByBalanceAndGateway(Invoice $invoice, User $user, float $balanceAmount, string $gateway, array $context = []): array
    {
        $resolvedGateway = $this->resolveGateway($gateway);

        throw_if(! $resolvedGateway->isEnabled(), new BusinessException(
            PaymentGatewayCode::label($gateway).'支付未启用'
        ));

        $traceId = $this->resolveTraceId($context, "mix:invoice:{$invoice->id}");
        $lockKey = "lock:pay:mix:invoice:{$invoice->id}";
        $normalizedBalanceAmount = round(max($balanceAmount, 0), 2);

        $payload = $this->withLock($lockKey, 20, function () use ($invoice, $user, $traceId, $normalizedBalanceAmount) {
            return DB::transaction(function () use ($invoice, $user, $traceId, $normalizedBalanceAmount) {
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

                $remainingAmount = round((float) $lockedInvoice->amount - (float) ($lockedInvoice->paid_amount ?? 0), 2);
                throw_if($remainingAmount <= 0, new BusinessException('当前账单无需支付'));
                throw_if($normalizedBalanceAmount <= 0, new BusinessException('余额支付金额必须大于 0'));
                throw_if($normalizedBalanceAmount >= $remainingAmount, new BusinessException('余额支付金额需小于待支付金额'));

                $currentBalance = $this->getUserBalance($lockedUser);
                throw_if($currentBalance < $normalizedBalanceAmount, new BusinessException('余额不足'));

                $balanceAfter = $this->setUserBalance($lockedUser, $currentBalance - $normalizedBalanceAmount);
                $this->financeLedgerWriter->createBalanceLog(
                    (int) $lockedUser->id,
                    FinanceLedgerEventType::INVOICE_PAYMENT,
                    -$normalizedBalanceAmount,
                    $balanceAfter,
                    (int) $lockedInvoice->id,
                    '账单余额支付 '.(string) $lockedInvoice->invoice_no,
                    [
                        'trace_id' => $traceId,
                    ]
                );

                $nextPaidAmount = round((float) ($lockedInvoice->paid_amount ?? 0) + $normalizedBalanceAmount, 2);
                $lockedInvoice->forceFill([
                    'paid_amount' => $nextPaidAmount,
                    'trace_id' => $traceId !== '' ? $traceId : $lockedInvoice->trace_id,
                ])->save();

                return [
                    'invoice' => $lockedInvoice,
                    'remaining_amount' => round(max((float) $lockedInvoice->amount - $nextPaidAmount, 0), 2),
                ];
            });
        }, '支付请求处理中，请勿重复提交');

        /** @var Invoice $lockedInvoice */
        $lockedInvoice = $payload['invoice'];
        $remainingAmount = (float) ($payload['remaining_amount'] ?? 0);

        throw_if($remainingAmount <= 0, new BusinessException('当前账单无需继续发起'.PaymentGatewayCode::label($gateway).'支付'));

        $gatewayPayment = DB::transaction(function () use ($lockedInvoice, $user, $remainingAmount, $traceId, $normalizedBalanceAmount, $gateway) {
            $existingPayment = Payment::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->whereGatewayKey($gateway)
                ->where('status', PaymentStatus::PENDING)
                ->where('amount', $remainingAmount)
                ->latest('id')
                ->first();

            if ($existingPayment instanceof Payment) {
                $existingRaw = (array) ($existingPayment->callback_raw ?? []);

                // 已退回过余额（balance_restored）或余额预扣额与本次不一致的旧单不可复用：
                // 复用会让本次预扣在后续取消时被旧单的回补幂等闸拦截，造成预扣余额被冻结。
                if (! empty($existingRaw['balance_restored'])
                    || (float) ($existingRaw['balance_amount'] ?? 0) !== (float) $normalizedBalanceAmount) {
                    $existingPayment = null;
                }
            }

            if ($existingPayment instanceof Payment) {
                $this->ensurePaymentGatewayAudit($existingPayment, $gateway, $traceId);

                return $existingPayment;
            }

            $payment = Payment::query()->create($this->paymentCreatePayload([
                'payment_no' => Payment::generatePaymentNo(),
                'user_id' => $user->id,
                'order_id' => (int) ($lockedInvoice->order?->id ?? 0) ?: null,
                'invoice_id' => $lockedInvoice->id,
                'gateway' => $gateway,
                'amount' => $remainingAmount,
                'status' => PaymentStatus::PENDING,
                'trace_id' => $traceId,
                'callback_raw' => [
                    'source' => "{$gateway}_precreate_mix",
                    'trace_id' => $traceId,
                    'mix_payment' => true,
                    'balance_amount' => $normalizedBalanceAmount,
                ],
            ]));
            $this->callbackProjector->syncProjection($payment);

            return $payment;
        });

        $subject = config('app.name', 'IDC').' - 账单 '.$lockedInvoice->invoice_no;
        try {
            $result = $this->precreateGatewayPayment(
                $gateway,
                $gatewayPayment->payment_no,
                $remainingAmount,
                $subject,
                $this->resolveInvoicePaymentTimeoutExpress($lockedInvoice),
                $context
            );
        } catch (\Throwable $exception) {
            $this->restoreReservedMixBalance($gatewayPayment, [
                'trace_id' => $traceId,
                'closed_reason' => "{$gateway}_precreate_failed",
                'suppress_logs' => true,
            ]);

            throw $exception;
        }

        return [
            'balance_amount' => number_format($normalizedBalanceAmount, 2, '.', ''),
            'payment_no' => $gatewayPayment->payment_no,
            'qr_code' => $result['qr_code'],
            'amount' => number_format($remainingAmount, 2, '.', ''),
            'paid_amount' => number_format((float) $lockedInvoice->paid_amount, 2, '.', ''),
            'payable_amount' => number_format($remainingAmount, 2, '.', ''),
        ];
    }

    /**
     * 混合支付预扣余额回补（balance_restored 幂等闸）。
     * 调用方：用户/系统取消账单（CheckoutService/OrderService）、回调与轮询的
     * 已取消/过期分支、预下单失败回滚。
     */
    public function restoreReservedMixBalance(Payment $payment, array $context = []): bool
    {
        return DB::transaction(function () use ($payment, $context) {
            $lockedPayment = Payment::query()
                ->lockForUpdate()
                ->find((int) $payment->id);

            if (! $lockedPayment instanceof Payment) {
                return false;
            }

            $balanceAmount = $this->resolveMixBalanceAmount($lockedPayment);
            // 幂等闸：预扣余额已退回过的支付单不再重复退回。
            // 回调/轮询路径（mark_payment_failed=false）退回后支付单仍保持 PENDING，
            // 若仅靠「金额>0 且 PENDING」放行，重复回调或并发轮询会把预扣余额退两次。
            if (PaymentCallbackRaw::isBalanceRestored($lockedPayment)) {
                return false;
            }

            if ($balanceAmount <= 0 || (int) $lockedPayment->status !== PaymentStatus::PENDING) {
                return false;
            }

            $invoice = $lockedPayment->invoice_id
                ? Invoice::query()->lockForUpdate()->with('order')->find((int) $lockedPayment->invoice_id)
                : null;
            $lockedUser = User::query()
                ->lockForUpdate()
                ->find((int) $lockedPayment->user_id);

            if (! $invoice instanceof Invoice || ! $lockedUser instanceof User) {
                return false;
            }

            $markPaymentFailed = array_key_exists('mark_payment_failed', $context)
                ? (bool) $context['mark_payment_failed']
                : true;
            $preserveInvoicePaidAmount = (bool) ($context['preserve_invoice_paid_amount'] ?? false);

            $balanceAfter = $this->setUserBalance($lockedUser, $this->getUserBalance($lockedUser) + $balanceAmount);
            $suppressLogs = (bool) ($context['suppress_logs'] ?? false);
            $this->financeLedgerWriter->createBalanceLog(
                (int) $lockedUser->id,
                FinanceLedgerEventType::INVOICE_REFUND,
                $balanceAmount,
                $balanceAfter,
                (int) $invoice->id,
                $suppressLogs
                    ? '组合支付预下单失败恢复余额 '.(string) $invoice->invoice_no
                    : '组合支付取消退回余额 '.(string) $invoice->invoice_no,
                [
                    'trace_id' => (string) ($context['trace_id'] ?? ''),
                ],
            );

            if (! $preserveInvoicePaidAmount) {
                $nextInvoicePaidAmount = number_format(
                    max(round((float) ($invoice->paid_amount ?? 0) - $balanceAmount, 2), 0),
                    2,
                    '.',
                    ''
                );
                $invoice->forceFill([
                    'paid_amount' => $nextInvoicePaidAmount,
                ])->save();

                if ($invoice->order instanceof Order) {
                    $nextOrderPaidAmount = number_format(
                        max(round((float) ($invoice->order->paid_amount ?? 0) - $balanceAmount, 2), 0),
                        2,
                        '.',
                        ''
                    );
                    $invoice->order->forceFill([
                        'paid_amount' => $nextOrderPaidAmount,
                    ])->save();
                }
            }

            $callbackRaw = PaymentCallbackRaw::rawDecode($lockedPayment);
            $callbackRaw[PaymentCallbackRaw::KEY_BALANCE_RESTORED] = true;
            $callbackRaw[PaymentCallbackRaw::KEY_BALANCE_RESTORED_AMOUNT] = number_format($balanceAmount, 2, '.', '');
            $callbackRaw['closed_reason'] = (string) ($context['closed_reason'] ?? 'mix_balance_restored');
            $callbackRaw['trace_id'] = (string) ($context['trace_id'] ?? ($callbackRaw['trace_id'] ?? ''));

            $paymentPayload = [
                'callback_raw' => $callbackRaw,
            ];
            if ($markPaymentFailed) {
                $paymentPayload['status'] = PaymentStatus::CANCELLED;
            }

            $lockedPayment->forceFill($paymentPayload)->save();
            $this->callbackProjector->syncProjection($lockedPayment);

            return true;
        });
    }

    /**
     * 捕获款金额大于账单当前应付时，回补账单名下其他混合支付单的预扣余额。
     * 调用方：统一入账引擎（notify / 主动查询的未支付主流程前置）。
     */
    public function restoreConflictingMixBalancesForCapturedPayment(
        Invoice $invoice,
        Payment $capturedPayment,
        string $reason,
        array $context = [],
    ): Invoice {
        $paymentAmount = round((float) $capturedPayment->amount, 2);
        if ($paymentAmount <= $this->invoicePayableAmount($invoice) + 0.0001) {
            return $invoice;
        }

        $pendingPayments = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', PaymentStatus::PENDING)
            ->where('id', '!=', $capturedPayment->id)
            ->lockForUpdate()
            ->get();

        foreach ($pendingPayments as $pendingPayment) {
            if ($this->resolveMixBalanceAmount($pendingPayment) <= 0) {
                continue;
            }

            $this->restoreReservedMixBalance($pendingPayment, [
                'closed_reason' => $reason,
                'trace_id' => (string) ($context['trace_id'] ?? ''),
            ]);
        }

        return Invoice::query()
            ->lockForUpdate()
            ->with('order')
            ->findOrFail((int) $invoice->id);
    }

    /**
     * 异常捕获款（重复/超额/已取消/过期/金额不符）转入余额，credited_to_balance 幂等闸。
     * 调用方：统一入账引擎四分支；测试经反射直接驱动本方法固化行为。
     */
    public function creditCapturedPaymentToBalance(
        Payment $payment,
        Invoice $invoice,
        string $tradeNo,
        array $raw,
        string $reason,
    ): void {
        $payment->refresh();
        // 幂等闸：该支付单已转入余额过则跳过，防止重复回调/并发轮询把同一笔网关款重复入账。
        if (PaymentCallbackRaw::isCreditedToBalance($payment)) {
            return;
        }

        $amount = round((float) $payment->amount, 2);
        if ($amount <= 0) {
            return;
        }

        $traceId = (string) ($raw['trace_id'] ?? $payment->trace_id ?? '');
        $lockedUser = User::query()
            ->lockForUpdate()
            ->findOrFail((int) $payment->user_id);
        $balanceAfter = $this->setUserBalance($lockedUser, $this->getUserBalance($lockedUser) + $amount);

        $this->financeLedgerWriter->createBalanceLog(
            (int) $lockedUser->id,
            FinanceLedgerEventType::RECHARGE,
            $amount,
            $balanceAfter,
            (int) $payment->id,
            '异常支付转入余额 '.(string) $payment->payment_no,
            [
                'trace_id' => $traceId,
            ]
        );

        $reasonFlags = match ($reason) {
            'invoice_already_paid' => [PaymentCallbackRaw::KEY_DUPLICATE_PAID => true],
            'cancelled_invoice' => ['cancelled_invoice' => true],
            'payment_window_expired' => ['payment_window_expired' => true],
            default => ['payable_amount_mismatch' => true],
        };

        $callbackRaw = array_merge(PaymentCallbackRaw::rawDecode($payment), $raw, $reasonFlags, [
            'abnormal_invoice_payment' => true,
            PaymentCallbackRaw::KEY_CREDITED_TO_BALANCE => true,
            PaymentCallbackRaw::KEY_CREDIT_REASON => $reason,
            PaymentCallbackRaw::KEY_CREDITED_AMOUNT => number_format($amount, 2, '.', ''),
            'ignored_business_update' => true,
            'invoice_id' => (int) $invoice->id,
        ]);

        $payment->forceFill([
            'trade_no' => $tradeNo,
            'status' => PaymentStatus::SUCCESS,
            'callback_raw' => $callbackRaw,
            'paid_at' => now(),
            'trace_id' => $traceId !== '' ? $traceId : $payment->trace_id,
        ])->save();
        $this->callbackProjector->syncProjection($payment);
    }

    /**
     * 读取支付单的混合支付预扣余额（mix_payment/balance_amount 口径的唯一读口）。
     * 退款口径（resolveBalanceRefundableAmount）与冲突回补共用。
     */
    public function resolveMixBalanceAmount(Payment $payment): float
    {
        $callbackRaw = PaymentCallbackRaw::rawDecode($payment, true);
        if (! PaymentCallbackRaw::isMixPayment($callbackRaw)) {
            return 0.0;
        }

        return PaymentCallbackRaw::mixBalanceAmount($callbackRaw);
    }

    /**
     * 与统一入账引擎各持一份的私有副本（3 行纯计算），避免 Mix↔引擎互依成环。
     */
    private function invoicePayableAmount(Invoice $invoice): float
    {
        return round(max((float) $invoice->amount - (float) ($invoice->paid_amount ?? 0), 0), 2);
    }

    private function getUserBalance(User $user): float
    {
        return $this->accountService->cashBalance($user, true);
    }

    private function setUserBalance(User $user, float $balance): string
    {
        return $this->accountService->setCashBalance($user, $balance);
    }
}
