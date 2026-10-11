<?php

declare(strict_types=1);

namespace App\Services\Service;

use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceOperationLog;
use App\Models\ServiceTransfer;
use App\Models\Setting;
use App\Models\User;
use App\Services\Notification\UserNotificationService;
use App\Services\System\ActivityLogService;
use App\Services\System\NotificationService;
use App\Services\Verification\VerificationGateService;
use App\Support\SchemaMetadataCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * M6 服务转移（用户间过户）核心服务。
 *
 * 状态机：0 待对方确认 → 1 双方已确认待生效 → 2 已完成；
 * 终态：3 已取消（发起方/管理员）、4 已拒绝（接收方/管理员）、5 已过期（调度）。
 * 风险校验双查：发起时快照 + 生效前终查（risk_flags 保存最后一次快照）。
 */
class ServiceTransferService
{
    /** 确认有效期（小时） */
    public const CONFIRM_TTL_HOURS = 72;

    /** 频控：单用户 7 天内发起+接收合计上限 */
    public const RATE_LIMIT_PER_WINDOW = 3;

    /** 频控窗口（天） */
    public const RATE_WINDOW_DAYS = 7;

    /** 未结账单拦截业务错误码（data.error_code） */
    public const ERROR_UNPAID_INVOICES = 'service_transfer_unpaid_invoices';

    public function __construct(
        private readonly UserNotificationService $notifications,
        private readonly NotificationService $mailer,
        private readonly ActivityLogService $activityLog,
        private readonly VerificationGateService $verificationGate,
    ) {}

    /**
     * 发起转移。
     *
     * @return ServiceTransfer 新建（或已有待确认）记录
     *
     * @throws BusinessException
     */
    public function initiate(Service $service, int $toUserId, int $initiatedBy, ?int $operatorId = null): ServiceTransfer
    {
        $fromUserId = (int) $service->user_id;

        if ((int) $service->status !== 1) {
            throw new BusinessException('仅运行中的服务可以发起转移');
        }

        if ($fromUserId === $toUserId) {
            throw new BusinessException('转出方与接收方不能为同一账号');
        }

        $toUser = User::query()->find($toUserId);
        if (! $toUser instanceof User || (int) $toUser->status !== 1) {
            throw new BusinessException('接收方账号不存在或已被禁用');
        }

        // M11 实名限制统一走 VerificationGateService（含 grace_days 与会员等级豁免口径，
        // 与下单/充值/提现一致；settings 缺省或未启用时 Gate 恒放行）。
        if (! $this->verificationGate->allowed($toUser, VerificationGateService::SCOPE_SERVICE_TRANSFER_RECEIVE)) {
            throw new BusinessException('接收方尚未完成实名认证，暂不能接收服务转移', 42200, 422, ['error_code' => 'verification_required']);
        }

        $this->ensureNoPendingTransfer($service);

        $this->ensureRateLimit($fromUserId);
        $this->ensureRateLimit($toUserId);

        $this->ensureNoUnpaidInvoices($service, $fromUserId);

        $now = now();
        $transfer = ServiceTransfer::query()->create([
            'service_id' => (int) $service->id,
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'status' => ServiceTransfer::STATUS_PENDING,
            'initiated_by' => $initiatedBy,
            'from_confirmed_at' => $initiatedBy !== ServiceTransfer::INITIATED_BY_TO ? $now : null,
            'to_confirmed_at' => $initiatedBy === ServiceTransfer::INITIATED_BY_TO ? $now : null,
            'expires_at' => $now->copy()->addHours(self::CONFIRM_TTL_HOURS),
            'risk_flags' => $this->riskFlags($service, $toUserId),
            'operator_id' => $operatorId,
        ]);

        $this->activityLog->logSafely(
            'service_transfer',
            'service_transfer.initiated',
            sprintf('服务 #%d 发起转移：用户 #%d → 用户 #%d', (int) $service->id, $fromUserId, $toUserId),
            ServiceTransfer::class,
            (int) $transfer->id,
            ['service_id' => (int) $service->id, 'from_user_id' => $fromUserId, 'to_user_id' => $toUserId, 'initiated_by' => $initiatedBy]
        );

        $this->notify($transfer, 'service_transfer_requested', '服务转移待确认');

        return $transfer;
    }

