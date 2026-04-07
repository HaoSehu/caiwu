<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Constants\InvoiceStatus;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\AccountTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\NotificationLog;
use App\Models\OperationLog;
use App\Models\Payment;
use App\Models\ReferralReward;
use App\Models\ReferralWithdrawal;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserAccount;
use App\Services\User\Concerns\HandlesAdminUserServices;
use App\Support\TextSanitizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UserService
{
    use HandlesAdminUserServices;

    public function __construct(
        private ClientServiceConsoleService $clientServiceConsoleService,
        private ReferralService $referralService,
        private OrderService $orderService,
        private PaymentService $paymentService,
        private NotificationService $notificationService,
        private OperationLogService $operationLogService,
        private ProvisionService $provisionService,
        private ServiceStatusSyncService $serviceStatusSyncService,
        private SettingService $settingService,
    ) {}

    /**
     * 用户列表 (管理端)
     */
    public function list(array $filters, int $perPage = 20)
    {
        $query = User::query()
            ->withReadAggregates()
            ->select([
                'id',
                'email',
                'phone',
                'nickname',
                'real_name',
                'verification_status',
                'is_verified',
                'status',
                'created_at',
            ])
            ->withCount([
                'services as opened_product_count' => fn ($serviceQuery) => $serviceQuery->where('status', ServiceStatus::ACTIVE),
            ]);

        if (!empty($filters['user_id'])) {
            $query->where('id', (int) $filters['user_id']);
        }
        if (!empty($filters['keyword'])) {
            $query->search($filters['keyword']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['is_verified']) && $filters['is_verified'] !== '') {
            if ((int) $filters['is_verified'] === 1) {
                $query->where('verification_status', 2);
            } else {
                $query->where('verification_status', '<>', 2);
            }
        }
        if (isset($filters['verification_status']) && $filters['verification_status'] !== '') {
            $targetStatus = (int) $filters['verification_status'];

            $query->where('verification_status', $targetStatus);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * 创建用户
     */
    public function create(array $data): User
    {
        $exists = User::where('email', $data['email'])->exists();
        throw_if($exists, new BusinessException('邮箱已存在'));

        $user = \DB::transaction(function () use ($data) {
            $user = User::create([
                'email'        => $data['email'],
                'password'     => $data['password'],
                'phone'        => TextSanitizer::clean((string) ($data['phone'] ?? '')),
                'status'       => $data['status'] ?? 1,
                'nickname'     => TextSanitizer::clean((string) ($data['nickname'] ?? '')) ?: null,
            ]);

            UserAccount::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'cash_balance' => number_format((float) ($data['balance'] ?? 0), 2, '.', ''),
                    'credit_limit' => number_format((float) ($data['credit_limit'] ?? 0), 2, '.', ''),
                ]
            );

            $this->referralService->ensureReferralCode($user);

            return $user;
        });

        return $user->refresh();
    }

    /**
     * 更新用户
     */
    public function update(User $user, array $data): User
    {
        $baseUpdateData = collect($data)->only([
            'phone', 'status', 'nickname', 'company', 'qq', 'admin_note',
        ])->toArray();

        foreach (['nickname', 'company', 'qq'] as $field) {
            if (array_key_exists($field, $baseUpdateData)) {
                $baseUpdateData[$field] = TextSanitizer::clean((string) $baseUpdateData[$field]);
            }
        }

        if (array_key_exists('admin_note', $baseUpdateData)) {
            $baseUpdateData['admin_note'] = TextSanitizer::clean((string) $baseUpdateData['admin_note'], true);
        }

        if (array_key_exists('phone', $baseUpdateData)) {
            $baseUpdateData['phone'] = TextSanitizer::clean((string) $baseUpdateData['phone']);
        }

        if (!empty($data['password'])) {
            $baseUpdateData['password'] = $data['password'];
        }

        $accountUpdateData = [];
        if (array_key_exists('credit_limit', $data)) {
            $accountUpdateData['credit_limit'] = number_format((float) $data['credit_limit'], 2, '.', '');
        }

        \DB::transaction(function () use ($user, $baseUpdateData, $accountUpdateData) {
            if ($baseUpdateData !== []) {
                $user->update($baseUpdateData);
            }

            if ($accountUpdateData !== []) {
                UserAccount::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    $accountUpdateData
                );
                $user->unsetRelation('account');
            }
        });

        return $user->refresh();
    }

    /**
     * 禁用/启用
     */
    public function toggleStatus(User $user): User
    {
        $user->update(['status' => $user->status === 1 ? 0 : 1]);
        return $user->refresh();
    }

    /**
     * 用户详情（含完整统计）
     */
    public function detail(User $user): array
    {
        $user->loadMissing([
            'account',
        ]);
        $memberLevel = $user->memberLevel;
        $countStats = User::query()
            ->whereKey($user->id)
            ->withCount([
                'services as service_active' => fn ($query) => $query->where('status', 1),
                'services as service_total',
                'orders as order_total',
                'orders as order_pending' => fn ($query) => $query->where('status', 0),
                'tickets as ticket_open' => fn ($query) => $query->whereIn('status', [0, 1, 2]),
                'tickets as ticket_closed' => fn ($query) => $query->where('status', 3),
                'tickets as ticket_total',
                'invoices as invoice_unpaid' => fn ($query) => $query->where('status', 0),
                'invoices as invoice_paid' => fn ($query) => $query->where('status', 1),
            ])
            ->first();

        $balanceSummary = $this->buildBalanceAggregateSummary($user);
        $invoiceSummary = Invoice::query()
            ->where('user_id', $user->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 0 THEN amount ELSE 0 END), 0) as unpaid_amount')
            ->first();
        $referralSummary = ReferralReward::query()
            ->where('referrer_user_id', $user->id)
            ->selectRaw('COUNT(*) as rewarded_orders_count')
            ->selectRaw('COALESCE(SUM(reward_amount), 0) as total_reward_amount')
            ->first();
        $directReferralCount = User::query()
            ->where('referrer_user_id', $user->id)
            ->count();
        $recentReferrals = User::query()
            ->withReadAggregates()
            ->where('referrer_user_id', $user->id)
            ->orderByDesc('referred_at')
            ->limit(8)
            ->get();
        $withdrawSummary = ReferralWithdrawal::query()
            ->where('user_id', $user->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 0 THEN amount ELSE 0 END), 0) as withdrawing_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 1 THEN amount ELSE 0 END), 0) as withdrawn_amount')
            ->first();

        return [
            'user'  => $user,
            'stats' => [
                'service_active'  => (int) ($countStats?->service_active ?? 0),
                'service_total'   => (int) ($countStats?->service_total ?? 0),
                'order_total'     => (int) ($countStats?->order_total ?? 0),
                'order_pending'   => (int) ($countStats?->order_pending ?? 0),
                'total_income'    => (float) ($balanceSummary->total_income ?? 0),
                'total_expense'   => (float) ($balanceSummary->total_expense ?? 0),
                'unpaid_amount'   => (float) ($invoiceSummary?->unpaid_amount ?? 0),
                'ticket_open'     => (int) ($countStats?->ticket_open ?? 0),
                'ticket_closed'   => (int) ($countStats?->ticket_closed ?? 0),
                'ticket_total'    => (int) ($countStats?->ticket_total ?? 0),
                'invoice_unpaid'  => (int) ($countStats?->invoice_unpaid ?? 0),
                'invoice_paid'    => (int) ($countStats?->invoice_paid ?? 0),
                'direct_referral_count' => $directReferralCount,
                'rewarded_orders_count' => (int) ($referralSummary?->rewarded_orders_count ?? 0),
                'total_referral_reward' => (float) ($referralSummary?->total_reward_amount ?? 0),
            ],
            'referral' => [
                'referral_code' => $user->referral_code,
                'referrer_user_id' => $user->referrer_user_id,
                'member_level' => $memberLevel ? [
                    'id' => $memberLevel->id,
                    'name' => $memberLevel->name,
                    'code' => $memberLevel->code,
                    'reward_rate' => (float) $memberLevel->reward_rate,
                ] : null,
                'total_sales_amount' => (float) $user->total_sales_amount,
                'referral_frozen_amount' => (float) $user->referral_frozen_amount,
                'referral_available_amount' => (float) $user->referral_available_amount,
                'referral_withdrawing_amount' => (float) ($withdrawSummary?->withdrawing_amount ?? $user->referral_withdrawing_amount),
                'referral_withdrawn_amount' => (float) ($withdrawSummary?->withdrawn_amount ?? $user->referral_withdrawn_amount),
                'recent_referrals' => $recentReferrals->map(fn (User $item) => [
                    'id' => $item->id,
                    'email' => $item->email,
                    'nickname' => $item->nickname,
                    'display_name' => $item->display_name,
                    'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
                    'referred_at' => $item->referred_at?->format('Y-m-d H:i:s'),
                ])->values()->all(),
            ],
        ];
    }

    /**
     * 用户账单列表
     */
    public function invoices(User $user, array $filters, int $perPage = 20)
    {
        $query = $user->invoices()->with([
            'order:id,order_no,status,service_id,paid_at,product_id',
            'order.product:id,name',
            'payments.callbacks',
            'items',
        ]);

        if (($filters['status'] ?? '') === '5') {
            $query->where(function ($builder) {
                $builder->whereHas('payments', fn ($paymentQuery) => $paymentQuery->where('status', PaymentStatus::REFUNDED))
                    ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('status', OrderStatus::REFUNDED));
            });
        } elseif (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Invoice $invoice) => $this->transformInvoiceListItem($invoice))
        );

        return $paginator;
    }

    public function invoiceDetail(User $user, int $invoiceId): array
    {
        $invoice = $this->findUserInvoice($user, $invoiceId);
        $invoice->loadMissing([
            'order.product:id,name',
            'payments.callbacks',
            'items',
        ]);

        $paymentSummary = $this->buildInvoicePaymentSummary($invoice);
        $refundActions = $this->resolveInvoiceRefundActions($invoice, $paymentSummary);
        $displayStatus = $this->resolveInvoiceDisplayStatus($invoice, $paymentSummary);

        $payments = $invoice->payments->map(function (Payment $payment) {
            $refund = (array) data_get((array) ($payment->callback_raw ?? []), 'refund', []);

            return [
                'id' => (int) $payment->id,
                'payment_no' => $payment->payment_no,
                'created_at' => $payment->created_at?->format('Y-m-d H:i:s'),
                'paid_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
                'gateway' => $payment->gateway,
                'gateway_label' => $this->resolvePaymentGatewayLabel((string) $payment->gateway),
                'trade_no' => $payment->trade_no,
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'status' => (int) $payment->status,
                'status_label' => $this->resolvePaymentStatusLabel((int) $payment->status),
                'refund_method' => (string) ($refund['refund_method'] ?? ''),
                'refund_method_label' => (string) ($refund['refund_method_label'] ?? ''),
                'refund_reason' => (string) ($refund['refund_reason'] ?? ''),
                'refunded_at' => (string) ($refund['refunded_at'] ?? ($refund['gmt_refund_pay'] ?? '')),
            ];
        })->values()->all();

        $items = $invoice->items->isNotEmpty()
            ? $invoice->items->map(fn (InvoiceItem $item) => [
                'id' => (int) $item->id,
                'description' => (string) $item->item_name,
                'amount' => number_format((float) $item->line_amount, 2, '.', ''),
            ])->values()->all()
            : [[
                'id' => (int) $invoice->id,
                'description' => $this->resolveInvoiceItemDescription($invoice),
                'amount' => number_format((float) $invoice->amount, 2, '.', ''),
            ]];

        $logs = $this->resolveInvoiceLogs($invoice);

        return [
            'invoice' => [
                'id' => (int) $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'user_name' => $user->display_name,
                'user_email' => $user->email,
                'order_id' => (int) ($invoice->order?->id ?? 0),
                'order_no' => $invoice->order?->order_no,
                'invoice_type' => $invoice->type,
                'invoice_type_label' => $this->resolveInvoiceTypeLabel((string) $invoice->type, $invoice),
                'amount' => number_format((float) $invoice->amount, 2, '.', ''),
                'paid_amount' => number_format((float) ($invoice->paid_amount ?? 0), 2, '.', ''),
                'payable_amount' => number_format(max((float) $invoice->amount - (float) ($invoice->paid_amount ?? 0), 0), 2, '.', ''),
                'status' => (int) $displayStatus['status'],
                'status_label' => (string) $displayStatus['status_label'],
                'raw_status' => (int) $invoice->status,
                'raw_status_label' => InvoiceStatus::$labels[$invoice->status] ?? (string) $invoice->status,
                'created_at' => $invoice->created_at?->format('Y-m-d H:i:s'),
                'due_date' => $invoice->due_date?->format('Y-m-d H:i:s') ?? $invoice->due_date?->format('Y-m-d'),
                'paid_at' => $invoice->paid_at?->format('Y-m-d H:i:s'),
                'payment_method' => $paymentSummary['gateway'] ?? '',
                'payment_method_label' => $paymentSummary['gateway_label'] ?? '--',
                'payment_trade_no' => $paymentSummary['trade_no'] ?? '',
                'payment_summary' => $paymentSummary,
                'refund_actions' => $refundActions,
                'remark' => '',
                'product_name' => (string) ($invoice->order?->display_product_name ?? ''),
            ],
            'payments' => $payments,
            'items' => $items,
            'logs' => $logs,
        ];
    }

    public function manualInvoiceEntry(User $user, int $invoiceId, array $data, array $context = []): array
    {
        $invoice = $this->findUserInvoice($user, $invoiceId);
        $invoice->loadMissing('order');

        throw_if(! $invoice->order, new BusinessException('账单未关联订单，暂不支持手动入账'));

        $this->orderService->updateManualPaymentStatus($invoice->order, [
            'action' => 'mark_paid',
            'amount' => $data['amount'],
            'paid_at' => $data['paid_at'],
            'payment_gateway' => $data['payment_gateway'] ?? 'manual',
            'trade_no' => $data['trade_no'] ?? '',
            'send_email' => (bool) ($data['send_email'] ?? false),
            'remark' => $data['remark'] ?? '',
            'sync_business_flow' => (bool) ($data['sync_business_flow'] ?? false),
        ], $context);

        return $this->invoiceDetail($user, $invoiceId);
    }

    public function sendInvoiceEmail(User $user, int $invoiceId, array $context = []): array
    {
        $invoice = $this->findUserInvoice($user, $invoiceId);
        $invoice->loadMissing([
            'order.product:id,name',
            'payments.callbacks',
            'items',
        ]);

        throw_if(trim((string) $user->email) === '', new BusinessException('用户未绑定邮箱，无法发送账单邮件'));

        $latestPayment = $invoice->payments->first(fn (Payment $payment) => (int) $payment->status === PaymentStatus::SUCCESS)
            ?? $invoice->payments->first();
        $this->notificationService->sendTemplateEmail((string) $user->email, NotificationService::TEMPLATE_INVOICE_NOTICE, [
            'site_name' => (string) config('idc.site_name', config('app.name', '创欧云')),
            'display_name' => (string) $user->display_name,
            'notice_title' => (int) $invoice->status === InvoiceStatus::PAID ? '账单支付确认' : '账单支付提醒',
            'invoice_no' => (string) $invoice->invoice_no,
            'order_no' => (string) ($invoice->order?->order_no ?? ''),
            'product_name' => (string) ($invoice->order?->display_product_name ?? ''),
            'amount' => number_format((float) $invoice->amount, 2, '.', ''),
            'status_label' => (string) (InvoiceStatus::$labels[$invoice->status] ?? (string) $invoice->status),
            'due_at' => $invoice->due_date ? ($invoice->due_date?->format('Y-m-d H:i:s') ?? $invoice->due_date?->format('Y-m-d')) : '',
            'paid_at' => $invoice->paid_at ? $invoice->paid_at->format('Y-m-d H:i:s') : '',
            'payment_method' => $latestPayment ? $this->resolvePaymentGatewayLabel((string) $latestPayment->gateway) : '',
            'trade_no' => (string) ($latestPayment?->trade_no ?? ''),
            'notice_message' => (int) $invoice->status === InvoiceStatus::PAID
                ? '该账单已支付完成，如有疑问请联系管理员。'
                : '该账单当前仍待支付，请尽快完成付款。',
        ]);

        $this->operationLogService->write(
            userId: ((int) ($context['operator_id'] ?? 0)) ?: null,
            userType: 'admin',
            action: 'invoice.email.sent',
            module: 'order',
            targetId: (int) ($invoice->order_id ?: 0) ?: null,
            detail: [
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'operator_name' => (string) ($context['operator_name'] ?? ''),
            ],
            ipAddress: (string) ($context['ip_address'] ?? ''),
        );

        return $this->invoiceDetail($user, $invoiceId);
    }

    public function refundInvoice(User $user, int $invoiceId, array $data, array $context = []): array
    {
        $invoice = $this->findUserInvoice($user, $invoiceId);
        $invoice->loadMissing([
            'order',
            'payments.callbacks',
        ]);

        $paymentSummary = $this->buildInvoicePaymentSummary($invoice);
        $refundActions = $this->resolveInvoiceRefundActions($invoice, $paymentSummary);
        $refundMethod = trim((string) ($data['refund_method'] ?? 'balance'));

        throw_if(
            ! ($refundActions['can_balance'] ?? false) && ! ($refundActions['can_original'] ?? false),
            new BusinessException((string) ($refundActions['blocked_reason'] ?? '当前账单不支持退款'))
        );

        $result = match ($refundMethod) {
            'balance' => $this->paymentService->refundInvoiceToBalance($user, $invoice, [
                'amount' => $data['amount'] ?? null,
                'remark' => $data['remark'] ?? '',
            ], $context),
            'original' => $this->refundInvoiceByOriginalRoute($user, $invoice, $paymentSummary, $refundActions, $data, $context),
            default => throw new BusinessException('不支持的退款方式'),
        };

        if (($result['already_refunded'] ?? false) !== true) {
            $refund = (array) ($result['refund'] ?? []);

            $this->operationLogService->write(
                userId: ((int) ($context['operator_id'] ?? 0)) ?: null,
                userType: 'admin',
                action: 'invoice.refund',
                module: 'order',
                targetId: (int) ($invoice->order_id ?: 0) ?: null,
                detail: [
                    'invoice_id' => (int) $invoice->id,
                    'invoice_no' => (string) $invoice->invoice_no,
                    'refund_method' => (string) ($refund['refund_method'] ?? $refundMethod),
                    'refund_amount' => (string) ($refund['refund_amount'] ?? ''),
                    'refund_reason' => (string) ($refund['refund_reason'] ?? ''),
                    'trade_no' => (string) ($refund['trade_no'] ?? ''),
                    'operator_name' => (string) ($context['operator_name'] ?? ''),
                ],
                ipAddress: (string) ($context['ip_address'] ?? ''),
            );
        }

        return $this->invoiceDetail($user, $invoiceId);
    }

    /**
     * 用户余额变动记录
     */
    public function balanceLogs(User $user, array $filters, int $perPage = 20): array
    {
        $query = $user->accountTransactions()->where('account_type', 'cash');

        if (! empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);
        $summary = $this->buildBalanceAggregateSummary($user);

        return [
            'paginator' => $paginator,
            'summary' => [
                'total_income'  => (float) ($summary->total_income ?? 0),
                'total_expense' => (float) ($summary->total_expense ?? 0),
                'balance'       => (float) ($user->account?->cash_balance ?? $user->balance),
                'total_count'   => (int) ($summary->total_count ?? 0),
            ],
        ];
    }

    /**
     * 用户工单列表
     */
    public function tickets(User $user, array $filters, int $perPage = 20): array
    {
        $query = $user->tickets()->with('service:id,name');

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['priority']) && $filters['priority'] !== '') {
            $query->where('priority', $filters['priority']);
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);
        $now = now();
        $currentMonthStart = $now->copy()->startOfMonth();
        $previousMonthStart = $currentMonthStart->copy()->subMonth();
        $currentYearStart = $now->copy()->startOfYear();
        $previousYearStart = $currentYearStart->copy()->subYear();

        $summary = Ticket::query()
            ->where('user_id', $user->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as this_month', [$currentMonthStart])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) as last_month',
                [$previousMonthStart, $currentMonthStart]
            )
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as this_year', [$currentYearStart])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) as last_year',
                [$previousYearStart, $currentYearStart]
            )
            ->first();

        return [
            'paginator' => $paginator,
            'summary' => [
                'this_month' => (int) ($summary?->this_month ?? 0),
                'last_month' => (int) ($summary?->last_month ?? 0),
                'this_year'  => (int) ($summary?->this_year ?? 0),
                'last_year'  => (int) ($summary?->last_year ?? 0),
            ],
        ];
    }

    /**
     * 用户操作日志
     */
    public function operationLogs(int $userId, array $filters, int $perPage = 20)
    {
        $query = OperationLog::where('user_id', $userId)->where('user_type', 'client');

        if (!empty($filters['keyword'])) {
            $query->where('action', 'like', "%{$filters['keyword']}%");
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * 用户短信日志
     */
    public function smsLogs(User $user, int $perPage = 20)
    {
        $phone = trim((string) $user->phone);
        if ($phone === '') {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $paginator = NotificationLog::where('channel', 'sms')
            ->where('recipient', $phone)
            ->selectRaw("id, recipient as phone, template_code, content, params_json, status, provider, request_id, error_msg, sent_at, created_at, updated_at, origin_type")
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $paginator->setCollection(
            $paginator->getCollection()->map(function (NotificationLog $log) {
                $item = $log->toArray();
                $item['phone'] = $this->maskPhone((string) ($item['phone'] ?? ''));
                if ($this->shouldRedactSmsLog($item)) {
                    $item['content'] = '短信验证码已发送（内容已脱敏）';
                    $item['params_json'] = $this->sanitizeSmsParams((array) ($item['params_json'] ?? []));
                }

                return $item;
            })
        );

        return $paginator;
    }

    /**
     * 用户邮件日志
     */
    public function emailLogs(User $user, int $perPage = 20)
    {
        return NotificationLog::where('channel', 'email')
            ->where('recipient', $user->email)
            ->selectRaw("id, template_code, recipient as to_email, subject, content, status, error_msg, sent_at, created_at, updated_at")
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    private function buildBalanceAggregateSummary(User $user): ?AccountTransaction
    {
        return AccountTransaction::query()
            ->where('user_id', $user->id)
            ->where('account_type', 'cash')
            ->selectRaw('COALESCE(SUM(CASE WHEN change_amount > 0 THEN change_amount ELSE 0 END), 0) as total_income')
            ->selectRaw('COALESCE(SUM(CASE WHEN change_amount < 0 THEN ABS(change_amount) ELSE 0 END), 0) as total_expense')
            ->selectRaw('COUNT(*) as total_count')
            ->first();
    }

    private function shouldRedactSmsLog(array $item): bool
    {
        $originType = trim((string) ($item['origin_type'] ?? ''));
        $templateCode = trim((string) ($item['template_code'] ?? ''));

        return $originType === 'sms_verify' || $templateCode === '100001';
    }

    private function sanitizeSmsParams(array $params): array
    {
        if ($params === []) {
            return ['code' => '***'];
        }

        $params['code'] = '***';

        return $params;
    }

    private function maskPhone(string $phone): string
    {
        $normalized = trim($phone);
        if ($normalized === '') {
            return '';
        }

        if (mb_strlen($normalized) <= 7) {
            return $normalized;
        }

        return mb_substr($normalized, 0, 3) . '****' . mb_substr($normalized, -4);
    }

    private function findUserInvoice(User $user, int $invoiceId): Invoice
    {
        return $user->invoices()
            ->whereKey($invoiceId)
            ->firstOrFail();
    }

    private function refundInvoiceByOriginalRoute(
        User $user,
        Invoice $invoice,
        ?array $paymentSummary,
        array $refundActions,
        array $data,
        array $context = [],
    ): array {
        throw_if(
            ! ($refundActions['can_original'] ?? false),
            new BusinessException((string) ($refundActions['original_blocked_reason'] ?? $refundActions['blocked_reason'] ?? '当前支付方式不支持原路退款'))
        );

        $gateway = (string) ($paymentSummary['gateway'] ?? '');

        if ($gateway === 'balance') {
            return $this->paymentService->refundInvoiceToBalance($user, $invoice, [
                'amount' => $data['amount'] ?? null,
                'remark' => $data['remark'] ?? '后台按原余额路径退款',
            ], $context);
        }

        throw_if($gateway !== 'alipay', new BusinessException('当前支付方式不支持原路退款'));
        throw_if(! $invoice->order, new BusinessException('账单未关联订单，暂不支持原路退款'));

        $this->orderService->updateManualPaymentStatus($invoice->order, [
            'action' => 'refund',
            'amount' => $data['amount'] ?? ($paymentSummary['amount'] ?? null),
            'remark' => $data['remark'] ?? '后台发起原路退款',
        ], $context);

        return [
            'already_refunded' => false,
            'refund' => [
                'refund_method' => 'original',
                'refund_method_label' => '原路退款',
                'refund_amount' => (string) ($paymentSummary['amount'] ?? ''),
                'refund_reason' => (string) ($data['remark'] ?? '后台发起原路退款'),
            ],
        ];
    }

    private function resolvePaymentGatewayLabel(string $gateway): string
    {
        return match ($gateway) {
            'alipay' => '支付宝支付',
            'wechat' => '微信支付',
            'balance' => '余额支付',
            'bank_transfer' => '银行转账',
            'offline' => '线下支付',
            default => '手动入账',
        };
    }

    private function transformInvoiceListItem(Invoice $invoice): array
    {
        $paymentSummary = $this->buildInvoicePaymentSummary($invoice);
        $refundActions = $this->resolveInvoiceRefundActions($invoice, $paymentSummary);
        $displayStatus = $this->resolveInvoiceDisplayStatus($invoice, $paymentSummary);

        return [
            'id' => (int) $invoice->id,
            'invoice_no' => (string) $invoice->invoice_no,
            'created_at' => $invoice->created_at?->format('Y-m-d H:i:s'),
            'due_date' => $invoice->due_date?->format('Y-m-d H:i:s') ?? $invoice->due_date?->format('Y-m-d'),
            'paid_at' => $invoice->paid_at?->format('Y-m-d H:i:s'),
            'amount' => number_format((float) $invoice->amount, 2, '.', ''),
            'paid_amount' => number_format((float) ($invoice->paid_amount ?? 0), 2, '.', ''),
            'type' => (string) $invoice->type,
            'type_label' => $this->resolveInvoiceTypeLabel((string) $invoice->type, $invoice),
            'status' => (int) $displayStatus['status'],
            'status_label' => (string) $displayStatus['status_label'],
            'raw_status' => (int) $invoice->status,
            'raw_status_label' => InvoiceStatus::$labels[$invoice->status] ?? (string) $invoice->status,
            'order_id' => (int) ($invoice->order?->id ?? 0),
            'order' => $invoice->order ? [
                'id' => (int) $invoice->order->id,
                'order_no' => (string) $invoice->order->order_no,
                'status' => (int) $invoice->order->status,
                'service_id' => (int) ($invoice->order->service_id ?? 0),
                'paid_at' => $invoice->order->paid_at?->format('Y-m-d H:i:s'),
                'product' => $invoice->order->product ? [
                    'id' => (int) $invoice->order->product->id,
                    'name' => (string) $invoice->order->product->name,
                ] : null,
            ] : null,
            'payment_summary' => $paymentSummary,
            'refund_actions' => $refundActions,
        ];
    }

    private function buildInvoicePaymentSummary(Invoice $invoice): ?array
    {
        if (! $invoice->relationLoaded('payments')) {
            $invoice->loadMissing(['payments' => fn ($query) => $query->orderByDesc('id')]);
        }

        $payment = $this->resolvePrimaryInvoicePayment($invoice->payments);

        if (! $payment instanceof Payment) {
            return null;
        }

        $refund = (array) data_get((array) ($payment->callback_raw ?? []), 'refund', []);

        return [
            'id' => (int) $payment->id,
            'payment_no' => (string) $payment->payment_no,
            'gateway' => (string) $payment->gateway,
            'gateway_label' => $this->resolvePaymentGatewayLabel((string) $payment->gateway),
            'trade_no' => (string) ($payment->trade_no ?? ''),
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'status' => (int) $payment->status,
            'status_label' => $this->resolvePaymentStatusLabel((int) $payment->status),
            'paid_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
            'refund_method' => (string) ($refund['refund_method'] ?? ''),
            'refund_method_label' => (string) ($refund['refund_method_label'] ?? ''),
            'refund_reason' => (string) ($refund['refund_reason'] ?? ''),
            'refunded_at' => (string) ($refund['refunded_at'] ?? ($refund['gmt_refund_pay'] ?? '')),
        ];
    }

    private function resolvePrimaryInvoicePayment(iterable $payments): ?Payment
    {
        $collection = collect($payments);

        return $collection
            ->first(fn (Payment $payment) => ! (bool) data_get((array) ($payment->callback_raw ?? []), 'duplicate_paid', false)
                && in_array((int) $payment->status, [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED], true))
            ?? $collection->first(fn (Payment $payment) => in_array((int) $payment->status, [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED], true));
    }

    private function resolveInvoiceDisplayStatus(Invoice $invoice, ?array $paymentSummary): array
    {
        if (($paymentSummary['status'] ?? null) === PaymentStatus::REFUNDED || (int) ($invoice->order?->status ?? -1) === OrderStatus::REFUNDED) {
            return [
                'status' => 5,
                'status_label' => '已退款',
            ];
        }

        return [
            'status' => (int) $invoice->status,
            'status_label' => InvoiceStatus::$labels[$invoice->status] ?? (string) $invoice->status,
        ];
    }

    private function resolveInvoiceRefundActions(Invoice $invoice, ?array $paymentSummary): array
    {
        $paymentGateway = (string) ($paymentSummary['gateway'] ?? '');
        $order = $invoice->order;
        $blockedReason = '';
        $originalBlockedReason = '';
        $canBalance = false;
        $canOriginal = false;

        if ((int) $invoice->status !== InvoiceStatus::PAID) {
            $blockedReason = '仅已支付账单支持退款';
        } elseif (! is_array($paymentSummary)) {
            $blockedReason = '未找到可退款的支付记录';
        } elseif (($paymentSummary['status'] ?? null) === PaymentStatus::REFUNDED || (int) ($order?->status ?? -1) === OrderStatus::REFUNDED) {
            $blockedReason = '该账单已完成退款';
        } elseif (
            $order
            && (
                (int) ($order->service_id ?? 0) > 0
                || in_array((int) $order->status, [OrderStatus::PROCESSING, OrderStatus::COMPLETED], true)
            )
        ) {
            $blockedReason = '订单已进入服务流程，请先处理业务资源后再退款';
        } elseif ($order && (int) $order->status !== OrderStatus::PAID) {
            $blockedReason = '当前订单状态不支持退款';
        } else {
            $canBalance = true;
            $canOriginal = in_array($paymentGateway, ['alipay', 'balance'], true);

            if (! $canOriginal) {
                $originalBlockedReason = '当前支付方式不支持原路退款';
            } elseif ($paymentGateway === 'alipay' && ! $order) {
                $canOriginal = false;
                $originalBlockedReason = '账单未关联订单，当前不支持支付宝原路退款';
            }
        }

        return [
            'can_balance' => $canBalance,
            'can_original' => $canOriginal,
            'blocked_reason' => $blockedReason,
            'original_blocked_reason' => $originalBlockedReason,
        ];
    }

    private function resolvePaymentStatusLabel(int $status): string
    {
        return match ($status) {
            PaymentStatus::SUCCESS => '已支付',
            PaymentStatus::FAILED => '失败',
            PaymentStatus::REFUNDED => '已退款',
            default => '未支付',
        };
    }

    private function resolveInvoiceTypeLabel(string $type, Invoice $invoice): string
    {
        return match ($type) {
            'renew' => '续费账单',
            'manual' => '手工账单',
            default => ($invoice->order?->display_product_name ?? '') !== '' ? '产品' : '普通账单',
        };
    }

    private function resolveInvoiceItemDescription(Invoice $invoice): string
    {
        $productName = trim((string) ($invoice->order?->display_product_name ?? ''));
        $billingCycle = trim((string) ($invoice->order?->billing_cycle ?? ''));
        $typeLabel = $this->resolveInvoiceTypeLabel((string) $invoice->type, $invoice);

        if ($productName === '') {
            return $typeLabel;
        }

        if ($billingCycle === '') {
            return $productName;
        }

        return "{$productName} / {$billingCycle}";
    }

    private function resolveInvoiceLogs(Invoice $invoice): array
    {
        if (! $invoice->order_id) {
            return [];
        }

        return OperationLog::query()
            ->where('module', 'order')
            ->where('subject_id', $invoice->order_id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (OperationLog $log) => [
                'id' => (int) $log->id,
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
                'action' => $log->action,
                'detail' => $this->stringifyOperationDetail((array) ($log->detail ?? [])),
                'ip_address' => $log->ip_address,
            ])
            ->values()
            ->all();
    }

    private function stringifyOperationDetail(array $detail): string
    {
        if ($detail === []) {
            return '-';
        }

        $pairs = [];

        foreach ($detail as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $value = '';
            }

            $pairs[] = "{$key}: {$value}";
        }

        return implode(' | ', $pairs);
    }
}
