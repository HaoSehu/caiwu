<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\FinanceLedgerEventType;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\FinanceLedgerEntry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\RechargeRecord;
use App\Models\User;
use App\Services\Finance\Core\Data\FinanceOperationResult;
use App\Services\Finance\Core\FinanceAccountService;
use App\Services\Finance\Core\FinanceIdempotencyService;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use App\Services\Integrations\Payments\PaymentGatewayOperationService;
use App\Services\Integrations\Plugins\PaymentGatewayBindingResolver;
use App\Services\System\ActivityLogService;
use App\Services\System\OperationLogService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 充值流 + 管理员手工余额调整：
 * 网关充值下单（复用同上下文 PENDING 单）、轮询/回调到账（加余额 + RECHARGE 台账
 * + 充值账单 + 充值凭证四件套）、过期充值单取消与账单补投影、管理员充值/扣费（幂等键）。
 * 网关回调入口（handleGatewayNotify）按 invoice_id 有无分派到本服务的到账逻辑。
 */
class RechargePaymentService
{
    use PaymentGatewayFlow;

    private ?PaymentGatewayOperationService $gatewayOperations = null;

    public function __construct(
        private PaymentGatewayManager $paymentGatewayManager,
        private CheckoutSecurityService $checkoutSecurityService,
        private InvoiceService $invoiceService,
        private FinanceAccountService $accounts,
        private FinanceDocumentService $financeDocumentService,
        private FinanceIdempotencyService $idempotency,
        private PaymentCallbackProjector $callbackProjector,
        private ActivityLogService $activityLogService,
        private ?PaymentGatewayBindingResolver $paymentGatewayBindingResolver = null,
    ) {}

    /**
     * 管理员余额调整（正数充值 / 负数扣费），幂等键必填、10 分钟占位。
     *
     * @return array<string, mixed>
     */
    public function adjustBalance(User $user, float $amount, string $remark = '管理员手动调整', array $context = []): array
    {
        throw_if($amount == 0, new BusinessException('调整金额不能为 0'));

        $idempotencyKey = trim((string) ($context['idempotency_key'] ?? ''));
        throw_if($idempotencyKey === '', new BusinessException('余额调整缺少幂等键（idempotency_key），已拒绝执行'));

        $operatorId = (int) ($context['operator_id'] ?? 0);
        $amountCents = Money::toCents($amount);
        $requestKey = 'balance-adjust:'.$user->id.':'.$idempotencyKey;

        try {
            $result = $this->idempotency->execute(
                'balance_adjust',
                'admin',
                $operatorId > 0 ? $operatorId : 0,
                $requestKey,
                [
                    'user_id' => (int) $user->id,
                    'amount' => Money::formatCents($amountCents),
                    'remark' => $remark,
                ],
                fn (): array => $this->applyBalanceAdjustment($user, $amount, $remark, $context),
                ['resource_type' => 'user', 'resource_id' => (int) $user->id],
            );
        } catch (BusinessException $exception) {
            // 幂等命中留痕：区分「已入账重放」与「误重试」，不动资金
            if (str_contains($exception->getMessage(), '幂等键') || str_contains($exception->getMessage(), '正在处理中')) {
                app(OperationLogService::class)->write(
                    userId: $operatorId ?: null,
                    userType: 'admin',
                    action: 'balance.adjust.duplicate_blocked',
                    module: 'finance',
                    targetId: (int) $user->id,
                    detail: [
                        'user_id' => (int) $user->id,
                        'idempotency_key' => $idempotencyKey,
                        'amount' => Money::formatCents($amountCents),
                        'operator_name' => trim((string) ($context['operator_name'] ?? '')),
                        'trace_id' => trim((string) ($context['trace_id'] ?? '')),
                    ],
                    ipAddress: (string) ($context['ip_address'] ?? '') ?: null,
                );
            }

            throw $exception;
        }

        return $this->finishBalanceAdjustment($user, $amount, $result, $context, $idempotencyKey);
    }

