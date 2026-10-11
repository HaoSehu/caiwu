<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Constants\RefundStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\ProductCatalog\ProductFullPathResolver;
use App\Support\AdminPrivacy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOrderSummaryResource extends JsonResource
{
    /**
     * 依赖显式注入（B6：Resource 不再经 app() 服务定位器隐式解析，调用方负责传入）。
     */
    public function __construct($resource, protected readonly ProductFullPathResolver $productFullPathResolver)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;

        return [
            'id' => (int) $order->id,
            'order_no' => (string) $order->order_no,
            'user_id' => (int) $order->user_id,
            'user' => $this->user($order),
            'invoice_id' => $order->invoice ? (int) $order->invoice->id : null,
            'invoice' => $this->invoice($order),
            'product_id' => (int) ($order->product_id ?? 0),
            'product_name' => $this->productName($order),
            'product_full_path' => $this->productPath($order),
            'product_type' => (string) ($order->product_type_snapshot ?? $order->product?->product_type ?? ''),
            'service' => $this->service($order),
            'type' => (string) $order->type,
            'type_label' => OrderType::label((string) $order->type),
            'status' => (int) $order->status,
            'status_label' => OrderStatus::$labels[(int) $order->status] ?? (string) $order->status,
            'amount' => $this->money($order->amount),
            'discount' => $this->money($order->discount),
            // 折扣分列：券减免（discount）与会员折扣（member_discount_amount）来源不同
            'member_discount_amount' => $this->money($order->member_discount_amount),
            'coupon_code' => (string) ($order->coupon_code ?? ''),
            'paid_amount' => $this->money($order->paid_amount),
            // 退款投影（订单侧与账单同值，由 TradeLifecycleService 同步写）
            'refund_amount' => $this->money($order->refund_amount),
            'refunded_at' => self::formatRefundTime($order->refunded_at),
            'refund_status' => $this->refundStatus($order),
            'billing_cycle' => (string) ($order->billing_cycle ?? ''),
            'quantity' => (int) ($order->quantity ?? 1),
            'paid_at' => $order->paid_at?->format('Y-m-d H:i:s'),
            'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 订单退款状态派生（RefundStatus，不落库）：已收口 REFUNDED 即全额；
     * 否则按累计退款额判定部分退款（存量真实退款依赖 P9 回填命令对齐）。
     */
    protected function refundStatus(Order $order): int
    {
        if ((int) $order->status === OrderStatus::REFUNDED) {
            return RefundStatus::FULL;
        }

        return RefundStatus::derive((float) ($order->refund_amount ?? 0), (float) $order->paid_amount);
    }

    /**
     * 退款时间统一输出（兼容 Carbon/字符串，空值 null；避免新增 ?->format 调用
     * 扰动 phpstan-baseline 计数）。
     */
    protected static function formatRefundTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function user(Order $order): ?array
    {
        if (! $order->user) {
            return null;
        }

        $privacy = AdminPrivacy::current();

        return [
            'id' => (int) $order->user->id,
            'email' => $privacy->email($order->user->email),
            'nickname' => (string) ($order->user->nickname ?? ''),
            'phone' => $privacy->phone($order->user->phone),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function invoice(Order $order): ?array
    {
        if (! $order->invoice) {
            return null;
        }

        return [
            'id' => (int) $order->invoice->id,
            'invoice_no' => (string) $order->invoice->invoice_no,
            'status' => (int) $order->invoice->status,
            'amount' => $this->money($order->invoice->amount),
            'paid_amount' => $this->money($order->invoice->paid_amount),
            'paid_at' => $order->invoice->paid_at?->format('Y-m-d H:i:s'),
            // 关联账单退款投影（orders 无 invoice_id 列，经 HasOne 读取）
            'refund_amount' => $this->money($order->invoice->refund_amount),
            'refund_method' => (string) ($order->invoice->refund_method ?? ''),
            'refunded_at' => self::formatRefundTime($order->invoice->refunded_at),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function service(Order $order): ?array
    {
        if (! $order->service) {
            return null;
        }

        return [
            'id' => (int) $order->service->id,
            'name' => (string) $order->service->name,
            'domain' => (string) ($order->service->domain ?? ''),
            'status' => (int) $order->service->status,
            'expires_at' => $order->service->expires_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function productName(Order $order): string
    {
        return $this->productFullPathResolver->nameForOrder($order);
    }

    protected function productPath(Order $order): string
    {
        return $this->productFullPathResolver->pathForOrder($order);
    }

    protected function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function payments(Order $order): array
    {
        $payments = $order->invoice?->payments ?? collect();

        return collect($payments)
            ->filter(fn (Payment $payment): bool => $payment->isThirdPartyGateway())
            ->map(fn (Payment $payment): array => [
                'id' => (int) $payment->id,
                'payment_no' => (string) $payment->payment_no,
                'gateway' => $payment->gatewayKey(),
                'gateway_key' => $payment->gatewayKey(),
                'trade_no' => (string) ($payment->trade_no ?? ''),
                'amount' => $this->money($payment->amount),
                'status' => (int) $payment->status,
                'paid_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
                'trace_id' => (string) ($payment->trace_id ?? ''),
                'created_at' => $payment->created_at?->format('Y-m-d H:i:s'),
            ])
            ->values()
            ->all();
    }
}
