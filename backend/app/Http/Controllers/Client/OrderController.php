<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Order\IndexRequest;
use App\Models\Order;
use App\Services\Order\OrderService;
use App\Services\ProductCatalog\ProductFullPathResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly ?ProductFullPathResolver $productFullPathResolver = null,
    ) {}

    public function index(IndexRequest $request)
    {
        $filters = $request->validated();
        $userId = (int) $request->user()->id;

        $query = Order::query()
            ->with([
                'invoice:id,invoice_no,order_id,type,status,amount,paid_amount,paid_at',
                'service:id,name,status,expires_at',
                'coupon:id,code,name,type,value',
                'product:id,product_type,service_type_code,first_product_group_id,second_product_group_id,third_product_group_id,remark,config_options,purchase_requires',
                'product.firstProductGroup:id,code,name',
                'product.secondProductGroup:id,first_product_group_id,name',
                'product.thirdProductGroup:id,second_product_group_id,name',
            ])
            ->where('user_id', $userId)
            ->orderByDesc('id');

        if (($filters['status'] ?? null) !== null) {
            $query->where('status', (int) $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where('order_no', 'like', '%'.$keyword.'%');
        }
        $this->applyDateFilter($query, $filters);

        $perPage = (int) ($filters['page_size'] ?? 15);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(fn (Order $order) => $this->transformOrderListItem($order))->values()->all();

        return $this->success([
            'list' => $items,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ]);
    }

    public function summary(Request $request)
    {
        $userId = (int) $request->user()->id;

        $row = Order::query()
            ->where('user_id', $userId)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending', [OrderStatus::PENDING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS paid', [OrderStatus::PAID])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS processing', [OrderStatus::PROCESSING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [OrderStatus::COMPLETED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cancelled', [OrderStatus::CANCELLED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS refunded', [OrderStatus::REFUNDED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN COALESCE(amount, 0) - COALESCE(paid_amount, 0) ELSE 0 END) AS unpaid_amount', [OrderStatus::PENDING])
            ->first();

        $now = now();
        $monthAmount = Order::query()
            ->where('user_id', $userId)
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->sum('amount');

        return $this->success([
            'total' => (int) ($row?->total ?? 0),
            'pending' => (int) ($row?->pending ?? 0),
            'paid' => (int) ($row?->paid ?? 0),
            'processing' => (int) ($row?->processing ?? 0),
            'completed' => (int) ($row?->completed ?? 0),
            'cancelled' => (int) ($row?->cancelled ?? 0),
            'refunded' => (int) ($row?->refunded ?? 0),
            'unpaid_amount' => number_format((float) ($row?->unpaid_amount ?? 0), 2, '.', ''),
            'month_amount' => number_format((float) $monthAmount, 2, '.', ''),
        ]);
    }

    public function show(int $id, Request $request)
    {
        $userId = (int) $request->user()->id;

        $order = Order::query()
            ->with([
                'invoice:id,invoice_no,order_id,type,status,amount,paid_amount,paid_at,due_date,created_at',
                'service:id,name,domain,status,expires_at',
                'coupon:id,code,name,type,value',
                'product:id,product_type,service_type_code,first_product_group_id,second_product_group_id,third_product_group_id,remark,config_options,purchase_requires',
                'product.firstProductGroup:id,code,name',
                'product.secondProductGroup:id,first_product_group_id,name',
                'product.thirdProductGroup:id,second_product_group_id,name',
            ])
            ->where('user_id', $userId)
            ->findOrFail($id);

        $data = $this->transformOrderDetail($order);

        return $this->success($data);
    }

    public function cancel(int $id, Request $request)
    {
        $userId = (int) $request->user()->id;

        $order = Order::query()
            ->where('user_id', $userId)
            ->findOrFail($id);

        $this->orderService->cancel($order, [
            'actor_type' => 'client',
            'ip_address' => $request->ip(),
        ]);

        return $this->success(['message' => '订单已取消']);
    }

    private function transformOrderListItem(Order $order): array
    {
        return [
            'id' => (int) $order->id,
            'order_no' => (string) $order->order_no,
            'type' => (string) $order->type,
            'type_label' => $this->orderTypeLabel((string) $order->type),
            'status' => (int) $order->status,
            'status_label' => OrderStatus::$labels[(int) $order->status] ?? '未知',
            'amount' => number_format((float) $order->amount, 2, '.', ''),
            'paid_amount' => number_format((float) $order->paid_amount, 2, '.', ''),
            'discount' => number_format((float) $order->discount, 2, '.', ''),
            'billing_cycle' => (string) ($order->billing_cycle ?? ''),
            'quantity' => (int) ($order->quantity ?? 1),
            'product_name' => (string) ($order->display_product_name ?? $order->product_spec_snapshot ?? ''),
            'product_full_path' => $this->productFullPathResolver()->pathForOrder($order),
            'service_name' => (string) ($order->service?->name ?? ''),
            'invoice' => $order->invoice ? [
                'id' => (int) $order->invoice->id,
                'invoice_no' => (string) $order->invoice->invoice_no,
                'status' => (int) $order->invoice->status,
                'amount' => number_format((float) $order->invoice->amount, 2, '.', ''),
            ] : null,
            'paid_at' => $order->paid_at?->format('Y-m-d H:i:s'),
            'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function transformOrderDetail(Order $order): array
    {
        $base = $this->transformOrderListItem($order);
        $base['coupon'] = $order->coupon ? [
            'id' => (int) $order->coupon->id,
            'code' => (string) $order->coupon->code,
            'name' => (string) ($order->coupon->name ?? ''),
            'type' => (string) ($order->coupon->type ?? ''),
            'value' => (string) ($order->coupon->value ?? ''),
        ] : null;
        $base['coupon_code'] = (string) ($order->coupon_code ?? '');
        $base['remark'] = (string) ($order->remark ?? '');
        $base['service'] = $order->service ? [
            'id' => (int) $order->service->id,
            'name' => (string) $order->service->name,
            'domain' => (string) ($order->service->domain ?? ''),
            'status' => (int) $order->service->status,
            'expires_at' => $order->service->expires_at?->format('Y-m-d H:i:s'),
        ] : null;
        $base['invoice'] = $order->invoice ? [
            'id' => (int) $order->invoice->id,
            'invoice_no' => (string) $order->invoice->invoice_no,
            'type' => (string) ($order->invoice->type ?? ''),
            'status' => (int) $order->invoice->status,
            'amount' => number_format((float) $order->invoice->amount, 2, '.', ''),
            'paid_amount' => number_format((float) $order->invoice->paid_amount, 2, '.', ''),
            'paid_at' => $order->invoice->paid_at?->format('Y-m-d H:i:s'),
            'due_date' => $order->invoice->due_date?->format('Y-m-d H:i:s'),
            'created_at' => $order->invoice->created_at?->format('Y-m-d H:i:s'),
        ] : null;
        $base['config_snapshot'] = (array) ($order->config_snapshot ?? []);
        $base['config_pricing_snapshot'] = (array) ($order->config_pricing_snapshot ?? []);

        return $base;
    }

    private function orderTypeLabel(string $type): string
    {
        return OrderType::label($type);
    }

    private function applyDateFilter($query, array $filters): void
    {
        $start = trim((string) ($filters['start_date'] ?? ''));
        $end = trim((string) ($filters['end_date'] ?? ''));

        if ($start === '' && $end === '') {
            return;
        }

        if ($start !== '' && $end !== '') {
            $query->whereBetween('created_at', [
                CarbonImmutable::parse($start)->startOfDay(),
                CarbonImmutable::parse($end)->endOfDay(),
            ]);

            return;
        }

        if ($start !== '') {
            $query->where('created_at', '>=', CarbonImmutable::parse($start)->startOfDay());

            return;
        }

        $query->where('created_at', '<=', CarbonImmutable::parse($end)->endOfDay());
    }

    private function productFullPathResolver(): ProductFullPathResolver
    {
        return $this->productFullPathResolver ?? app(ProductFullPathResolver::class);
    }
}
