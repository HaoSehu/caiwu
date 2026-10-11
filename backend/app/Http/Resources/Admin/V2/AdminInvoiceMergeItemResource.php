<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Constants\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 账单合并子账单关联快照（M1）：总账单 merge-items 清单行。
 * 子账单状态随取随显（child_status），已支付/待支付一目了然。
 *
 * @property int $id
 * @property int $master_invoice_id
 * @property int $child_invoice_id
 * @property string $child_invoice_no
 * @property string $amount_snapshot
 * @property Invoice|null $childInvoice
 */
class AdminInvoiceMergeItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $child = $this->resource->childInvoice;

        return [
            'id' => (int) $this->resource->id,
            'child_invoice_id' => (int) $this->resource->child_invoice_id,
            'child_invoice_no' => (string) $this->resource->child_invoice_no,
            'amount_snapshot' => (string) $this->resource->amount_snapshot,
            'child_status' => $child instanceof Invoice ? (int) $child->status : null,
            'child_status_label' => $child instanceof Invoice
                ? (string) (InvoiceStatus::$labels[(int) $child->status] ?? '')
                : '',
            'child_paid_amount' => $child instanceof Invoice ? (string) $child->paid_amount : null,
            'child_paid_at' => $child?->paid_at?->toIso8601String(),
            'merged_at' => $child?->merged_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
