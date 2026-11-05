<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\Core\FinanceAccountService;
use App\Services\Finance\Core\FinanceLedgerService;
use App\Support\CacheKey;
use App\Support\Money;
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
        private FinanceAccountService $accounts,
        private FinanceLedgerService $ledger,
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
        $lockKey = CacheKey::lock('lock:pay:invoice', (string) $invoice->id);

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

                $amount = $lockedInvoice->payableAmount();
                throw_if($amount < 0, new BusinessException('账单应付金额异常，无法支付'));

                // 0 元账单（全额优惠/免费商品）同样走这条余额收口：不扣款、不写扣款台账，
                // 只推进状态机。它没有第三方支付入口，余额支付是它唯一的收口方式。
                $amountCents = Money::toCents($amount);

                if ($amountCents > 0) {
                    $currentBalance = $this->accounts->balanceCents((int) $lockedUser->id, true);
                    throw_if($currentBalance < $amountCents, new BusinessException('余额不足'));

                    $this->accounts->debit(
                        (int) $lockedUser->id,
                        $amountCents,
                        FinanceLedgerEventType::INVOICE_PAYMENT,
                        [
                            'source_type' => 'invoice',
                            'source_id' => (int) $lockedInvoice->id,
                            'remark' => '支付账单 '.(string) $lockedInvoice->invoice_no,
                            // 控制器上下文统一 actor_name，兼容服务层 operator_name/operator 键
                            'operator_type' => 'member',
                            'operator_name' => trim((string) ($context['actor_name'] ?? $context['operator_name'] ?? $context['operator'] ?? '')),
                            'trace_id' => $traceId,
                        ],
                    );
                }

                $lockedInvoice = $this->tradeLifecycleService->markInvoicePaid($lockedInvoice, [
                    'trace_id' => $traceId,
                ]);

                if ($amountCents === 0) {
                    // 0 元账单没有扣款流水可作渠道证据，补一条 0 元台账说明「这笔账单由余额收口」，
                    // 否则账单/订单的「支付方式」会显示为空。金额为 0 不改变余额与批次，
                    // 只留下溯源事实（与收款 0 元的语义一致）。
                    $this->recordZeroAmountBalanceSettlement($lockedInvoice, $traceId);
                }

                $this->invoiceSettlementService->closeOtherPendingPayments($lockedInvoice, 0, 'invoice_paid_by_balance');

                return $lockedInvoice;
            });
        }, '支付请求处理中，请勿重复提交');

        $this->paidInvoiceOrchestrator->handlePaidInvoice($invoice, $traceId !== '' ? 'balance:'.$traceId : 'balance:'.$invoice->id);

        return $paidInvoice->fresh() ?? $paidInvoice;
    }

    /**
     * 0 元余额收口的溯源台账：金额 0、余额不变，只记录「哪张账单由余额方式结清」。
     *
     * 必须是 0 元行而不是省略：账单/订单的「支付方式」与账单摘要都从这条证据派生
     * （见 PaymentChannelLabel::channelNameFromLedger），缺了它 0 元账单会显示为空。
     * 不建批次也不动余额——资金侧本来就没有发生变动。
     *
     * 金额 0 不改变余额与批次剩余，加锁只为保证台账 balance_after 快照与并发扣款一致：
     * 先锁 users 行（与 payByBalance 中非 0 金额路径的加锁顺序保持一致），
     * 再在锁内取资金账户（accountFor 带锁），避免读到并发资金操作中途的余额。
     * 调用点已处于 DB 事务内，锁在事务提交时释放。
     */
    private function recordZeroAmountBalanceSettlement(Invoice $invoice, string $traceId): void
    {
        // 锁用户行，统一本用户资金操作的行锁顺序：先 users，后 finance_accounts
        User::query()->lockForUpdate()->findOrFail((int) $invoice->user_id);

        $account = $this->accounts->accountFor((int) $invoice->user_id, true);

        $this->ledger->append(
            $account,
            FinanceLedgerEventType::INVOICE_PAYMENT,
            0,
            (int) Money::toCents($account->available_amount),
            [
                'source_type' => 'invoice',
                'source_id' => (int) $invoice->id,
                'remark' => '0 元账单余额结清 '.(string) $invoice->invoice_no,
                'operator_type' => 'member',
                'trace_id' => $traceId,
            ],
        );
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
