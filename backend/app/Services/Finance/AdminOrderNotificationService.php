<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Jobs\SendPaidInvoiceAdminNotificationJob;
use App\Models\AdminUser;
use App\Models\AutomationLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Messaging\MessageDispatcher;
use App\Services\ProductCatalog\ProductFullPathResolver;
use App\Services\System\NotificationService;
use App\Support\AccountIdentifier;
use App\Support\AdminPermissions;
use App\Support\BillingCycleLabels;
use App\Support\PaymentChannelLabel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AdminOrderNotificationService
{
    private const TASK_KEY = 'admin-order-notification';

    public function __construct(
        private MessageDispatcher $messageDispatcher,
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

        // 与账单/订单展示同一渠道判据（见 PaymentChannelLabel::channelScopeSql），
        // 否则旧库迁入的支付单在通知里会显示为「未知方式」。
        [$channelScopeSql, $channelScopeBindings] = PaymentChannelLabel::channelScopeSql();

        $payment = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->whereRaw($channelScopeSql, $channelScopeBindings)
            ->where('status', PaymentStatus::SUCCESS)
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
        $this->assertRecipientsUsable('付款通知', $invoice, $recipients);

        $failures = [];

        foreach ($recipients as $admin) {
            $ruleKey = 'admin:'.(int) $admin->id;

            if (AutomationLog::hasRecord(self::TASK_KEY, 'invoice_paid', 'invoice', (int) $invoice->id, $ruleKey)) {
                continue;
            }

            try {
                $this->messageDispatcher->sendNow(
                    'admin.invoice_paid.'.$invoice->id.'.admin:'.$admin->id,
                    'admin_order_paid',
                    'invoice',
                    (int) $invoice->id,
                    [[
                        'audience' => 'admin',
                        'account_id' => (int) $admin->id,
                        'target_key' => 'admin:'.$admin->id,
                        'email' => (string) $admin->email,
                        'mobile' => '',
                        'name' => (string) $admin->display_name,
                    ]],
                    [
                        'recipient_name' => $admin->display_name,
                        'user_name' => $invoice->user?->display_name ?: '客户',
                        'user_email' => (string) ($invoice->user?->email ?? '未绑定'),
                        'order_no' => (string) ($invoice->order?->order_no ?? ''),
                        'invoice_no' => (string) ($invoice->invoice_no ?? ''),
                        'product_name' => $this->resolveInvoiceProductDisplayText($invoice, $invoice->order),
                        'billing_cycle_label' => BillingCycleLabels::label((string) ($invoice->billing_cycle ?? '')),
                        'paid_amount' => number_format((float) ($invoice->paid_amount ?? $invoice->amount ?? 0), 2, '.', ''),
                        // 管理端按「插件名称-支付渠道」展示，插件名与渠道分别给出，不拼成单一展示串。
                        'payment_method' => $payment instanceof Payment
                            ? trim(PaymentChannelLabel::pluginNameFromPayment($payment).' / '.PaymentChannelLabel::channelNameFromPayment($payment), ' /')
                            : PaymentGatewayCode::channelLabel(''),
                        'trade_no' => (string) ($payment?->trade_no ?? ''),
                        'paid_at' => $invoice->paid_at?->format('Y-m-d H:i:s')
                            ?? $payment?->paid_at?->format('Y-m-d H:i:s')
                            ?? now()->format('Y-m-d H:i:s'),
                    ],
                    'admin'
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
        $this->assertRecipientsUsable('新订单通知', $invoice, $recipients);

        $failures = [];

        foreach ($recipients as $admin) {
            $ruleKey = 'admin:'.(int) $admin->id;

            if (AutomationLog::hasRecord(self::TASK_KEY, 'invoice_created', 'invoice', (int) $invoice->id, $ruleKey)) {
                continue;
            }

            try {
                $this->messageDispatcher->sendNow(
                    'admin.invoice_created.'.$invoice->id.'.admin:'.$admin->id,
                    'admin_order_created',
                    'invoice',
                    (int) $invoice->id,
                    [[
                        'audience' => 'admin',
                        'account_id' => (int) $admin->id,
                        'target_key' => 'admin:'.$admin->id,
                        'email' => (string) $admin->email,
                        'mobile' => '',
                        'name' => (string) $admin->display_name,
                    ]],
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
                    ],
                    'admin'
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

    /**
     * 解析账单通知的管理员收件人。
     *
     * 邮箱必须能投递：历史上存在「email = test@123456」这类脏数据
     * （管理端保存时已有 email 校验，但这些行早于校验或来自导入），
     * 它们走到传输层只会得到 recipient_invalid，进而让整批通知抛异常、
     * Job 反复重试直到耗尽。非法邮箱在这里直接排除，并留一条 warning
     * 说明是谁被跳过——不能静默丢弃，管理员需要知道自己的邮箱配错了。
     */
    private function resolveRecipients(): Collection
    {
        $invalidEmails = [];

        $recipients = AdminUser::query()
            ->with('role')
            ->where('status', PaymentStatus::SUCCESS)
            ->whereNotNull('email')
            ->orderBy('id')
            ->get(['id', 'username', 'nickname', 'email', 'role_id'])
            ->filter(function (AdminUser $admin) use (&$invalidEmails) {
                $email = trim((string) ($admin->email ?? ''));
                if ($email === '') {
                    return false;
                }

                if (! AccountIdentifier::isValidEmail($email)) {
                    $invalidEmails[] = [
                        'admin_id' => (int) $admin->id,
                        'username' => (string) $admin->username,
                        'email' => $email,
                    ];

                    return false;
                }

                return $this->hasOrderNotificationPermission($admin);
            })
            ->unique(fn (AdminUser $admin) => mb_strtolower(trim((string) $admin->email)))
            ->values();

        if ($invalidEmails !== []) {
            Log::warning('[管理员账单通知] 跳过邮箱格式非法的管理员，请修正其后台邮箱', [
                'admins' => $invalidEmails,
            ]);
        }

        return $recipients;
    }

    /**
     * 收件人集合为空时绝不能静默返回。
     *
     * 空集合的成因必须分辨清楚：
     * - 全库根本没有「有订单权限且在用的管理员」→ 配置问题，不该反复重试，静默返回；
     * - 有这类管理员、但他们的邮箱全部不合法 → 数据问题，抛异常进 failed_jobs；
     * - 有这类管理员、邮箱也合法、却仍然解析为空 → 属未知异常，抛异常。
     *
     * 只判「非法邮箱是否存在」不够：当库里有非法邮箱管理员、而另一位有权限的
     * 管理员恰好没填邮箱时，会误报成「全员非法」。这里改为按权限基数判断。
     */
    private function assertRecipientsUsable(string $label, Invoice $invoice, Collection $recipients): void
    {
        if ($recipients->isNotEmpty()) {
            return;
        }

        $candidates = AdminUser::query()
            ->with('role')
            ->where('status', PaymentStatus::SUCCESS)
            ->get(['id', 'username', 'email', 'role_id'])
            ->filter(fn (AdminUser $admin): bool => $this->hasOrderNotificationPermission($admin));

        if ($candidates->isEmpty()) {
            // 库里没有有权限的管理员：正常配置状态，不重试
            return;
        }

        $invalid = $candidates
            ->filter(fn (AdminUser $admin): bool => ! AccountIdentifier::isValidEmail((string) ($admin->email ?? '')))
            ->map(fn (AdminUser $admin): string => trim((string) ($admin->email ?? '')) !== ''
                ? (string) $admin->email
                : '(未填写邮箱)')
            ->values()
            ->all();

        throw new \RuntimeException(sprintf(
            '%s无可用收件人：%d 位有订单权限的管理员中有 %d 位邮箱不可用（%s），账单 %s 未发送',
            $label,
            $candidates->count(),
            count($invalid),
            implode('、', array_slice($invalid, 0, 5)),
            (string) ($invoice->invoice_no ?? ('#'.(int) $invoice->id)),
        ));
    }

    /**
     * 管理员是否有权接收订单/账单通知。
     */
    private function hasOrderNotificationPermission(AdminUser $admin): bool
    {
        return $admin->hasPermission(AdminPermissions::ORDER_LIST)
            || $admin->hasPermission(AdminPermissions::ORDER_DETAIL)
            || $admin->hasPermission(AdminPermissions::ORDER_MANAGE)
            || $admin->hasPermission(AdminPermissions::INVOICE_LIST)
            || $admin->hasPermission(AdminPermissions::INVOICE_DETAIL)
            || $admin->hasPermission(AdminPermissions::INVOICE_MANAGE);
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
