<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\RefundStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\ReferralReward;
use App\Models\Refund;
use App\Services\System\ActivityLogService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 退款标记（纯标记通道）：登记「线下已退款 / 对账修复」的退款事实，支持部分退款。
 *
 * 与真退款（InvoiceRefundService::refundInvoiceToBalance）的本质区别：
 * 不动任何资金——不改余额、不写台账、不生成红字账单、不改 Payment、不回退推广奖励。
 * 资金红线对应三唯一写口（AccountService/FinanceLedgerWriter/FinanceDocumentService）零调用。
 *
 * 落点：refunds 行（refund_method=manual_mark，payment_id/refund_invoice_id 为 NULL）
 * + TradeLifecycleService::markInvoiceRefundMarked 累加投影；可退上限取
 * InvoiceRefundService::markRefundableAmountOf（整笔实收累计口径，支持多笔
 * 支付拆分场景逐步标记），超额拒绝、退完幂等短路。
 */
class InvoiceRefundMarkService
{
    public function __construct(
        private InvoiceRefundService $refundService,
        private TradeLifecycleService $tradeLifecycleService,
        private ActivityLogService $activityLogService,
    ) {}

    /**
     * 账单侧入口：标记账单已退款（支持部分金额）。
     *
     * @param  array{amount?: float|string, remark?: string}  $payload
     * @param  array{operator_id?: int, operator_name?: string, trace_id?: string, operator_type?: string}  $context
     * @return array<string, mixed> AdminActionResultResource 兼容信封
     */
    public function markInvoiceRefunded(Invoice $invoice, array $payload = [], array $context = []): array
    {
        $lockKey = "lock:admin:refund-mark:invoice:{$invoice->id}";

        try {
            return Cache::lock($lockKey, 15)->block(3, function () use ($invoice, $payload, $context) {
                return DB::transaction(function () use ($invoice, $payload, $context) {
                    $lockedInvoice = Invoice::query()
                        ->lockForUpdate()
                        ->with(['order'])
                        ->findOrFail((int) $invoice->id);

                    return $this->markLockedInvoice($lockedInvoice, $payload, $context);
                });
            });
        } catch (LockTimeoutException) {
            throw new BusinessException('退款标记正在处理中，请稍候重试');
        }
    }

    /**
     * 订单侧入口：解析关联账单后走同一标记内核（订单↔账单联动）。
     *
     * 注意：orders 表无 invoice_id 列，关联方向是 invoices.order_id → orders.id，
     * 必须经 $order->invoice（HasOne）解析，不能读 $order->invoice_id。
     *
     * @param  array{amount?: float|string, remark?: string}  $payload
     * @param  array{operator_id?: int, operator_name?: string, trace_id?: string, operator_type?: string}  $context
     * @return array<string, mixed>
     */
    public function markOrderRefunded(Order $order, array $payload = [], array $context = []): array
    {
        $invoice = $order->invoice;
        if (! $invoice instanceof Invoice) {
            throw new BusinessException('该订单未关联账单，无法标记退款');
        }

        return $this->markInvoiceRefunded($invoice, $payload, $context);
    }

