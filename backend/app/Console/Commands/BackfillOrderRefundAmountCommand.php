<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 存量订单退款投影回填命令（退款标记批 2026-10-11）。
 *
 * 背景：orders.refund_amount/refunded_at 为新增投影列，迁移前的历史退款
 * （真退款全额收口经 markInvoiceRefunded 只写 invoices 侧退款列；订单侧仅
 * 级联状态）在新列上为 0.00/NULL。本命令按关联账单的 refunds 已完成累计
 * （纯标记与真退款同表口径）回填订单投影，并同步最近退款时间。
 *
 * 默认 dry-run 只输出差异预览；确认后追加 --apply 实际写入。
 * 单订单独立事务，逐批 chunkById，可重复执行（以账单累计为真源，写同值幂等）。
 *
 * 用法：
 *   php artisan finance:backfill-order-refund-amount
 *   php artisan finance:backfill-order-refund-amount --apply
 */
class BackfillOrderRefundAmountCommand extends Command
{
    protected $signature = 'finance:backfill-order-refund-amount
        {--apply : 真正写入；不指定时仅 dry-run 预览}
        {--limit=500 : 单次最多处理条数}';

    protected $description = '按关联账单的已完成退款累计回填存量订单的 refund_amount/refunded_at 投影';

    public function handle(): int
    {
        if (! Schema::hasColumn('orders', 'refund_amount')) {
            $this->error('orders.refund_amount 列不存在，请先执行迁移 2026_10_11_000001。');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $limit = max(1, (int) $this->option('limit'));

        // 候选：账单名下有已完成退款记录的订单（含已收口 REFUNDED 与部分退款仍 PAID）。
        $orderIds = Invoice::query()
            ->whereHas('refunds', fn ($query) => $query->where('status', Refund::STATUS_COMPLETED))
            ->whereNotNull('order_id')
            ->distinct()
            ->limit($limit)
            ->pluck('order_id');

        if ($orderIds->isEmpty()) {
            $this->info('没有需要回填的订单（无关联账单退款记录）。');

            return self::SUCCESS;
        }

        $changed = 0;
        $skipped = 0;

        foreach ($orderIds as $orderId) {
            $order = Order::query()->find((int) $orderId);
            if (! $order instanceof Order) {
                $skipped++;

                continue;
            }

            $projection = $this->resolveProjection((int) $order->id);
            $currentAmount = round((float) ($order->refund_amount ?? 0), 2);
            $targetAmount = $projection['refund_amount'];

            if (abs($currentAmount - $targetAmount) <= 0.00001
                && (string) ($order->refunded_at ?? '') === (string) $projection['refunded_at']) {
                $skipped++;

                continue;
            }

            $this->line(sprintf(
                '[%s] order#%d %s：refund_amount %s → %s，refunded_at %s → %s',
                $apply ? '回填' : '预览',
                (int) $order->id,
                (string) $order->order_no,
                $currentAmount > 0 ? number_format($currentAmount, 2, '.', '') : '0.00',
                number_format($targetAmount, 2, '.', ''),
                (string) ($order->refunded_at ?? 'NULL'),
                $projection['refunded_at'] ?? 'NULL',
            ));

            if (! $apply) {
                $changed++;

                continue;
            }

            DB::transaction(function () use ($order, $projection, &$changed): void {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail((int) $order->id);
                $lockedOrder->forceFill([
                    'refund_amount' => $projection['refund_amount'],
                    'refunded_at' => $projection['refunded_at'],
                ])->save();
                $changed++;
            });
        }

        $this->info(sprintf(
            '处理完成：%d 条待回填 / %d 条已一致跳过（候选上限 %d）。%s',
            $changed,
            $skipped,
            $limit,
            $apply ? '' : '以上为 dry-run 预览。确认无误后，追加 --apply 实际执行。',
        ));

        return self::SUCCESS;
    }

    /**
     * 以账单侧 refunds 累计为真源计算订单投影目标值。
     *
     * @return array{refund_amount: float, refunded_at: ?string}
     */
    private function resolveProjection(int $orderId): array
    {
        // selectRaw 别名走 DB::table 形态避免 Larastan 对模型动态属性的 property.notFound。
        $aggregate = DB::table('refunds')
            ->join('invoices', 'invoices.id', '=', 'refunds.invoice_id')
            ->where('invoices.order_id', $orderId)
            ->where('refunds.status', Refund::STATUS_COMPLETED)
            ->selectRaw('COALESCE(SUM(refunds.amount), 0) as refunded_total')
            ->selectRaw('MAX(refunds.refunded_at) as last_refunded_at')
            ->first();

        $refundedTotal = $aggregate === null
            ? 0.0
            : round((float) $aggregate->refunded_total, 2);

        // 最近退款时间优先取账单投影列（历史真退款只有账单侧有值），退化取 refunds 最值。
        $lastRefundedAt = Invoice::query()
            ->where('order_id', $orderId)
            ->whereNotNull('refunded_at')
            ->orderByDesc('refunded_at')
            ->value('refunded_at');

        $refundedAt = $lastRefundedAt ?? $aggregate?->last_refunded_at;

        // 已收口 REFUNDED 的订单退款额对齐账单累计；仍 PAID 的部分退款同理。
        $orderStatus = Order::query()->whereKey($orderId)->value('status');
        if ((int) $orderStatus === OrderStatus::REFUNDED
            && $refundedTotal <= 0.00001
            && Invoice::query()->where('order_id', $orderId)->where('status', InvoiceStatus::REFUNDED)->exists()) {
            // refunds 无记录但订单已收口（历史对账修复路径）：以账单 refund_amount 投影为准。
            $invoiceRefundAmount = (float) Invoice::query()
                ->where('order_id', $orderId)
                ->orderByDesc('id')
                ->value('refund_amount');
            $refundedTotal = max($refundedTotal, $invoiceRefundAmount);
        }

        return [
            'refund_amount' => $refundedTotal,
            'refunded_at' => $refundedAt !== null
                ? (is_string($refundedAt) ? $refundedAt : $refundedAt->format('Y-m-d H:i:s'))
                : null,
        ];
    }
}
