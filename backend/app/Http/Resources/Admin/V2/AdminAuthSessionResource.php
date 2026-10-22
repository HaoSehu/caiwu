<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAuthSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = is_array($this->resource) ? $this->resource : [];

        return [
            'token' => (string) ($payload['token'] ?? ''),
            // 双因素未完成时不签令牌，前端据 mfa_required 切到验证码步骤，
            // challenge_token 只用于一次 /login/mfa 调用。
            'mfa_required' => (bool) ($payload['mfa_required'] ?? false),
            'challenge_token' => (string) ($payload['challenge_token'] ?? ''),
            'admin' => (new AdminAuthProfileResource((array) ($payload['admin'] ?? [])))->resolve(),
        ];
    }
}