    /**
     * 锁内标记主流程（调用方保证已持账单行锁与命名锁）。
     *
     * @param  array{amount?: float|string, remark?: string}  $payload
     * @param  array{operator_id?: int, operator_name?: string, trace_id?: string, operator_type?: string}  $context
     * @return array<string, mixed>
     */
    private function markLockedInvoice(Invoice $lockedInvoice, array $payload, array $context): array
    {
        $this->assertMarkableType($lockedInvoice);

        // M1 合并账单守卫（2026-10-11 裁决：退款按子账单为对象）：合并总账单本身
        // 不做退款入口——其金额是子账单聚合投影，直接标记会破坏子账单退款/提成冲正口径。
        if ((int) $lockedInvoice->merge_role === 1) {
            throw new BusinessException('合并总账单不支持退款标记，请按子账单操作');
        }

        // 与真退款通道互斥（2026-10-11 裁决：一单只选一种）：已有「退款到余额」
        // 记录的账单不再受理纯标记——两种资金语义叠加后对账无法解释。
        // 置于幂等短路之前：全额真退款的账单再点标记应明确报「已用余额退款」，
        // 而不是含糊的「已标记」。
        $hasBalanceRefund = Refund::query()
            ->where('invoice_id', (int) $lockedInvoice->id)
            ->where('refund_method', Refund::METHOD_BALANCE)
            ->where('status', Refund::STATUS_COMPLETED)
            ->exists();
        if ($hasBalanceRefund) {
            throw new BusinessException('该账单已有退款到余额记录，请在「退款到余额」入口处理');
        }

        $refundableAmount = $this->refundService->markRefundableAmountOf($lockedInvoice);
        $completedTotal = $this->completedRefundTotal($lockedInvoice);

        // 已退完 → 幂等短路（不产生新 refunds 行；无论账单已收口 REFUNDED
        // 还是部分退完仍 PAID，重复提交统一返回 already_marked）。
        if ($refundableAmount <= 0.00001 && $completedTotal > 0) {
            return $this->alreadyMarkedResult($lockedInvoice, $context);
        }

        $this->assertMarkableStatus($lockedInvoice);

        // 从未退过但可退为 0（如重复支付已转余额）→ 明确拒绝而非静默成功。
        if ($refundableAmount <= 0.00001) {
            throw new BusinessException('当前账单无可标记的退款金额');
        }

        $refundAmount = round((float) ($payload['amount'] ?? 0), 2);
        if ($refundAmount <= 0) {
            throw new BusinessException('退款金额必须大于 0');
        }
        if ($refundAmount - $refundableAmount > 0.00001) {
            throw new BusinessException(sprintf('退款金额超过账单剩余可退金额（剩余 %.2f 元）', $refundableAmount));
        }

        $reason = trim((string) ($payload['remark'] ?? ''));
        if ($reason === '') {
            throw new BusinessException('请填写退款标记原因');
        }

        $traceId = trim((string) ($context['trace_id'] ?? ''));
        $isFullyRefunded = abs($refundAmount - $refundableAmount) <= 0.00001;

        // ① refunds 明细行（纯标记：不动资金，payment/refund_invoice 恒空）
        $refund = Refund::query()->create([
            'refund_no' => Refund::generateRefundNo(),
            'user_id' => (int) $lockedInvoice->user_id,
            'invoice_id' => (int) $lockedInvoice->id,
            'payment_id' => null,
            'refund_invoice_id' => null,
            'amount' => number_format($refundAmount, 2, '.', ''),
            'status' => Refund::STATUS_COMPLETED,
            'refund_method' => Refund::METHOD_MANUAL_MARK,
            'currency' => 'CNY',
            'reason' => $reason,
            'gateway_refund_no' => null,
            'operator_type' => trim((string) ($context['operator_type'] ?? 'admin')),
            'operator_id' => isset($context['operator_id']) ? (int) $context['operator_id'] : null,
            'operator_name' => trim((string) ($context['operator_name'] ?? '')) ?: null,
            'trace_id' => $traceId !== '' ? $traceId : null,
            'refunded_at' => now(),
        ]);

        // ② 状态机投影：累加 refund_amount；仅全额收口 REFUNDED + 级联订单 + 券回落
        $updatedInvoice = $this->tradeLifecycleService->markInvoiceRefundMarked(
            $lockedInvoice,
            $refundAmount,
            Refund::METHOD_MANUAL_MARK,
            $traceId,
            $isFullyRefunded,
        );

        // ③ 审计（写失败不阻断投影落库）；推广奖励敞口仅留痕不回退
        $referralRewardPending = $this->hasRewardedReferral($lockedInvoice);
        $refundedTotalAfter = round($completedTotal + $refundAmount, 2);
        $markOrder = $lockedInvoice->order;
        $auditContext = array_filter([
            'invoice_no' => (string) $lockedInvoice->invoice_no,
            'order_no' => $markOrder instanceof Order ? (string) $markOrder->order_no : '',
            'refund_no' => (string) $refund->refund_no,
            'refund_amount' => number_format($refundAmount, 2, '.', ''),
            'refunded_total' => number_format($refundedTotalAfter, 2, '.', ''),
            'refundable_before' => number_format($refundableAmount, 2, '.', ''),
            'is_fully_refunded' => $isFullyRefunded ? 1 : 0,
            'refund_method' => Refund::METHOD_MANUAL_MARK,
            'reason' => $reason,
            'referral_reward_pending' => $referralRewardPending ? 1 : 0,
            'operator_name' => trim((string) ($context['operator_name'] ?? '')),
            'trace_id' => $traceId,
        ], static fn (mixed $value): bool => $value !== '');

        $this->activityLogService->logSafely(
            'finance',
            'finance.refund_mark.created',
            '退款标记登记：'.$lockedInvoice->invoice_no.' '.number_format($refundAmount, 2, '.', ''),
            'refund',
            (int) $refund->id,
            $auditContext,
        );
        $this->activityLogService->logSafely(
            'finance',
            'finance.refund_mark.executed',
            $isFullyRefunded
                ? '账单 '.$lockedInvoice->invoice_no.' 已全额标记退款'
                : '账单 '.$lockedInvoice->invoice_no.' 已部分标记退款 '.number_format($refundAmount, 2, '.', ''),
            'invoice',
            (int) $lockedInvoice->id,
            $auditContext,
        );

        Log::info('[退款标记] 纯标记登记完成', [
            'invoice_id' => (int) $lockedInvoice->id,
            'invoice_no' => (string) $lockedInvoice->invoice_no,
            'refund_id' => (int) $refund->id,
            'refund_amount' => number_format($refundAmount, 2, '.', ''),
            'refunded_total' => number_format($refundedTotalAfter, 2, '.', ''),
            'is_fully_refunded' => $isFullyRefunded,
            'operator_id' => $context['operator_id'] ?? null,
        ]);

        $order = $updatedInvoice->order;

        return [
            'id' => (int) $refund->id,
            'status' => 'completed',
            'message' => $isFullyRefunded ? '退款标记成功，账单已全额收口' : '退款标记成功',
            'detail' => [
                'type' => 'invoice_refund_mark',
                'already_marked' => false,
                'refund' => [
                    'id' => (int) $refund->id,
                    'refund_no' => (string) $refund->refund_no,
                    'amount' => number_format($refundAmount, 2, '.', ''),
                    'refund_method' => Refund::METHOD_MANUAL_MARK,
                    'reason' => $reason,
                    'refunded_at' => self::formatDateTime($refund->refunded_at),
                ],
                'invoice' => [
                    'id' => (int) $updatedInvoice->id,
                    'invoice_no' => (string) $updatedInvoice->invoice_no,
                    'status' => (int) $updatedInvoice->status,
                    'paid_amount' => (string) $updatedInvoice->paid_amount,
                    'refund_amount' => (string) ($updatedInvoice->refund_amount ?? $refundedTotalAfter),
                    'refund_status' => RefundStatus::derive($refundedTotalAfter, max((float) $updatedInvoice->paid_amount, (float) $updatedInvoice->amount)),
                    'refunded_at' => self::formatDateTime($updatedInvoice->refunded_at),
                ],
                'order' => $order instanceof Order ? [
                    'id' => (int) $order->id,
                    'order_no' => (string) $order->order_no,
                    'status' => (int) $order->status,
                    'refund_amount' => (string) ($order->refund_amount ?? '0.00'),
                ] : null,
                'referral_reward_pending' => $referralRewardPending,
            ],
        ];
    }

