<?php

namespace App\Http\Controllers\Client;

use App\Constants\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
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
                'invoice:id,order_id,invoice_no,status,amount,paid_amount,paid_at',
                'product:id,product_type,product_group_id',
                'product.categoryMapping:id,name',
                'service:id,name,status',
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

        $items = collect($paginator->items())->map(fn (Order $order) => [
            'id' => (int) $order->id,
            'order_no' => (string) $order->order_no,
            'type' => (string) $order->type,
            'type_label' => $order->type === 'renew' ? '续费' : '新购',
            'status' => (int) $order->status,
            'status_label' => OrderStatus::$labels[(int) $order->status] ?? '未知',
            'amount' => number_format((float) $order->amount, 2, '.', ''),
            'discount' => number_format((float) $order->discount, 2, '.', ''),
            'paid_amount' => number_format((float) $order->paid_amount, 2, '.', ''),
            'billing_cycle' => (string) ($order->billing_cycle ?? ''),
            'product_name' => (string) ($order->display_product_name ?: ($order->product?->categoryMapping?->name ?? '')),
            'service_name' => (string) ($order->service?->name ?? ''),
            'invoice_id' => (int) ($order->invoice?->id ?? 0),
            'invoice_no' => (string) ($order->invoice?->invoice_no ?? ''),
            'invoice_status' => $order->invoice ? (int) $order->invoice->status : null,
            'paid_at' => $order->paid_at?->format('Y-m-d H:i:s'),
            'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
        ])->values()->all();

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
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [OrderStatus::COMPLETED])
            ->first();

        return $this->success([
            'total' => (int) ($row?->total ?? 0),
            'pending' => (int) ($row?->pending ?? 0),
            'paid' => (int) ($row?->paid ?? 0),
            'completed' => (int) ($row?->completed ?? 0),
        ]);
    }
}
