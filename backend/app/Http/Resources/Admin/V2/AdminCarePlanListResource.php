<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\CarePlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCarePlanListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CarePlan $plan */
        $plan = $this->resource;

        return [
            'id' => (int) $plan->id,
            'name' => (string) $plan->name,
            'type' => (int) $plan->type,
            'type_label' => $plan->typeLabel(),
            'offset_days' => (int) $plan->offset_days,
            'channel' => (int) $plan->channel,
            'channel_label' => [1 => '邮件', 2 => '站内信', 3 => '两者'][(int) $plan->channel] ?? (string) $plan->channel,
            'template_id' => $plan->template_id !== null ? (int) $plan->template_id : null,
            'template_name' => (string) ($plan->template->name ?? ''),
            'is_enabled' => (int) $plan->is_enabled,
            'last_planned_date' => $plan->last_planned_date?->toDateString(),
            'created_at' => $plan->created_at?->toDateTimeString(),
        ];
    }
}