    /**
     * 类型白名单守卫（幂等短路之前执行：非销售类型一律拒绝，不做短路）。
     */
    private function assertMarkableType(Invoice $lockedInvoice): void
    {
        $type = InvoiceType::normalize((string) ($lockedInvoice->type ?: InvoiceType::NEW_PURCHASE));

        if ($type === InvoiceType::RECHARGE) {
            throw new BusinessException('充值账单不支持退款标记（款项已同步入余额）');
        }
        if ($type === InvoiceType::MANUAL) {
            throw new BusinessException('补录账单请使用红字冲抵通道，不支持退款标记');
        }
        if (! in_array($type, [InvoiceType::NEW_PURCHASE, 'normal', InvoiceType::RENEW, InvoiceType::UPGRADE], true)) {
            throw new BusinessException('当前账单类型不支持退款标记');
        }
    }

    /**
     * 状态守卫：账单须 PAID、关联订单须 PAID（账单无订单时跳过订单守卫）。
     * 在幂等短路之后执行——已退完的重复提交直接 already_marked，不落到这里。
     */
    private function assertMarkableStatus(Invoice $lockedInvoice): void
    {
        $status = (int) $lockedInvoice->status;
        if ($status === InvoiceStatus::REFUNDED) {
            throw new BusinessException('该账单已全额退款');
        }
        if ($status !== InvoiceStatus::PAID) {
            throw new BusinessException('仅已支付账单支持退款标记');
        }

        $order = $lockedInvoice->order;
        if ($order instanceof Order) {
            $orderStatus = (int) $order->status;
            if ($orderStatus === OrderStatus::REFUNDED) {
                throw new BusinessException('关联订单已退款');
            }
            if ($orderStatus === OrderStatus::CANCELLED) {
                throw new BusinessException('关联订单已取消，无法标记退款');
            }
            if ($orderStatus !== OrderStatus::PAID) {
                throw new BusinessException('关联订单状态与账单不一致，请先执行对账修复');
            }
        }
    }

