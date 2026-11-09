<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\MixPaymentService;
use App\Services\Finance\PaymentCallbackProjector;
use App\Services\Finance\TradeLifecycleService;
use App\Services\Order\Concerns\HandlesOrderCalculation;
use App\Services\System\OperationLogService;
use App\Support\InvoicePaymentSummary;
use App\Support\PaymentChannelLabel;
use Illuminate\Support\Facades\DB;

class OrderService
{
    use HandlesOrderCalculation;

    // RANGE_TYPES / OS_TYPES / BILLING_CYCLE_MONTHS / TYPE_FIELD_MAP 已移入 HandlesOrderCalculation Trait

    public function __construct(
        private MixPaymentService $mixPaymentService,
        private CheckoutSecurityService $checkoutSecurityService,
        private OperationLogService $operationLogService,
        private PaymentCallbackProjector $callbackProjector,
        private TradeLifecycleService $tradeLifecycleService,
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

            // 补录订单纠错通道（M-57c）：补录订单（config_snapshot.admin_manual）入账后即 PAID，
            // 标准取消闸只放行 PENDING，导致一次金额/周期填错就永久占库存、且账单没有纠错入口。
            // 只对管理员开放，且订单名下不得有任何第三方网关支付单（真实资金未进系统）；
            // 无账单的 PAID 订单不属于补录路径（补录必然先建订单再建账单），维持原拒绝语义。
            $adminManualPaidCancel = (int) $lockedOrder->status === OrderStatus::PAID
                && (string) ($context['actor_type'] ?? '') === 'admin'
                && $invoice instanceof Invoice
                && (int) $invoice->status === InvoiceStatus::PAID
                && $this->isAdminManualOrder($lockedOrder)
                && ! $this->hasThirdPartyPayments($lockedOrder, $invoice);

            throw_if(
                ! $adminManualPaidCancel && (int) $lockedOrder->status !== OrderStatus::PENDING,
                new BusinessException('仅待支付订单可取消')
            );

            $lockedOrder->setRelation('invoice', $invoice);
            if ($adminManualPaidCancel) {
                // 账单保持 PAID 只打 requires_refund 标记：直接置 CANCELLED 会留下
                // 「已取消 + paid_amount>0」的悬空收款（与 M-54 同类），且红字冲抵
                // （要求原单 PAID）随之不可用；保持 PAID 则补录红字冲抵通道继续有效。
                // 补录账单已在 M-57a 中对同周期自助续费豁免，不再阻塞续费。
                // 进入本分支的前提已包含 $invoice instanceof Invoice，无需再判一次。
                if (! (bool) $invoice->requires_refund) {
                    $invoice->forceFill(['requires_refund' => true])->save();
                }

                $this->closePendingPaymentsForOrderCancel($invoice, $context);

                $lockedOrder->forceFill(['status' => OrderStatus::CANCELLED])->save();
            } elseif ($invoice instanceof Invoice) {
                throw_if(
                    ! in_array((int) $invoice->status, [InvoiceStatus::UNPAID, InvoiceStatus::CANCELLED], true),
                    new BusinessException('当前账单状态不支持取消订单')
                );

                $this->closePendingPaymentsForOrderCancel($invoice, $context);

                // 状态联动与投影收敛到生命周期状态机：账单取消 + PENDING 订单级联 + 券释放。
                $updatedInvoice = $this->tradeLifecycleService->cancelTrade($invoice, $context);
                $lockedOrder->setRelation('invoice', $updatedInvoice);
            } else {
                // 无账单订单：状态机以账单为锚，无锚时订单自行取消。
                $lockedOrder->forceFill([
                    'status' => OrderStatus::CANCELLED,
                ])->save();
            }

            return $lockedOrder->fresh(['user:id,email,nickname', 'product', 'invoice', 'service']) ?? $lockedOrder;
        });

        $actorType = trim((string) ($context['actor_type'] ?? 'system')) ?: 'system';
        $actorUserId = isset($context['actor_user_id']) ? (int) ($context['actor_user_id'] ?? 0) : 0;

        if ($actorType === 'client' && $actorUserId <= 0) {
            $actorUserId = (int) $updatedOrder->user_id;
        }

        // 先取出关联账单再判空：PHPStan 把 HasOne 关系判为必然非空，`?->` 反而触发
        // nullsafe.neverNull；用局部变量判空既满足静态分析也保留运行期空安全。
        $cancelledInvoice = $updatedOrder->invoice;

        $this->operationLogService->write(
            userId: $actorUserId > 0 ? $actorUserId : null,
            userType: $actorType,
            action: 'order.cancel',
            module: 'order',
            targetId: (int) $updatedOrder->id,
            detail: [
                'order_no' => (string) $updatedOrder->order_no,
                'order_type' => (string) $updatedOrder->type,
                'invoice_id' => $cancelledInvoice === null ? 0 : (int) $cancelledInvoice->id,
                'invoice_no' => $cancelledInvoice === null ? '' : (string) $cancelledInvoice->invoice_no,
                'product_id' => (int) ($updatedOrder->product_id ?? 0),
                'product_name' => (string) $updatedOrder->display_product_name,
                'actor_name' => (string) ($context['actor_name'] ?? ''),
                'reason' => (string) ($context['reason'] ?? ''),
                'trace_id' => (string) ($context['trace_id'] ?? ''),
                // 补录 PAID 订单纠错取消（M-57c）：审计需能区分普通取消与
                // 「订单取消、账单打 requires_refund 待人工退款/红字冲抵」的分支。
                'requires_refund' => $cancelledInvoice !== null && (bool) $cancelledInvoice->requires_refund,
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

        // 管理员手动开通产生的挂账订单按 due_date 计费，不参与支付会话窗口自动清理：
        // 与账单侧（CheckoutService::cancelExpiredUnpaidInvoice）和定时清理同一口径。
        // 一旦在此取消，cancel() 会级联取消关联挂账单（TradeLifecycleService::cancelTrade），
        // 用户在账期内失去支付入口。预留统计按订单状态（PENDING/PAID）占用库存，
        // 豁免清理的挂账订单会持续占用名额，需管理端显式取消释放。
        if ($this->isAdminManualOrder($freshOrder)) {
            return $freshOrder;
        }

        if (! $this->checkoutSecurityService->isPaymentSessionExpired($freshOrder)) {
            return $freshOrder;
        }

        if ($freshOrder->invoice instanceof Invoice) {
            $invoice = $freshOrder->invoice;

            // 非待支付/已取消账单不能经订单清理连带取消；0 元账单豁免支付窗口（M-5），
            // 与 CheckoutService::cancelExpiredUnpaidInvoice 同口径。
            // 部分核销账单（paid_amount>0）由 cancel() 内的状态机守卫拒绝（M-54），
            // 这里交给下方 try/catch 兜底，不提前返回——组合支付预扣的账单预扣可回补，
            // 提前返回会把预扣余额永久冻结在过期账单上。
            if (
                ! in_array((int) $invoice->status, [InvoiceStatus::UNPAID, InvoiceStatus::CANCELLED], true)
                || $invoice->payableAmount() <= 0
            ) {
                return $freshOrder;
            }
        }

        try {
            return $this->cancel($freshOrder, array_merge([
                'actor_type' => 'system',
                'actor_name' => 'payment-window-expired',
                'reason' => 'payment_window_expired',
            ], $context));
        } catch (BusinessException) {
            // 部分核销的账单拒绝自动取消（M-54）：懒清理运行在客户端 GET 请求内，
            // 不能把守卫异常冒泡成 500，保留订单/账单等待人工退款或红字冲抵处理。
            return $freshOrder;
        }
    }

    public function cancelExpiredPendingOrdersForUser(?int $userId = null, array $context = []): int
    {
        $threshold = now()->subSeconds(CheckoutSecurityService::paymentSessionTtlSeconds());
        $query = Order::query()
            ->where('status', OrderStatus::PENDING)
            ->where('created_at', '<=', $threshold);

        // 候选集约束与定时清理同源（CheckoutSecurityService::applyExpiredOrderSweepScope）：
        // 账期内按 due_date 计窗口的续费订单不进候选集；due_date 已过的仍会被正常关闭；
        // 挂账订单留在候选集内，由 cancelExpiredPendingOrder 的 admin_manual 豁免拒绝。
        $this->checkoutSecurityService->applyExpiredOrderSweepScope($query);

        if ($userId !== null && $userId > 0) {
            $query->where('user_id', $userId);
        }

        $count = 0;

        $query->chunkById(100, function ($orders) use (&$count, $context): void {
            foreach ($orders as $order) {
                $updated = $this->cancelExpiredPendingOrder($order, $context);
                if ((int) $updated->status === OrderStatus::CANCELLED) {
                    $count++;
                }
            }
        });

        return $count;
    }

    /**
     * 管理端挂账标记判断：config_snapshot.admin_manual=true。
     *
     * 公开给清理任务复用（僵尸挂账订单收敛），避免同一判据散落在多个服务里各写一套。
     */
    public function isAdminManualOrder(Order $order): bool
    {
        $snapshot = $order->config_snapshot;
        if (! is_array($snapshot)) {
            return false;
        }

        return filter_var($snapshot['admin_manual'] ?? false, FILTER_VALIDATE_BOOL);
    }

    /**
     * 订单名下是否存在非补录（manual）网关的支付单（含支付中/已成功/已取消）。
     *
     * 取消补录 PAID 订单只允许「无真实资金流入」的场景：manual 网关只是记账凭证，
     * 出现任何非 manual 支付行即说明资金真实进过系统（含 demo 等非标准网关），
     * 必须走退款而不是取消。
     */
    private function hasThirdPartyPayments(Order $order, ?Invoice $invoice = null): bool
    {
        // 显式判空而不是 ?->：PHPStan 对「参数已声明可空但依赖注入链上游非空」的
        // 推断不稳定，显式分支能同时满足静态分析与运行期语义。
        $invoiceId = $invoice === null ? 0 : (int) $invoice->id;

        $payments = Payment::query()
            ->where(function ($query) use ($order, $invoiceId): void {
                $query->where('order_id', (int) $order->id);
                if ($invoiceId > 0) {
                    $query->orWhere('invoice_id', $invoiceId);
                }
            })
            ->get(['id', 'gateway_key']);

        foreach ($payments as $payment) {
            if ($payment->gatewayKey() !== PaymentGatewayCode::MANUAL) {
                return true;
            }
        }

        return false;
    }

    /**
     * 取消订单前关闭账单名下 PENDING 支付单（组合支付预扣余额先行回补）。
     *
     * @param  array<string, mixed>  $context
     */
    private function closePendingPaymentsForOrderCancel(Invoice $invoice, array $context): void
    {
        $pendingPayments = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', PaymentStatus::PENDING)
            ->lockForUpdate()
            ->get();

        foreach ($pendingPayments as $pendingPayment) {
            // 组合支付（余额+网关）先行扣除了余额，取消时需把预扣余额退回。
            if ($this->mixPaymentService->restoreReservedMixBalance($pendingPayment, [
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
            $this->callbackProjector->syncProjection($pendingPayment);
        }
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

    private function loadAdminOrder(int $orderId): Order
    {
        $order = Order::query()
            ->with([
                'user:id,email,nickname',
                'product',
                // 渠道名要走回调证据（历史支付单的 gateway_key 为空），预取避免逐行回查。
                // 顺序必须保留：buildPaymentSummary 取 first()，倒序才是「最新一条」。
                'payments' => fn ($query) => $query
                    ->with('callbacks:payment_id,callback_type,payload_json')
                    ->orderByDesc('id'),
                'invoice.payments' => fn ($query) => $query
                    ->with('callbacks:payment_id,callback_type,payload_json')
                    ->orderByDesc('id'),
                // 余额支付不产生 Payment，渠道证据在账单扣款台账上。
                'invoice.ledgerEntries:id,source_type,source_id,event_type,change_amount,operator_type,trace_id,remark',
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

        // 判据是「渠道能否还原」而不是「是否第三方真实资金流入」：旧库 payments 没有渠道快照列，
        // 真实渠道写在 callback_raw 里，只用 isThirdPartyGateway() 会让这批历史订单的支付方式为空。
        $payments = collect($payments)
            ->filter(fn (Payment $payment) => PaymentChannelLabel::hasKnownChannel($payment))
            ->values();

        $payment = $payments
            ->first(fn (Payment $item) => ! (bool) data_get((array) ($item->callback_raw ?? []), 'duplicate_paid', false)
                && in_array((int) $item->status, [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED], true))
            ?? $payments->first(fn (Payment $item) => in_array((int) $item->status, [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED], true));

        if (! $payment instanceof Payment) {
            // 余额支付不产生 Payment，渠道只在账单扣款台账上；不回落这一层，
            // 管理端订单详情的支付链对余额支付订单恒为空。
            return ($invoice = $order->invoice) instanceof Invoice
                ? InvoicePaymentSummary::fromBalanceLedger($invoice)
                : null;
        }

        $refund = (array) data_get((array) ($payment->callback_raw ?? []), 'refund', []);

        return [
            'id' => (int) $payment->id,
            'payment_no' => (string) $payment->payment_no,
            'trade_no' => (string) ($payment->trade_no ?? ''),
            'gateway' => PaymentChannelLabel::resolveGatewayKey($payment),
            'gateway_key' => PaymentChannelLabel::resolveGatewayKey($payment),
            'gateway_label' => PaymentChannelLabel::channelNameFromPayment($payment),
            'plugin_name' => PaymentChannelLabel::pluginNameFromPayment($payment),
            'channel_name' => PaymentChannelLabel::channelNameFromPayment($payment),
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
