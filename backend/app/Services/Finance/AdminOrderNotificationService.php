<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Jobs\SendPaidInvoiceAdminNotificationJob;
use App\Models\AdminUser;
use App\Models\AutomationLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\ProductCatalog\ProductFullPathResolver;
use App\Services\System\NotificationService;
use App\Support\AdminPermissions;
use App\Support\BillingCycleLabels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AdminOrderNotificationService
{
    private const TASK_KEY = 'admin-order-notification';

    public function __construct(
        private NotificationService $notificationService,
        private ?ProductFullPathResolver $productFullPathResolver = null,
    ) {}

    /**
     * 入口：把管理员付款通知派发到队列。此前走 terminating 回调在同一 FPM worker 内
     * 串行向全部管理员 SMTP 发信，邮件多或 SMTP 慢时 worker 被占满；失败由 Job 重试兜底。
     * 实际发送逻辑见 notifyInvoicePaidNow（由 SendPaidInvoiceAdminNotificationJob 调用）。
     */
    public function notifyInvoicePaidAfterResponse(Invoice $invoice): void
    {
        $invoiceId = (int) $invoice->id;
        if ($invoiceId <= 0) {
            return;
        }

        SendPaidInvoiceAdminNotificationJob::dispatch($invoiceId);
    }

    /**
     * 队列任务的实际执行体：加载账单并逐个管理员发送付款通知。
     */
    public function notifyInvoicePaidNow(int $invoiceId): void
    {
        $this->dispatchInvoiceNotificationNow($invoiceId);
    }

    /**
     * 入口：把管理员新订单通知派发到队列（模板 100021，账单创建后）。
     */
    public function notifyInvoiceCreatedAfterResponse(Invoice $invoice): void
    {
        $invoiceId = (int) $invoice->id;
        if ($invoiceId <= 0) {
            return;
        }

        SendPaidInvoiceAdminNotificationJob::dispatch($invoiceId, 'invoice_created');
    }

    /**
     * 队列任务的实际执行体：加载账单并逐个管理员发送新订单通知。
     */
    public function notifyInvoiceCreatedNow(int $invoiceId): void
    {
        $this->dispatchInvoiceCreatedNotificationNow($invoiceId);
    }

    private function dispatchInvoiceNotificationNow(int $invoiceId): void
    {
        $invoice = Invoice::query()->with([
            'user:id,email,nickname',
            'order:id,order_no,status,type,service_id,paid_at,product_id,billing_cycle,product_spec_snapshot,product_type_snapshot,config_snapshot',
            'order.product:id,product_type,service_type_code,product_group_id,remark,config_options,purchase_requires',
            'order.product.productGroup:id,second_product_group_id,name',
            'order.product.productGroup.secondProductGroup:id,first_product_group_id,name',
            'order.product.productGroup.secondProductGroup.firstProductGroup:id,code,name',
            'product:id,product_type,service_type_code,product_group_id,config_options,purchase_requires',
            'product.productGroup:id,second_product_group_id,name',
            'product.productGroup.secondProductGroup:id,first_product_group_id,name',
            'product.productGroup.secondProductGroup.firstProductGroup:id,code,name',
        ])->find($invoiceId);

        if (! $invoice instanceof Invoice || (int) $invoice->status !== InvoiceStatus::PAID) {
            return;
        }

        $payment = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->whereGatewayKeyIn(PaymentGatewayCode::thirdPartyGateways())
            ->where('status', 1)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first(Payment::gatewayProjectionColumns([
                'id',
                'invoice_id',
                'plugin_id',
                'trade_no',
                'paid_at',
            ]));

        $recipients = $this->resolveRecipients();
        if ($recipients->isEmpty()) {
            return;
        }

        $failures = [];

        foreach ($recipients as $admin) {
            $ruleKey = 'admin:'.(int) $admin->id;

            if (AutomationLog::hasRecord(self::TASK_KEY, 'invoice_paid', 'invoice', (int) $invoice->id, $ruleKey)) {
                continue;
            }

            try {
                $this->notificationService->sendTemplateEmail(
                    (string) $admin->email,
                    NotificationService::TEMPLATE_ADMIN_ORDER_PAID,
                    [
                        'recipient_name' => $admin->display_name,
                        'user_name' => $invoice->user?->display_name ?: '客户',
                        'user_email' => (string) ($invoice->user?->email ?? '未绑定'),
                        'order_no' => (string) ($invoice->order?->order_no ?? ''),
                        'invoice_no' => (string) ($invoice->invoice_no ?? ''),
                        'product_name' => $this->resolveInvoiceProductDisplayText($invoice, $invoice->order),
                        'billing_cycle_label' => BillingCycleLabels::label((string) ($invoice->billing_cycle ?? '')),
                        'paid_amount' => number_format((float) ($invoice->paid_amount ?? $invoice->amount ?? 0), 2, '.', ''),
                        'payment_method' => PaymentGatewayCode::displayLabel($payment?->gatewayKey() ?? ''),
                        'trade_no' => (string) ($payment?->trade_no ?? ''),
                        'paid_at' => $invoice->paid_at?->format('Y-m-d H:i:s')
                            ?? $payment?->paid_at?->format('Y-m-d H:i:s')
                            ?? now()->format('Y-m-d H:i:s'),
                    ]
                );

                AutomationLog::markExecuted(
                    self::TASK_KEY,
                    'invoice_paid',
                    'invoice',
                    (int) $invoice->id,
                    $ruleKey,
                    [
                        'admin_id' => (int) $admin->id,
                        'email' => (string) $admin->email,
                        'template_code' => NotificationService::TEMPLATE_ADMIN_ORDER_PAID,
                    ]
                );
            } catch (\Throwable $exception) {
                $failures[] = [
                    'admin_id' => (int) $admin->id,
                    'email' => (string) $admin->email,
                    'message' => $exception->getMessage(),
                ];

                Log::warning('[管理员账单通知] 邮件发送失败', [
                    'action' => 'invoice_paid',
                    'invoice_id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'admin_id' => $admin->id,
                    'email' => (string) $admin->email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($failures !== []) {
            throw new \RuntimeException($this->buildNotificationFailureMessage('付款通知', $invoice, $failures));
        }
    }

    /**
     * 新订单通知（模板 100021）的实际执行体：加载账单并逐个管理员发送。
     * 与支付通知（100022）的差异面仅模板码、AutomationLog action 与 payload 组装；
     * 收件人解析、per-admin 幂等与失败日志结构完全一致。
     */
    private function dispatchInvoiceCreatedNotificationNow(int $invoiceId): void
    {
        $invoice = Invoice::query()->with([
            'user:id,email,nickname',
            'order:id,order_no,status,type,service_id,paid_at,product_id,billing_cycle,product_spec_snapshot,product_type_snapshot,config_snapshot',
        ])->find($invoiceId);

        if (! $invoice instanceof Invoice) {
            return;
        }

        $recipients = $this->resolveRecipients();
        if ($recipients->isEmpty()) {
            return;
        }

        $failures = [];

        foreach ($recipients as $admin) {
            $ruleKey = 'admin:'.(int) $admin->id;

            if (AutomationLog::hasRecord(self::TASK_KEY, 'invoice_created', 'invoice', (int) $invoice->id, $ruleKey)) {
                continue;
            }

            try {
                $this->notificationService->sendTemplateEmail(
                    (string) $admin->email,
                    NotificationService::TEMPLATE_ADMIN_ORDER_CREATED,
                    [
                        'recipient_name' => $admin->display_name,
                        'user_name' => $invoice->user?->display_name ?: '客户',
                        'user_email' => (string) ($invoice->user?->email ?? '未绑定'),
                        'order_no' => (string) ($invoice->order?->order_no ?? ''),
                        'invoice_no' => (string) ($invoice->invoice_no ?? ''),
                        'order_type_label' => InvoiceType::label((string) ($invoice->order?->type ?: $invoice->type ?: '')),
                        'product_name' => $this->resolveInvoiceProductDisplayText($invoice, $invoice->order),
                        'billing_cycle_label' => BillingCycleLabels::label((string) ($invoice->billing_cycle ?? '')),
                        'order_amount' => number_format((float) ($invoice->amount ?? 0), 2, '.', ''),
                        'order_status_label' => (string) (OrderStatus::$labels[(int) ($invoice->order?->status ?? 0)] ?? '-'),
                        'created_at' => $invoice->created_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
                    ]
                );

                AutomationLog::markExecuted(
                    self::TASK_KEY,
                    'invoice_created',
                    'invoice',
                    (int) $invoice->id,
                    $ruleKey,
                    [
                        'admin_id' => (int) $admin->id,
                        'email' => (string) $admin->email,
                        'template_code' => NotificationService::TEMPLATE_ADMIN_ORDER_CREATED,
                    ]
                );
            } catch (\Throwable $exception) {
                $failures[] = [
                    'admin_id' => (int) $admin->id,
                    'email' => (string) $admin->email,
                    'message' => $exception->getMessage(),
                ];

                Log::warning('[管理员账单通知] 新订单邮件发送失败', [
                    'action' => 'invoice_created',
                    'invoice_id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'admin_id' => $admin->id,
                    'email' => (string) $admin->email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($failures !== []) {
            throw new \RuntimeException($this->buildNotificationFailureMessage('新订单通知', $invoice, $failures));
        }
    }

    /**
     * 把逐条失败聚合成一条可读的中文消息。
     * 聚合而不是首个异常即中断：一次重试要覆盖全部收件人，提前退出会让后面的管理员永远收不到信；
     * 但失败又必须让队列看到异常才能重试，所以收集完再抛。
     *
     * @param  array<int, array{admin_id: int, email: string, message: string}>  $failures
     */
    private function buildNotificationFailureMessage(string $label, Invoice $invoice, array $failures): string
    {
        $details = array_map(
            static fn (array $failure): string => sprintf(
                'admin#%d(%s) %s',
                (int) $failure['admin_id'],
                (string) $failure['email'],
                (string) $failure['message'],
            ),
            $failures,
        );

        return sprintf(
            '%s发送失败 %d 位管理员（账单 %s）：%s',
            $label,
            count($failures),
            (string) ($invoice->invoice_no ?? ('#'.(int) $invoice->id)),
            implode('；', $details),
        );
    }

    private function resolveRecipients(): Collection
    {
        return AdminUser::query()
            ->with('role')
            ->where('status', 1)
            ->whereNotNull('email')
            ->orderBy('id')
            ->get(['id', 'username', 'nickname', 'email', 'role_id'])
            ->filter(function (AdminUser $admin) {
                $email = trim((string) ($admin->email ?? ''));

                return $email !== ''
                    && (
                        $admin->hasPermission(AdminPermissions::ORDER_LIST)
                        || $admin->hasPermission(AdminPermissions::ORDER_DETAIL)
                        || $admin->hasPermission(AdminPermissions::ORDER_MANAGE)
                        || $admin->hasPermission(AdminPermissions::INVOICE_LIST)
                        || $admin->hasPermission(AdminPermissions::INVOICE_DETAIL)
                        || $admin->hasPermission(AdminPermissions::INVOICE_MANAGE)
                    );
            })
            ->unique(fn (AdminUser $admin) => mb_strtolower(trim((string) $admin->email)))
            ->values();
    }

    private function resolveOrderProductDisplayText(Order $order): string
    {
        $path = $this->productFullPathResolver()->pathForOrder($order);

        return $path !== '' ? $path : (string) $order->display_product_name;
    }

    private function resolveInvoiceProductDisplayText(Invoice $invoice, ?Order $order = null): string
    {
        $snapshot = is_array($invoice->product_snapshot_json ?? null) ? $invoice->product_snapshot_json : [];
        $snapshotPath = $this->productFullPathResolver()->pathFromSnapshot($snapshot);
        if ($snapshotPath !== '') {
            return $snapshotPath;
        }

        foreach (['product_name', 'product_spec_snapshot'] as $key) {
            $value = trim((string) ($snapshot[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $path = $this->productFullPathResolver()->pathForInvoice($invoice);
        if ($path !== '') {
            return $path;
        }

        if ($order instanceof Order) {
            return $this->resolveOrderProductDisplayText($order);
        }

        return (string) $invoice->display_product_name;
    }

    private function productFullPathResolver(): ProductFullPathResolver
    {
        return $this->productFullPathResolver ??= app(ProductFullPathResolver::class);
    }
}
