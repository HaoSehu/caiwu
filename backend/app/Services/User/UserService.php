<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\MemberLevel;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\PromotionAmbassador;
use App\Models\RechargeRecord;
use App\Models\ReferralReward;
use App\Models\ReferralWithdrawal;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserReferral;
use App\Services\Automation\ServiceStatusSyncService;
use App\Services\ClientServiceConsole\ClientServiceConsoleService;
use App\Services\Finance\FinanceLedgerQueryService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\TradeLifecycleService;
use App\Services\Provisioning\ProvisionService;
use App\Services\Referral\ReferralService;
use App\Services\System\OperationLogService;
use App\Services\System\SettingService;
use App\Services\User\Concerns\HandlesAdminUserServices;
use App\Support\AccountIdentifier;
use App\Support\SchemaMetadataCache;
use App\Support\TextSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserService
{
    use HandlesAdminUserServices;

    public function __construct(
        private ClientServiceConsoleService $clientServiceConsoleService,
        private ReferralService $referralService,
        private InvoiceService $invoiceService,
        private FinanceLedgerQueryService $financeLedgerQueryService,
        private OperationLogService $operationLogService,
        private ProvisionService $provisionService,
        private ServiceStatusSyncService $serviceStatusSyncService,
        private SettingService $settingService,
        private TradeLifecycleService $tradeLifecycleService,
        private ?AccountService $accountService = null,
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
                'company',
                'qq',
                'member_level_id',
                'verification_status',
                'is_verified',
                'status',
                'created_at',
            ])
            ->withCount([
                'services as opened_product_count' => fn ($serviceQuery) => $serviceQuery->where('status', ServiceStatus::ACTIVE),
            ]);

        if (! empty($filters['user_id'])) {
            $query->where('id', (int) $filters['user_id']);
        }
        if (! empty($filters['keyword'])) {
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
        $phone = AccountIdentifier::normalizeOptionalPhone((string) ($data['phone'] ?? ''));
        $this->assertUniquePhone($phone);

        $user = DB::transaction(function () use ($data, $phone) {
            $initialBalance = number_format((float) ($data['balance'] ?? 0), 2, '.', '');

            $user = User::create([
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $phone,
                'status' => $data['status'] ?? 1,
                'nickname' => TextSanitizer::clean((string) ($data['nickname'] ?? '')),
            ]);

            $this->accounts()->updateAccount($user, [
                'cash_balance' => $initialBalance,
                'credit_limit' => number_format((float) ($data['credit_limit'] ?? 0), 2, '.', ''),
            ]);

            $this->referralService->ensureReferralCode($user);

            return $user;
        });

        return $this->reloadUserReadRelations($user);
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
            // 无原始隐私权限的会话回显脱敏值（如 138****1234）时，任何入口
            // 都不应把 * 剥成残缺号码再入库；含 * 的脱敏值一律视为「未修改」。
            if (str_contains((string) $baseUpdateData['phone'], '*')) {
                unset($baseUpdateData['phone']);
            } else {
                $baseUpdateData['phone'] = AccountIdentifier::normalizeOptionalPhone((string) $baseUpdateData['phone']);
                $this->assertUniquePhone($baseUpdateData['phone'], (int) $user->id);
            }
        }

        $passwordChanged = ! empty($data['password']);
        if ($passwordChanged) {
            $baseUpdateData['password'] = $data['password'];
        }

        $accountUpdateData = [];
        if (array_key_exists('credit_limit', $data)) {
            $accountUpdateData['credit_limit'] = number_format((float) $data['credit_limit'], 2, '.', '');
        }

        DB::transaction(function () use ($user, $baseUpdateData, $accountUpdateData, $passwordChanged) {
            if ($baseUpdateData !== []) {
                $user->update($baseUpdateData);
            }

            if ($passwordChanged) {
                $user->tokens()->delete();
            }

            if ($accountUpdateData !== []) {
                $this->accounts()->updateAccount($user, $accountUpdateData);
                $user->unsetRelation('account');
            }
        });

        return $this->reloadUserReadRelations($user);
    }

    /**
     * 禁用/启用
     */
    public function toggleStatus(User $user): User
    {
        $user->update(['status' => $user->status === 1 ? 0 : 1]);

        return $this->reloadUserReadRelations($user);
    }

    /**
     * 管理员设置用户会员等级（等级为手工配置，null 表示置为未分级）。
     *
     * @param  int|null  $levelId  目标等级 ID；null 表示置为未分级
     */
    public function adjustMemberLevel(User $user, ?int $levelId, array $context = []): User
    {
        $targetLevel = null;
        if ($levelId !== null) {
            $targetLevel = MemberLevel::query()->find($levelId);
            throw_if($targetLevel === null, new BusinessException('目标会员等级不存在'));
        }

        $previousLevelId = (int) ($user->member_level_id ?? 0);

        DB::transaction(function () use ($user, $targetLevel): void {
            // 双写 users 与 user_referrals（若存在），保证投影表与主表一致
            if (SchemaMetadataCache::hasTable('user_referrals')) {
                UserReferral::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['member_level_id' => $targetLevel?->id]
                );
            }

            $user->forceFill([
                'member_level_id' => $targetLevel?->id,
            ])->save();
            $user->unsetRelation('memberLevel');
        });

        $this->operationLogService->write(
            userId: (int) ($context['actor_user_id'] ?? 0) ?: null,
            userType: (string) ($context['actor_type'] ?? 'admin'),
            action: 'admin.user.member_level_adjust',
            module: 'user',
            targetId: (int) $user->id,
            detail: [
                'from_member_level_id' => $previousLevelId ?: null,
                'to_member_level_id' => $targetLevel?->id,
                'to_member_level_name' => $targetLevel?->name,
                'actor_name' => (string) ($context['actor_name'] ?? ''),
                'trace_id' => (string) ($context['trace_id'] ?? ''),
            ],
            ipAddress: ($context['ip_address'] ?? null) ? (string) $context['ip_address'] : null,
        );

        return $this->reloadUserReadRelations($user);
    }

    /**
     * 管理员指派用户推广大使档位（null 表示置为未指派，返利按全局配置兜底）。
     *
     * @param  int|null  $ambassadorId  目标大使档位 ID；null 表示置为未指派
     */
    public function adjustPromotionAmbassador(User $user, ?int $ambassadorId, array $context = []): User
    {
        $targetAmbassador = null;
        if ($ambassadorId !== null) {
            $targetAmbassador = PromotionAmbassador::query()->find($ambassadorId);
            throw_if($targetAmbassador === null, new BusinessException('目标推广大使档位不存在'));
        }

        $previousAmbassadorId = (int) ($user->promotion_ambassador_id ?? 0);

        $user->forceFill([
            'promotion_ambassador_id' => $targetAmbassador?->id,
        ])->save();
        $user->unsetRelation('promotionAmbassador');

        $this->operationLogService->write(
            userId: (int) ($context['actor_user_id'] ?? 0) ?: null,
            userType: (string) ($context['actor_type'] ?? 'admin'),
            action: 'admin.user.promotion_ambassador_adjust',
            module: 'user',
            targetId: (int) $user->id,
            detail: [
                'from_promotion_ambassador_id' => $previousAmbassadorId ?: null,
                'to_promotion_ambassador_id' => $targetAmbassador?->id,
                'to_promotion_ambassador_name' => $targetAmbassador?->name,
                'actor_name' => (string) ($context['actor_name'] ?? ''),
                'trace_id' => (string) ($context['trace_id'] ?? ''),
            ],
            ipAddress: ($context['ip_address'] ?? null) ? (string) $context['ip_address'] : null,
        );

        return $this->reloadUserReadRelations($user);
    }

    /**
     * 删除用户（资产保护）：
     * 仅当无在用服务、无未付账单、账户余额为 0 时才允许删除，否则拒绝并提示先处理资产。
     */
    public function deleteUser(User $user, array $context = []): void
    {
        $activeServiceCount = Service::query()
            ->where('user_id', (int) $user->id)
            ->whereIn('status', [ServiceStatus::PENDING, ServiceStatus::ACTIVE, ServiceStatus::SUSPENDED])
            ->count();
        throw_if($activeServiceCount > 0, new BusinessException('该用户存在在用服务，请先处理服务后再删除'));

        $unpaidInvoiceCount = Invoice::query()
            ->where('user_id', (int) $user->id)
            ->where('status', InvoiceStatus::UNPAID)
            ->count();
        throw_if($unpaidInvoiceCount > 0, new BusinessException('该用户存在未付账单，请先处理账单后再删除'));

        $balance = (float) $user->balance;
        throw_if($balance != 0, new BusinessException('该用户账户仍有余额，请先清零后再删除'));

        // 事务内软删：唯一键释放（ReleasesUniqueKeysOnDelete）与 deleted_at 写入原子
        DB::transaction(function () use ($user): void {
            $user->delete();
        });

        $this->operationLogService->write(
            userId: ((int) ($context['operator_id'] ?? 0)) ?: null,
            userType: 'admin',
            action: 'user.deleted',
            module: 'user',
            targetId: (int) $user->id,
            detail: [
                'email' => (string) $user->email,
                'nickname' => (string) $user->nickname,
                'operator_name' => (string) ($context['operator_name'] ?? ''),
                'trace_id' => (string) ($context['trace_id'] ?? ''),
            ],
            ipAddress: (string) ($context['ip_address'] ?? ''),
        );
    }

    /**
     * 用户详情（含完整统计）
     */
    public function detail(User $user): array
    {
        $user->loadMissing([
            'memberLevel',
            'promotionAmbassador',
            'account',
        ]);
        $memberLevel = $user->memberLevel;
        $promotionAmbassador = $user->promotionAmbassador;
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
        $orderTotal = (int) ($countStats?->order_total ?? 0);
        $orderPending = (int) ($countStats?->order_pending ?? 0);
        $invoiceUnpaid = (int) ($countStats?->invoice_unpaid ?? 0);
        $invoicePaid = (int) ($countStats?->invoice_paid ?? 0);

        if ($orderTotal === 0 && ($invoiceUnpaid > 0 || $invoicePaid > 0)) {
            $orderTotal = $invoiceUnpaid + $invoicePaid;
            $orderPending = $invoiceUnpaid;
        }

        $balanceSummary = $this->financeLedgerQueryService->summaryForClient($user, []);
        $invoiceSummary = Invoice::query()
            ->where('user_id', $user->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 0 THEN amount ELSE 0 END), 0) as unpaid_amount')
            ->first();
        $referralSummary = ReferralReward::query()
            ->where('referrer_user_id', $user->id)
            ->selectRaw('COUNT(*) as rewarded_orders_count')
            ->selectRaw('COALESCE(SUM(reward_amount), 0) as total_reward_amount')
            ->first();
        $directReferralCount = $this->referralService->directReferralCount((int) $user->id);
        $recentReferrals = $this->referralService->recentDirectReferrals((int) $user->id, 8, true);
        $withdrawSummary = ReferralWithdrawal::query()
            ->where('user_id', $user->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 0 THEN amount ELSE 0 END), 0) as withdrawing_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 1 THEN amount ELSE 0 END), 0) as withdrawn_amount')
            ->first();

        return [
            'user' => $user,
            'stats' => [
                'service_active' => (int) ($countStats?->service_active ?? 0),
                'service_total' => (int) ($countStats?->service_total ?? 0),
                'order_total' => $orderTotal,
                'order_pending' => $orderPending,
                'total_income' => (float) ($balanceSummary['total_in'] ?? 0),
                'total_expense' => (float) ($balanceSummary['total_out'] ?? 0),
                'unpaid_amount' => (float) ($invoiceSummary?->unpaid_amount ?? 0),
                'ticket_open' => (int) ($countStats?->ticket_open ?? 0),
                'ticket_closed' => (int) ($countStats?->ticket_closed ?? 0),
                'ticket_total' => (int) ($countStats?->ticket_total ?? 0),
                'invoice_unpaid' => $invoiceUnpaid,
                'invoice_paid' => $invoicePaid,
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
                ] : null,
                'promotion_ambassador' => $promotionAmbassador ? [
                    'id' => $promotionAmbassador->id,
                    'name' => $promotionAmbassador->name,
                    'reward_rate' => (float) $promotionAmbassador->reward_rate,
                    'renewal_reward_rate' => (float) $promotionAmbassador->renewal_reward_rate,
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
            'order:id,order_no,status,type,service_id,paid_at,product_id,billing_cycle',
            'order.product:id,product_type,service_type_code,product_group_id,config_options,purchase_requires',
            'product:id,product_type,service_type_code,product_group_id,config_options,purchase_requires',
            'service:id,name,status,expires_at',
            'payments',
            'items',
        ]);

        if (($filters['status'] ?? '') === '5') {
            $query->where(function ($builder) {
                $builder->whereHas('payments', fn ($paymentQuery) => $paymentQuery
                    ->whereGatewayKeyIn(PaymentGatewayCode::thirdPartyGateways())
                    ->where('status', PaymentStatus::REFUNDED))
                    ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('status', OrderStatus::REFUNDED));
            });
        } elseif (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $type = (string) $filters['type'];
            if (in_array($type, ['new', 'normal'], true)) {
                $query->whereIn('type', ['new', 'normal']);
            } else {
                $query->where('type', $type);
            }
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Invoice $invoice) => $this->transformInvoiceListItem($invoice))
        );

        return $paginator;
    }

    public function invoiceDetail(User $user, int $invoiceId): array
    {
        $this->findUserInvoice($user, $invoiceId);

        return $this->invoiceService->adminDetail($invoiceId);
    }

    /**
     * 用户充值记录：recharge_record 净额流水（含手工充值入账与退款冲抵负数行）。
     *
     * @param  array<string, mixed>  $filters
     */
    public function rechargeRecords(User $user, array $filters, int $perPage = 20)
    {
        $query = RechargeRecord::query()
            ->with(['invoice:id,invoice_no'])
            ->where('user_id', (int) $user->id);

        $direction = trim((string) ($filters['direction'] ?? ''));
        if ($direction !== '') {
            $query->where('direction', $direction);
        }

        return $query->orderByDesc('id')->paginate($perPage);
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
                'this_year' => (int) ($summary?->this_year ?? 0),
                'last_year' => (int) ($summary?->last_year ?? 0),
            ],
        ];
    }

    /**
     * 用户操作日志
     */
    public function operationLogs(int $userId, array $filters, int $perPage = 20)
    {
        $query = ActivityLog::where('actor_id', $userId)->where('actor_type', 'client');

        if (! empty($filters['keyword'])) {
            $query->where('action', 'like', "%{$filters['keyword']}%");
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (! empty($filters['ip_address'])) {
            $query->where('ip_address', 'like', "%{$filters['ip_address']}%");
        }

        if (! empty($filters['source'])) {
            if ($filters['source'] === 'api') {
                $query->whereNotNull('context->method');
            } else {
                $query->whereNull('context->method');
            }
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * 用户短信日志
     */
    public function smsLogs(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $phone = trim((string) $user->phone);
        if ($phone === '') {
            return $this->emptyPaginator($perPage);
        }

        $query = $this->buildUserSmsLogQuery($phone);
        if ($query === null) {
            return $this->emptyPaginator($perPage);
        }

        $paginator = $query
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $paginator->setCollection(
            $paginator->getCollection()->map(function ($log) {
                $item = $log->toArray();
                $item['params_json'] = $this->normalizeNotificationParams($item['params_json'] ?? []);

                return $item;
            })
        );

        return $paginator;
    }

    /**
     * 用户邮件日志
     */
    public function emailLogs(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $email = trim((string) $user->email);
        if ($email === '') {
            return $this->emptyPaginator($perPage);
        }

        $query = $this->buildUserEmailLogQuery($email);
        if ($query === null) {
            return $this->emptyPaginator($perPage);
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    private function assertUniquePhone(?string $phone, ?int $ignoreUserId = null): void
    {
        if ($phone === null || $phone === '') {
            return;
        }

        $query = User::query()->where('phone', $phone);
        if ($ignoreUserId !== null) {
            $query->where('id', '<>', $ignoreUserId);
        }

        if ($query->exists()) {
            throw new BusinessException('手机号已被注册');
        }
    }

    private function reloadUserReadRelations(User $user): User
    {
        $relations = ['account'];

        return $user->fresh($relations) ?? $user->loadMissing($relations);
    }

    private function accounts(): AccountService
    {
        return $this->accountService ??= app(AccountService::class);
    }

    private function buildUserSmsLogQuery(string $phone): ?Builder
    {
        if (! SchemaMetadataCache::hasTable('message_logs')) {
            return null;
        }

        return MessageLog::query()
            ->where('channel', 'sms')
            ->where('recipient', $phone)
            ->selectRaw('id, recipient as phone, template_code, content, params_json, status, provider, request_id, error_msg, sent_at, created_at, updated_at, origin_type');
    }

    private function buildUserEmailLogQuery(string $email): ?Builder
    {
        if (! SchemaMetadataCache::hasTable('message_logs')) {
            return null;
        }

        return MessageLog::query()
            ->where('channel', 'email')
            ->where('recipient', $email)
            ->selectRaw('id, template_code, recipient as to_email, subject, content, status, error_msg, sent_at, created_at, updated_at');
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage);
    }

    private function normalizeNotificationParams(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function findUserInvoice(User $user, int $invoiceId): Invoice
    {
        return $user->invoices()
            ->whereKey($invoiceId)
            ->firstOrFail();
    }

    private function resolvePaymentGatewayLabel(string $gateway): string
    {
        return match ($gateway) {
            // 与 PaymentGatewayCode::LABELS 一致的词条统一走集中定义，避免多处文案漂移。
            PaymentGatewayCode::ALIPAY,
            PaymentGatewayCode::YIPAY,
            PaymentGatewayCode::WECHAT,
            PaymentGatewayCode::BALANCE => PaymentGatewayCode::label($gateway),
            // 业务侧扩展口径与本地历史文案不属于集中 LABELS，保留原样。
            'bank_transfer' => '银行转账',
            'offline' => '线下支付',
            default => '手动入账',
        };
    }

    private function transformInvoiceListItem(Invoice $invoice): array
    {
        $detail = $this->invoiceService->adminListItem($invoice);
        $paymentSummary = $this->buildInvoicePaymentSummary($invoice);

        return [
            ...$detail,
            'created_at' => (string) ($detail['created_at'] ?? $invoice->created_at?->format('Y-m-d H:i:s')),
            'due_date' => (string) ($detail['due_date'] ?? $invoice->due_date?->format('Y-m-d')),
            'paid_at' => (string) ($detail['paid_at'] ?? $invoice->paid_at?->format('Y-m-d H:i:s')),
            'payment_summary' => $detail['payment_summary'] ?? $paymentSummary,
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
            'gateway' => $payment->gatewayKey(),
            'gateway_key' => $payment->gatewayKey(),
            'gateway_label' => $this->resolvePaymentGatewayLabel($payment->gatewayKey()),
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
        $collection = collect($payments)
            ->filter(fn (Payment $payment) => $payment->isThirdPartyGateway())
            ->values();

        // 已转入余额的异常支付（重复支付/超额支付）不作为主支付单：
        // 其金额已退回用户余额，展示与退款决策均不应再按该支付单处理。
        $isRefundablePayment = fn (Payment $payment): bool => in_array((int) $payment->status, [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED], true)
            && ! (bool) data_get((array) ($payment->callback_raw ?? []), 'credited_to_balance', false);

        return $collection
            ->first(fn (Payment $payment) => $isRefundablePayment($payment)
                && ! (bool) data_get((array) ($payment->callback_raw ?? []), 'duplicate_paid', false))
            ?? $collection->first($isRefundablePayment);
    }

    private function resolveInvoiceDisplayStatus(Invoice $invoice, ?array $paymentSummary): array
    {
        if (($paymentSummary['status'] ?? null) === PaymentStatus::REFUNDED || (int) $invoice->status === InvoiceStatus::REFUNDED) {
            return [
                'status' => InvoiceStatus::REFUNDED,
                'status_label' => '已退款',
            ];
        }

        return [
            'status' => (int) $invoice->status,
            'status_label' => InvoiceStatus::$labels[$invoice->status] ?? (string) $invoice->status,
        ];
    }

    private function resolvePaymentStatusLabel(int $status): string
    {
        return match ($status) {
            PaymentStatus::SUCCESS => '已支付',
            PaymentStatus::REFUNDED => '已退款',
            PaymentStatus::CANCELLED => '已取消',
            default => '未支付',
        };
    }
}
