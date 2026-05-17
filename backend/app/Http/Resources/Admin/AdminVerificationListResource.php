<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AdminVerificationListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'email' => (string) $this->email,
            'phone' => (string) ($this->phone ?? ''),
            'nickname' => (string) ($this->nickname ?? ''),
            'display_name' => (string) $this->display_name,
            'real_name' => (string) ($this->real_name ?? ''),
            'id_card_masked' => $this->maskIdCard(),
            'verification_status' => (int) ($this->verification_status ?? 0),
            'verification_message' => (string) ($this->verification_message ?? ''),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
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

        return mb_substr($idCard, 0, 6).str_repeat('*', max($length - 10, 1)).mb_substr($idCard, -4);
    }
}
