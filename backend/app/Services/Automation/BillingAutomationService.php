<?php

namespace App\Services\Automation;

use App\Constants\InvoiceStatus;
use App\Constants\ServiceStatus;
use App\Models\AutomationLog;
use App\Models\Invoice;
use App\Models\Service;
use App\Services\Provisioning\ServiceRenewService;
use App\Services\System\NotificationService;
use App\Services\System\SettingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BillingAutomationService
{
    public function __construct(
        private SettingService $settingService,
        private NotificationService $notificationService,
        private ServiceRenewService $serviceRenewService,
    ) {}

    public function handle(): array
    {
        $config = $this->settingService->getAutomationConfig();

        return [
            'renew_notice_sent' => $this->sendRenewNotices($config),
            'renew_orders_created' => $this->createRenewOrders($config),
            'invoice_pre_due_sent' => $this->sendInvoiceBeforeDueReminders($config),
            'invoice_overdue_sent' => $this->sendInvoiceOverdueReminders($config),
            'invoices_marked_overdue' => $this->markInvoicesOverdue($config),
        ];
    }

    // ─── 服务续费提醒 ──────────────────────────────────────────────────────────

    private function sendRenewNotices(array $config): int
    {
        $renewNoticeDays = collect((array) ($config['renew_notice_days_before'] ?? []))
            ->map(fn ($item) => (int) $item)
            ->filter(fn (int $item) => in_array($item, [7, 3, 1], true))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (! $config['renew_notice_enabled'] || $renewNoticeDays === []) {
            return 0;
        }

        $resolvedNow = now();
        $siteName = (string) config('idc.site_name', config('app.name', '服务商'));
        $maxRenewNoticeDays = max($renewNoticeDays);

        // 仅通知正常与已暂停的服务（已取消、已终止不再提醒）
        $services = Service::query()
            ->with('user:id,email,nickname')
            ->whereIn('status', [ServiceStatus::ACTIVE, ServiceStatus::SUSPENDED])
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [
                $resolvedNow->copy()->startOfDay(),
                $resolvedNow->copy()->addDays($maxRenewNoticeDays)->endOfDay(),
            ])
            ->get();

        $count = 0;

        foreach ($services as $service) {
            $email = trim((string) ($service->user?->email ?? ''));
            if ($email === '') {
                continue;
            }

            $expiresAt = $service->expires_at instanceof Carbon
                ? $service->expires_at
                : Carbon::parse($service->expires_at);

            $days = $this->resolveDaysLeft($expiresAt, $resolvedNow);
            $milestoneDays = $this->resolveRenewNoticeMilestone($days, $renewNoticeDays);

            if ($milestoneDays === null) {
                continue;
            }

            $ruleKey = 'expiry:'.$expiresAt->format('Y-m-d').':days:'.$milestoneDays;
            if (! AutomationLog::recordOnce('billing-maintenance', 'renew_notice', 'service', (int) $service->id, $ruleKey)) {
                continue;
            }

            $displayName = $service->user?->display_name ?? '客户';
            $expiryStr = $expiresAt->format('Y-m-d H:i');

            $urgencyLine = $days === 0
                ? "您的服务 {$service->name} 今天到期，请立即续费。"
                : ($days === 1
                    ? "您的服务 {$service->name} 明天到期，请尽快完成续费。"
                    : "您的服务 {$service->name} 将在 {$days} 天后到期。");

            try {
                $this->notificationService->sendTemplateEmail($email, NotificationService::TEMPLATE_SERVICE_RENEW_REMINDER, [
                    'site_name' => $siteName,
                    'display_name' => $displayName,
                    'service_name' => (string) $service->name,
                    'days_left' => $days,
                    'expires_at' => $expiryStr,
                    'billing_cycle_label' => $this->resolveCycleLabel((string) $service->billing_cycle),
                    'urgency_message' => $urgencyLine,
                ]);
                AutomationLog::markExecuted(
                    'billing-maintenance',
                    'renew_notice',
                    'service',
                    (int) $service->id,
                    $ruleKey,
                    [
                        'email' => $email,
                        'days_left' => $days,
                        'expires_at' => $expiryStr,
                    ]
                );
                $count++;
            } catch (\Throwable $exception) {
                AutomationLog::forgetRecord('billing-maintenance', 'renew_notice', 'service', (int) $service->id, $ruleKey);
                Log::warning('[定时任务] 服务续费提醒发送失败', [
                    'service_id' => $service->id,
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $count;
    }

    // ─── 自动生成续费账单 ──────────────────────────────────────────────────────

    private function createRenewOrders(array $config): int
    {
        $renewNoticeDays = collect((array) ($config['renew_notice_days_before'] ?? []))
            ->map(fn ($item) => (int) $item)
            ->filter(fn (int $item) => in_array($item, [7, 3, 1], true))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (! $config['renew_create_invoice_enabled'] || $renewNoticeDays === []) {
            return 0;
        }

        $resolvedNow = now();
        // 取最大提前天数作为建单窗口，窗口内若此前未建单则补建一次。
        $triggerDays = max($renewNoticeDays);

        $services = Service::query()
            ->with('user:id,email,nickname')
            ->where('status', ServiceStatus::ACTIVE)
            ->where('auto_renew', 1)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [
                $resolvedNow->copy()->startOfDay(),
                $resolvedNow->copy()->addDays($triggerDays)->endOfDay(),
            ])
            ->get();

        $count = 0;

        foreach ($services as $service) {
            if (! $service->user) {
                continue;
            }

            $expiresAt = $service->expires_at instanceof Carbon
                ? $service->expires_at
                : Carbon::parse($service->expires_at);

            $days = $this->resolveDaysLeft($expiresAt, $resolvedNow);
            if ($days < 0 || $days > $triggerDays) {
                continue;
            }

            $ruleKey = 'expiry:'.$expiresAt->format('Y-m-d').':auto_order:'.$triggerDays;
            if (! AutomationLog::recordOnce(
                'billing-maintenance', 'renew_order_create', 'service', (int) $service->id, $ruleKey
            )) {
                continue;
            }

            try {
                $this->serviceRenewService->createRenewInvoiceForUser(
                    $service->user, (int) $service->id, (string) $service->billing_cycle
                );
                Log::info('[定时任务] 自动生成续费订单', [
                    'service_id' => $service->id,
                    'days_left' => $days,
                ]);
                AutomationLog::markExecuted(
                    'billing-maintenance',
                    'renew_order_create',
                    'service',
                    (int) $service->id,
                    $ruleKey,
                    [
                        'days_left' => $days,
                        'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                    ]
                );
                $count++;
            } catch (\Throwable $exception) {
                AutomationLog::forgetRecord('billing-maintenance', 'renew_order_create', 'service', (int) $service->id, $ruleKey);
                Log::warning('[定时任务] 自动生成续费订单失败', [
                    'service_id' => $service->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $count;
    }

    // ─── 账单到期前提醒 ────────────────────────────────────────────────────────

    private function sendInvoiceBeforeDueReminders(array $config): int
    {
        if (! $config['invoice_unpaid_reminder_enabled'] || $config['invoice_unpaid_before_due_days'] < 0) {
            return 0;
        }

        $siteName = (string) config('idc.site_name', config('app.name', '服务商'));
        $targetDays = $config['invoice_unpaid_before_due_days'];

        $invoices = Invoice::query()
            ->with(['user:id,email,nickname', 'order.product:id,product_type,product_group_id,config_options,purchase_requires'])
            ->where('status', InvoiceStatus::UNPAID)
            ->whereNotNull('due_date')
            ->get();

        $count = 0;

        foreach ($invoices as $invoice) {
            $dueDate = Carbon::parse($invoice->due_date);
            $days = (int) now()->startOfDay()->diffInDays($dueDate->copy()->startOfDay(), false);

            if ($days !== $targetDays) {
                continue;
            }

            $ruleKey = 'due:'.$dueDate->format('Y-m-d').':before:'.$days;
            if (! AutomationLog::recordOnce(
                'billing-maintenance', 'invoice_before_due', 'invoice', (int) $invoice->id, $ruleKey
            )) {
                continue;
            }

            $tailText = $days === 0
                ? '账单今天到期，请立即完成付款，避免账单逾期影响您的服务使用。'
                : ($days === 1
                    ? '账单明天到期，请尽快完成付款。'
                    : "账单将在 {$days} 天后到期，请及时完成付款。");

            if ($this->sendInvoiceReminderEmail(
                $invoice,
                $siteName,
                NotificationService::TEMPLATE_INVOICE_PAYMENT_REMINDER,
                $tailText
            )) {
                AutomationLog::markExecuted(
                    'billing-maintenance',
                    'invoice_before_due',
                    'invoice',
                    (int) $invoice->id,
                    $ruleKey,
                    [
                        'days_before_due' => $days,
                        'due_date' => $dueDate->format('Y-m-d'),
                    ]
                );
                $count++;

                continue;
            }

            AutomationLog::forgetRecord('billing-maintenance', 'invoice_before_due', 'invoice', (int) $invoice->id, $ruleKey);
        }

        return $count;
    }

    // ─── 账单逾期后催付 ────────────────────────────────────────────────────────

    private function sendInvoiceOverdueReminders(array $config): int
    {
        if (! $config['invoice_unpaid_reminder_enabled'] || $config['invoice_overdue_reminder_days'] === []) {
            return 0;
        }

        $siteName = (string) config('idc.site_name', config('app.name', '服务商'));

        $invoices = Invoice::query()
            ->with(['user:id,email,nickname', 'order.product:id,product_type,product_group_id,config_options,purchase_requires'])
            ->whereIn('status', [InvoiceStatus::UNPAID, InvoiceStatus::OVERDUE])
            ->whereNotNull('due_date')
            ->get();

        $count = 0;

        foreach ($invoices as $invoice) {
            $dueDate = Carbon::parse($invoice->due_date);
            $overdue = (int) $dueDate->copy()->startOfDay()->diffInDays(now()->startOfDay(), false);

            if ($overdue <= 0 || ! in_array($overdue, $config['invoice_overdue_reminder_days'], true)) {
                continue;
            }

            $ruleKey = 'due:'.$dueDate->format('Y-m-d').':overdue:'.$overdue;
            if (! AutomationLog::recordOnce(
                'billing-maintenance', 'invoice_overdue', 'invoice', (int) $invoice->id, $ruleKey
            )) {
                continue;
            }

            $tailText = "账单已逾期 {$overdue} 天，请尽快完成付款，以避免影响您的服务使用。若继续未付，我们将按相关条款处理。";

            if ($this->sendInvoiceReminderEmail(
                $invoice,
                $siteName,
                NotificationService::TEMPLATE_INVOICE_OVERDUE_REMINDER,
                $tailText
            )) {
                AutomationLog::markExecuted(
                    'billing-maintenance',
                    'invoice_overdue',
                    'invoice',
                    (int) $invoice->id,
                    $ruleKey,
                    [
                        'overdue_days' => $overdue,
                        'due_date' => $dueDate->format('Y-m-d'),
                    ]
                );
                $count++;

                continue;
            }

            AutomationLog::forgetRecord('billing-maintenance', 'invoice_overdue', 'invoice', (int) $invoice->id, $ruleKey);
        }

        return $count;
    }

    // ─── 未付款账单标为逾期 ───────────────────────────────────────────────────

    private function markInvoicesOverdue(array $config): int
    {
        $graceDays = $config['invoice_overdue_after_days'];

        // due_date 当天不算逾期，需要严格晚于 due_date（加上宽限天数）才标记
        $threshold = now()->subDays($graceDays)->toDateString();

        return Invoice::query()
            ->where('status', InvoiceStatus::UNPAID)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $threshold)   // 严格小于，避免到期当天被误标
            ->update(['status' => InvoiceStatus::OVERDUE]);
    }

    // ─── 公共邮件发送 ──────────────────────────────────────────────────────────

    private function sendInvoiceReminderEmail(Invoice $invoice, string $siteName, string $templateCode, string $tailText): bool
    {
        $email = trim((string) ($invoice->user?->email ?? ''));
        if ($email === '') {
            return false;
        }

        $displayName = $invoice->user?->display_name ?? '客户';
        $dueDate = Carbon::parse($invoice->due_date)->format('Y-m-d');

        try {
            $this->notificationService->sendTemplateEmail($email, $templateCode, [
                'site_name' => $siteName,
                'display_name' => $displayName,
                'invoice_no' => (string) $invoice->invoice_no,
                'order_no' => (string) ($invoice->order?->order_no ?? ''),
                'product_name' => (string) ($invoice->order?->display_product_name ?? ''),
                'amount' => number_format((float) $invoice->amount, 2, '.', ''),
                'due_date' => $dueDate,
                'notice_message' => $tailText,
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('[定时任务] 账单提醒邮件发送失败', [
                'invoice_id' => $invoice->id,
                'email' => $email,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function resolveCycleLabel(string $cycle): string
    {
        return [
            'monthly' => '月付',
            'quarterly' => '季付',
            'semiannually' => '半年付',
            'annually' => '年付',
            'biennially' => '两年付',
            'triennially' => '三年付',
            'one_time' => '一次性',
            'onetime' => '一次性',
        ][$cycle] ?? $cycle;
    }

    private function resolveDaysLeft(Carbon $expiresAt, ?Carbon $resolvedNow = null): int
    {
        $now = $resolvedNow?->copy() ?? now();

        return (int) $now->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false);
    }

    private function resolveRenewNoticeMilestone(int $daysLeft, array $renewNoticeDays): ?int
    {
        if ($daysLeft < 0) {
            return null;
        }

        foreach ($renewNoticeDays as $milestoneDays) {
            if ($daysLeft <= (int) $milestoneDays) {
                return (int) $milestoneDays;
            }
        }

        return null;
    }
}
