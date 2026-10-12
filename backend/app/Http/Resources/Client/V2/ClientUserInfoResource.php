<?php

declare(strict_types=1);

namespace App\Http\Resources\Client\V2;

use App\Models\User;
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