    /**
     * 幂等短路信封：账单退款已全额标记，重放不产生新记录。
     *
     * @param  array{operator_id?: int, operator_name?: string, trace_id?: string}  $context
     * @return array<string, mixed>
     */
    private function alreadyMarkedResult(Invoice $lockedInvoice, array $context): array
    {
        $refundedTotal = $this->completedRefundTotal($lockedInvoice);
        $order = $lockedInvoice->order;

        Log::info('[退款标记] 幂等短路：账单已全额标记', [
            'invoice_id' => (int) $lockedInvoice->id,
            'invoice_no' => (string) $lockedInvoice->invoice_no,
            'operator_id' => $context['operator_id'] ?? null,
        ]);

        return [
            'id' => (int) $lockedInvoice->id,
            'status' => 'completed',
            'message' => '该账单已全额标记退款，未重复记录',
            'detail' => [
                'type' => 'invoice_refund_mark',
                'already_marked' => true,
                'refund' => null,
                'invoice' => [
                    'id' => (int) $lockedInvoice->id,
                    'invoice_no' => (string) $lockedInvoice->invoice_no,
                    'status' => (int) $lockedInvoice->status,
                    'paid_amount' => (string) $lockedInvoice->paid_amount,
                    'refund_amount' => (string) ($lockedInvoice->refund_amount ?? number_format($refundedTotal, 2, '.', '')),
                    'refund_status' => RefundStatus::FULL,
                    'refunded_at' => self::formatDateTime($lockedInvoice->refunded_at),
                ],
                'order' => $order instanceof Order ? [
                    'id' => (int) $order->id,
                    'order_no' => (string) $order->order_no,
                    'status' => (int) $order->status,
                    'refund_amount' => (string) ($order->refund_amount ?? '0.00'),
                ] : null,
                'referral_reward_pending' => $this->hasRewardedReferral($lockedInvoice),
            ],
        ];
    }

    /**
     * 日期时间统一输出格式（兼容 Carbon 与字符串，空值返回 null）。
     */
    private static function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * 账单名下累计已完成退款金额（refunds 表口径，纯标记与真退款同表累计）。
     */
    private function completedRefundTotal(Invoice $invoice): float
    {
        return round((float) Refund::query()
            ->where('invoice_id', (int) $invoice->id)
            ->where('status', Refund::STATUS_COMPLETED)
            ->sum('amount'), 2);
    }

    /**
     * 推广奖励敞口探测（只读）：存在已发放/冻结未回退的奖励时，纯标记不会自动
     * 回退（回退即扣余额，违反「不动钱」裁决），由调用方提示人工核对。
     */
    private function hasRewardedReferral(Invoice $invoice): bool
    {
        return ReferralReward::query()
            ->where(function ($query) use ($invoice): void {
                $query->where('invoice_id', (int) $invoice->id);
                if ((int) ($invoice->order_id ?? 0) > 0) {
                    $query->orWhere('order_id', (int) $invoice->order_id);
                }
            })
            ->whereIn('status', [ReferralReward::STATUS_FROZEN, ReferralReward::STATUS_REWARDED])
            ->exists();
    }
}