    /**
     * 确认转移（接收方确认，或管理员代确认；发起方在发起时已视为确认）。
     */
    public function confirm(ServiceTransfer $transfer, int $userId, bool $isAdmin = false): ServiceTransfer
    {
        $confirmed = DB::transaction(function () use ($transfer, $userId, $isAdmin): ServiceTransfer {
            // TOCTOU 防护：事务内锁行重读最新状态再判定，替代调用方持有的裸内存实例。
            /** @var ServiceTransfer $locked */
            $locked = ServiceTransfer::query()->whereKey((int) $transfer->id)->lockForUpdate()->firstOrFail();

            if (in_array((int) $locked->status, [ServiceTransfer::STATUS_CANCELLED, ServiceTransfer::STATUS_REJECTED, ServiceTransfer::STATUS_EXPIRED], true)) {
                throw new BusinessException('该转移请求已结束，无法确认');
            }

            if ((int) $locked->status === ServiceTransfer::STATUS_COMPLETED) {
                return $locked;
            }

            // 过期校验：与 expireDue 同语义（进行中且 expires_at 已过即拒绝）。
            if ($locked->expires_at->lt(now())) {
                throw new BusinessException('转移确认已过期');
            }

            if (! $isAdmin && ! $this->canConfirm($locked, $userId)) {
                throw new BusinessException('仅待确认方可以确认该转移请求');
            }

            $this->fillPendingConfirmation($locked, $userId, $isAdmin);

            return $locked->refresh();
        });

        if ((int) $confirmed->status === ServiceTransfer::STATUS_COMPLETED) {
            return $confirmed;
        }

        if ((int) $confirmed->status !== ServiceTransfer::STATUS_CONFIRMED) {
            return $confirmed;
        }

        $this->notify($confirmed, 'service_transfer_confirmed', '服务转移已双方确认');

        if ($this->manualReviewEnabled()) {
            $this->activityLog->logSafely(
                'service_transfer',
                'service_transfer.manual_review_pending',
                sprintf('转移 #%d 已双方确认，等待管理员复核生效', (int) $confirmed->id),
                ServiceTransfer::class,
                (int) $confirmed->id,
                ['transfer_id' => (int) $confirmed->id]
            );

            return $confirmed;
        }

        return $this->execute($confirmed);
    }

