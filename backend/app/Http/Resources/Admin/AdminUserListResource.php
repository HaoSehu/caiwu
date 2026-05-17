<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AdminUserListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->resource->relationLoaded('profile') ? $this->resource->getRelation('profile') : null;
        $nickname = trim((string) ($profile?->nickname ?? $this->resource->getRawOriginal('nickname') ?? ''));

        return [
            'id' => (int) $this->id,
            'email' => (string) $this->email,
            'nickname' => $nickname,
            'display_name' => $this->resolveDisplayName($nickname),
            'company' => trim((string) ($profile?->company ?? $this->resource->getRawOriginal('company') ?? '')),
            'qq' => trim((string) ($profile?->qq ?? $this->resource->getRawOriginal('qq') ?? '')),
            'referral_code' => (string) ($this->referral_code ?? ''),
            'member_level_id' => $this->member_level_id !== null ? (int) $this->member_level_id : null,
            'verification_status' => (int) ($this->verification_status ?? 0),
            'real_name' => (string) ($this->real_name ?? ''),
            'balance' => (float) ($this->resource->balance ?? 0),
            'credit_limit' => (float) ($this->resource->credit_limit ?? 0),
            'status' => (int) ($this->status ?? 0),
            'is_verified' => (int) ($this->is_verified ?? 0),
            'opened_product_count' => (int) ($this->opened_product_count ?? 0),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
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
}