    /**
     * 余额调整的业务事务体：入账 + 账单 + 充值凭证。由幂等外壳在业务事务外调用。
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function applyBalanceAdjustment(User $user, float $amount, string $remark, array $context): array
    {
        $amountCents = Money::toCents($amount);

        return DB::transaction(function () use ($user, $amount, $remark, $context, $amountCents): array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $currentCents = $this->accounts->balanceCents((int) $lockedUser->id, true);

            throw_if($currentCents + $amountCents < 0, new BusinessException(
                '扣减后余额不足，当前余额 ¥'.number_format(Money::fromCents($currentCents), 2).'，扣减 ¥'.number_format(abs($amount), 2)
            ));

            $eventType = $amountCents > 0
                ? FinanceLedgerEventType::MANUAL_RECHARGE
                : FinanceLedgerEventType::MANUAL_DEDUCTION;
            $mutation = $amountCents > 0
                ? $this->accounts->credit((int) $lockedUser->id, $amountCents, $eventType, [
                    'source_type' => 'manual_adjustment',
                    'source_id' => (int) $lockedUser->id,
                    'remark' => $remark,
                    'operator_type' => (string) ($context['operator_type'] ?? 'admin'),
                    'operator_id' => $context['operator_id'] ?? null,
                    'operator_name' => trim((string) ($context['operator_name'] ?? '')),
                    'trace_id' => trim((string) ($context['trace_id'] ?? '')),
                ])
                : $this->accounts->debit((int) $lockedUser->id, -$amountCents, $eventType, [
                    'source_type' => 'manual_adjustment',
                    'source_id' => (int) $lockedUser->id,
                    'remark' => $remark,
                    'operator_type' => (string) ($context['operator_type'] ?? 'admin'),
                    'operator_id' => $context['operator_id'] ?? null,
                    'operator_name' => trim((string) ($context['operator_name'] ?? '')),
                    'trace_id' => trim((string) ($context['trace_id'] ?? '')),
                ]);
            $transaction = FinanceLedgerEntry::query()->findOrFail($mutation->entryId);

            $operatorName = trim((string) ($context['operator_name'] ?? $context['operator'] ?? ''));
            $invoice = $amount > 0
                ? $this->invoiceService->createForRecharge($lockedUser, abs($amount), null, $remark, trim((string) ($context['trace_id'] ?? '')))
                : $this->invoiceService->createForDeduction($lockedUser, abs($amount), $remark, trim((string) ($context['trace_id'] ?? '')));

            $invoice->forceFill([
                'remark' => $remark,
                'operator' => $operatorName !== '' ? $operatorName : null,
            ])->save();

            $rechargeRecord = null;
            if ($amount > 0) {
                $rechargeRecord = $this->financeDocumentService->recordRecharge(
                    $invoice,
                    null,
                    $transaction,
                    'admin_recharge',
                    [
                        'record_remark' => '管理员手工充值',
                        'operator_type' => (string) ($context['operator_type'] ?? 'admin'),
                        'operator_id' => $context['operator_id'] ?? null,
                        'operator_name' => $operatorName,
                        'trace_id' => (string) ($context['trace_id'] ?? ''),
                    ],
                );
            }

            return [
                'invoice' => $invoice->fresh(),
                'transaction' => $transaction,
                'recharge_record' => $rechargeRecord,
            ];
        });
    }

    /**
     * 余额调整成功后的审计收尾：重放命中时不重复写审计。
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function finishBalanceAdjustment(User $user, float $amount, FinanceOperationResult $result, array $context, string $idempotencyKey): array
    {
        $payload = $result->payload;

        if ($result->replayed) {
            return $payload;
        }

        // S1-3：余额调整成功路径补 activity_logs 审计行（写入失败不阻断已完成的资金调整）。
        $invoice = $payload['invoice'] ?? null;
        $rechargeRecord = $payload['recharge_record'] ?? null;

        $this->activityLogService->logSafely(
            'finance',
            'balance.adjust.completed',
            sprintf('管理员余额%s成功：¥%s', $amount > 0 ? '增加' : '扣减', number_format(abs($amount), 2, '.', '')),
            'user',
            (int) $user->id,
            array_filter([
                'amount' => number_format($amount, 2, '.', ''),
                'operator_name' => trim((string) ($context['operator_name'] ?? $context['operator'] ?? '')),
                'invoice_no' => $invoice instanceof Invoice ? (string) $invoice->invoice_no : '',
                'recharge_record_id' => $rechargeRecord instanceof RechargeRecord ? (int) $rechargeRecord->id : null,
                'idempotency_key' => $idempotencyKey,
                'trace_id' => trim((string) ($context['trace_id'] ?? '')),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        );

        return $payload;
    }

    /**
     * 第三方网关充值（通用入口）
     *
     * @return array<string, mixed>
     */
    public function rechargeByGateway(User $user, float $amount, string $gateway, array $context = []): array
    {
        $traceId = $this->resolveTraceId($context, "{$gateway}:user:{$user->id}:".now()->format('YmdHis'));
        $this->assertVerifiedUser($user);

        $resolvedGateway = $this->resolveGateway($gateway);

        throw_if(! $resolvedGateway->isEnabled(), new BusinessException(
            PaymentGatewayCode::label($gateway).'支付未启用'
        ));
        throw_if($amount < 1, new BusinessException('充值金额不能小于 1 元'));
        throw_if($amount > 50000, new BusinessException('单笔充值不能超过 50000 元'));

        $normalizedAmount = round($amount, 2);
        $gatewayContext = $this->rechargeGatewayContext($context);
        $lockKey = "lock:recharge:create:{$user->id}:".md5(implode('|', [
            $gateway,
            $this->rechargeGatewayContextKey($gatewayContext),
            number_format($normalizedAmount, 2, '.', ''),
        ]));

        $payment = $this->withLock($lockKey, 20, function () use ($user, $normalizedAmount, $traceId, $gateway, $gatewayContext) {
            return DB::transaction(function () use ($user, $normalizedAmount, $traceId, $gateway, $gatewayContext) {
                $payment = Payment::query()
                    ->where('user_id', $user->id)
                    ->whereNull('invoice_id')
                    ->whereGatewayKey($gateway)
                    ->where('status', PaymentStatus::PENDING)
                    ->where('amount', $normalizedAmount)
                    ->where('created_at', '>=', now()->subSeconds(CheckoutSecurityService::paymentSessionTtlSeconds()))
                    ->lockForUpdate()
                    ->latest('id')
                    ->get()
                    ->first(fn (Payment $payment): bool => $this->rechargePaymentMatchesGatewayContext($payment, $gatewayContext));

                if ($payment) {
                    $this->ensurePaymentGatewayAudit($payment, $gateway, $traceId);
                    // 复用旧单在事务内补齐网关上下文：崩溃后 payment_context 缺失会绕过复用匹配造成重复建单
                    $payment->forceFill([
                        'callback_raw' => array_merge(
                            (array) ($payment->callback_raw ?? []),
                            $this->rechargeGatewayCallbackRaw($gatewayContext)
                        ),
                    ])->save();

                    return $payment;
                }

                return Payment::query()->create($this->paymentCreatePayload([
                    'payment_no' => Payment::generatePaymentNo(),
                    'user_id' => $user->id,
                    'invoice_id' => null,
                    'gateway' => $gateway,
                    'payment_type' => trim((string) ($gatewayContext['payment_type'] ?? '')),
                    'amount' => $normalizedAmount,
                    'status' => PaymentStatus::PENDING,
                    'callback_raw' => $this->rechargeGatewayCallbackRaw($gatewayContext),
                    'trace_id' => $traceId,
                ]));
            });
        }, '充值请求处理中，请勿重复提交');

        $subject = config('app.name', 'IDC').' - 账户充值 ¥'.number_format($normalizedAmount, 2, '.', '');
        $result = $this->precreateGatewayPayment(
            $gateway,
            $payment->payment_no,
            $normalizedAmount,
            $subject,
            $this->resolvePaymentTimeoutExpress($payment),
            $context
        );

        $payment->forceFill([
            'callback_raw' => array_merge((array) ($payment->callback_raw ?? []), [
                'source' => "{$gateway}_recharge_precreate",
                'trace_id' => $traceId,
            ], $this->rechargeGatewayCallbackRaw($gatewayContext)),
        ])->save();
        $this->callbackProjector->syncProjection($payment);

        return [
            'payment_no' => $payment->payment_no,
            'qr_code' => $result['qr_code'],
            'amount' => number_format($normalizedAmount, 2, '.', ''),
        ];
    }

