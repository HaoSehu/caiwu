<?php

namespace App\Services\Automation;

use App\Constants\InvoiceStatus;
use App\Constants\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\CheckoutService;
use App\Services\System\SettingService;
use Illuminate\Support\Facades\Log;

/**
 * 账单清理自动化服务：定时把超时未支付的账单、充值单置为失败。
 * 替代旧的 OrderCleanupAutomationService。
 */
class InvoiceCleanupAutomationService
{
    public function __construct(
        private SettingService $settingService,
        private CheckoutService $checkoutService,
    ) {}

    public function handle(): array
    {
        $config = $this->settingService->getAutomationConfig();

        return [
            'invoices_cancelled' => $this->cleanupPendingInvoices($config),
            'recharges_expired' => $this->cleanupPendingRecharges($config),
        ];
    }

    private function cleanupPendingInvoices(array $config): int
    {
        if (! $config['pending_order_cleanup_enabled']) {
            return 0;
        }

        $hours = max(1, (int) ($config['pending_order_cleanup_after_hours'] ?? 1));
        $threshold = now()->subHours($hours);

        $invoices = Invoice::query()
            ->with(['product'])
            ->where('status', InvoiceStatus::UNPAID)
            ->where('created_at', '<=', $threshold)
            ->get();

        $count = 0;

        foreach ($invoices as $invoice) {
            try {
                $this->checkoutService->cancel($invoice, [
                    'actor_type' => 'system',
                    'actor_name' => 'schedule:invoice-cleanup',
                    'reason' => 'auto_close_unpaid_after_timeout',
                ]);

                Log::info('[定时任务] 超时未支付账单自动关闭', [
                    'invoice_id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'expire_after_hours' => $hours,
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

    private function cleanupPendingRecharges(array $config): int
    {
        if (! $config['pending_recharge_cleanup_enabled']) {
            return 0;
        }

        $days = max(0, $config['pending_recharge_cleanup_after_days']);
        $threshold = now()->subDays($days);

        $updated = Payment::query()
            ->whereNull('invoice_id')
            ->where('status', PaymentStatus::PENDING)
            ->where('created_at', '<=', $threshold)
            ->update([
                'status' => PaymentStatus::FAILED,
                'callback_raw' => [
                    'source' => 'automation',
                    'reason' => 'pending_recharge_expired',
                    'days' => $days,
                ],
            ]);

        if ($updated > 0) {
            Log::info('[定时任务] 超时未支付充值单失效', [
                'count' => $updated,
                'days_old' => $days,
            ]);
        }

        return $updated;
    }
}
