<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\User;
use App\Services\User\AccountService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 消费-余额支付流：账单全额余额支付（不产生 Payment 记录）。
 * 支付成功后的履约/返利编排交给 InvoicePaidOrchestrator；账单维度关其他
 * PENDING 支付单复用统一入账引擎的 closeOtherPendingPayments。
 */
class BalanceInvoicePaymentService
{
    public function __construct(
        private AccountService $accountService,
        private FinanceLedgerWriter $financeLedgerWriter,
        private InvoiceSettlementService $invoiceSettlementService,
        private InvoicePaidOrchestrator $paidInvoiceOrchestrator,
        private TradeLifecycleService $tradeLifecycleService,
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
                        // 控制器上下文统一 actor_name，兼容服务层 operator_name/operator 键
                        'operator' => trim((string) ($context['actor_name'] ?? $context['operator_name'] ?? $context['operator'] ?? '')),
                        'trace_id' => $traceId,
                    ]
                );

                $lockedInvoice = $this->tradeLifecycleService->markInvoicePaid($lockedInvoice, [
                    'trace_id' => $traceId,
                ]);

                $this->invoiceSettlementService->closeOtherPendingPayments($lockedInvoice, 0, 'invoice_paid_by_balance');

                return $lockedInvoice;
            });
        }, '支付请求处理中，请勿重复提交');

        $this->paidInvoiceOrchestrator->handlePaidInvoice($invoice, $traceId !== '' ? 'balance:'.$traceId : 'balance:'.$invoice->id);

        return $paidInvoice->fresh() ?? $paidInvoice;
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
