<?php

declare(strict_types=1);

namespace App\Http\Resources\Client\V2;

use App\Http\Resources\Admin\V2\AdminInvoiceSummaryResource;
use Illuminate\Http\Request;

class ClientInvoiceSummaryResource extends AdminInvoiceSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $invoice = is_array($this->resource) ? $this->resource : [];

        return [
            'id' => (int) ($invoice['id'] ?? 0),
            'invoice_no' => (string) ($invoice['invoice_no'] ?? ''),
            'order_id' => (int) ($invoice['order_id'] ?? 0),
            'order' => $this->order($invoice['order'] ?? null),
            'product_id' => (int) ($invoice['product_id'] ?? 0),
            'product' => $this->product($invoice['product'] ?? null),
            'product_spec_display' => (string) ($invoice['product_spec_display'] ?? ''),
            'product_display_name' => (string) ($invoice['product_display_name'] ?? ''),
            'combined_display_name' => (string) ($invoice['combined_display_name'] ?? ''),
            'product_full_path' => (string) ($invoice['product_full_path'] ?? ''),
            'type' => (string) ($invoice['type'] ?? ''),
            'type_label' => (string) ($invoice['type_label'] ?? ''),
            // 计费模式：账单自身的 billing_cycle 优先，历史账单为空时回落关联订单（下单时按商品周期写入）
            'billing_cycle' => $this->resolveBillingCycle($invoice),
            'amount' => (string) ($invoice['amount'] ?? '0.00'),
            'discount' => (string) ($invoice['discount'] ?? '0.00'),
            'member_discount_amount' => (string) ($invoice['member_discount_amount'] ?? '0.00'),
            'coupon_code' => (string) ($invoice['coupon_code'] ?? ''),
            'coupon_name' => (string) ($invoice['coupon_name'] ?? ''),
            'paid_amount' => (string) ($invoice['paid_amount'] ?? '0.00'),
            'payable_amount' => (string) ($invoice['payable_amount'] ?? '0.00'),
            'status' => (int) ($invoice['status'] ?? 0),
            'status_label' => (string) ($invoice['status_label'] ?? ''),
            // 支付窗口是否已过：详情接口的 payment_security.can_pay 在列表侧的等价只读标记，
            // 供列表页判断「去支付」是否还有意义（与 CheckoutSecurityService 同一判据）。
            'payment_window_expired' => (bool) ($invoice['payment_window_expired'] ?? false),
            'summary' => $this->summary($invoice['summary'] ?? null),
            'payment_summary' => $this->paymentSummary($invoice['payment_summary'] ?? null),
            'due_date' => $invoice['due_date'] ?? null,
            'created_at' => $invoice['created_at'] ?? null,
            'paid_at' => $invoice['paid_at'] ?? null,
            // 结束时间：已支付=支付完成时间；已取消=账单过期（付款期限）；已退款=退款时间；待支付不下发
            'finished_at' => $invoice['finished_at'] ?? null,
        ];
    }

    /**
     * 计费周期英文键（monthly / quarterly / semiannually / annually …）。
     * 账单表在迁移前的老数据可能为空，此时回落到关联订单的周期——订单才是「按商品周期下单」的原始记录。
     *
     * @param  array<string, mixed>  $invoice
     */
    private function resolveBillingCycle(array $invoice): string
    {
        $cycle = trim((string) ($invoice['billing_cycle'] ?? ''));
        if ($cycle !== '') {
            return $cycle;
        }

        return trim((string) ($invoice['order']['billing_cycle'] ?? ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function paymentSummary(mixed $payment): ?array
    {
        if (! is_array($payment)) {
            return null;
        }

        return [
            'id' => (int) ($payment['id'] ?? 0),
            'payment_no' => (string) ($payment['payment_no'] ?? ''),
            'gateway' => (string) ($payment['gateway'] ?? ''),
            'gateway_key' => (string) ($payment['gateway_key'] ?? ''),
            'gateway_label' => (string) ($payment['gateway_label'] ?? ''),
            'amount' => (string) ($payment['amount'] ?? '0.00'),
            'status' => (int) ($payment['status'] ?? 0),
            'status_label' => (string) ($payment['status_label'] ?? ''),
            'paid_at' => $payment['paid_at'] ?? null,
        ];
    }
}
