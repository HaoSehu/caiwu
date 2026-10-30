<?php

namespace App\Services\Automation;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\CheckoutService;
use App\Services\Finance\RechargePaymentService;
use App\Services\Order\OrderService;
use App\Services\System\SettingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * 账单清理自动化服务：定时把超时未支付的账单、订单、充值单关闭。
 * 替代旧的 OrderCleanupAutomationService。
 */
class InvoiceCleanupAutomationService
{
    /**
     * 挂账僵尸订单的收敛宽限期（天）。
     *
     * 挂账订单本身被豁免支付窗口清理（见 OrderService::cancelExpiredPendingOrder），
     * 但关联的挂账单被管理员取消后，订单会永久停在 PENDING：用户点进订单只有
     * 「无可支付账单」，管理端也没有取消入口，只能靠人工改库。
     * 这里给 7 天宽限期（与账单 due_date 默认账期一致），超期后把订单收敛为 CANCELLED。
     */
    private const ABANDONED_ADMIN_MANUAL_DAYS = 7;

    public function __construct(
        private SettingService $settingService,
        private CheckoutService $checkoutService,
        private RechargePaymentService $paymentService,
        private OrderService $orderService,
        private CheckoutSecurityService $checkoutSecurityService,
    ) {}

    public function handle(): array
    {
        $config = $this->settingService->getAutomationConfig();

        // D6A-02：清理开关此前在清理侧完全未被读取，管理端"已关闭=不会自动取消未付款账单"
        // 与运行语义相反。此处按开关短路：关闭时跳过未付账单与未付订单的自动取消，
        // 充值单清理有独立开关（pending_recharge_cleanup_enabled），不受本开关控制。
        $cleanupEnabled = (bool) ($config['pending_order_cleanup_enabled'] ?? true);

        if (! $cleanupEnabled) {
            Log::info('[定时任务] 未付款订单/账单自动清理已关闭，跳过本轮清理', [
                'pending_order_cleanup_enabled' => false,
            ]);
        }

        // D-2：挂账订单终态收敛不受上面这个开关约束。收敛对象不是「未付款待收单据」，
        // 而是「关联账单已被管理员取消、订单却仍停在 PENDING」的僵尸单——它已经没有
        // 可收款路径，开关关掉只是不再动未付款单据，不该让这类订单永久滞留
        // （N3-02-B 要消灭的正是这个洞，跟着开关一起停等于关开关就复发）。
        $summary = [
            'invoices_cancelled' => $cleanupEnabled ? $this->cleanupPendingInvoices($config) : 0,
            'orders_cancelled' => $cleanupEnabled ? $this->cleanupPendingOrders($config) : 0,
            'abandoned_orders_cancelled' => $this->cancelAbandonedAdminManualOrders(),
            'recharges_expired' => $this->cleanupPendingRecharges($config),
        ];

        if (! $cleanupEnabled) {
            $summary['pending_order_cleanup_skipped'] = true;
        }

        return $summary;
    }

