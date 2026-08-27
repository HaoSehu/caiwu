<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\PromotionAmbassador;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PromotionAmbassador */
class AdminPromotionAmbassadorListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'reward_rate' => number_format((float) $this->reward_rate, 2, '.', ''),
            'renewal_reward_rate' => number_format((float) $this->renewal_reward_rate, 2, '.', ''),
            'status' => (int) $this->status,
            'remark' => $this->remark !== null ? (string) $this->remark : null,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