    /**
     * 生效事务：锁定服务行 → 复核风险项 → 变更归属 → 留痕 → 置完成。
     * 重复调用幂等（已完成直接返回）。
     */
    public function execute(ServiceTransfer $transfer): ServiceTransfer
    {
        if ((int) $transfer->status === ServiceTransfer::STATUS_COMPLETED) {
            return $transfer;
        }

        // 过期校验：与 expireDue 同语义（进行中且 expires_at 已过即拒绝生效）。
        if (in_array((int) $transfer->status, ServiceTransfer::ACTIVE_STATUSES, true)
            && $transfer->expires_at->lt(now())) {
            throw new BusinessException('转移确认已过期');
        }

        $fromUserId = (int) $transfer->from_user_id;
        $toUserId = (int) $transfer->to_user_id;

        $notified = DB::transaction(function () use ($transfer, $fromUserId, $toUserId): ServiceTransfer {
            /** @var Service|null $service */
            $service = Service::query()->whereKey((int) $transfer->service_id)->lockForUpdate()->first();
            if (! $service instanceof Service) {
                throw new BusinessException('目标服务不存在');
            }

            if ((int) $service->status !== 1) {
                throw new BusinessException('目标服务当前状态不允许转移');
            }

            if ((int) $service->user_id !== $fromUserId) {
                throw new BusinessException('服务归属已变更，转移无法继续');
            }

            $this->ensureNoUnpaidInvoices($service, $fromUserId);

            $toUser = User::query()->whereKey($toUserId)->lockForUpdate()->first();
            if (! $toUser instanceof User || (int) $toUser->status !== 1) {
                throw new BusinessException('接收方账号不可用，转移无法生效');
            }

            // 生效前复核接收方实名 Gate（防实名解绑后仍完成过户；双确认到生效存在时间窗）。
            throw_if(
                ! $this->verificationGate->allowed($toUser, VerificationGateService::SCOPE_SERVICE_TRANSFER_RECEIVE),
                new BusinessException('接收方实名状态已不满足，转移无法生效', 42200, 422, ['error_code' => 'verification_required'])
            );

            $this->ensureNoPendingTransfer($service, (int) $transfer->id);

            $service->forceFill(['user_id' => $toUserId])->save();

            $this->writeOperationLog($service, $transfer, $fromUserId, $toUserId);

            $transfer->forceFill([
                'status' => ServiceTransfer::STATUS_COMPLETED,
                'risk_flags' => $this->riskFlags($service->refresh(), $toUserId),
            ])->save();

            $this->activityLog->logSafely(
                'service_transfer',
                'service_transfer.completed',
                sprintf('服务 #%d 转移完成：用户 #%d → 用户 #%d', (int) $service->id, $fromUserId, $toUserId),
                ServiceTransfer::class,
                (int) $transfer->id,
                ['service_id' => (int) $service->id, 'from_user_id' => $fromUserId, 'to_user_id' => $toUserId]
            );

            return $transfer;
        });

        $this->notify($notified, 'service_transfer_completed', '服务转移完成');

        return $notified;
    }

    /**
     * 拒绝转移（接收方或管理员）。
     */
    public function reject(ServiceTransfer $transfer, int $userId, ?string $reason = null, bool $isAdmin = false): ServiceTransfer
    {
        $this->ensureClosable($transfer);

        if (! $isAdmin && (int) $transfer->to_user_id !== $userId) {
            throw new BusinessException('仅接收方可以拒绝该转移请求');
        }

        $transfer->forceFill([
            'status' => ServiceTransfer::STATUS_REJECTED,
            'reject_reason' => $this->normalizeReason($reason),
        ])->save();

        $this->logClosed($transfer, 'rejected', $userId, $isAdmin);
        $this->notify($transfer, 'service_transfer_rejected', '服务转移已拒绝');

        return $transfer;
    }

    /**
     * 取消转移（发起方或管理员）。
     */
    public function cancel(ServiceTransfer $transfer, int $userId, ?string $reason = null, bool $isAdmin = false): ServiceTransfer
    {
        $this->ensureClosable($transfer);

        if (! $isAdmin && ! $this->canCancel($transfer, $userId)) {
            throw new BusinessException('仅发起方可以撤销该转移请求');
        }

        $transfer->forceFill([
            'status' => ServiceTransfer::STATUS_CANCELLED,
            'reject_reason' => $this->normalizeReason($reason),
        ])->save();

        $this->logClosed($transfer, 'cancelled', $userId, $isAdmin);
        $this->notify($transfer, 'service_transfer_rejected', '服务转移已取消');

        return $transfer;
    }

