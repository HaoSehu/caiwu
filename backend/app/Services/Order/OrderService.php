<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\CouponService;
use App\Services\Finance\PaymentService;
use App\Services\Order\Concerns\HandlesOrderCalculation;
use App\Services\System\OperationLogService;
use App\Support\StockReservation;
use Illuminate\Support\Facades\DB;

class OrderService
{
    use HandlesOrderCalculation;

    // RANGE_TYPES / OS_TYPES / BILLING_CYCLE_MONTHS / TYPE_FIELD_MAP 已移入 HandlesOrderCalculation Trait

    public function __construct(
        private PaymentService $paymentService,
        private CouponService $couponService,
        private CheckoutSecurityService $checkoutSecurityService,
        private OperationLogService $operationLogService,
    ) {}

    /**
     * 取消订单
     */
    public function cancel(Order $order, array $context = []): Order
    {
        $updatedOrder = DB::transaction(function () use ($order, $context) {
            // 与 CheckoutService::cancel 保持一致的锁顺序：先锁 Invoice，再锁 Order，
            // 避免同一笔订单/账单经双入口并发取消时发生行锁死锁。
            $invoice = Invoice::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->first();

            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            throw_if(
                (int) $lockedOrder->status !== OrderStatus::PENDING,
                new BusinessException('仅待支付订单可取消')
            );

            $lockedOrder->setRelation('invoice', $invoice);
            if ($invoice instanceof Invoice) {
                throw_if(
                    ! in_array((int) $invoice->status, [InvoiceStatus::UNPAID, InvoiceStatus::CANCELLED], true),
                    new BusinessException('当前账单状态不支持取消订单')
                );

                $pendingPayments = Payment::query()
                    ->where('invoice_id', $invoice->id)
                    ->where('status', PaymentStatus::PENDING)
                    ->lockForUpdate()
                    ->get();

                foreach ($pendingPayments as $pendingPayment) {
                    // 组合支付（余额+网关）先行扣除了余额，取消时需把预扣余额退回。
                    if ($this->paymentService->restoreReservedMixBalance($pendingPayment, [
                        'trace_id' => (string) ($context['trace_id'] ?? ''),
                        'closed_reason' => 'order_cancelled',
                    ])) {
                        continue;
                    }

                    $callbackRaw = (array) ($pendingPayment->callback_raw ?? []);
                    $callbackRaw['closed_reason'] = 'order_cancelled';
                    $callbackRaw['closed_by'] = (string) ($context['actor_type'] ?? 'system');
                    $callbackRaw['trace_id'] = (string) ($context['trace_id'] ?? '');

                    $pendingPayment->forceFill([
                        'status' => PaymentStatus::CANCELLED,
                        'callback_raw' => $callbackRaw,
                    ])->save();
                    $this->paymentService->syncProjection($pendingPayment);
                }

                if ((int) $invoice->status !== InvoiceStatus::CANCELLED) {
                    $invoice->forceFill([
                        'status' => InvoiceStatus::CANCELLED,
                    ])->save();
                }
            }

            $lockedOrder->forceFill([
                'status' => OrderStatus::CANCELLED,
            ])->save();

            if ($invoice instanceof Invoice) {
                $this->couponService->syncInvoiceCouponUsage($invoice);
            }

            // 仅新购订单在创建时预扣库存，取消时按创建时实际预扣量对称恢复。
            if ((string) $lockedOrder->type === OrderType::NEW && $lockedOrder->product_id) {
                $product = Product::query()
                    ->lockForUpdate()
                    ->find($lockedOrder->product_id);

                if ($product instanceof Product) {
                    StockReservation::restore($product, $lockedOrder->config_snapshot, (int) ($lockedOrder->quantity ?? 1));
                }
            }

            return $lockedOrder->fresh(['user:id,email,nickname', 'product', 'invoice', 'service']) ?? $lockedOrder;
        });

        $actorType = trim((string) ($context['actor_type'] ?? 'system')) ?: 'system';
        $actorUserId = isset($context['actor_user_id']) ? (int) ($context['actor_user_id'] ?? 0) : 0;

        if ($actorType === 'client' && $actorUserId <= 0) {
            $actorUserId = (int) $updatedOrder->user_id;
        }

        $this->operationLogService->write(
            userId: $actorUserId > 0 ? $actorUserId : null,
            userType: $actorType,
            action: 'order.cancel',
            module: 'order',
            targetId: (int) $updatedOrder->id,
            detail: [
                'order_no' => (string) $updatedOrder->order_no,
                'order_type' => (string) $updatedOrder->type,
                'invoice_id' => (int) ($updatedOrder->invoice?->id ?? 0),
                'invoice_no' => (string) ($updatedOrder->invoice?->invoice_no ?? ''),
                'product_id' => (int) ($updatedOrder->product_id ?? 0),
                'product_name' => (string) $updatedOrder->display_product_name,
                'actor_name' => (string) ($context['actor_name'] ?? ''),
                'reason' => (string) ($context['reason'] ?? ''),
                'trace_id' => (string) ($context['trace_id'] ?? ''),
            ],
            ipAddress: ($context['ip_address'] ?? null) ? (string) $context['ip_address'] : null,
        );

        return $updatedOrder;
    }

    public function cancelExpiredPendingOrder(Order $order, array $context = []): Order
    {
        $freshOrder = $order->fresh(['invoice']) ?? $order;

        if ((int) $freshOrder->status !== OrderStatus::PENDING) {
            return $freshOrder;
        }

        if (! $this->checkoutSecurityService->isPaymentSessionExpired($freshOrder)) {
            return $freshOrder;
        }

        if (
            $freshOrder->invoice instanceof Invoice
            && ! in_array((int) $freshOrder->invoice->status, [InvoiceStatus::UNPAID, InvoiceStatus::CANCELLED], true)
        ) {
            return $freshOrder;
        }

        return $this->cancel($freshOrder, array_merge([
            'actor_type' => 'system',
            'actor_name' => 'payment-window-expired',
            'reason' => 'payment_window_expired',
        ], $context));
    }

