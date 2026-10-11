<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Services\Verification\VerificationRestrictionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * M11 未实名自动限制配置投影（管理端 GET /v2/admin/settings/verification-restrictions）。
 *
 * @property array{enabled: bool, grace_days: int, exempt_member_level_ids: list<int>, scope: array<string, bool>} $resource
 */
class AdminVerificationRestrictionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $config = $this->resource;

        return [
            'enabled' => (bool) $config['enabled'],
            'grace_days' => (int) $config['grace_days'],
            'exempt_member_level_ids' => array_map('intval', $config['exempt_member_level_ids']),
            'scope' => collect(VerificationRestrictionService::SCOPES)
                ->mapWithKeys(static fn (string $scope): array => [
                    $scope => (bool) ($config['scope'][$scope] ?? false),
                ])
                ->all(),
        ];
    }
}
