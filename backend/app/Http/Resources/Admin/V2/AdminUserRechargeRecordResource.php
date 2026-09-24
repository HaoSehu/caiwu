<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\Invoice;
use App\Models\RechargeRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserRechargeRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var RechargeRecord $record */
        $record = $this->resource;
        $invoice = $record->invoice;

        return [
            'id' => (int) $record->id,
            'record_no' => (string) ($record->record_no ?? ''),
            'scene' => (string) ($record->scene ?? ''),
            'entry_type' => (string) ($record->entry_type ?? ''),
            'direction' => (string) ($record->direction ?? 'in'),
            'amount' => number_format((float) ($record->amount ?? 0), 2, '.', ''),
            'currency' => (string) ($record->currency ?? 'CNY'),
            'remark' => (string) ($record->remark ?? ''),
            'operator_name' => (string) ($record->operator_name ?? ''),
            'invoice_id' => $record->invoice_id !== null ? (int) $record->invoice_id : null,
            'invoice_no' => $invoice instanceof Invoice ? (string) $invoice->invoice_no : '',
            'order_id' => $record->order_id !== null ? (int) $record->order_id : null,
            'payment_id' => $record->payment_id !== null ? (int) $record->payment_id : null,
            'trace_id' => (string) ($record->trace_id ?? ''),
            'created_at' => $record->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
