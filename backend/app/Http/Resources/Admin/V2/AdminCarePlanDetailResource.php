<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\CarePlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCarePlanDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CarePlan $plan */
        $plan = $this->resource;

        return AdminCarePlanListResource::make($plan)->toArray($request) + [
            'subject' => $plan->subject,
            'content' => $plan->content,
            'filter_json' => is_array($plan->filter_json) ? $plan->filter_json : [],
            'updated_at' => $plan->updated_at?->toDateTimeString(),
        ];
    }
}