    /**
     * 轮询充值支付状态
     *
     * @return array<string, mixed>
     */
    public function queryRechargeStatus(Payment $payment): array
    {
        if ((int) $payment->status === PaymentStatus::SUCCESS) {
            $this->ensureRechargeInvoiceProjection($payment);

            return ['paid' => true, 'trade_no' => $payment->trade_no];
        }

        $gateway = $payment->gatewayKey();
        $result = $this->queryGatewayPayment($gateway, $payment->payment_no);

        if (in_array($result['trade_status'], ['TRADE_SUCCESS', 'TRADE_FINISHED', 'SUCCESS'], true)) {
            // 金额校验：轮询路径不经过签名验证，必须与异步通知同级的金额比对，防止异常金额入账
            $queryAmount = round((float) ($result['total_amount'] ?? 0), 2);
            $expectedAmount = round((float) $payment->amount, 2);
            if ($queryAmount <= 0 || ! Money::equals($queryAmount, $expectedAmount)) {
                Log::warning('[充值主动查询] 金额校验失败，拒绝入账', [
                    'payment_no' => $payment->payment_no,
                    'expected' => $expectedAmount,
                    'query' => $queryAmount,
                ]);

                return [
                    'paid' => false,
                    'trade_status' => $result['trade_status'],
                    'status' => (int) $payment->status,
                    'status_label' => PaymentStatus::$labels[(int) $payment->status] ?? '未知',
                ];
            }

            $this->completeRechargePayment($payment, $result['trade_no'], $result['raw']);

            return ['paid' => true, 'trade_no' => $result['trade_no']];
        }

        $payment = $this->cancelExpiredPendingRecharge($payment, [
            'reason' => 'payment_window_expired',
            'actor_name' => 'recharge-status-poll',
        ]);

        return [
            'paid' => false,
            'trade_status' => $result['trade_status'],
            'status' => (int) $payment->status,
            'status_label' => PaymentStatus::$labels[(int) $payment->status] ?? '未知',
        ];
    }

