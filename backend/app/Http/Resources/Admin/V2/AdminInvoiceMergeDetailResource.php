<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Constants\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * 合并总账单概览（M1）：总账单核心口径 + merge_items 子账单清单与状态。
 * 供总账单详情页与 merge-items 列表接口复用。
 *
 * @property Invoice $resource
 */
class AdminInvoiceMergeDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $invoice = $this->resource;
        $mergeItems = $invoice->mergeItems->loadMissing('childInvoice');

        return [
            'master' => [
                'id' => (int) $invoice->id,
                'invoice_no' => (string) $invoice->invoice_no,
                'user_id' => (int) $invoice->user_id,
                'type' => (string) $invoice->type,
                'currency' => (string) $invoice->currency,
                'amount' => (string) $invoice->amount,
                'discount' => (string) $invoice->discount,
                'member_discount_amount' => (string) $invoice->member_discount_amount,
                'paid_amount' => (string) $invoice->paid_amount,
                'status' => (int) $invoice->status,
                'status_label' => (string) (InvoiceStatus::$labels[(int) $invoice->status] ?? ''),
                'due_date' => $invoice->due_date !== null ? Carbon::parse($invoice->due_date)->toDateString() : null,
                'paid_at' => $invoice->paid_at !== null ? Carbon::parse($invoice->paid_at)->toIso8601String() : null,
                'merge_role' => (int) $invoice->merge_role,
                'remark' => (string) $invoice->remark,
                'created_at' => $invoice->created_at?->toIso8601String(),
            ],
            'summary' => [
                'child_count' => $mergeItems->count(),
                'settled_count' => $mergeItems
                    ->filter(fn ($item): bool => $item->childInvoice instanceof Invoice
                        && (int) $item->childInvoice->status === InvoiceStatus::PAID)
                    ->count(),
                'snapshot_amount_total' => (string) number_format(
                    (float) $mergeItems->sum(static fn ($item): float => (float) $item->amount_snapshot),
                    2,
                    '.',
                    ''
                ),
            ],
            'merge_items' => AdminInvoiceMergeItemResource::collection($mergeItems)->resolve(),
        ];
    }
}
