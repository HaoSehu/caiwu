<?php

declare(strict_types=1);

namespace App\Http\Resources\SalesCommission\V2;

use App\Models\SalesCommissionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesCommissionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SalesCommissionPlan $plan */
        $plan = $this->resource;

        return [
            'id' => (int) $plan->id,
            'name' => (string) $plan->name,
            'basis' => (int) $plan->basis,
            'basis_label' => (int) $plan->basis === SalesCommissionPlan::BASIS_ORDER_AMOUNT ? '订单金额' : '实付金额',
            'is_enabled' => (int) $plan->is_enabled,
            'valid_from' => $plan->valid_from?->format('Y-m-d'),
            'valid_to' => $plan->valid_to?->format('Y-m-d'),
            'remark' => (string) ($plan->remark ?? ''),
            'tiers' => $plan->relationLoaded('tiers') ? $plan->tiers->map(fn ($tier): array => [
                'id' => (int) $tier->id,
                'min_amount' => number_format((float) $tier->min_amount, 2, '.', ''),
                'max_amount' => $tier->max_amount !== null ? number_format((float) $tier->max_amount, 2, '.', '') : null,
                'rate' => number_format((float) $tier->rate, 2, '.', ''),
            ])->values()->all() : [],
            'product_rates' => $plan->relationLoaded('productRates') ? $plan->productRates->map(fn ($rate): array => [
                'id' => (int) $rate->id,
                'product_id' => (int) $rate->product_id,
                'product_name' => $rate->product ? (string) $rate->product->name : '',
                'rate' => $rate->rate !== null ? number_format((float) $rate->rate, 2, '.', '') : null,
                'fixed_amount' => $rate->fixed_amount !== null ? number_format((float) $rate->fixed_amount, 2, '.', '') : null,
            ])->values()->all() : [],
            'created_at' => $plan->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
