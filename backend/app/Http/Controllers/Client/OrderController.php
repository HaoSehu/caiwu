<?php

namespace App\Http\Controllers\Client;

use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Constants\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private ProductDisplayNameResolver $productDisplayNameResolver,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'in:new,renew'],
            'keyword' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Order::with([
                'invoice:id,order_id,invoice_no,type,status,amount,paid_amount,paid_at,due_date,created_at',
                'invoice.payments:id,invoice_id,payment_no,gateway,amount,status,paid_at,created_at',
                'payments:id,order_id,invoice_id,payment_no,gateway,amount,status,paid_at,created_at',
                'product:id,product_type,product_group_id,remark,config_options,purchase_requires',
                'product.categoryMapping:id,name,parent_group_id,product_type',
                'product.categoryMapping.parent:id,name,parent_group_id,product_type',
                'service:id,name,domain,status,expires_at',
            ])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id');

        if (($filters['status'] ?? null) !== null) {
            $query->where('status', (int) $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword) {
                $builder->where('order_no', 'like', '%'.$keyword.'%')
                    ->orWhere('product_spec_snapshot', 'like', '%'.$keyword.'%')
                    ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_no', 'like', '%'.$keyword.'%'))
                    ->orWhereHas('service', fn ($serviceQuery) => $serviceQuery->where('name', 'like', '%'.$keyword.'%'));
            });
        }

        $perPage = (int) ($filters['page_size'] ?? 15);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn (Order $order) => $this->transformOrder($order))
            ->values()
            ->all();

        return $this->success([
            'list' => $items,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $order = Order::with([
                'invoice:id,order_id,invoice_no,type,status,amount,paid_amount,paid_at,due_date,created_at',
                'invoice.payments:id,invoice_id,payment_no,gateway,amount,status,paid_at,created_at',
                'payments:id,order_id,invoice_id,payment_no,gateway,amount,status,paid_at,created_at',
                'product:id,product_type,product_group_id,remark,config_options,purchase_requires',
                'product.categoryMapping:id,name,parent_group_id,product_type',
                'product.categoryMapping.parent:id,name,parent_group_id,product_type',
                'service:id,name,domain,status,expires_at',
            ])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return $this->success($this->transformOrder($order));
    }

    public function summary(Request $request)
    {
        $userId = (int) $request->user()->id;

        $row = Order::query()
            ->where('user_id', $userId)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending', [OrderStatus::PENDING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS paid', [OrderStatus::PAID])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [OrderStatus::COMPLETED])
            ->first();

        return $this->success([
            'total' => (int) ($row?->total ?? 0),
            'pending' => (int) ($row?->pending ?? 0),
            'paid' => (int) ($row?->paid ?? 0),
            'completed' => (int) ($row?->completed ?? 0),
        ]);
    }

    private function transformOrder(Order $order): array
    {
        return [
            'id' => (int) $order->id,
            'order_no' => (string) $order->order_no,
            'type' => (string) $order->type,
            'type_label' => $this->orderTypeLabel((string) $order->type),
            'status' => (int) $order->status,
            'status_label' => OrderStatus::$labels[(int) $order->status] ?? '未知',
            'amount' => $this->money($order->amount),
            'discount' => $this->money($order->discount),
            'paid_amount' => $this->money($order->paid_amount),
            'billing_cycle' => (string) ($order->billing_cycle ?? ''),
            'quantity' => (int) ($order->quantity ?? 1),
            'product_id' => (int) ($order->product_id ?? 0),
            'product_name' => $this->resolveOrderProductName($order),
            'product_full_path' => $this->resolveOrderProductPath($order),
            'product_type' => (string) ($order->product_type_snapshot ?? $order->product?->product_type ?? ''),
            'service_id' => (int) ($order->service_id ?? $order->service?->id ?? 0),
            'service_name' => (string) ($order->service?->name ?? ''),
            'service' => $order->service ? [
                'id' => (int) $order->service->id,
                'name' => (string) $order->service->name,
                'domain' => (string) ($order->service->domain ?? ''),
                'status' => (int) $order->service->status,
                'expires_at' => $order->service->expires_at?->format('Y-m-d H:i:s'),
            ] : null,
            'invoice_id' => (int) ($order->invoice?->id ?? 0),
            'invoice_no' => (string) ($order->invoice?->invoice_no ?? ''),
            'invoice_status' => $order->invoice ? (int) $order->invoice->status : null,
            'invoice' => $order->invoice ? [
                'id' => (int) $order->invoice->id,
                'invoice_no' => (string) $order->invoice->invoice_no,
                'type' => (string) $order->invoice->type,
                'status' => (int) $order->invoice->status,
                'amount' => $this->money($order->invoice->amount),
                'paid_amount' => $this->money($order->invoice->paid_amount),
                'paid_at' => $order->invoice->paid_at?->format('Y-m-d H:i:s'),
            ] : null,
            'config_snapshot' => (array) ($order->config_snapshot ?? []),
            'config_pricing_snapshot' => (array) ($order->config_pricing_snapshot ?? []),
            'payments' => $this->resolveOrderPayments($order),
            'paid_at' => $order->paid_at?->format('Y-m-d H:i:s'),
            'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $order->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function resolveOrderPayments(Order $order): array
    {
        $payments = $order->invoice?->payments;
        if (! $payments || $payments->isEmpty()) {
            $payments = $order->payments;
        }

        return collect($payments)
            ->map(fn (Payment $payment) => [
                'id' => (int) $payment->id,
                'payment_no' => (string) $payment->payment_no,
                'gateway' => (string) $payment->gateway,
                'gateway_label' => $this->paymentGatewayLabel((string) $payment->gateway),
                'amount' => $this->money($payment->amount),
                'status' => (int) $payment->status,
                'status_label' => $this->paymentStatusLabel((int) $payment->status),
                'paid_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
                'created_at' => $payment->created_at?->format('Y-m-d H:i:s'),
            ])
            ->values()
            ->all();
    }

    private function resolveOrderProductName(Order $order): string
    {
        $snapshot = trim((string) ($order->display_product_name ?? $order->product_spec_snapshot ?? ''));
        if ($snapshot !== '' && $snapshot !== '未配置规格') {
            return $snapshot;
        }

        return $this->resolveProductName($order->product, (int) ($order->product_id ?? 0));
    }

    private function resolveOrderProductPath(Order $order): string
    {
        $product = $order->product;
        $productType = trim((string) ($order->product_type_snapshot ?? $product?->product_type ?? ''));
        $category = $product instanceof Product && $product->relationLoaded('categoryMapping')
            ? $product->categoryMapping
            : null;
        $parentCategory = $category && $category->relationLoaded('parent') ? $category->parent : null;

        $segments = [
            ProductType::labelOf($productType),
            trim((string) ($parentCategory?->name ?? '')),
            trim((string) ($category?->name ?? '')),
            $this->resolveOrderProductName($order),
        ];

        $clean = [];
        foreach ($segments as $segment) {
            $segment = trim((string) $segment);
            if ($segment === '' || $segment === '-' || in_array($segment, $clean, true)) {
                continue;
            }
            $clean[] = $segment;
        }

        return $clean !== [] ? implode('/', $clean) : $this->resolveOrderProductName($order);
    }

    private function resolveProductName(?Product $product, int $productId): string
    {
        if ($product instanceof Product) {
            $resolved = $this->productDisplayNameResolver->resolveForProduct($product);
            $name = trim((string) ($resolved['combined_display_name'] ?? $resolved['product_spec_display'] ?? $resolved['product_display_name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return $productId > 0 ? "产品 #{$productId}" : '未配置产品';
    }

    private function orderTypeLabel(string $type): string
    {
        return match ($type) {
            'new', 'normal' => '新购',
            'renew' => '续费',
            'upgrade' => '附加配置',
            default => $type !== '' ? $type : '普通',
        };
    }

    private function paymentGatewayLabel(string $gateway): string
    {
        return match ($gateway) {
            PaymentGatewayCode::ALIPAY => PaymentGatewayCode::label(PaymentGatewayCode::ALIPAY),
            'wechat' => '微信支付',
            'balance' => '余额支付',
            'free' => '免支付',
            'manual' => '手动入账',
            default => $gateway !== '' ? $gateway : '-',
        };
    }

    private function paymentStatusLabel(int $status): string
    {
        return match ($status) {
            PaymentStatus::SUCCESS => '已支付',
            PaymentStatus::FAILED => '失败',
            PaymentStatus::REFUNDED => '已退款',
            default => '待支付',
        };
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}
