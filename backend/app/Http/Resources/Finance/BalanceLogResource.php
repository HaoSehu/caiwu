<?php

namespace App\Http\Resources\Finance;

use App\Constants\FinanceLedgerEventType;
use App\Models\BalanceLog;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BalanceLog */
class BalanceLogResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var BalanceLog $log */
        $log = $this->resource;

        $eventType = (string) ($log->event_type ?? $log->type ?? '');
        $normalizedEventType = match ($eventType) {
            'invoice_payment' => 'consume',
            'invoice_refund' => 'refund',
            'manual_deduction' => 'admin_deduct',
            'manual_recharge' => 'recharge',
            'system_adjustment' => 'adjust',
            'referral_credit_cash' => 'referral_withdraw_approved',
            default => $eventType,
        };

        return [
            'id' => (int) $log->id,
            'event_type' => $normalizedEventType,
            'event_type_label' => FinanceLedgerEventType::label($normalizedEventType),
            'change_amount' => number_format((float) ($log->change_amount ?? $log->amount ?? 0), 2, '.', ''),
            'balance_after' => number_format((float) ($log->balance_after ?? $log->balance ?? 0), 2, '.', ''),
            'reference_id' => $log->reference_id !== null
                ? (int) $log->reference_id
                : ($log->related_id !== null ? (int) $log->related_id : null),
            'remark' => (string) ($log->remark ?? ''),
            'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
