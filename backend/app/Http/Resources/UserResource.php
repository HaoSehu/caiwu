<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $memberLevel = $this->resource->relationLoaded('memberLevel') ? $this->resource->getRelation('memberLevel') : null;
        $profile = $this->resource->relationLoaded('profile') ? $this->resource->getRelation('profile') : null;
        $account = $this->resource->relationLoaded('account') ? $this->resource->getRelation('account') : null;
        $nickname = trim((string) ($profile?->nickname ?? $this->resource->getRawOriginal('nickname') ?? ''));
        $company = trim((string) ($profile?->company ?? $this->resource->getRawOriginal('company') ?? ''));
        $qq = trim((string) ($profile?->qq ?? $this->resource->getRawOriginal('qq') ?? ''));
        $adminNote = trim((string) ($profile?->admin_note ?? $this->resource->getRawOriginal('admin_note') ?? ''));

        return [
            'id'            => $this->id,
            'email'         => $this->email,
            'nickname'      => $nickname,
            'display_name'  => $this->resolveDisplayName($nickname),
            'phone'         => $this->phone,
            'company'       => $company,
            'qq'            => $qq,
            'admin_note'    => $adminNote !== '' ? $adminNote : null,
            'referral_code' => $this->referral_code,
            'referrer_user_id' => $this->referrer_user_id,
            'member_level_id' => $this->member_level_id,
            'total_sales_amount' => $this->total_sales_amount,
            'member_level' => $memberLevel ? [
                'id' => $memberLevel->id,
                'name' => $memberLevel->name,
                'code' => $memberLevel->code,
                'reward_rate' => $memberLevel->reward_rate,
            ] : null,
            'balance'       => $this->formatMoney($account?->cash_balance ?? 0),
            'active_services_count' => $this->active_services_count ?? 0,
            'credit_limit'  => $this->formatMoney($account?->credit_limit ?? 0),
            'status'        => $this->status,
            'is_verified'   => $this->is_verified,
            'real_name'     => $this->real_name,
            'id_card_masked' => $this->maskIdCard(),
            'verification_certify_id' => $this->verification_certify_id,
            'referred_at' => $this->referred_at?->format('Y-m-d H:i:s'),
            'alipay_real_name' => $this->alipay_real_name,
            'alipay_account' => $this->alipay_account,
            'last_login_at' => $this->last_login_at?->format('Y-m-d H:i:s'),
            'last_login_ip' => $this->last_login_ip,
            'created_at'    => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function maskIdCard(): string
    {
        $idCard = (string) ($this->id_card ?? '');

        if ($idCard === '') {
            return '-';
        }

        $length = mb_strlen($idCard);
        if ($length <= 8) {
            return $idCard;
        }

        return mb_substr($idCard, 0, 6) . str_repeat('*', max($length - 10, 1)) . mb_substr($idCard, -4);
    }

    private function resolveDisplayName(string $nickname): string
    {
        $realName = trim((string) ($this->real_name ?? ''));
        if ($realName !== '' && ((int) ($this->verification_status ?? 0) === 2 || (int) ($this->is_verified ?? 0) === 1)) {
            return $realName;
        }

        if ($nickname !== '' && preg_replace('/[\s\?？\x{FFFD}]+/u', '', $nickname) !== '') {
            return $nickname;
        }

        $email = trim((string) ($this->email ?? ''));
        if ($email !== '') {
            return $email;
        }

        return trim((string) ($this->phone ?? ''));
    }

    private function formatMoney(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
