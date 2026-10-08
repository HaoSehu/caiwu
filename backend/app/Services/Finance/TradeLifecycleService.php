<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Order;
use App\Support\SchemaMetadataCache;
use Illuminate\Support\Facades\DB;

/**
 * 交易生命周期状态机（方案 2 职责重划的核心：单一写者）。
 *
 * 账单是资金域唯一真源，订单是履约工单；订单上的金额/状态列降级为
 * 「创建时资金快照 + 生命周期投影」，本服务是这些投影的唯一写者。
 * 所有账单↔订单状态联动（入账、取消、退款、部分支付递减）必须经本服务，
 * 任何路径不得绕开直接 forceFill 订单状态。
 *
 * 锁顺序约定：先 Invoice 后 Order（与 CheckoutService/OrderService 取消入口
 * 一致）；调用方持有的行锁在同一事务内重复加锁不阻塞，状态机统一按此顺序
 * 再锁一次，保证独立调用时也有确定顺序。
 */
class TradeLifecycleService
{
    public function __construct(
        private CouponService $couponService,
    ) {}

    /**
     * 账单入账：账单置 PAID + 实收额，订单同步投影 PAID + 实收额。
     *
     * 状态守卫（双保险）：UNPAID 正常入账；已 PAID 幂等——不重写账单资金列
     * （防止部分支付累计被全额覆盖），仅补齐订单投影（对账修复依赖此路径）；
     * 其余状态直接拒绝，兜底未来调用方漏加入口守卫。
     * 事务外的履约/推荐/优惠券核销编排仍由 InvoicePaidOrchestrator 承担，
     * 本方法不负责（保持回调事务与履约分派的边界）。
     *
     * @param  array{paid_amount?: float|string|null, paid_at?: \DateTimeInterface|string|null, trace_id?: string|null}  $options
     * @param  array<string, mixed>  $context  预留：后续接 trace 传播；当前各入口 trace 经 options 显式传入
     */
    public function markInvoicePaid(Invoice $invoice, array $options = [], array $context = []): Invoice
    {
        $paidAt = $options['paid_at'] ?? now();
        $traceId = trim((string) ($options['trace_id'] ?? ''));

        return DB::transaction(function () use ($invoice, $options, $paidAt, $traceId) {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail((int) $invoice->id);

            // 默认实收额在锁内取（金额真源以锁定行为准，防过期实例）。
            $explicitPaidAmount = array_key_exists('paid_amount', $options) && $options['paid_amount'] !== null;
            $paidAmount = $explicitPaidAmount
                ? number_format((float) $options['paid_amount'], 2, '.', '')
                : (string) $lockedInvoice->amount;

            if ((int) $lockedInvoice->status !== InvoiceStatus::PAID) {
                throw_if(
                    ! in_array((int) $lockedInvoice->status, [InvoiceStatus::UNPAID], true),
                    new BusinessException('账单状态异常，无法入账')
                );

                $lockedInvoice->forceFill([
                    'status' => InvoiceStatus::PAID,
                    'paid_amount' => $paidAmount,
                    'paid_at' => $paidAt,
                    'trace_id' => $traceId !== '' ? $traceId : $lockedInvoice->trace_id,
                ])->save();
            } elseif (! $explicitPaidAmount) {
                // 幂等重入（对账修复等二次调用）：账单已 PAID 时不重写资金列，
                // 订单投影金额对齐账单累计实收而非应付全额。
                $paidAmount = (string) $lockedInvoice->paid_amount;
            }

            $lockedInvoice->order?->forceFill([
                'status' => OrderStatus::PAID,
                'paid_amount' => $paidAmount,
                'paid_at' => $paidAt,
            ])->save();

            return $lockedInvoice;
        });
    }

    /**
     * 取消交易：账单置 CANCELLED + 级联取消 PENDING 订单 + 券释放。
     *
     * 状态守卫（双保险）：已 CANCELLED 幂等跳过写入继续级联；PAID/REFUNDED
     * 拒绝取消（已入账资金须走退款，不允许直接抹平），兜底未来调用方漏加守卫。
     * 调用方负责关闭名下 PENDING 支付单（组合支付预扣回补属支付域，
     * 见 MixPaymentService::restoreReservedMixBalance）。
     * 券释放经 syncInvoiceCouponUsage 幂等重算：账单取消后券回落可复用。
     *
     * @param  array<string, mixed>  $context  预留：后续接 trace 传播；当前审计键由调用方写入支付单
     */
    public function cancelTrade(Invoice $invoice, array $context = []): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail((int) $invoice->id);

