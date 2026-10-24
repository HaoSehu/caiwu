<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Services\ProductCatalog\ProductFullPathResolver;
use App\Support\Concerns\FiltersDateRange;
use App\Support\LikeEscape;

class ClientOrderQueryService
{
    use FiltersDateRange;

    public function __construct(
        private readonly OrderService $orders,
        private readonly ProductFullPathResolver $productFullPathResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $expiredContext
     * @return array<string, mixed>
     */
    public function paginate(int $userId, array $filters, array $expiredContext): array
    {
        $this->orders->cancelExpiredPendingOrdersForUser($userId, $expiredContext);

        $query = Order::query()
            ->with($this->relations())
            ->where('user_id', $userId)
            ->orderByDesc('id');

        if (($filters['status'] ?? null) !== null) {
            $query->where('status', (int) $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['keyword'])) {
            $query->where('order_no', 'like', LikeEscape::contains(trim((string) $filters['keyword'])));
        }
        $this->applyDateFilter($query, $filters);

        $paginator = $query->paginate(max(1, min((int) ($filters['page_size'] ?? 15), 100)));

        // 订单缺 product_spec_snapshot 时，展示名与商品全路径会在 listItem() 内逐行回源解析；
        // 拆分商品的源商品、以及商品投影刻意未取的 custom_display_name 都是逐行单查，
        // 会随每页行数放大成 N 次往返（N+1）。按当页商品一次预载，行输出不变。
        $products = collect($paginator->items())
            ->map(static function (Order $order): ?Product {
                $product = $order->product;

                return $product instanceof Product ? $product : null;
            })
            ->filter();
        app(ProductDisplayNameResolver::class)->preloadSourceProducts($products);
        app(ProductDisplayNameResolver::class)->preloadCustomDisplayNames($products);

        return [
            'list' => collect($paginator->items())
                ->map(fn (Order $order): array => $this->listItem($order))
                ->values()
                ->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    /**
     * @param  array<string, mixed>  $expiredContext
     * @return array<string, int|string>
     */
    public function summary(int $userId, array $expiredContext): array
    {
        $this->orders->cancelExpiredPendingOrdersForUser($userId, $expiredContext);

        // 计数维度仍是订单（履约工单），金额口径收敛到账单（资金真源）。
        $row = Order::query()
            ->where('user_id', $userId)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending', [OrderStatus::PENDING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS paid', [OrderStatus::PAID])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cancelled', [OrderStatus::CANCELLED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS refunded', [OrderStatus::REFUNDED])
            ->first();

        $now = now();

        // 待付金额：未付账单的应付余额（部分支付的账单按 amount - paid_amount 计）。
        $unpaidAmount = Invoice::query()
            ->where('user_id', $userId)
            ->where('status', InvoiceStatus::UNPAID)
            ->selectRaw('SUM(GREATEST(COALESCE(amount, 0) - COALESCE(paid_amount, 0), 0)) AS unpaid_amount')
            ->value('unpaid_amount');

        // 本月消费：本月创建账单的应付金额合计（订单金额列是创建时快照，不再作为计算依据）。
        // 类型白名单与订单消费值域（new/renew/upgrade，normal 为存量新购读取兼容）对齐，
        // 充值/返利/扣款/退款红字/手工账单不属于消费，不得计入。
        $monthAmount = Invoice::query()
            ->where('user_id', $userId)
            ->whereIn('type', [InvoiceType::NEW_PURCHASE, InvoiceType::RENEW, InvoiceType::UPGRADE, 'normal'])
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->sum('amount');

        return [
            'total' => (int) ($row?->total ?? 0),
            'pending' => (int) ($row?->pending ?? 0),
            'paid' => (int) ($row?->paid ?? 0),
            'cancelled' => (int) ($row?->cancelled ?? 0),
            'refunded' => (int) ($row?->refunded ?? 0),
            'unpaid_amount' => number_format((float) ($unpaidAmount ?? 0), 2, '.', ''),
            'month_amount' => number_format((float) $monthAmount, 2, '.', ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $expiredContext
     * @return array<string, mixed>
     */
    public function detail(int $userId, int $orderId, array $expiredContext): array
    {
        $order = $this->findForUser($userId, $orderId);
        $this->orders->cancelExpiredPendingOrder($order, $expiredContext);

        return $this->detailItem($this->findForUser($userId, $orderId));
    }

    private function findForUser(int $userId, int $orderId): Order
    {
        return Order::query()
            ->with($this->relations())
            ->where('user_id', $userId)
            ->findOrFail($orderId);
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'invoice:id,invoice_no,order_id,type,status,amount,paid_amount,paid_at,due_date,created_at',
            'service:id,name,domain,status,expires_at',
            'coupon:id,code,name,discount_type,discount_value',
            'product:id,product_type,service_type_code,product_group_id,remark,config_options,purchase_requires',
            'product.productGroup:id,second_product_group_id,name',
            'product.productGroup.secondProductGroup:id,first_product_group_id,name',
            'product.productGroup.secondProductGroup.firstProductGroup:id,code,name',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(Order $order): array
    {
        $coupon = $order->coupon;

        return [
            'id' => (int) $order->id,
            'order_no' => (string) $order->order_no,
            'type' => (string) $order->type,
            'type_label' => OrderType::label((string) $order->type),
            'status' => (int) $order->status,
            'status_label' => OrderStatus::$labels[(int) $order->status] ?? '未知',
            'amount' => number_format((float) $order->amount, 2, '.', ''),
            'paid_amount' => number_format((float) $order->paid_amount, 2, '.', ''),
            'discount' => number_format((float) $order->discount, 2, '.', ''),
            // 折扣分列：券减免（discount）与会员折扣来源不同；券标识给出券码与券名
            'member_discount_amount' => number_format((float) ($order->member_discount_amount ?? 0), 2, '.', ''),
            'coupon_code' => (string) ($order->coupon_code ?? ''),
            'coupon_name' => $coupon instanceof Coupon ? (string) ($coupon->name ?? '') : '',
            'billing_cycle' => (string) ($order->billing_cycle ?? ''),
            'quantity' => (int) ($order->quantity ?? 1),
            'product_name' => (string) ($order->display_product_name ?? $order->product_spec_snapshot ?? ''),
            'product_full_path' => $this->productFullPathResolver->pathForOrder($order),
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

    /**
     * @return array<string, mixed>
     */
    private function detailItem(Order $order): array
    {
        $base = $this->listItem($order);
        $base['coupon'] = $order->coupon ? [
            'id' => (int) $order->coupon->id,
            'code' => (string) $order->coupon->code,
            'name' => (string) ($order->coupon->name ?? ''),
            'discount_type' => (string) ($order->coupon->discount_type ?? ''),
            'discount_value' => (string) ($order->coupon->discount_value ?? ''),
        ] : null;
        $base['coupon_code'] = (string) ($order->coupon_code ?? '');
        $base['remark'] = (string) ($order->remark ?? '');
        $base['service'] = $this->serviceSnapshot($order);
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

    /**
     * @return array<string, int|string>|null
     */
    private function serviceSnapshot(Order $order): ?array
    {
        $snapshot = $order->service_snapshot;

        if (is_array($snapshot) && $snapshot !== []) {
            return [
                'instance_id' => (int) ($snapshot['instance_id'] ?? 0),
                'hostname' => (string) ($snapshot['hostname'] ?? ''),
            ];
        }

        if ($order->service) {
            return [
                'instance_id' => (int) $order->service->id,
                'hostname' => (string) ($order->service->domain ?? ''),
            ];
        }

        return null;
    }
}
