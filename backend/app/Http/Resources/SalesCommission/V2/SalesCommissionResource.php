<?php

declare(strict_types=1);

namespace App\Http\Resources\SalesCommission\V2;

use App\Models\SalesCommission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesCommissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SalesCommission $commission */
        $commission = $this->resource;

        return [
            'id' => (int) $commission->id,
            'admin_user_id' => (int) $commission->admin_user_id,
            'admin_user' => $commission->adminUser ? [
                'id' => (int) $commission->adminUser->id,
                'display_name' => (string) $commission->adminUser->display_name,
            ] : null,
            'user_id' => (int) $commission->user_id,
            'user' => $commission->user ? [
                'id' => (int) $commission->user->id,
                'email' => (string) $commission->user->email,
                'nickname' => (string) $commission->user->nickname,
                'display_name' => (string) $commission->user->display_name,
            ] : null,
            'order_id' => $commission->order_id !== null ? (int) $commission->order_id : null,
            'invoice_id' => (int) $commission->invoice_id,
            'invoice_no' => $commission->invoice ? (string) $commission->invoice->invoice_no : '',
            'plan_id' => $commission->plan_id !== null ? (int) $commission->plan_id : null,
            'plan_snapshot' => is_array($commission->plan_snapshot) ? $commission->plan_snapshot : null,
            'base_amount' => number_format((float) $commission->base_amount, 2, '.', ''),
            'rate' => number_format((float) $commission->rate, 2, '.', ''),
            'commission_amount' => number_format((float) $commission->commission_amount, 2, '.', ''),
            'status' => (int) $commission->status,
            'status_label' => SalesCommission::statusLabel((int) $commission->status),
            'settlement_id' => $commission->settlement_id !== null ? (int) $commission->settlement_id : null,
            'settled_at' => $commission->settled_at?->format('Y-m-d H:i:s'),
            'trace_id' => (string) ($commission->trace_id ?? ''),
            'created_at' => $commission->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
