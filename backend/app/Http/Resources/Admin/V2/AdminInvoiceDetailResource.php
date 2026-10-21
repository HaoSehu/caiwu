<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use Illuminate\Http\Request;

class AdminInvoiceDetailResource extends AdminInvoiceSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $invoice = is_array($this->resource) ? $this->resource : [];

        return [
            'id' => (int) ($invoice['id'] ?? 0),
            'basic' => [
                'invoice_no' => (string) ($invoice['invoice_no'] ?? ''),
                'type' => (string) ($invoice['type'] ?? ''),
                'type_label' => (string) ($invoice['type_label'] ?? ''),
                'status' => (int) ($invoice['status'] ?? 0),
                'status_label' => (string) ($invoice['status_label'] ?? ''),
                'raw_status' => (int) ($invoice['raw_status'] ?? $invoice['status'] ?? 0),
                'raw_status_label' => (string) ($invoice['raw_status_label'] ?? $invoice['status_label'] ?? ''),
                'billing_cycle' => (string) ($invoice['billing_cycle'] ?? ''),
                'quantity' => (int) ($invoice['quantity'] ?? 1),
                'due_date' => $invoice['due_date'] ?? null,
            ],
            'display' => [
                'product_spec_snapshot' => (string) ($invoice['product_spec_snapshot'] ?? ''),
                'product_spec_display' => (string) ($invoice['product_spec_display'] ?? ''),
                'product_display_name' => (string) ($invoice['product_display_name'] ?? ''),
                'combined_display_name' => (string) ($invoice['combined_display_name'] ?? ''),
                'product_full_path' => (string) ($invoice['product_full_path'] ?? ''),
                'summary' => $this->summary($invoice['summary'] ?? null),
            ],
            'financial' => [
                'amount' => (string) ($invoice['amount'] ?? '0.00'),
                'discount' => (string) ($invoice['discount'] ?? '0.00'),
                'member_discount_amount' => (string) ($invoice['member_discount_amount'] ?? '0.00'),
                // W7：会员折扣明细快照（等级/组/比例），管理端不做脱敏
                'member_discount_snapshot' => $this->stripSensitiveKeys((array) ($invoice['member_discount_snapshot'] ?? [])),
                'coupon_code' => (string) ($invoice['coupon_code'] ?? ''),
                'coupon_name' => (string) ($invoice['coupon_name'] ?? ''),
                'paid_amount' => (string) ($invoice['paid_amount'] ?? '0.00'),
                'payable_amount' => (string) ($invoice['payable_amount'] ?? '0.00'),
                'paid_at' => $invoice['paid_at'] ?? null,
                // 退款投影（纯标记与真退款同表累计，详见执行计划 2026-10-11）
                'refund_amount' => (string) ($invoice['refund_amount'] ?? '0.00'),
                'refundable_amount' => (string) ($invoice['refundable_amount'] ?? '0.00'),
                'refund_method' => (string) ($invoice['refund_method'] ?? ''),
                'refunded_at' => $invoice['refunded_at'] ?? null,
                'refund_status' => (int) ($invoice['refund_status'] ?? 0),
                'refund_status_label' => (string) ($invoice['refund_status_label'] ?? ''),
                'is_partially_refunded' => (bool) ($invoice['is_partially_refunded'] ?? false),
                // 退款到余额（唯一通道）：可退额为单次口径的轻量近似，真值以服务端守卫为准。
                // manual_mark 纯标记通道已于 2026-10-20 移除，对应能力位不再下发。
                'refundable_amount_for_balance' => (string) ($invoice['refundable_amount_for_balance'] ?? '0.00'),
            ],
            'user' => $this->user($invoice['user'] ?? null),
            'order' => $this->order($invoice['order'] ?? null),
            'product' => $this->product($invoice['product'] ?? null),
            'service' => $this->service($invoice['service'] ?? null),
            'scene' => $this->stripSensitiveKeys((array) ($invoice['scene'] ?? [])),
            'fulfillment' => [
                'pending' => (bool) ($invoice['fulfillment_pending'] ?? false),
                'pending_at' => $invoice['fulfillment_pending_at'] ?? null,
                'cleared_at' => $invoice['fulfillment_cleared_at'] ?? null,
                'requires_refund' => (bool) ($invoice['requires_refund'] ?? false),
            ],
            'configuration' => [
                'config_snapshot' => $this->stripSensitiveKeys((array) ($invoice['config_snapshot'] ?? [])),
                'config_pricing_snapshot' => $this->stripSensitiveKeys((array) ($invoice['config_pricing_snapshot'] ?? [])),
                'coupon_snapshot' => $this->stripSensitiveKeys((array) ($invoice['coupon_snapshot'] ?? [])),
            ],
            'payment_chain' => [
                'payment_summary' => is_array($invoice['payment_summary'] ?? null)
                    ? $this->stripSensitiveKeys($invoice['payment_summary'])
                    : null,
                'payments' => $this->cleanList($invoice['payments'] ?? []),
            ],
            'refund_records' => $this->cleanList($invoice['refunds'] ?? []),
            'items' => $this->cleanList($invoice['items'] ?? []),
            'logs' => $this->cleanList($invoice['logs'] ?? []),
            'audit' => [
                'trace_id' => (string) ($invoice['trace_id'] ?? ''),
                'refund_trace_id' => (string) ($invoice['refund_trace_id'] ?? ''),
            ],
            'actions' => [
                'can_cancel' => (bool) ($invoice['can_cancel'] ?? false),
                'can_refund_to_balance' => (bool) ($invoice['can_refund_to_balance'] ?? false),
            ],
            'timestamps' => [
                'created_at' => $invoice['created_at'] ?? null,
                'updated_at' => $invoice['updated_at'] ?? null,
            ],
        ];
    }
}