    private function cleanupPendingInvoices(array $config): int
    {
        $ttlSeconds = CheckoutSecurityService::paymentSessionTtlSeconds();
        $threshold = now()->subSeconds($ttlSeconds);

        $count = 0;

        // 与订单/充值单清理同口径：分批装载，避免账单积压时一次性载入全表撑爆内存、拖长事务。
        // 这里用 chunkById 而非 chunk：清理会把命中的账单状态改为 CANCELLED，使其从 UNPAID
        // 结果集中消失；若用基于 offset 的 chunk，结果集收缩会导致分页偏移漂移、漏处理后续账单。
        // chunkById 以主键 id 为游标（id > 上一批最大值）推进，可安全规避该问题。
        // 分块大小 100 与既有订单/充值单清理保持一致。
        $query = Invoice::query()
            ->with(['product'])
            ->where('status', InvoiceStatus::UNPAID)
            ->where('created_at', '<=', $threshold);

        // 候选集约束与请求路径懒清理同源：账期内按 due_date 计费的续费账单不进候选集，
        // 避免每小时把它们连 product 一起装载进内存再逐条跳过。
        $this->checkoutSecurityService->applyExpiredInvoiceSweepScope($query);

        $query->chunkById(100, function ($invoices) use (&$count, $ttlSeconds): void {
            foreach ($invoices as $invoice) {
                // 管理员挂账单（config_snapshot.admin_manual）的窗口豁免不在本层判断，
                // 统一由 cancelExpiredUnpaidInvoice 收口，避免请求路径与定时任务出现两套口径。
                // D6A-01：系统生成的续费账单（自动续费扣款账单、到期前定时建单）按 due_date 计费，
                // 到期前/逾期 1、3、5 天的提醒与催收链路都依赖这张账单存活；
                // created_at 超过 5 分钟不等于已过期——是否过期统一由 CheckoutSecurityService 的支付窗口判据决定
                // （续费账单按 due_date 计，其余按 5 分钟），下方 cancelExpiredUnpaidInvoice 会按同一判据跳过未过期账单；
                // due_date 已过的续费账单仍会在本轮正常关闭，避免无限期占用未付账单。
                try {
                    $updated = $this->checkoutService->cancelExpiredUnpaidInvoice($invoice, [
                        'actor_type' => 'system',
                        'actor_name' => 'schedule:invoice-cleanup',
                        'reason' => 'payment_window_expired',
                    ]);

                    if ((int) $updated->status !== InvoiceStatus::CANCELLED) {
                        continue;
                    }

                    Log::info('[定时任务] 超时未支付账单自动关闭', [
                        'invoice_id' => $invoice->id,
                        'invoice_no' => $invoice->invoice_no,
                        'expire_after_seconds' => $ttlSeconds,
                        'age_minutes' => $invoice->created_at ? $invoice->created_at->diffInMinutes(now()) : null,
                    ]);
                    $count++;
                } catch (\Throwable $exception) {
                    Log::warning('[定时任务] 账单自动关闭失败', [
                        'invoice_id' => $invoice->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        });

        return $count;
    }

    private function cleanupPendingOrders(array $config): int
    {
        // D6A-01：续费账单按其 due_date 计费后，影子续费订单必须与账单同口径——
        // OrderService::cancel 会级联取消关联账单，若订单仍按 5 分钟窗口清理，
        // 修好的账单会被连带关闭，修复失效；且自动续费失败重试会复用"账单未付但订单已取消"的订单，导致扣款失败循环。
        // 订单侧不再单独判类型，统一交给 cancelExpiredPendingOrder 的支付窗口守卫（跟随关联账单 due_date）。
        $ttlSeconds = CheckoutSecurityService::paymentSessionTtlSeconds();
        $threshold = now()->subSeconds($ttlSeconds);

        $count = 0;

        $query = Order::query()
            ->where('status', OrderStatus::PENDING)
            ->where('created_at', '<=', $threshold);

        // 候选集约束与请求路径懒清理同源：账期内按 due_date 计窗口的续费订单不进候选集。
        $this->checkoutSecurityService->applyExpiredOrderSweepScope($query);

        $query->chunkById(100, function ($orders) use (&$count): void {
            // 挂账订单的豁免不在本层判断，统一由 cancelExpiredPendingOrder 收口，避免多套口径。
            foreach ($orders as $order) {
                // D-1：逐条兜底，与账单侧（cleanupPendingInvoices）同口径。任一条取消失败
                // （账单状态已变化、并发取消等抛 BusinessException）若冒泡出 chunk 回调，
                // 会中断整轮 handle()，把后面的挂账订单收敛与充值单清理一起跳过。
                try {
                    $updated = $this->orderService->cancelExpiredPendingOrder($order, [
                        'actor_type' => 'system',
                        'actor_name' => 'schedule:order-cleanup',
                        'reason' => 'payment_window_expired',
                    ]);

                    if ((int) $updated->status === OrderStatus::CANCELLED) {
                        $count++;
                    }
                } catch (\Throwable $exception) {
                    Log::warning('[定时任务] 订单自动关闭失败', [
                        'order_id' => $order->id,
                        'order_no' => $order->order_no,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        });

        return $count;
    }

    /**
     * 收敛僵尸挂账订单：关联的挂账单已被取消且超过宽限期，订单仍停在待支付。
     *
     * 挂账订单被豁免支付窗口清理（OrderService::cancelExpiredPendingOrder 的
     * admin_manual 早返回）是必要的——否则会经 cancelTrade 级联取消用户仍在账期内
     * 可支付的挂账单。但豁免是单向的：挂账单被管理端取消后，订单没有任何路径离开
     * PENDING，用户端只剩「无可支付账单」，只能人工改库。这里补一条终态收敛。
     *
     * 判据：订单 PENDING + 关联账单 CANCELLED + 账单 updated_at 超 7 天。
     * 账单表无 cancelled_at，取消必然 forceFill(...)->save() 刷新 updated_at，
     * 且挂账单取消后不再有写入，用 updated_at 作为取消时刻的近似值足够。
     * admin_manual 标记仍在 JSON 内，先按关联账单条件把候选集压到极小，再在 PHP 侧判标记
     * （JSON_EXTRACT 可判定但不可索引，不放进 SQL 的过滤链）。
     */
    private function cancelAbandonedAdminManualOrders(): int
    {
        $threshold = now()->subDays(self::ABANDONED_ADMIN_MANUAL_DAYS);

        $count = 0;

        $query = Order::query()
            ->where('status', OrderStatus::PENDING)
            ->whereHas('invoice', function (Builder $invoiceQuery) use ($threshold): void {
                $invoiceQuery->where('status', InvoiceStatus::CANCELLED)
                    ->where('updated_at', '<=', $threshold);
            });

        $query->chunkById(100, function ($orders) use (&$count): void {
            foreach ($orders as $order) {
                if (! $this->orderService->isAdminManualOrder($order)) {
                    continue;
                }

                // D-4：invoices.order_id 没有唯一约束，理论上同一订单可能挂多张账单（实库当前无此数据，
                // 已用 GROUP BY order_id HAVING count(*) > 1 核查）。whereHas 只证明「存在一张已取消且超期的账单」，
                // 而 OrderService::cancel 会按 order_id 取账单并允许取消「待支付」的那张——
                // 一单多账单时可能把用户仍在账期内可支付的账单一起取消。因此这里把订单名下账单一次性取出来，
                // 只有全部为 CANCELLED 才动手，不依赖 HasOne 的取值顺序。
                $invoices = Invoice::query()
                    ->where('order_id', $order->id)
                    ->get(['id', 'status']);

                $cancelledInvoice = $invoices->first(
                    static fn (Invoice $invoice): bool => (int) $invoice->status === InvoiceStatus::CANCELLED
                );

                $hasPayableInvoice = $invoices->contains(
                    static fn (Invoice $invoice): bool => (int) $invoice->status !== InvoiceStatus::CANCELLED
                );

                if ($hasPayableInvoice || $cancelledInvoice === null) {
                    continue;
                }

                try {
                    $updated = $this->orderService->cancel($order, [
                        'actor_type' => 'system',
                        'actor_name' => 'schedule:order-cleanup',
                        'reason' => 'admin_manual_abandoned',
                    ]);

                    if ((int) $updated->status !== OrderStatus::CANCELLED) {
                        continue;
                    }

                    Log::info('[定时任务] 挂账订单关联账单已取消且超期，自动收敛', [
                        'order_id' => $order->id,
                        'order_no' => $order->order_no,
                        'invoice_id' => (int) $cancelledInvoice->id,
                        'grace_days' => self::ABANDONED_ADMIN_MANUAL_DAYS,
                    ]);
                    $count++;
                } catch (\Throwable $exception) {
                    Log::warning('[定时任务] 挂账订单自动收敛失败', [
                        'order_id' => $order->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        });

        return $count;
    }

    private function cleanupPendingRecharges(array $config): int
    {
        // 管理端「充值单自动清理」开关：关闭时不自动关闭超时充值单（与 pending_order_cleanup_enabled 同语义）。
        if (! (bool) ($config['pending_recharge_cleanup_enabled'] ?? true)) {
            return 0;
        }

        $ttlSeconds = CheckoutSecurityService::paymentSessionTtlSeconds();
        $threshold = now()->subSeconds($ttlSeconds);

        $updated = 0;

        // 与账单/订单清理同口径：分批装载，防积压场景一次性载入全表。
        Payment::query()
            ->whereNull('invoice_id')
            ->where('status', PaymentStatus::PENDING)
            ->where('created_at', '<=', $threshold)
            ->chunkById(100, function ($payments) use (&$updated): void {
                foreach ($payments as $payment) {
                    $closed = $this->paymentService->cancelExpiredPendingRecharge($payment, [
                        'actor_type' => 'system',
                        'actor_name' => 'schedule:invoice-cleanup',
                        'reason' => 'payment_window_expired',
                    ]);
                    if ((int) $closed->status === PaymentStatus::CANCELLED) {
                        $updated++;
                    }
                }
            });

        if ($updated > 0) {
            Log::info('[定时任务] 超时未支付充值单自动关闭', [
                'count' => $updated,
                'expire_after_seconds' => $ttlSeconds,
            ]);
        }

        return $updated;
    }
}