    public function cancelExpiredPendingOrdersForUser(?int $userId = null, array $context = []): int
    {
        $threshold = now()->subSeconds(CheckoutSecurityService::paymentSessionTtlSeconds());
        $query = Order::query()
            ->where('status', OrderStatus::PENDING)
            ->where('created_at', '<=', $threshold);

        if ($userId !== null && $userId > 0) {
            $query->where('user_id', $userId);
        }

        $count = 0;

        $query->chunkById(100, function ($orders) use (&$count, $context): void {
            foreach ($orders as $order) {
                // 管理员手动开通产生的挂账订单按 due_date 计费，不受 5 分钟支付会话窗口约束；
                // 且这类订单从未扣过库存，被取消会触发 stock+1 造成库存虚增。
                if ($this->isAdminManualOrder($order)) {
                    continue;
                }

                $updated = $this->cancelExpiredPendingOrder($order, $context);
                if ((int) $updated->status === OrderStatus::CANCELLED) {
                    $count++;
                }
            }
        });

        return $count;
    }

    private function isAdminManualOrder(Order $order): bool
    {
        $snapshot = $order->config_snapshot;
        if (! is_array($snapshot)) {
            return false;
        }

        return filter_var($snapshot['admin_manual'] ?? false, FILTER_VALIDATE_BOOL);
    }

    /**
     * 查询订单列表 (管理端)
     */
    public function adminList(array $filters, int $perPage = 20)
    {
        $query = Order::with(['user:id,email,nickname', 'product:id,product_type,service_type_code,product_group_id,config_options,purchase_requires']);

        if (! empty($filters['order_no'])) {
            $query->where('order_no', $filters['order_no']);
        }
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function adminDetail(int $id): Order
    {
        return $this->loadAdminOrder($id);
    }

    private function assertPurchaseRequires(Product $product, int $userId): void
    {
        $requires = (array) ($product->purchase_requires ?? []);

        $user = User::find($userId);
        if (! $user) {
            return;
        }

        throw_if(
            ! $user->hasCompletedVerification(),
            new BusinessException('该商品需要实名认证后才能购买，请先完成实名认证', 40301)
        );

        if (! empty($requires['require_phone'])) {
            throw_if(
                empty(trim((string) ($user->phone ?? ''))),
                new BusinessException('该商品需要绑定手机号后才能购买，请先添加手机号', 40302)
            );
        }
    }

    private function resolvePaymentGatewayLabel(string $gateway): string
    {
        return match ($gateway) {
            // 与 PaymentGatewayCode::LABELS 一致的词条统一走集中定义，避免多处文案漂移。
            PaymentGatewayCode::ALIPAY,
            PaymentGatewayCode::YIPAY,
            PaymentGatewayCode::WECHAT,
            PaymentGatewayCode::BALANCE => PaymentGatewayCode::label($gateway),
            // 业务侧扩展口径与本地历史文案不属于集中 LABELS，保留原样。
            'free' => '免支付',
            'bank_transfer' => '银行转账',
            'offline' => '线下支付',
            default => '手动入账',
        };
    }

    private function loadAdminOrder(int $orderId): Order
    {
        $order = Order::query()
            ->with([
                'user:id,email,nickname',
                'product',
                'payments' => fn ($query) => $query->orderByDesc('id'),
                'invoice.payments' => fn ($query) => $query->orderByDesc('id'),
                'service',
            ])
            ->findOrFail($orderId);

        $order->setAttribute('payment_summary', $this->buildPaymentSummary($order));
        $order->invoice?->unsetRelation('payments');

        return $order;
    }

    private function buildPaymentSummary(Order $order): ?array
    {
        $payments = $order->relationLoaded('payments')
            ? $order->payments
            : collect();

        if ($payments->isEmpty() && $order->invoice?->relationLoaded('payments')) {
            $payments = $order->invoice->payments;
        }

        $payments = collect($payments)
            ->filter(fn (Payment $payment) => $payment->isThirdPartyGateway())
            ->values();

        $payment = $payments
            ->first(fn (Payment $item) => ! (bool) data_get((array) ($item->callback_raw ?? []), 'duplicate_paid', false)
                && in_array((int) $item->status, [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED], true))
            ?? $payments->first(fn (Payment $item) => in_array((int) $item->status, [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED], true));

        if (! $payment instanceof Payment) {
            return null;
        }

        $refund = (array) data_get((array) ($payment->callback_raw ?? []), 'refund', []);

        return [
            'id' => (int) $payment->id,
            'payment_no' => (string) $payment->payment_no,
            'trade_no' => (string) ($payment->trade_no ?? ''),
            'gateway' => $payment->gatewayKey(),
            'gateway_key' => $payment->gatewayKey(),
            'gateway_label' => $this->resolvePaymentGatewayLabel($payment->gatewayKey()),
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'status' => (int) $payment->status,
            'paid_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
            'refund_method' => (string) ($refund['refund_method'] ?? ''),
            'refund_method_label' => (string) ($refund['refund_method_label'] ?? ''),
            'refund_amount' => (string) ($refund['refund_amount'] ?? ''),
            'refund_reason' => (string) ($refund['refund_reason'] ?? ''),
            'refunded_at' => (string) ($refund['refunded_at'] ?? ($refund['gmt_refund_pay'] ?? '')),
            'out_request_no' => (string) ($refund['out_request_no'] ?? ''),
        ];
    }
}