    /**
     * 批量过期：status IN(0,1) 且 expires_at 已过期的记录置 5 并通知双方。
     *
     * @return int 关闭数量
     */
    public function expireDue(Carbon $now): int
    {
        $expired = 0;

        ServiceTransfer::query()
            ->whereIn('status', ServiceTransfer::ACTIVE_STATUSES)
            ->where('expires_at', '<', $now)
            ->orderBy('id')
            ->chunkById(100, function ($transfers) use (&$expired, $now): void {
                foreach ($transfers as $transfer) {
                    // 逐条重查状态，规避与并发确认/生效的竞态。
                    $fresh = ServiceTransfer::query()
                        ->whereKey((int) $transfer->id)
                        ->whereIn('status', ServiceTransfer::ACTIVE_STATUSES)
                        ->where('expires_at', '<', $now)
                        ->first();

                    if (! $fresh instanceof ServiceTransfer) {
                        continue;
                    }

                    $fresh->forceFill(['status' => ServiceTransfer::STATUS_EXPIRED])->save();
                    $expired++;
                    $this->notify($fresh, 'service_transfer_rejected', '服务转移已过期');
                }
            });

        if ($expired > 0) {
            $this->activityLog->logSystemSafely(
                'service_transfer',
                'service_transfer.expired',
                sprintf('批量关闭过期服务转移 %d 条', $expired),
                null,
                null,
                ['count' => $expired, 'as_of' => $now->toDateTimeString()]
            );
        }

        return $expired;
    }

    /**
     * 风险快照：未结账单数 / 服务状态 / 频控余量 / 接收方实名判定。
     *
     * @return array<string, mixed>
     */
    public function riskFlags(Service $service, ?int $toUserId = null): array
    {
        $fromUserId = (int) $service->user_id;

        return [
            'unpaid_invoice_count' => $this->unpaidInvoiceCount($service, $fromUserId),
            'service_status' => (int) $service->status,
            'from_rate_remaining' => max(0, self::RATE_LIMIT_PER_WINDOW - $this->recentTransferCount($fromUserId)),
            'to_rate_remaining' => $toUserId !== null
                ? max(0, self::RATE_LIMIT_PER_WINDOW - $this->recentTransferCount($toUserId))
                : null,
            'to_verified' => $toUserId !== null
                ? $this->userVerified($toUserId)
                : null,
            'verification_required' => $this->receiverVerificationRequired(),
            'snapshot_at' => now()->toDateTimeString(),
        ];
    }

    // -------- 校验 --------

    private function ensureNoPendingTransfer(Service $service, ?int $ignoreTransferId = null): void
    {
        $exists = ServiceTransfer::query()
            ->where('service_id', (int) $service->id)
            ->whereIn('status', ServiceTransfer::ACTIVE_STATUSES)
            ->when($ignoreTransferId !== null, fn ($query) => $query->whereKeyNot($ignoreTransferId))
            ->exists();

        if ($exists) {
            throw new BusinessException('该服务已存在进行中的转移请求');
        }
    }

    private function ensureRateLimit(int $userId): void
    {
        if ($this->recentTransferCount($userId) >= self::RATE_LIMIT_PER_WINDOW) {
            throw new BusinessException('发起/接收服务转移过于频繁，请 7 天后再试');
        }
    }

    private function recentTransferCount(int $userId): int
    {
        return (int) ServiceTransfer::query()
            ->where('created_at', '>=', now()->subDays(self::RATE_WINDOW_DAYS))
            ->where(function ($query) use ($userId): void {
                $query->where('from_user_id', $userId)->orWhere('to_user_id', $userId);
            })
            ->count();
    }

    private function ensureNoUnpaidInvoices(Service $service, int $fromUserId): void
    {
        if ($this->unpaidInvoiceCount($service, $fromUserId) > 0) {
            throw new BusinessException('转出方存在该服务的未结账单，请先结清后再发起转移', 42200, 422, [
                'error_code' => self::ERROR_UNPAID_INVOICES,
            ]);
        }
    }

    private function unpaidInvoiceCount(Service $service, int $userId): int
    {
        return (int) Invoice::query()
            ->where('user_id', $userId)
            ->where('service_id', (int) $service->id)
            ->where('status', 0)
            ->count();
    }

    private function canConfirm(ServiceTransfer $transfer, int $userId): bool
    {
        if (! $transfer->involvesUser($userId)) {
            return false;
        }

        return $transfer->from_confirmed_at === null || $transfer->to_confirmed_at === null;
    }

