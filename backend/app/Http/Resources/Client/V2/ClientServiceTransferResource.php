<?php

declare(strict_types=1);

namespace App\Http\Resources\Client\V2;

use App\Models\ServiceTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 客户端服务转移记录投影。
 *
 * @mixin ServiceTransfer
 */
class ClientServiceTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service = $this->resource->service;
        $fromUser = $this->resource->fromUser;
        $toUser = $this->resource->toUser;

        return [
            'id' => (int) $this->resource->id,
            'service_id' => (int) $this->resource->service_id,
            'service_name' => (string) ($service->name ?? ''),
            'service_domain' => (string) ($service->domain ?? ''),
            'from_user_id' => (int) $this->resource->from_user_id,
            'from_user_email' => (string) ($fromUser->email ?? ''),
            'to_user_id' => (int) $this->resource->to_user_id,
            'to_user_email' => (string) ($toUser->email ?? ''),
            'status' => (int) $this->resource->status,
            'status_label' => $this->resource->statusLabel(),
            'initiated_by' => (int) $this->resource->initiated_by,
            'initiated_by_label' => ServiceTransfer::INITIATED_BY_LABELS[$this->resource->initiated_by] ?? '',
            'from_confirmed_at' => $this->resource->from_confirmed_at?->toDateTimeString(),
            'to_confirmed_at' => $this->resource->to_confirmed_at?->toDateTimeString(),
            'expires_at' => $this->resource->expires_at?->toDateTimeString(),
            'reject_reason' => $this->resource->reject_reason,
            'created_at' => $this->resource->created_at?->toDateTimeString(),
        ];
    }
}
