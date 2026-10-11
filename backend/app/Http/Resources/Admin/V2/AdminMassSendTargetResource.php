<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\MassSendTarget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminMassSendTargetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MassSendTarget $target */
        $target = $this->resource;

        return [
            'id' => (int) $target->id,
            'mass_send_id' => (int) $target->mass_send_id,
            'user_id' => (int) $target->user_id,
            'email' => $target->email,
            'status' => (int) $target->status,
            'status_label' => [0 => '待发', 1 => '成功', 2 => '失败'][(int) $target->status] ?? (string) $target->status,
            'error' => $target->error,
            'sent_at' => $target->sent_at?->toDateTimeString(),
            'created_at' => $target->created_at?->toDateTimeString(),
        ];
    }
}