    private function canCancel(ServiceTransfer $transfer, int $userId): bool
    {
        if (! $transfer->involvesUser($userId)) {
            return false;
        }

        // 发起方：转出方发起→from；接收方发起→to；管理员代发起→from 可撤销。
        $initiatorUserId = (int) $transfer->initiated_by === ServiceTransfer::INITIATED_BY_TO
            ? (int) $transfer->to_user_id
            : (int) $transfer->from_user_id;

        return $userId === $initiatorUserId;
    }

    private function fillPendingConfirmation(ServiceTransfer $transfer, int $userId, bool $isAdmin): void
    {
        $now = now();
        $fromConfirmed = $transfer->from_confirmed_at !== null;
        $toConfirmed = $transfer->to_confirmed_at !== null;

        if (! $fromConfirmed && ($isAdmin || (int) $transfer->from_user_id === $userId)) {
            $transfer->forceFill(['from_confirmed_at' => $now])->save();
            $fromConfirmed = true;
        } elseif (! $toConfirmed && ($isAdmin || (int) $transfer->to_user_id === $userId)) {
            $transfer->forceFill(['to_confirmed_at' => $now])->save();
            $toConfirmed = true;
        }

        if ($fromConfirmed && $toConfirmed && (int) $transfer->status === ServiceTransfer::STATUS_PENDING) {
            $transfer->forceFill(['status' => ServiceTransfer::STATUS_CONFIRMED])->save();
        }
    }

    private function ensureClosable(ServiceTransfer $transfer): void
    {
        if (! in_array((int) $transfer->status, ServiceTransfer::ACTIVE_STATUSES, true)) {
            throw new BusinessException('该转移请求已结束');
        }
    }

    // -------- 配置 --------

    /**
     * 接收方实名限制：读 settings key=verification.restrictions（M11 配置）。
     * enabled=false 或缺 scope.service_transfer_receive 时视为不限制（自带默认兜底）。
     */
    private function receiverVerificationRequired(): bool
    {
        $raw = Setting::getValue('verification', 'restrictions');
        if (! is_string($raw) || trim($raw) === '') {
            return false;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return false;
        }

        if (! in_array($decoded['enabled'] ?? false, [true, 1, '1', 'true', 'on'], true)) {
            return false;
        }

        $scope = is_array($decoded['scope'] ?? null) ? $decoded['scope'] : [];

        return in_array($scope['service_transfer_receive'] ?? false, [true, 1, '1', 'true', 'on'], true);
    }

    private function manualReviewEnabled(): bool
    {
        $value = Setting::getValue('service_transfer', 'manual_review', '0');

        return in_array($value, [true, 1, '1', 'true', 'on'], true);
    }

    private function userVerified(int $userId): bool
    {
        $user = User::query()->find($userId);

        return $user instanceof User && $user->hasCompletedVerification();
    }

    // -------- 留痕与通知 --------

