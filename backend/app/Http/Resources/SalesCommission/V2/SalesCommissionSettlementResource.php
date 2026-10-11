<?php

declare(strict_types=1);

namespace App\Http\Resources\SalesCommission\V2;

use App\Models\SalesCommission;
use App\Models\SalesCommissionSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesCommissionSettlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SalesCommissionSettlement $settlement */
        $settlement = $this->resource;

        return [
            'id' => (int) $settlement->id,
            'admin_user_id' => (int) $settlement->admin_user_id,
            'admin_user' => $settlement->adminUser ? [
                'id' => (int) $settlement->adminUser->id,
                'display_name' => (string) $settlement->adminUser->display_name,
            ] : null,
            'period_start' => $settlement->period_start?->format('Y-m-d'),
            'period_end' => $settlement->period_end?->format('Y-m-d'),
            'total_amount' => number_format((float) $settlement->total_amount, 2, '.', ''),
            'commission_count' => (int) $settlement->commission_count,
            'status' => (int) $settlement->status,
            'status_label' => SalesCommissionSettlement::statusLabel((int) $settlement->status),
            'operator_id' => $settlement->operator_id !== null ? (int) $settlement->operator_id : null,
            'settled_at' => $settlement->settled_at?->format('Y-m-d H:i:s'),
            'remark' => (string) ($settlement->remark ?? ''),
            'commissions' => $settlement->relationLoaded('commissions') ? $settlement->commissions->map(fn ($commission): array => [
                'id' => (int) $commission->id,
                'invoice_id' => (int) $commission->invoice_id,
                'base_amount' => number_format((float) $commission->base_amount, 2, '.', ''),
                'rate' => number_format((float) $commission->rate, 2, '.', ''),
                'commission_amount' => number_format((float) $commission->commission_amount, 2, '.', ''),
                'status' => (int) $commission->status,
                'status_label' => SalesCommission::statusLabel((int) $commission->status),
            ])->values()->all() : [],
            'created_at' => $settlement->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
