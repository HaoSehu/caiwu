<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Constants\PaymentStatus;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Services\ProductCatalog\ProductFullPathResolver;
use App\Support\Concerns\FiltersDateRange;
use App\Support\LikeEscape;
use App\Support\Pagination;
use App\Support\PaymentChannelLabel;
use Illuminate\Database\Eloquent\Collection;

class ClientOrderQueryService
{
    use FiltersDateRange;

    public function __construct(
        private readonly OrderService $orders,
        private readonly ProductFullPathResolver $productFullPathResolver,
        private readonly CheckoutSecurityService $checkoutSecurityService,
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

        $paginator = $query->paginate(Pagination::clamp((int) ($filters['page_size'] ?? 15)));

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
     * @return array<int|string, \Closure|string>
     */
    private function relations(): array
    {
        return [
            'payments' => self::successfulPaymentQuery(),
            // 账单同时承载两种渠道证据：账单支付单（渠道权威来源）
            // 与余额支付的扣款台账。这里一次给出完整关系，避免对同一关系重复 with()。
            'invoice' => static fn ($query) => $query
                ->select(['id', 'invoice_no', 'order_id', 'type', 'status', 'amount', 'paid_amount', 'paid_at', 'due_date', 'created_at'])
                ->with([
                    'payments' => self::successfulPaymentQuery(),
                    'ledgerEntries:id,source_type,source_id,event_type,change_amount,operator_type,trace_id,remark',
                ]),
            'service:id,name,domain,status,expires_at',
            'coupon:id,code,name,discount_type,discount_value',
            'product:id,product_type,service_type_code,product_group_id,remark,config_options,purchase_requires',
            'product.productGroup:id,second_product_group_id,name',
            'product.productGroup.secondProductGroup:id,first_product_group_id,name',
            'product.productGroup.secondProductGroup.firstProductGroup:id,code,name',
        ];
    }

    /**
     * 可展示支付方式的支付单查询（成功/已退款里取最新一条，与账单侧同口径）。
     * 投影走 gatewayProjectionColumns() 而刻意不取 callback_raw：它含网关签名与买家标识；
     * 历史支付单的渠道只写在回调里（gateway_key 为空），因此随行预取 payment_callbacks
     * 供渠道还原使用——该表只有渠道标记，不含网关签名与买家标识。
     *
     * 注意：payments.order_id 是冗余字段，不保证与实际订单一致（见 Payment 模型注释），
     * 存量里就有只挂 invoice_id 的成功支付单，所以订单侧渠道以账单支付单为准，
     * 订单支付单只作兼容回落，两者都为空时再回落账单扣款台账。
     */
    /**
     * 主支付单：成功/已退款里取最新一条，并跳过「重复支付转余额」的记录。
     *
     * 与 InvoiceService::resolvePrimaryInvoicePayment() / UserService 同一口径：
     * 重复支付（duplicate_paid）的钱已退回用户余额，不能代表这笔账单实际走的渠道。
     * 订单侧若直接 first() 会取到它，导致订单与账单显示不同渠道。
     *
     * @param  iterable<Payment>  $payments  已按 id 倒序
     */
    private static function primaryPayment(iterable $payments): ?Payment
    {
        $collection = collect($payments)
            ->filter(static fn (Payment $payment): bool => in_array(
                (int) $payment->status,
                [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED],
                true
            ))
            ->values();

        return $collection->first(static fn (Payment $payment): bool => ! (bool) data_get(
            (array) ($payment->callback_raw ?? []),
            'duplicate_paid',
            false
        )) ?? $collection->first();
    }

    private static function successfulPaymentQuery(): \Closure
    {
        // invoice_id / order_id 是 Eloquent 归组用的外键：投影里缺任一侧，
        // 嵌套加载（invoice.payments）或订单侧加载都会把行全丢掉。
        return static fn ($query) => $query
            ->select(Payment::gatewayProjectionColumns(['id', 'order_id', 'invoice_id', 'status', 'amount', 'paid_at']))
            ->with('callbacks:payment_id,callback_type,payload_json')
            ->whereIn('status', [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED])
            ->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(Order $order): array
    {
        $coupon = $order->coupon;
        $paymentChannel = $this->paymentChannelLabel($order);

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
            // 支付窗口是否已关闭随订单下发：前端订单列表/详情的「去支付」入口据此与账单侧（payment_window_expired）保持一致
            'invoice' => $order->invoice ? [
                'id' => (int) $order->invoice->id,
                'invoice_no' => (string) $order->invoice->invoice_no,
                'status' => (int) $order->invoice->status,
                'amount' => number_format((float) $order->invoice->amount, 2, '.', ''),
                'payment_window_expired' => $this->checkoutSecurityService->isPaymentSessionExpired($order->invoice),
            ] : null,
            // 支付渠道（用户端只读渠道名）：gateway_label 是既有列名，channel_name 是与管理端/账单侧
            // 同一语义的新字段。插件名（plugin_name）属于管理端与日志维度，不下发到用户端。
            'gateway_label' => $paymentChannel,
            'channel_name' => $paymentChannel,
            'paid_at' => $order->paid_at?->format('Y-m-d H:i:s'),
            // 结束时间按状态取值：已支付=支付完成时间；已取消=账单过期（付款期限）；已退款=退款时间。
            // 待支付仍在进行中，不下发（前端显示占位符）。
            'finished_at' => $this->finishedAt($order),
            'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 订单「结束时间」：状态决定取值口径，未结束（待支付）返回 null。
     * 已取消的订单没有独立的取消时间列，以记录最后更新时间（手动取消 / 过期回收的处理时间）作为结束时间。
     */
    private function finishedAt(Order $order): ?string
    {
        $time = match ((int) $order->status) {
            OrderStatus::PAID => $order->paid_at,
            OrderStatus::CANCELLED => $order->updated_at,
            OrderStatus::REFUNDED => $order->refunded_at ?? $order->paid_at,
            default => null,
        };

        return $this->formatTime($time);
    }

    /**
     * 时间统一输出（兼容 Carbon / 字符串，空值 null），避免对可能为字符串的字段直接调 format()。
     */
    private function formatTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * 订单的支付渠道名：以账单支付单（invoice.payments）为准，订单侧支付单作为回落，
     * 两者都没有时再回落账单扣款台账——余额支付不产生 Payment，渠道只落在台账上。
     *
     * 为什么账单支付单优先：payments.order_id 是冗余字段，不保证与实际订单一致
     * （见 Payment 模型注释），而 invoice_id 才是资金的权威关联；先看订单侧会与账单侧口径分叉，
     * 出现同一订单列表显示 A 渠道、账单显示 B 渠道的情况。订单侧只用于兼容
     * 账单关系缺失（无 invoice / 账单支付单未加载）的历史数据。
     *
     * 未支付、或支付已关闭的订单返回空串，由前端统一兜底为占位符。
     */
    private function paymentChannelLabel(Order $order): string
    {
        $invoice = $order->relationLoaded('invoice') ? $order->invoice : null;

        $payment = null;

        // 先取账单支付单：invoice.payments 是权威关联（invoice_id 才是资金的权威关联）。
        if ($invoice instanceof Invoice && $invoice->relationLoaded('payments')) {
            /** @var Collection<int, Payment> $invoicePayments */
            $invoicePayments = $invoice->payments;
            $payment = self::primaryPayment($invoicePayments);
        }

        // 账单侧为空时回落到订单侧支付单（order_id 冗余，仅作兼容）。
        if (! $payment instanceof Payment && $order->relationLoaded('payments')) {
            /** @var Collection<int, Payment> $orderPayments */
            $orderPayments = $order->payments;
            $payment = self::primaryPayment($orderPayments);
        }

        if ($payment instanceof Payment) {
            return PaymentChannelLabel::channelNameFromPayment($payment);
        }

        if (! $invoice instanceof Invoice) {
            return '';
        }

        // 台账证据同样要走既有口径：只对已入账的账单派生，避免把待支付账单说成余额支付。
        if (! in_array((int) $invoice->status, [InvoiceStatus::PAID, InvoiceStatus::REFUNDED], true)) {
            return '';
        }

        return PaymentChannelLabel::channelNameFromLedger($invoice->ledgerEntries);
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
            'payment_window_expired' => $this->checkoutSecurityService->isPaymentSessionExpired($order->invoice),
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