    public function cancelExpiredPendingRecharge(Payment $payment, array $context = []): Payment
    {
        return DB::transaction(function () use ($payment, $context): Payment {
            $lockedPayment = Payment::query()
                ->lockForUpdate()
                ->findOrFail((int) $payment->id);

            if ((int) ($lockedPayment->invoice_id ?? 0) > 0 || (int) $lockedPayment->status !== PaymentStatus::PENDING) {
                return $lockedPayment;
            }

            // 仅未过期的支付单不允许取消；已过期的继续走取消流程
            if ($this->checkoutSecurityService->paymentRecordExpiresAt($lockedPayment)->greaterThan(CarbonImmutable::now())) {
                return $lockedPayment;
            }

            $callbackRaw = (array) ($lockedPayment->callback_raw ?? []);
            $callbackRaw['closed_reason'] = (string) ($context['reason'] ?? 'payment_window_expired');
            $callbackRaw['closed_by'] = (string) ($context['actor_type'] ?? 'system');
            $callbackRaw['closed_at'] = now()->toDateTimeString();
            $callbackRaw['trace_id'] = (string) ($context['trace_id'] ?? ($lockedPayment->trace_id ?? ''));
            $callbackRaw['payment_window_expired'] = true;

            $lockedPayment->forceFill([
                'status' => PaymentStatus::CANCELLED,
                'callback_raw' => $callbackRaw,
            ])->save();
            $this->callbackProjector->syncProjection($lockedPayment);

            // S1-3：充值取消成功路径补 activity_logs 审计行（写入失败不阻断取消动作本身）。
            $this->activityLogService->logSystemSafely(
                'finance',
                'recharge.cancelled',
                '充值单超时取消：'.(string) $lockedPayment->payment_no,
                'payment',
                (int) $lockedPayment->id,
                [
                    'payment_no' => (string) $lockedPayment->payment_no,
                    'user_id' => (int) $lockedPayment->user_id,
                    'amount' => number_format((float) $lockedPayment->amount, 2, '.', ''),
                    'closed_reason' => (string) ($context['reason'] ?? 'payment_window_expired'),
                    'trace_id' => (string) ($context['trace_id'] ?? ($lockedPayment->trace_id ?? '')),
                ],
            );

            return $lockedPayment;
        });
    }

