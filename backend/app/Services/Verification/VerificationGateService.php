<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Services\System\OperationLogService;
use Illuminate\Support\Carbon;

/**
 * M11 未实名自动限制：统一判定 Gate。
 *
 * 判定口径（对齐既有实名通过态）：
 * - users.is_verified = 1 或 verification_status = 2（已认证）——即 User::hasCompletedVerification()；
 * - grace_days：按 users.created_at 的注册宽限天数，宽限期内放行；
 * - exempt_member_level_ids：命中豁免会员等级直接放行；
 * - enabled=false 或 scope 未开启时全放行（裁决：enabled 默认 false，上线不自动拦截）。
 *
 * 服务层兜底与路由中间件均调用 ensureCan()，防绕过 API 层直调服务；
 * 管理员代下单（order.manual_entry）与代充值路径不经过本 Gate（结构性豁免）。
 */
class VerificationGateService
{
    public const SCOPE_PURCHASE = 'purchase';

    public const SCOPE_RECHARGE = 'recharge';

    public const SCOPE_WITHDRAWAL = 'withdrawal';

    public const SCOPE_SERVICE_TRANSFER_RECEIVE = 'service_transfer_receive';

    public const SCOPE_RENEWAL = 'renewal';

    public const ERROR_CODE_VERIFICATION_REQUIRED = 'verification_required';

    public function __construct(
        private VerificationRestrictionService $restrictions,
        private OperationLogService $operationLogService,
    ) {}

    public function allowed(User $user, string $scope): bool
    {
        $config = $this->restrictions->get();

        if (! $config['enabled']) {
            return true;
        }

        if (! ($config['scope'][$scope] ?? false)) {
            return true;
        }

        // 已实名（含接口回执同步的通过态）直接放行。
        if ($user->hasCompletedVerification()) {
            return true;
        }

        // 注册宽限期：按 users.created_at 判定。
        if ($config['grace_days'] > 0 && $this->graceDaysRemaining($user, $config['grace_days']) > 0) {
            return true;
        }

        // 会员等级豁免。
        $memberLevelId = (int) ($user->member_level_id ?? 0);
        if ($memberLevelId > 0 && in_array($memberLevelId, $config['exempt_member_level_ids'], true)) {
            return true;
        }

        return false;
    }

    /**
     * 宽限剩余天数（向下取整，0 表示已过宽限期；与配置无关时返回配置值）。
     */
    public function graceDaysRemaining(User $user, ?int $graceDays = null): int
    {
        $graceDays ??= $this->restrictions->get()['grace_days'];
        if ($graceDays <= 0) {
            return 0;
        }

        $createdAt = $user->created_at;
        if (! $createdAt instanceof Carbon) {
            return 0;
        }

        return max(0, $graceDays - (int) floor($createdAt->copy()->startOfDay()->diffInDays(now()->startOfDay())));
    }

    /**
     * 当前用户实际被拦截的 scope 列表（仅 enabled 且 scope 开启且未实名/未豁免时才“实际受限”）。
     *
     * @return list<string>
     */
    public function restrictedScopes(User $user): array
    {
        $config = $this->restrictions->get();
        if (! $config['enabled'] || $user->hasCompletedVerification() || $this->isExempted($user, $config)) {
            return [];
        }

        // 宽限期内实际可下单，投影不得标记为受限（与 allowed() 口径一致）。
        if ($config['grace_days'] > 0 && $this->graceDaysRemaining($user, $config['grace_days']) > 0) {
            return [];
        }

        return array_values(array_filter(
            VerificationRestrictionService::SCOPES,
            fn (string $scope): bool => (bool) ($config['scope'][$scope] ?? false)
        ));
    }

    public function isExempted(User $user, ?array $config = null): bool
    {
        $config ??= $this->restrictions->get();
        $memberLevelId = (int) ($user->member_level_id ?? 0);

        return $memberLevelId > 0 && in_array($memberLevelId, $config['exempt_member_level_ids'], true);
    }

    /**
     * 拦截时抛业务异常：JSON data.error_code=verification_required（前端引导跳转实名），
     * 并写 activity log（event=verification.blocked，带 user_id/scope）便于排障与误伤回溯。
     */
    public function ensureCan(User $user, string $scope): void
    {
        if ($this->allowed($user, $scope)) {
            return;
        }

        $this->operationLogService->write(
            userId: (int) $user->id,
            userType: 'client',
            action: 'verification.blocked',
            module: 'verification',
            targetId: (int) $user->id,
            detail: [
                'user_id' => (int) $user->id,
                'scope' => $scope,
                'member_level_id' => (int) ($user->member_level_id ?? 0),
            ],
        );

        throw new BusinessException(
            '请先完成实名认证后再进行此操作',
            40300,
            403,
            [
                'error_code' => self::ERROR_CODE_VERIFICATION_REQUIRED,
                'redirect' => '/client/verification',
                'scope' => $scope,
            ]
        );
    }
}