    private function writeOperationLog(Service $service, ServiceTransfer $transfer, int $fromUserId, int $toUserId): void
    {
        try {
            if (! SchemaMetadataCache::hasTable('service_operation_logs')) {
                return;
            }

            ServiceOperationLog::query()->create([
                'service_instance_id' => (int) $service->id,
                'operation_type' => 'service_transfer',
                'request_payload_json' => [
                    'transfer_id' => (int) $transfer->id,
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $toUserId,
                ],
                'response_payload_json' => [
                    'status' => ServiceTransfer::STATUS_COMPLETED,
                    'completed_at' => now()->toDateTimeString(),
                ],
                'result_status' => 'success',
                'operator_type' => (int) $transfer->operator_id !== 0 && $transfer->operator_id !== null ? 'admin' : 'client',
                'operator_id' => $transfer->operator_id,
                'trace_id' => $transfer->trace_id,
                'executed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            // 留痕失败不阻断过户主流程，仅告警（activity log 为准）。
            Log::warning('[服务转移] service_operation_logs 留痕失败', [
                'transfer_id' => (int) $transfer->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function logClosed(ServiceTransfer $transfer, string $action, int $userId, bool $isAdmin): void
    {
        $this->activityLog->logSafely(
            'service_transfer',
            'service_transfer.'.$action,
            sprintf('转移 #%d 已%s', (int) $transfer->id, $action === 'cancelled' ? '取消' : '拒绝'),
            ServiceTransfer::class,
            (int) $transfer->id,
            [
                'transfer_id' => (int) $transfer->id,
                'actor_user_id' => $userId,
                'is_admin' => $isAdmin,
                'reason' => $transfer->reject_reason,
            ]
        );
    }

    /**
     * 站内信 + 邮件通知双方；任何通知失败不阻断业务主流程。
     */
    private function notify(ServiceTransfer $transfer, string $code, string $title): void
    {
        try {
            $params = $this->templateParams($transfer);
            $content = $this->renderInboxContent($code, $params);

            foreach ([(int) $transfer->from_user_id, (int) $transfer->to_user_id] as $userId) {
                $this->notifications->create($userId, $code, $title, $content, null, [
                    'service_transfer_id' => (int) $transfer->id,
                    'service_id' => (int) $transfer->service_id,
                ]);
            }

            foreach ($this->participantEmails($transfer) as $email) {
                try {
                    $this->mailer->sendTemplateEmail($email, $code, $params);
                } catch (Throwable $exception) {
                    Log::warning('[服务转移] 邮件通知发送失败，已忽略', [
                        'transfer_id' => (int) $transfer->id,
                        'code' => $code,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('[服务转移] 通知写入失败，已忽略', [
                'transfer_id' => (int) $transfer->id,
                'code' => $code,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function templateParams(ServiceTransfer $transfer): array
    {
        $fromEmail = (string) (User::query()->find((int) $transfer->from_user_id)->email ?? '');
        $toEmail = (string) (User::query()->find((int) $transfer->to_user_id)->email ?? '');

        return [
            'service_name' => (string) (Service::query()->find((int) $transfer->service_id)->name ?? ('服务 #'.$transfer->service_id)),
            'from_user_email' => $fromEmail,
            'to_user_email' => $toEmail,
            'expires_at' => $transfer->expires_at->format('Y-m-d H:i'),
        ];
    }

    /**
     * @param  array<string, string>  $params
     */
    private function renderInboxContent(string $code, array $params): string
    {
        $lines = [
            'service_transfer_requested' => '服务「%s」的转移请求（%s → %s）等待确认，请于 %s 前处理，逾期自动过期。',
            'service_transfer_confirmed' => '服务「%s」的转移请求（%s → %s）已获双方确认，即将生效。',
            'service_transfer_completed' => '服务「%s」已从 %s 转移至 %s，转移已完成。',
            'service_transfer_rejected' => '服务「%s」的转移请求（%s → %s）已结束（拒绝/取消/过期）。',
        ];

        return sprintf(
            $lines[$code] ?? '服务转移状态更新：%s（%s → %s）。',
            $params['service_name'] ?? '',
            $params['from_user_email'] ?? '',
            $params['to_user_email'] ?? '',
            $params['expires_at'] ?? ''
        );
    }

    /**
     * @return list<string>
     */
    private function participantEmails(ServiceTransfer $transfer): array
    {
        $emails = [];

        foreach (['from_user_id', 'to_user_id'] as $field) {
            $email = trim((string) (User::query()->find((int) $transfer->{$field})->email ?? ''));
            if ($email !== '') {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    private function normalizeReason(?string $reason): ?string
    {
        $normalized = mb_substr(trim((string) $reason), 0, 255);

        return $normalized === '' ? null : $normalized;
    }
}