            if ((int) $lockedInvoice->status !== InvoiceStatus::CANCELLED) {
                throw_if(
                    ! in_array((int) $lockedInvoice->status, [InvoiceStatus::UNPAID], true),
                    new BusinessException('当前账单状态不支持取消')
                );

                $lockedInvoice->forceFill(['status' => InvoiceStatus::CANCELLED])->save();
            }

            $linkedOrder = $lockedInvoice->order_id
                ? Order::query()->lockForUpdate()->find((int) $lockedInvoice->order_id)
                : null;
            if ($linkedOrder instanceof Order && (int) $linkedOrder->status === OrderStatus::PENDING) {
                $linkedOrder->forceFill(['status' => OrderStatus::CANCELLED])->save();
            }

            $this->couponService->syncInvoiceCouponUsage($lockedInvoice);

            return $lockedInvoice;
        });
    }

    /**
     * 账单退款入账：置 REFUNDED + 退款列（按实库 schema 条件写）+ 订单投影 + 券重算。
     *
     * 调用方：余额退款（InvoiceRefundService）、对账修复的订单侧退款回填。
     * 订单级联：账单退款全额落地后订单同步投影 REFUNDED（调用方守卫保证订单
     * 为 PAID 或不存在；对账修复场景订单已是 REFUNDED，重复写同值幂等）。
     * 与取消路径同一券口径：无有效已付账单时用户券回落可复用，方法幂等。
     *
     * @param  array<string, mixed>  $refundRecord
     * @param  array<string, mixed>  $context
     */
    public function markInvoiceRefunded(Invoice $invoice, array $refundRecord, string $refundMethod, float $refundAmount, array $context = []): Invoice
    {
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

        $refundedOrder = $invoice->order_id
            ? Order::query()->lockForUpdate()->find((int) $invoice->order_id)
            : null;
        if ($refundedOrder instanceof Order) {
            $refundedOrder->forceFill(['status' => OrderStatus::REFUNDED])->save();
        }

        $this->couponService->syncInvoiceCouponUsage($invoice);

        return $invoice;
    }

    /**
     * 部分支付投影递减：组合支付预扣余额回补时，同步递减账单与订单的
     * paid_amount（不为负）。不改变状态——递减到 0 也不回退 PAID，
     * 状态流转一律经 markInvoicePaid / cancelTrade。
     */
    public function decrementPaidProjection(Invoice $invoice, float $balanceAmount): void
    {
        $decrement = round(max($balanceAmount, 0), 2);
        if ($decrement <= 0) {
            return;
        }

        $nextInvoicePaidAmount = number_format(
            max(round((float) ($invoice->paid_amount ?? 0) - $decrement, 2), 0),
            2,
            '.',
            ''
        );
        $invoice->forceFill(['paid_amount' => $nextInvoicePaidAmount])->save();

        if ($invoice->order instanceof Order) {
            $nextOrderPaidAmount = number_format(
                max(round((float) ($invoice->order->paid_amount ?? 0) - $decrement, 2), 0),
                2,
                '.',
                ''
            );
            $invoice->order->forceFill(['paid_amount' => $nextOrderPaidAmount])->save();
        }
    }

    /**
     * 部分支付投影递增：组合支付预扣余额时，同步递增账单与订单的
     * paid_amount（与 decrementPaidProjection 对称，保持两侧投影一致）。
     * 不改变状态——最终入账仍由 markInvoicePaid 按锁内实收额收口。
     */
    public function reservePaidProjection(Invoice $invoice, float $balanceAmount): void
    {
        $increment = round(max($balanceAmount, 0), 2);
        if ($increment <= 0) {
            return;
        }

        $invoice->forceFill([
            'paid_amount' => number_format(round((float) ($invoice->paid_amount ?? 0) + $increment, 2), 2, '.', ''),
        ])->save();

        if ($invoice->order instanceof Order) {
            $invoice->order->forceFill([
                'paid_amount' => number_format(round((float) ($invoice->order->paid_amount ?? 0) + $increment, 2), 2, '.', ''),
            ])->save();
        }
    }
}
