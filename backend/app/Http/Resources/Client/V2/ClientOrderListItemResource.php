<?php

declare(strict_types=1);

namespace App\Http\Resources\Client\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 客户端订单列表项。
 *
 * 本 Resource 是 `ClientOrderQueryService::listItem()` 输出的**契约声明层**：
 * 字段值由 Service 计算后以数组传入（`new ClientOrderListItemResource($listItemArray)`），
 * 本类只负责把该结构显式化，使 Apifox 数据模型可以绑定它。
 *
 * 这样做的原因：`listItem()` 依赖主支付单选择、支付渠道三级降级、台账证据等
 * 复杂业务逻辑，把它复制进 Resource 会产生两份实现并随时间漂移。字段口径的
 * 唯一真源仍是 Service。
 *
 * @property array<string, mixed> $resource
 */
class ClientOrderListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        return [
            'id' => (int) ($item['id'] ?? 0),
            'order_no' => (string) ($item['order_no'] ?? ''),
            'type' => (string) ($item['type'] ?? ''),
            'type_label' => (string) ($item['type_label'] ?? ''),
            'status' => (int) ($item['status'] ?? 0),
            'status_label' => (string) ($item['status_label'] ?? ''),
            'amount' => (string) ($item['amount'] ?? '0.00'),
            'paid_amount' => (string) ($item['paid_amount'] ?? '0.00'),
            'discount' => (string) ($item['discount'] ?? '0.00'),
            'member_discount_amount' => (string) ($item['member_discount_amount'] ?? '0.00'),
            'coupon_code' => (string) ($item['coupon_code'] ?? ''),
            'coupon_name' => (string) ($item['coupon_name'] ?? ''),
            'billing_cycle' => (string) ($item['billing_cycle'] ?? ''),
            'quantity' => (int) ($item['quantity'] ?? 1),
            'product_name' => (string) ($item['product_name'] ?? ''),
            'product_full_path' => (string) ($item['product_full_path'] ?? ''),
            'service_name' => (string) ($item['service_name'] ?? ''),
            'invoice' => $this->invoice($item['invoice'] ?? null),
            'gateway_label' => (string) ($item['gateway_label'] ?? ''),
            'channel_name' => (string) ($item['channel_name'] ?? ''),
            'paid_at' => $item['paid_at'] ?? null,
            'finished_at' => $item['finished_at'] ?? null,
            'created_at' => $item['created_at'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function invoice(mixed $invoice): ?array
    {
        if (! is_array($invoice)) {
            return null;
        }

        return [
            'id' => (int) ($invoice['id'] ?? 0),
            'invoice_no' => (string) ($invoice['invoice_no'] ?? ''),
            'status' => (int) ($invoice['status'] ?? 0),
            'amount' => (string) ($invoice['amount'] ?? '0.00'),
            'payment_window_expired' => (bool) ($invoice['payment_window_expired'] ?? false),
        ];
    }
}