    public function cancelExpiredPendingRechargesForUser(int $userId, array $context = []): int
    {
        $threshold = now()->subSeconds(CheckoutSecurityService::paymentSessionTtlSeconds());
        $count = 0;

        // 与订单侧清理（OrderService::cancelExpiredPendingOrdersForUser）一致按主键分批，
        // 避免积压场景（调度停摆、批量异常单）一次性把全部过期充值单装载进内存。
        Payment::query()
            ->where('user_id', $userId)
            ->whereNull('invoice_id')
            ->where('status', PaymentStatus::PENDING)
            ->where('created_at', '<=', $threshold)
            ->chunkById(100, function ($payments) use (&$count, $context): void {
                foreach ($payments as $payment) {
                    $updated = $this->cancelExpiredPendingRecharge($payment, $context);
                    if ((int) $updated->status === PaymentStatus::CANCELLED) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    /**
     * 完成充值到账：加余额 + RECHARGE 台账 + 充值账单 + 充值凭证四件套。
     * 网关回调与主动轮询两条通道共用；幂等由支付单状态机保证。
     */
    public function completeRechargePayment(Payment $payment, string $tradeNo, array $raw = []): void
    {
        $lockKey = "lock:recharge:payment:{$payment->id}";

        Cache::lock($lockKey, 30)->block(5, function () use ($payment, $tradeNo, $raw) {
            DB::transaction(function () use ($payment, $tradeNo, $raw) {
                $lockedPayment = Payment::query()
                    ->lockForUpdate()
                    ->findOrFail($payment->id);

                if ((int) $lockedPayment->status === PaymentStatus::SUCCESS) {
                    if (! $lockedPayment->invoice_id) {
                        $user = User::query()->lockForUpdate()->findOrFail($lockedPayment->user_id);
                        $this->invoiceService->createForRecharge($user, (float) $lockedPayment->amount, $lockedPayment, null, (string) ($lockedPayment->trace_id ?? ''));
                    }

                    return;
                }

                $traceId = $this->resolveTraceId(
                    ['trace_id' => $lockedPayment->trace_id],
                    'recharge:payment:'.$lockedPayment->id
                );
                $callbackRaw = $raw;
                $callbackRaw['trace_id'] = $traceId;

                $this->callbackProjector->recordPaymentCallback($lockedPayment, 'payment', $callbackRaw, true, $tradeNo, $traceId);

                $lockedPayment->forceFill([
                    'trade_no' => $tradeNo,
                    'status' => PaymentStatus::SUCCESS,
                    'callback_raw' => $callbackRaw,
                    'paid_at' => now(),
                    'trace_id' => $traceId,
                ])->save();
                $this->callbackProjector->syncProjection($lockedPayment);

                $user = User::query()->lockForUpdate()->findOrFail($lockedPayment->user_id);
                $mutation = $this->accounts->credit(
                    (int) $user->id,
                    Money::toCents((float) $lockedPayment->amount),
                    FinanceLedgerEventType::RECHARGE,
                    [
                        'source_type' => 'payment',
                        'source_id' => (int) $lockedPayment->id,
                        // 充值单可属任意第三方网关，备注使用中性文案，不硬编码具体渠道
                        'remark' => '账户充值 '.(string) $lockedPayment->payment_no,
                        'operator_type' => 'system',
                        'trace_id' => (string) ($lockedPayment->trace_id ?? ''),
                    ],
                );
                $transaction = FinanceLedgerEntry::query()->findOrFail($mutation->entryId);

                $invoice = $this->invoiceService->createForRecharge(
                    $user,
                    (float) $lockedPayment->amount,
                    $lockedPayment,
                    null,
                    (string) ($lockedPayment->trace_id ?? ''),
                );
                $this->financeDocumentService->recordRecharge(
                    $invoice,
                    $lockedPayment,
                    $transaction,
                    'user_recharge',
                    [
                        'trace_id' => (string) ($lockedPayment->trace_id ?? ''),
                    ],
                );
            });
        });
    }

    /**
     * 已 SUCCESS 的充值单补齐缺失的充值账单投影（锁 + 幂等）。
     */
    public function ensureRechargeInvoiceProjection(Payment $payment): ?Invoice
    {
        if ((int) ($payment->invoice_id ?? 0) > 0) {
            return Invoice::query()->find((int) $payment->invoice_id);
        }

        if ((int) $payment->status !== PaymentStatus::SUCCESS || ! $payment->isThirdPartyGateway()) {
            return null;
        }

        $lockKey = "lock:recharge:payment:{$payment->id}";

        return Cache::lock($lockKey, 30)->block(5, function () use ($payment) {
            return DB::transaction(function () use ($payment) {
                $lockedPayment = Payment::query()
                    ->lockForUpdate()
                    ->findOrFail($payment->id);

                if ((int) ($lockedPayment->invoice_id ?? 0) > 0) {
                    return Invoice::query()->find((int) $lockedPayment->invoice_id);
                }

                if ((int) $lockedPayment->status !== PaymentStatus::SUCCESS || ! $lockedPayment->isThirdPartyGateway()) {
                    return null;
                }

                $user = User::query()->lockForUpdate()->findOrFail($lockedPayment->user_id);

                return $this->invoiceService->createForRecharge($user, (float) $lockedPayment->amount, $lockedPayment, null, (string) ($lockedPayment->trace_id ?? ''));
            });
        });
    }

    /**
     * @return array<string, string>
     */
    private function rechargeGatewayContext(array $context): array
    {
        $paymentType = trim((string) ($context['payment_type'] ?? ''));

        return $paymentType !== '' ? ['payment_type' => $paymentType] : [];
    }

    /**
     * @param  array<string, string>  $gatewayContext
     */
    private function rechargeGatewayContextKey(array $gatewayContext): string
    {
        if ($gatewayContext === []) {
            return 'default';
        }

        ksort($gatewayContext, SORT_STRING);

        return md5(json_encode($gatewayContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
    }

    /**
     * @param  array<string, string>  $gatewayContext
     */
    private function rechargePaymentMatchesGatewayContext(Payment $payment, array $gatewayContext): bool
    {
        $callbackRaw = (array) ($payment->callback_raw ?? []);
        $storedContext = $callbackRaw['payment_context'] ?? null;
        $storedContext = is_array($storedContext) ? $storedContext : [];

        return $this->rechargeGatewayContext($storedContext) === $gatewayContext;
    }

    /**
     * @param  array<string, string>  $gatewayContext
     * @return array<string, mixed>
     */
    private function rechargeGatewayCallbackRaw(array $gatewayContext): array
    {
        if ($gatewayContext === []) {
            return [];
        }

        // P-09：通道类型已落在 payments.payment_type 独立列，callback_raw 只保留流程标记；
        // payment_context 仅用于同一网关下「同通道复用旧单」的匹配，不再是通道类型的唯一来源。
        return [
            'payment_context' => $gatewayContext,
        ];
    }

    private function assertVerifiedUser(User $user): void
    {
        throw_if(
            ! $user->hasCompletedVerification(),
            new BusinessException('请先完成实名认证后再继续操作', 40301)
        );
    }
}
