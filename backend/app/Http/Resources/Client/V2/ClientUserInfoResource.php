<?php

declare(strict_types=1);

namespace App\Http\Resources\Client\V2;

use App\Models\User;
use App\Services\Auth\LoginAlertSettingService;
use App\Services\Verification\VerificationGateService;
use App\Services\Verification\VerificationRestrictionService;
use App\Support\SensitiveDataSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class ClientUserInfoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $memberLevel = $this->memberLevel;
        $promotionAmbassador = $this->relationLoaded('promotionAmbassador') ? $this->getRelation('promotionAmbassador') : null;

        return [
            'id' => $this->id,
            'email' => (string) ($this->email ?? ''),
            'nickname' => $this->nickname,
            'display_name' => (string) ($this->display_name ?? ''),
            'phone' => (string) ($this->phone ?? ''),
            'cash_balance' => (string) $this->balance,
            'credit_limit' => (string) $this->credit_limit,
            'referral_frozen_balance' => (string) $this->referral_frozen_amount,
            'referral_available_balance' => (string) $this->referral_available_amount,
            'referral_pending_withdrawal_balance' => (string) $this->referral_withdrawing_amount,
            'referral_withdrawn_balance' => (string) $this->referral_withdrawn_amount,
            'referral_code' => $this->referral_code,
            'referrer_user_id' => $this->referrer_user_id,
            'member_level_id' => $this->member_level_id,
            'total_sales_amount' => $this->total_sales_amount,
            'member_level' => $memberLevel ? [
                'id' => $memberLevel->id,
                'name' => $memberLevel->name,
            ] : null,
            'promotion_ambassador_id' => $this->promotion_ambassador_id,
            'promotion_ambassador' => $promotionAmbassador ? [
                'id' => $promotionAmbassador->id,
                'name' => $promotionAmbassador->name,
                'reward_rate' => $promotionAmbassador->reward_rate,
                'renewal_reward_rate' => $promotionAmbassador->renewal_reward_rate,
            ] : null,
            'status' => $this->status,
            'is_verified' => $this->is_verified,
            'real_name' => $this->real_name,
            'id_card_masked' => $this->maskIdCard((string) $this->id_card),
            'verification_status' => $this->verification_status,
            'verification_message' => $this->verification_message,
            'verification_certify_id' => $this->verification_certify_id,
            'login_email_alert' => (int) $this->login_email_alert,
            'login_notify' => (int) (($this->login_notify ?? null) ?? $this->login_email_alert),
            'login_location_alert' => (int) ($this->login_location_alert ?? 1),
            // M10：登录提醒启用的通道列表（settings users.login_alert.channels）
            'login_alert_channels' => app(LoginAlertSettingService::class)->channels(),
            'password_change_alert' => (int) ($this->password_change_alert ?? 1),
            'phone_change_alert' => (int) ($this->phone_change_alert ?? 1),
            'email_change_alert' => (int) ($this->email_change_alert ?? 1),
            'marketing_alert' => (int) ($this->marketing_alert ?? 0),
            'alipay_account' => [
                'real_name' => $this->alipay_real_name,
                'account' => $this->alipay_account,
                'is_bound' => $this->hasBoundAlipayAccount(),
            ],
            'last_login_at' => $this->last_login_at?->format('Y-m-d H:i:s'),
            'last_login_ip' => (string) ($this->last_login_ip ?? ''),
            'verified_at' => $this->verified_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            // M11：未实名限制投影（enabled=false 时 restricted_scopes 恒为空，前端据此置灰入口）
            'restrictions' => $this->restrictionsProjection(),
        ];
    }

    /**
     * M11 restrictions 投影：
     * - enabled：总开关（裁决默认 false，上线不自动拦截）；
     * - restricted_scopes：当前用户实际会被拦截的 scope 列表（已实名/豁免时为空）；
     * - enabled_scopes：配置中开启限制的 scope（无论用户是否已实名，供前端展示口径）；
     * - grace_days_remaining：注册宽限剩余天数；
     * - exempted：是否命中会员等级豁免。
     *
     * @return array<string, mixed>
     */
    private function restrictionsProjection(): array
    {
        $user = $this->resource;
        if (! $user instanceof User) {
            return [
                'enabled' => false,
                'restricted_scopes' => [],
                'enabled_scopes' => [],
                'grace_days_remaining' => 0,
                'exempted' => false,
            ];
        }

        $gate = app(VerificationGateService::class);
        $config = app(VerificationRestrictionService::class)->get();
        $enabledScopes = array_values(array_filter(
            VerificationRestrictionService::SCOPES,
            fn (string $scope): bool => (bool) ($config['scope'][$scope] ?? false)
        ));

        return [
            'enabled' => (bool) $config['enabled'],
            'restricted_scopes' => $gate->restrictedScopes($user),
            'enabled_scopes' => $enabledScopes,
            'grace_days_remaining' => $gate->graceDaysRemaining($user, (int) $config['grace_days']),
            'exempted' => $gate->isExempted($user, $config),
        ];
    }

    private function maskIdCard(string $idCard): string
    {
        if ($idCard === '') {
            return '';
        }

        // 证件号不足 8 位视为未录入完整证件，沿用原样返回的历史约定。
        if (mb_strlen($idCard) <= 8) {
            return $idCard;
        }

        return SensitiveDataSanitizer::maskKeepingEnds($idCard);
    }
}
