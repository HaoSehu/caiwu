<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\OrderStatus;
use App\Constants\InvoiceStatus;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Order\Concerns\HandlesOrderCalculation;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderService
{
    use HandlesOrderCalculation;

    private const RANGE_TYPES = [4, 7, 9, 11, 14, 15, 16, 17, 18, 19];
    private const OS_TYPES = [5];
    private const BILLING_CYCLE_MONTHS = [
        'monthly' => 1,
        'quarterly' => 3,
        'semiannually' => 6,
        'annually' => 12,
    ];
    private const TYPE_FIELD_MAP = [
        4 => 'ip_num',
        5 => 'os',
        6 => 'cpu',
        7 => 'cpu',
        8 => 'memory',
        9 => 'memory',
        10 => 'bw',
        11 => 'bw',
        12 => 'area',
        13 => 'system_disk_size',
        14 => 'system_disk_size',
        16 => 'cpu',
        17 => 'memory',
        18 => 'bw',
        19 => 'system_disk_size',
    ];

    public function __construct(
        private InvoiceService $invoiceService,
        private PaymentService $paymentService,
        private ProductCatalogService $productCatalogService,
        private CheckoutSecurityService $checkoutSecurityService,
        private CouponService $couponService,
        private OperationLogService $operationLogService,
        private NotificationService $notificationService,
        private AdminOrderNotificationService $adminOrderNotificationService,
    ) {}

    /**
     * 创建新购订单
     */
    public function create(int $userId, array $data, array $context = []): Order
    {
        $productId = (int) ($data['product_id'] ?? 0);
        $billingCycle = trim((string) ($data['billing_cycle'] ?? ''));
        $quantity = max((int) ($data['quantity'] ?? 1), 1);
        $rawConfig = (array) ($data['config'] ?? []);
        $quoteToken = trim((string) ($data['quote_token'] ?? ''));
        $userCouponId = (int) ($data['user_coupon_id'] ?? 0);
        $idempotencyKey = trim((string) ($context['idempotency_key'] ?? ''));

        throw_if($productId <= 0, new BusinessException('商品信息错误'));
        throw_if($billingCycle === '', new BusinessException('计费周期不能为空'));
        throw_if($quoteToken === '', new BusinessException('报价凭证已失效，请刷新配置后重试'));
        throw_if($idempotencyKey === '', new BusinessException('请求缺少幂等标识，请刷新页面后重试'));

        $lockKey = 'lock:order:create:' . $userId . ':' . sha1($idempotencyKey);

        try {
            $order = Cache::lock($lockKey, 15)->block(5, function () use (
                $userId,
                $productId,
                $billingCycle,
                $quantity,
                $rawConfig,
                $quoteToken,
                $userCouponId,
                $idempotencyKey,
                $context
            ) {
                $idempotentOrderId = $this->checkoutSecurityService->resolveIdempotentOrderId($userId, $idempotencyKey);
                if ($idempotentOrderId) {
                    $existingOrder = $this->freshCheckoutOrder($idempotentOrderId);
                    if ($existingOrder) {
                        return $existingOrder;
                    }
                }

                return DB::transaction(function () use (
                    $userId,
                        $productId,
                        $billingCycle,
                        $quantity,
                        $rawConfig,
                        $quoteToken,
                        $userCouponId,
                    $idempotencyKey,
                    $context
                ) {
                    $product = Product::query()
                        ->lockForUpdate()
                        ->findOrFail($productId);

                    throw_if($product->status !== 1, new BusinessException('产品已下架'));
                    $product->loadMissing('supplier');
                    $this->productCatalogService->assertProductCanBeProvisioned($product, $quantity);

                    $this->assertPurchaseRequires($product, $userId);

                    $normalizedConfig = $this->normalizeConfig($product, $rawConfig);
                    $fingerprint = $this->checkoutSecurityService->buildCheckoutFingerprint(
                        $product->id,
                        $billingCycle,
                        $quantity,
                        $normalizedConfig,
                        $userCouponId
                    );

                    $recentOrderId = $this->checkoutSecurityService->resolveFingerprintOrderId($userId, $fingerprint);
                    if ($recentOrderId) {
                        $recentOrder = $this->freshCheckoutOrder($recentOrderId);
                        if ($recentOrder) {
                            $this->checkoutSecurityService->rememberCreatedOrder(
                                $userId,
                                $idempotencyKey,
                                $fingerprint,
                                $recentOrder->id
                            );

                            return $recentOrder;
                        }
                    }

                    $quote = $this->quote($product, $billingCycle, $normalizedConfig, $quantity);
                    $amount = (float) ($quote['total_amount'] ?? 0);
                    $configPricingSnapshot = $this->buildConfigPricingSnapshot($product, $billingCycle, $normalizedConfig, $quantity);
                    throw_if($amount <= 0, new BusinessException('无效的计费周期'));
                    $couponPayload = $this->couponService->reserveOwnedCouponForOrder($userCouponId, $userId, $product, $billingCycle, $amount, 'new');
                    $discountAmount = (float) ($couponPayload['discount_amount'] ?? 0);
                    $payableAmount = max($amount - $discountAmount, 0);

                    $this->checkoutSecurityService->assertQuoteToken(
                        $quoteToken,
                        $product->id,
                        $billingCycle,
                        $quantity,
                        $normalizedConfig,
                        $this->formatAmount($amount),
                        $this->formatAmount($payableAmount),
                        $couponPayload['user_coupon_id'] ?? $userCouponId
                    );

                    $order = Order::query()->create([
                        'order_no' => Order::generateOrderNo(),
                        'user_id' => $userId,
                        'product_id' => $product->id,
                        'product_name_snapshot' => (string) $product->name,
                        'product_type_snapshot' => (string) $product->product_type,
                        'coupon_id' => $couponPayload['id'] ?? null,
                        'user_coupon_id' => $couponPayload['user_coupon_id'] ?? null,
                        'coupon_code' => $couponPayload['code'] ?? null,
                        'type' => 'new',
                        'amount' => $amount,
                        'discount' => $discountAmount,
                        'billing_cycle' => $billingCycle,
                        'quantity' => $quantity,
                        'config_snapshot' => $normalizedConfig,
                        'config_pricing_snapshot' => $configPricingSnapshot,
                        'coupon_snapshot' => $couponPayload,
                        'status' => OrderStatus::PENDING,
                    ]);

                    $this->invoiceService->createFromOrder($order);

                    if ((int) $product->stock > 0) {
                        $product->decrement('stock', $quantity);
                    }

                    $this->checkoutSecurityService->rememberCreatedOrder(
                        $userId,
                        $idempotencyKey,
                        $fingerprint,
                        (int) $order->id
                    );

                    $this->operationLogService->write(
                        userId: $userId,
                        userType: 'client',
                        action: 'order.create',
                        module: 'order',
                        targetId: (int) $order->id,
                        detail: [
                            'order_no' => (string) $order->order_no,
                            'product_id' => (int) $product->id,
                            'product_name' => (string) $product->name,
                            'billing_cycle' => $billingCycle,
                            'quantity' => $quantity,
                            'amount' => $this->formatAmount($amount),
                            'discount' => $this->formatAmount($discountAmount),
                            'coupon_code' => (string) ($couponPayload['code'] ?? ''),
                            'quote_token_hash' => substr(hash('sha256', $quoteToken), 0, 16),
                            'idempotency_key_hash' => substr(hash('sha256', $idempotencyKey), 0, 16),
                            'trace_id' => (string) ($context['trace_id'] ?? ''),
                        ],
                        ipAddress: (string) ($context['ip_address'] ?? ''),
                    );

                    return $order->load('invoice');
                });
            });

            if ($order instanceof Order && $order->wasRecentlyCreated) {
                $this->adminOrderNotificationService->notifyOrderCreated($order);
            }

            return $order;
        } catch (LockTimeoutException) {
            throw new BusinessException('订单正在处理中，请勿重复提交');
        }
    }

    /**
     * 取消订单
     */
    public function cancel(Order $order, array $context = []): Order
    {
        $updatedOrder = DB::transaction(function () use ($order, $context) {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            throw_if(
                (int) $lockedOrder->status !== OrderStatus::PENDING,
                new BusinessException('仅待付款订单可取消')
            );

            $invoice = Invoice::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            $lockedOrder->setRelation('invoice', $invoice);
            if ($invoice instanceof Invoice) {
                throw_if(
                    ! in_array((int) $invoice->status, [InvoiceStatus::UNPAID, InvoiceStatus::OVERDUE, InvoiceStatus::CANCELLED], true),
                    new BusinessException('当前账单状态不支持取消订单')
                );

                $pendingPayments = Payment::query()
                    ->where('invoice_id', $invoice->id)
                    ->where('status', PaymentStatus::PENDING)
                    ->lockForUpdate()
                    ->get();

                foreach ($pendingPayments as $pendingPayment) {
                    $callbackRaw = (array) ($pendingPayment->callback_raw ?? []);
                    $callbackRaw['closed_reason'] = 'order_cancelled';
                    $callbackRaw['closed_by'] = (string) ($context['actor_type'] ?? 'system');
                    $callbackRaw['trace_id'] = (string) ($context['trace_id'] ?? '');

                    $pendingPayment->forceFill([
                        'status' => PaymentStatus::FAILED,
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

            $this->couponService->releaseOrderCoupon($lockedOrder);

            $lockedOrder->forceFill([
                'status' => OrderStatus::CANCELLED,
            ])->save();

            // 仅新购订单在创建时预扣库存，取消时恢复库存。
            if ((string) $lockedOrder->type === 'new' && $lockedOrder->product_id) {
                $product = Product::query()
                    ->lockForUpdate()
                    ->find($lockedOrder->product_id);

                if ($product instanceof Product && (int) $product->stock >= 0) {
                    $product->increment('stock', max((int) ($lockedOrder->quantity ?? 1), 1));
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

    /**
     * 查询订单列表 (管理端)
     */
    public function adminList(array $filters, int $perPage = 20)
    {
        $query = Order::with(['user:id,email,nickname', 'product:id,name']);

        if (!empty($filters['order_no'])) {
            $query->where('order_no', $filters['order_no']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function adminDetail(int $id): Order
    {
        return $this->loadAdminOrder($id);
    }

    public function updateManualPaymentStatus(Order $order, array $payload, array $context = []): Order
    {
        $order->loadMissing(['invoice', 'product.supplier', 'service', 'user.referrer']);

        return match ((string) ($payload['action'] ?? '')) {
            'mark_paid' => $this->markPaidManually($order, $payload, $context),
            'mark_unpaid' => $this->markUnpaidManually($order, $payload, $context),
            'refund' => $this->refundByPaymentMethod($order, $payload, $context),
            default => throw new BusinessException('不支持的支付状态操作'),
        };
    }

    private function assertPurchaseRequires(Product $product, int $userId): void
    {
        $requires = (array) ($product->purchase_requires ?? []);

        if (empty($requires)) {
            return;
        }

        $user = User::find($userId);
        if (! $user) {
            return;
        }

        if (! empty($requires['require_verification'])) {
            throw_if(
                (int) $user->is_verified !== 1,
                new BusinessException('该商品需要实名认证后才能购买，请先完成实名认证', 40301)
            );
        }

        if (! empty($requires['require_phone'])) {
            throw_if(
                empty(trim((string) ($user->phone ?? ''))),
                new BusinessException('该商品需要绑定手机号后才能购买，请先添加手机号', 40302)
            );
        }
    }

    private function markPaidManually(Order $order, array $payload, array $context): Order
    {
        $invoice = $order->invoice;

        throw_if(! $invoice instanceof Invoice, new BusinessException('订单未关联账单，无法修改支付状态'));
        throw_if((int) $order->status !== OrderStatus::PENDING, new BusinessException('仅待付款订单支持手动设为已支付'));
        throw_if(
            ! in_array((int) $invoice->status, [InvoiceStatus::UNPAID, InvoiceStatus::OVERDUE], true),
            new BusinessException('当前账单状态不支持手动设为已支付')
        );

        $paidAt = ! empty($payload['paid_at'])
            ? Carbon::parse((string) $payload['paid_at'])
            : now();
        $requestedAmount = round((float) ($payload['amount'] ?? $invoice->amount), 2);
        $payableAmount = round((float) $invoice->amount - (float) ($invoice->paid_amount ?? 0), 2);
        $paymentGateway = trim((string) ($payload['payment_gateway'] ?? 'manual')) ?: 'manual';
        $tradeNo = trim((string) ($payload['trade_no'] ?? ''));
        $sendEmail = (bool) ($payload['send_email'] ?? false);
        $remark = trim((string) ($payload['remark'] ?? ''));
        $syncBusinessFlow = (bool) ($payload['sync_business_flow'] ?? false);
        $paidAmount = $payableAmount;

        throw_if($payableAmount <= 0, new BusinessException('当前账单无需再入账'));
        throw_if(abs($requestedAmount - $payableAmount) > 0.00001, new BusinessException('当前仅支持按账单应付金额全额入账'));

        DB::transaction(function () use ($order, $invoice, $paidAt, $paidAmount, $paymentGateway, $tradeNo, $remark, $context, $sendEmail) {
            Payment::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', PaymentStatus::PENDING)
                ->update([
                    'status' => PaymentStatus::FAILED,
                ]);

            $payment = Payment::query()->create([
                'payment_no' => Payment::generatePaymentNo(),
                'user_id' => $order->user_id,
                'invoice_id' => $invoice->id,
                'gateway' => $paymentGateway,
                'trade_no' => $tradeNo !== '' ? $tradeNo : $this->generateManualTradeNo($order),
                'amount' => $paidAmount,
                'status' => PaymentStatus::SUCCESS,
                'callback_raw' => [
                    'source' => 'admin_manual',
                    'action' => 'mark_paid',
                    'payment_gateway' => $paymentGateway,
                    'remark' => $remark,
                    'send_email' => $sendEmail,
                    'operator_id' => (int) ($context['operator_id'] ?? 0),
                    'operator_name' => (string) ($context['operator_name'] ?? ''),
                    'trace_id' => (string) ($context['trace_id'] ?? ''),
                ],
                'paid_at' => $paidAt,
            ]);
            $this->paymentService->syncProjection($payment);

            $invoice->forceFill([
                'status' => InvoiceStatus::PAID,
                'paid_amount' => $paidAmount,
                'paid_at' => $paidAt,
            ])->save();

            $order->forceFill([
                'status' => OrderStatus::PAID,
                'paid_amount' => $paidAmount,
                'paid_at' => $paidAt,
            ])->save();
        });

        if ($syncBusinessFlow) {
            $this->paymentService->handlePaidInvoice($invoice, $this->buildManualTraceId($order, $context, 'paid'));
        }

        $updatedOrder = $this->freshAdminOrder($order);

        if ($sendEmail) {
            $this->sendManualPaymentEmail($updatedOrder, $remark, $paymentGateway, $tradeNo !== '' ? $tradeNo : null);
        }

        $this->operationLogService->write(
            userId: ((int) ($context['operator_id'] ?? 0)) ?: null,
            userType: 'admin',
            action: 'order.payment.mark_paid',
            module: 'order',
            targetId: $updatedOrder->id,
            detail: [
                'order_no' => $updatedOrder->order_no,
                'invoice_id' => $invoice->id,
                'paid_amount' => $paidAmount,
                'paid_at' => $paidAt->format('Y-m-d H:i:s'),
                'payment_gateway' => $paymentGateway,
                'trade_no' => $tradeNo,
                'send_email' => $sendEmail,
                'sync_business_flow' => $syncBusinessFlow,
                'remark' => $remark,
                'operator_name' => (string) ($context['operator_name'] ?? ''),
            ],
            ipAddress: (string) ($context['ip_address'] ?? ''),
        );

        return $updatedOrder;
    }

    private function markUnpaidManually(Order $order, array $payload, array $context): Order
    {
        $invoice = $order->invoice;

        throw_if(! $invoice instanceof Invoice, new BusinessException('订单未关联账单，无法修改支付状态'));
        throw_if((int) $order->status !== OrderStatus::PAID, new BusinessException('仅已付款订单支持恢复为未支付'));
        throw_if((int) ($order->service_id ?? 0) > 0, new BusinessException('订单已进入服务流程，不支持恢复为未支付'));

        $hasRealSuccessPayment = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', PaymentStatus::SUCCESS)
            ->where('gateway', '!=', 'manual')
            ->exists();

        throw_if($hasRealSuccessPayment, new BusinessException('存在真实支付记录，不能直接恢复为未支付'));

        $manualSuccessPayments = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('gateway', 'manual')
            ->where('status', PaymentStatus::SUCCESS);

        throw_if(! $manualSuccessPayments->exists(), new BusinessException('仅支持回退后台手动设为已支付的订单'));

        $remark = trim((string) ($payload['remark'] ?? ''));

        DB::transaction(function () use ($order, $invoice, $manualSuccessPayments) {
            $payments = $manualSuccessPayments->get();
            $manualSuccessPayments->update([
                'status' => PaymentStatus::FAILED,
                'paid_at' => null,
            ]);
            foreach ($payments as $payment) {
                $payment->refresh();
                $this->paymentService->syncProjection($payment);
            }

            $invoice->forceFill([
                'status' => InvoiceStatus::UNPAID,
                'paid_amount' => 0,
                'paid_at' => null,
            ])->save();

            $order->forceFill([
                'status' => OrderStatus::PENDING,
                'paid_amount' => 0,
                'paid_at' => null,
            ])->save();
        });

        $updatedOrder = $this->freshAdminOrder($order);

        $this->operationLogService->write(
            userId: ((int) ($context['operator_id'] ?? 0)) ?: null,
            userType: 'admin',
            action: 'order.payment.mark_unpaid',
            module: 'order',
            targetId: $updatedOrder->id,
            detail: [
                'order_no' => $updatedOrder->order_no,
                'invoice_id' => $invoice->id,
                'remark' => $remark,
                'operator_name' => (string) ($context['operator_name'] ?? ''),
            ],
            ipAddress: (string) ($context['ip_address'] ?? ''),
        );

        return $updatedOrder;
    }

    private function refundByPaymentMethod(Order $order, array $payload, array $context): Order
    {
        $result = $this->paymentService->refundOrder($order, $payload, $context);
        $updatedOrder = $this->freshAdminOrder($order);

        if (($result['already_refunded'] ?? false) !== true) {
            $refund = (array) ($result['refund'] ?? []);

            $this->operationLogService->write(
                userId: ((int) ($context['operator_id'] ?? 0)) ?: null,
                userType: 'admin',
                action: 'order.payment.refund',
                module: 'order',
                targetId: $updatedOrder->id,
                detail: [
                    'order_no' => $updatedOrder->order_no,
                    'invoice_id' => (int) ($updatedOrder->invoice?->id ?? 0),
                    'payment_id' => (int) ($result['payment_id'] ?? 0),
                    'refund_method' => (string) ($refund['refund_method'] ?? ''),
                    'refund_amount' => (string) ($refund['refund_amount'] ?? ''),
                    'refund_reason' => (string) ($refund['refund_reason'] ?? ''),
                    'out_request_no' => (string) ($refund['out_request_no'] ?? ''),
                    'trade_no' => (string) ($refund['trade_no'] ?? ''),
                    'operator_name' => (string) ($context['operator_name'] ?? ''),
                ],
                ipAddress: (string) ($context['ip_address'] ?? ''),
            );
        }

        return $updatedOrder;
    }

    private function freshAdminOrder(Order $order): Order
    {
        return $this->loadAdminOrder((int) $order->id);
    }

    private function freshCheckoutOrder(int $orderId): ?Order
    {
        return Order::query()
            ->with(['product:id,name', 'invoice', 'service'])
            ->where('status', '!=', OrderStatus::CANCELLED)
            ->find($orderId);
    }

    private function buildManualTraceId(Order $order, array $context, string $suffix): string
    {
        $traceId = trim((string) ($context['trace_id'] ?? ''));

        if ($traceId !== '') {
            return "manual:{$suffix}:{$traceId}";
        }

        return "manual:{$suffix}:order:{$order->id}";
    }

    private function generateManualTradeNo(Order $order): string
    {
        return 'MANUAL-' . $order->id . '-' . Str::upper(Str::random(12));
    }

    private function sendManualPaymentEmail(Order $order, string $remark, string $paymentGateway, ?string $tradeNo = null): void
    {
        $order->loadMissing(['user', 'invoice']);

        $email = trim((string) ($order->user?->email ?? ''));
        $invoiceNo = trim((string) ($order->invoice?->invoice_no ?? ''));

        if ($email === '' || $invoiceNo === '') {
            return;
        }

        try {
            $this->notificationService->sendTemplateEmail($email, NotificationService::TEMPLATE_MANUAL_PAYMENT_CONFIRM, [
                'invoice_no' => $invoiceNo,
                'order_no' => (string) $order->order_no,
                'paid_amount' => number_format((float) ($order->paid_amount ?? 0), 2, '.', ''),
                'payment_method' => $this->resolvePaymentGatewayLabel($paymentGateway),
                'paid_at' => $order->paid_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
                'trade_no' => $tradeNo ?? '',
                'remark' => $remark,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('[订单手动入账] 支付确认邮件发送失败', [
                'order_id' => $order->id,
                'invoice_id' => $order->invoice?->id,
                'email' => $email,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function resolvePaymentGatewayLabel(string $gateway): string
    {
        return match ($gateway) {
            'alipay' => '支付宝支付',
            'wechat' => '微信支付',
            'balance' => '余额支付',
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
                'invoice.payments' => fn ($query) => $query->orderByDesc('id'),
                'service',
            ])
            ->findOrFail($orderId);

        $order->setAttribute('payment_summary', $this->buildPaymentSummary($order->invoice));
        $order->invoice?->unsetRelation('payments');

        return $order;
    }

    private function buildPaymentSummary(?Invoice $invoice): ?array
    {
        if (! $invoice || ! $invoice->relationLoaded('payments')) {
            return null;
        }

        $payment = $invoice->payments
            ->first(fn (Payment $item) => ! (bool) data_get((array) ($item->callback_raw ?? []), 'duplicate_paid', false)
                && in_array((int) $item->status, [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED], true))
            ?? $invoice->payments->first(fn (Payment $item) => in_array((int) $item->status, [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED], true));

        if (! $payment instanceof Payment) {
            return null;
        }

        $refund = (array) data_get((array) ($payment->callback_raw ?? []), 'refund', []);

        return [
            'id' => (int) $payment->id,
            'payment_no' => (string) $payment->payment_no,
            'trade_no' => (string) ($payment->trade_no ?? ''),
            'gateway' => (string) $payment->gateway,
            'gateway_label' => $this->resolvePaymentGatewayLabel((string) $payment->gateway),
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
