<?php

namespace App\Services\Automation;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Constants\PaymentStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\CheckoutService;
use App\Services\Finance\PaymentService;
use App\Services\Order\OrderService;
use App\Services\System\SettingService;
use Illuminate\Support\Facades\Log;

/**
 * 账单清理自动化服务：定时把超时未支付的账单、订单、充值单关闭。
 * 替代旧的 OrderCleanupAutomationService。
 */
class InvoiceCleanupAutomationService
{
    public function __construct(
        private SettingService $settingService,
        private CheckoutService $checkoutService,
        private PaymentService $paymentService,
        private OrderService $orderService,
    ) {}

    public function handle(): array
    {
        $config = $this->settingService->getAutomationConfig();

        // D6A-02：清理开关此前在清理侧完全未被读取，管理端"已关闭=不会自动取消未付款账单"
        // 与运行语义相反。此处按开关短路：关闭时跳过未付账单与未付订单的自动取消，
        // 充值单清理有独立开关（pending_recharge_cleanup_enabled），不受本开关控制。
        if (! (bool) ($config['pending_order_cleanup_enabled'] ?? true)) {
            Log::info('[定时任务] 未付款订单/账单自动清理已关闭，跳过本轮清理', [
                'pending_order_cleanup_enabled' => false,
            ]);

            return [
                'invoices_cancelled' => 0,
                'orders_cancelled' => 0,
                'recharges_expired' => $this->cleanupPendingRecharges($config),
                'pending_order_cleanup_skipped' => true,
            ];
        }

        return [
            'invoices_cancelled' => $this->cleanupPendingInvoices($config),
            'orders_cancelled' => $this->cleanupPendingOrders($config),
            'recharges_expired' => $this->cleanupPendingRecharges($config),
        ];
    }

    /**
     * 管理端手动开通的账单：创建时 config_snapshot 打了 admin_manual 标记
     * （createDirect 直接写入，createFromOrder 从订单快照继承）。
     */
    private function isAdminManualInvoice(Invoice $invoice): bool
    {
        $snapshot = $invoice->config_snapshot;
        if (! is_array($snapshot)) {
            return false;
        }

        return filter_var($snapshot['admin_manual'] ?? false, FILTER_VALIDATE_BOOL);
    }

    private function cleanupPendingInvoices(array $config): int
    {
        $ttlSeconds = CheckoutSecurityService::paymentSessionTtlSeconds();
        $threshold = now()->subSeconds($ttlSeconds);

        $invoices = Invoice::query()
            ->with(['product'])
            ->where('status', InvoiceStatus::UNPAID)
            ->where('created_at', '<=', $threshold)
            ->get();

        $count = 0;

        foreach ($invoices as $invoice) {
            // 管理员手动开通产生的挂账账单按 due_date 计费，不受 5 分钟支付会话窗口约束
            if ($this->isAdminManualInvoice($invoice)) {
                continue;
            }

            // D6A-01：系统生成的续费账单（自动续费扣款账单、到期前定时建单）按 due_date 计费，
            // 到期前/逾期 1、3、5 天的提醒与催收链路都依赖这张账单存活；
            // 创建 5 分钟即被取消会导致催收提醒永不可达、用户账单"闪现即消失"。
            // 续费账单不走 5 分钟支付会话，豁免本轮清理。
            if ((string) $invoice->type === InvoiceType::RENEW) {
                continue;
            }

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

        return $count;
    }

    private function cleanupPendingOrders(array $config): int
    {
        // D6A-01：续费账单已在 cleanupPendingInvoices 豁免 5 分钟支付会话取消，
        // 其影子续费订单必须同步豁免——OrderService::cancel 会级联取消关联账单，
        // 若只豁免账单，影子订单仍会在 5 分钟后被取消并把账单连带关闭，修复失效；
        // 且自动续费失败重试会复用"账单未付但订单已取消"的订单，导致扣款失败循环。
        // OrderService 在本任务不可修改，豁免逻辑收敛在清理服务内逐单处理。
        $ttlSeconds = CheckoutSecurityService::paymentSessionTtlSeconds();
        $threshold = now()->subSeconds($ttlSeconds);

        $count = 0;

        Order::query()
            ->where('status', OrderStatus::PENDING)
            ->where('created_at', '<=', $threshold)
            ->chunkById(100, function ($orders) use (&$count): void {
                foreach ($orders as $order) {
                    // 系统生成的续费影子订单按 due_date 计费，与续费账单同口径豁免
                    if ((string) $order->type === OrderType::RENEW) {
                        continue;
                    }

                    // 管理员手动开通的挂账订单按 due_date 计费（与 OrderService 全量清理口径一致）
                    if ($this->isAdminManualOrder($order)) {
                        continue;
                    }

                    $updated = $this->orderService->cancelExpiredPendingOrder($order, [
                        'actor_type' => 'system',
                        'actor_name' => 'schedule:order-cleanup',
                        'reason' => 'payment_window_expired',
                    ]);

                    if ((int) $updated->status === OrderStatus::CANCELLED) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function isAdminManualOrder(Order $order): bool
    {
        // 与 OrderService::isAdminManualOrder 同款实现：config_snapshot 为 array cast
        $snapshot = (array) $order->config_snapshot;

        return filter_var($snapshot['admin_manual'] ?? false, FILTER_VALIDATE_BOOL);
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
