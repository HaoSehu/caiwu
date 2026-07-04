<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use App\Support\AdminPrivacy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AdminVerificationListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $privacy = AdminPrivacy::fromRequest($request);
        $email = (string) $this->email;
        $phone = (string) ($this->phone ?? '');
        $realName = (string) ($this->real_name ?? '');

        return [
            'id' => (int) $this->id,
            'email' => $privacy->email($email),
            'phone' => $privacy->phone($phone),
            'nickname' => (string) ($this->nickname ?? ''),
            'display_name' => $privacy->displayName($this->display_name, $email, $phone, $realName),
            'real_name' => $privacy->name($realName),
            'id_card_masked' => $privacy->idCard($this->id_card),
            'verification_status' => (int) ($this->verification_status ?? 0),
            'verification_message' => (string) ($this->verification_message ?? ''),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
