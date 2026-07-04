<?php

namespace App\Http\Resources\Admin;

use App\Models\OperationLog;
use App\Support\AdminPrivacy;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OperationLog */
class OperationLogResource extends JsonResource
{
    public function toArray($request): array
    {
        $privacy = AdminPrivacy::fromRequest($request);

        return [
            'id' => (int) $this->id,
            'user_id' => $this->user_id !== null ? (int) $this->user_id : null,
            'user_type' => (string) ($this->user_type ?? ''),
            'action' => (string) ($this->action ?? ''),
            'module' => (string) ($this->module ?? ''),
            'subject_id' => $this->subject_id !== null
                ? (int) $this->subject_id
                : ($this->target_id !== null ? (int) $this->target_id : null),
            'context' => $privacy->payload(is_array($this->context ?? null) ? $this->context : $this->detail),
            'ip_address' => $privacy->ip($this->ip_address),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
