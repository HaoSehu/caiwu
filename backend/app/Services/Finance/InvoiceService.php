<?php

namespace App\Services\Finance;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\ManualPaymentGateway;
use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Constants\RefundStatus;
use App\Exceptions\BusinessException;
use App\Models\ActivityLog;
use App\Models\FinanceRefund;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Services\ProductCatalog\ProductFullPathResolver;
use App\Services\System\OperationLogService;
use App\Support\AdminPrivacy;
use App\Support\Concerns\FiltersDateRange;
use App\Support\InvoicePaymentSummary;
use App\Support\LikeEscape;
use App\Support\Money;
use App\Support\PaymentChannelLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    use FiltersDateRange;

    public function __construct(
        private readonly PaymentCallbackProjector $callbackProjector,
        private readonly TradeLifecycleService $tradeLifecycleService,
        private readonly ?ProductDisplayNameResolver $productDisplayNameResolver = null,
    ) {}

    /**
     * 账单查询的预载关系集（列表 / 详情 / 管理端 / 客户端共用一份定义）。
     *
     * 四处调用方原本各自复制同一段关系链，差异收敛为下面四个开关：
     * - detail：详情多预载 items、退款记录与下单快照列；
     * - withUser：客户端视图不需要 user（避免把他人资料带进响应）；
     * - productWithCustomDisplayName：一旦预载 custom_display_name，
     *   Product::getNameAttribute() 会提前命中「已加载自定义展示名」分支，
     *   把带规格后缀的展示名换成裸自定义名 —— 客户端详情与用户侧列表刻意不带该列；
     * - orderWithAmounts：客户端详情要展示实付/折扣等金额列，管理端列表由 invoices 主表提供。
     *
     * @param  array{detail?: bool, withUser?: bool, productWithCustomDisplayName?: bool, orderWithAmounts?: bool, orderWithSnapshots?: bool}  $options
     * @return array<int, string>
     */
    private function invoiceRelations(array $options = []): array
    {
        $detail = $options['detail'] ?? false;
        $withUser = $options['withUser'] ?? true;
        $productWithCustomDisplayName = $options['productWithCustomDisplayName'] ?? true;
        $orderWithAmounts = $options['orderWithAmounts'] ?? false;
        $orderWithSnapshots = $options['orderWithSnapshots'] ?? $detail;

        $productColumns = array_values(array_unique(array_merge(
            ['id'],
            Product::optionalSelectColumns([
                'product_type',
                'service_type_code',
                'product_group_id',
                ...($productWithCustomDisplayName ? ['custom_display_name'] : []),
                'remark',
                'purchase_requires',
            ]),
        )));
        $productProjection = implode(',', $productColumns);

        $orderColumns = 'order_no,status,type,service_id,paid_at,product_id,billing_cycle';
        if ($orderWithAmounts) {
            $orderColumns .= ',amount,discount,paid_amount,quantity';
        }
        if ($orderWithSnapshots) {
            $orderColumns .= ',product_spec_snapshot,product_type_snapshot,config_snapshot';
        }
        // 客户端详情会把订单的下单快照一并下发给前端；管理端列表不需要。
        if ($orderWithAmounts) {
            $orderColumns .= ',config_pricing_snapshot';
        }

        $relations = [
            'order:id,'.$orderColumns,
            'order.product:'.$productProjection,
            'order.product.productGroup:id,second_product_group_id,name',
            'order.product.productGroup.secondProductGroup:id,first_product_group_id,name',
            'order.product.productGroup.secondProductGroup.firstProductGroup:id,code,name',
            'product:'.$productProjection,
            'product.productGroup:id,second_product_group_id,name',
            'product.productGroup.secondProductGroup:id,first_product_group_id,name',
            'product.productGroup.secondProductGroup.firstProductGroup:id,code,name',
            'service:id,name,status,expires_at',
            // 渠道名要走回调证据（历史账单的 gateway_key 为空），预取避免逐行回查。
            'payments.callbacks:payment_id,callback_type,payload_json',
            // 余额支付没有 Payment 记录，渠道要从扣款台账派生（见 InvoicePaymentSummary::fromBalanceLedger）。
            'ledgerEntries:id,source_type,source_id,event_type,change_amount,operator_type,trace_id,remark',
        ];

        if ($withUser) {
            array_unshift($relations, 'user:id,email,nickname,phone');
        }

        if ($detail) {
            $relations[] = 'items';
            $relations[] = 'refunds:id,invoice_id,refund_no,amount,status,funds_moved,data_origin,reason,operator_name,refunded_at';
        }

        return $relations;
    }

    /**
     * 根据订单创建账单
     */
    public function createFromOrder(Order $order): Invoice
    {
        $invoice = Invoice::create([
            'invoice_no' => Invoice::generateInvoiceNoFromOrderNo((string) $order->order_no),
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'product_id' => $order->product_id,
            'product_spec_snapshot' => $order->product_spec_snapshot,
            'product_type_snapshot' => $order->product_type_snapshot,
            'service_id' => $order->service_id,
            'coupon_id' => $order->coupon_id,
            'user_coupon_id' => $order->user_coupon_id,
            'coupon_code' => $order->coupon_code,
            'type' => match ((string) $order->type) {
                OrderType::NEW => InvoiceType::NEW_PURCHASE,
                OrderType::RENEW => InvoiceType::RENEW,
                OrderType::UPGRADE => InvoiceType::UPGRADE,
                default => 'normal',
            },
            // 订单金额即应付价（所有链路统一口径），不再按目录价语义反推扣减折扣列
            'amount' => max((float) ($order->amount ?? 0), 0),
            'discount' => $order->discount ?? 0,
            'member_discount_amount' => (float) ($order->member_discount_amount ?? 0),
            'member_discount_snapshot' => $order->member_discount_snapshot,
            'billing_cycle' => $order->billing_cycle,
            'quantity' => $order->quantity ?? 1,
            'config_snapshot' => $order->config_snapshot,
            'config_pricing_snapshot' => $order->config_pricing_snapshot,
            'coupon_snapshot' => $order->coupon_snapshot,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(7),
            'trace_id' => $order->trace_id,
        ]);

        return $this->syncProjection($invoice);
    }

    /**
     * 直接创建账单（不依赖订单）
     */
    public function createDirect(array $data): Invoice
    {
        $invoice = Invoice::create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => $data['user_id'],
            'product_id' => $data['product_id'] ?? null,
            'product_spec_snapshot' => $data['product_spec_snapshot'] ?? null,
            'product_type_snapshot' => $data['product_type_snapshot'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'coupon_id' => $data['coupon_id'] ?? null,
            'user_coupon_id' => $data['user_coupon_id'] ?? null,
            'coupon_code' => $data['coupon_code'] ?? null,
            'type' => $data['type'] ?? 'normal',
            'amount' => $data['amount'],
            'discount' => $data['discount'] ?? 0,
            // 调用方带会员折扣时必须透传，静默丢弃会让账单资金组成不完整
            'member_discount_amount' => (float) ($data['member_discount_amount'] ?? 0),
            'member_discount_snapshot' => $data['member_discount_snapshot'] ?? null,
            'paid_amount' => (float) ($data['paid_amount'] ?? 0),
            'billing_cycle' => $data['billing_cycle'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'config_snapshot' => $data['config_snapshot'] ?? null,
            'config_pricing_snapshot' => $data['config_pricing_snapshot'] ?? null,
            'coupon_snapshot' => $data['coupon_snapshot'] ?? null,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => $data['due_date'] ?? now()->addDays(7),
            'trace_id' => $data['trace_id'] ?? null,
        ]);

        return $this->syncProjection($invoice);
    }

    /**
     * 充值到账 -> 创建充值类型账单
     */
    public function createForRecharge(User $user, float $amount, ?Payment $payment = null, ?string $remark = null, ?string $traceId = null): Invoice
    {
        if ($payment instanceof Payment && (int) ($payment->invoice_id ?? 0) > 0) {
            $existing = Invoice::query()
                ->where('user_id', (int) $user->id)
                ->find((int) $payment->invoice_id);

            if ($existing instanceof Invoice) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($user, $amount, $payment, $remark, $traceId): Invoice {
            $invoice = Invoice::create([
                'invoice_no' => Invoice::generateInvoiceNo(),
                'user_id' => $user->id,
                'type' => InvoiceType::RECHARGE,
                'amount' => $amount,
                'paid_amount' => $amount,
                'status' => InvoiceStatus::PAID,
                'paid_at' => now(),
                'due_date' => null,  // 充值账单已立即付清，截止日期无意义
                'config_snapshot' => array_filter([
                    'remark' => $remark,
                    'payment_no' => $payment?->payment_no,
                ], static fn ($value) => $value !== null && $value !== ''),
                'trace_id' => $traceId ?: $payment?->trace_id,
            ]);

            if ($payment) {
                $payment->forceFill(['invoice_id' => $invoice->id])->save();
            }

            return $this->syncProjection($invoice);
        });
    }

    /**
     * 推广返利入账 -> 创建入账类型账单
     */
    public function createForReferralCredit(User $user, float $amount, ?string $remark = null, ?string $traceId = null): Invoice
    {
        $invoice = Invoice::create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => $user->id,
            'type' => InvoiceType::REFERRAL_CREDIT,
            'amount' => $amount,
            'paid_amount' => $amount,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'due_date' => null,  // 推广返利账单已立即入账，截止日期无意义
            'config_snapshot' => $remark ? ['remark' => $remark] : null,
            'trace_id' => $traceId,
        ]);

        return $this->syncProjection($invoice);
    }

    /**
     * 扣款 -> 创建扣款类型账单
     */
    public function createForDeduction(User $user, float $amount, ?string $remark = null, ?string $traceId = null): Invoice
    {
        $invoice = Invoice::create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => $user->id,
            'type' => InvoiceType::DEDUCTION,
            'amount' => $amount,
            'paid_amount' => $amount,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'due_date' => null,  // 扣款账单已立即扣清，截止日期无意义
            'config_snapshot' => $remark ? ['remark' => $remark] : null,
            'trace_id' => $traceId,
        ]);

        return $this->syncProjection($invoice);
    }

    /**
     * 查询账单列表
     */
    public function adminList(array $filters, int $perPage = 20)
    {
        $query = Invoice::with($this->invoiceRelations())
            ->select('invoices.*')->selectSub(
                // 退款状态派生（列表轻量口径）：已完成退款累计，单子查询预加载无 N+1。
                // 必须用 selectSub 显式命名：withSum 生成的属性名是 refunds_sum_amount，
                // 读取点取 refund_completed_total 会恒为 NULL，退化成逐行聚合查询。
                // 前置 select('invoices.*') 不可省：addSelect 在没有列清单时会把整行投影顶掉。
                FinanceRefund::query()
                    ->selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('finance_refunds.invoice_id', 'invoices.id')
                    ->where('status', FinanceRefund::STATUS_COMPLETED),
                'refund_completed_total'
            );

        if (! empty($filters['invoice_no'])) {
            $query->where('invoice_no', $filters['invoice_no']);
        }
        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            // 关键字模糊匹配统一走 LikeEscape::contains：转义 % _ \ 通配符，
            // 否则用户输入 "%" 会把全表命中、输入 "_" 会误匹配任意单字符。
            $likeKeyword = LikeEscape::contains($keyword);
            $query->where(function ($query) use ($keyword, $likeKeyword) {
                $query->where('invoice_no', 'like', $likeKeyword)
                    ->orWhere('id', $keyword)
                    ->orWhereHas('user', function ($userQuery) use ($likeKeyword) {
                        $userQuery->where('email', 'like', $likeKeyword)
                            ->orWhere('nickname', 'like', $likeKeyword)
                            ->orWhere('phone', 'like', $likeKeyword);
                    })
                    ->orWhereHas('order', function ($orderQuery) use ($likeKeyword) {
                        $orderQuery->where('order_no', 'like', $likeKeyword);
                    });
            });
        }
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            if ((int) $filters['status'] === InvoiceStatus::PARTIAL_PAID) {
                // 派生展示态筛选：库内仍为待支付，按「已付金额介于 (0, amount)」命中。
                $query->where('status', InvoiceStatus::UNPAID)
                    ->where('paid_amount', '>', 0)
                    ->whereColumn('paid_amount', '<', 'amount');
            } elseif ((int) $filters['status'] === InvoiceStatus::REFUNDED) {
                // 「有过退款」语义（退款标记批 2026-10-11）：已收口 REFUNDED、第三方原路
                // 退款（payment 已 REFUNDED），或名下存在已完成退款记录（含纯标记部分退款，
                // 账单仍 PAID）均命中；与客户端 InvoiceV2QueryService 同口径（M-63）。
                $query->where(function ($query) {
                    $query->whereHas('payments', fn ($paymentQuery) => $paymentQuery->where('status', PaymentStatus::REFUNDED))
                        ->orWhere('status', InvoiceStatus::REFUNDED)
                        ->orWhereHas('refunds', fn ($refundQuery) => $refundQuery->where('status', FinanceRefund::STATUS_COMPLETED));
                });
            } else {
                $query->where('status', $filters['status']);
            }
        }
        if (! empty($filters['type'])) {
            $type = $filters['type'];
            if (in_array($type, ['new', 'normal'], true)) {
                $query->whereIn('type', ['new', 'normal']);
            } else {
                $query->where('type', $type);
            }
        }
        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }
        // 服务控制台「相关账单」按实例过滤；不传时行为不变。
        if (! empty($filters['service_id'])) {
            $query->where('service_id', (int) $filters['service_id']);
        }
        $this->applyDateFilter($query, $filters);

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        // 展示名解析会回源拆分商品的源商品、并补齐投影里没有的 custom_display_name，
        // 逐行解析会随当页账单行数增长；必须在渲染前按当页商品一次性预载。
        $this->preloadInvoiceProductDisplayNames($paginator->items());

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Invoice $invoice) => $this->transformAdminInvoiceListItem($invoice))
        );

        return $paginator;
    }

    public function adminDetail(int $id): array
    {
        $invoice = Invoice::with($this->invoiceRelations(['detail' => true]))->findOrFail($id);

        [
            'scene' => $scene,
            'payment_summary' => $paymentSummary,
            'display_status' => $displayStatus,
            'product_display_name' => $productDisplayName,
            'product_spec_display' => $productSpecDisplay,
            'combined_display_name' => $combinedDisplayName,
        ] = $this->resolveDetailContext($invoice);

        // 可退额唯一真源：一律取 InvoiceRefundService（与真退款的提交守卫同口径，
        // 实收基数 − 已转余额 − 已完成退款）。本服务不能构造注入它——会经
        // FinanceDocumentService→InvoiceService 形成依赖环，故运行时解析一次复用。
        $refundableAmount = app(InvoiceRefundService::class)->markRefundableAmountOf($invoice);

        return [
            // 履约状态独立列：原在 config_snapshot 内，JSON 共享读改写会丢标记，
            // 详情页也需要它判断「已付未履约待人工退款」。
            'fulfillment_pending' => (bool) $invoice->fulfillment_pending,
            'fulfillment_pending_at' => $invoice->fulfillment_pending_at !== null
                ? (string) $invoice->fulfillment_pending_at
                : null,
            'fulfillment_cleared_at' => $invoice->fulfillment_cleared_at !== null
                ? (string) $invoice->fulfillment_cleared_at
                : null,
            'requires_refund' => (bool) $invoice->requires_refund,
            'id' => (int) $invoice->id,
            'invoice_no' => (string) $invoice->invoice_no,
            'user_id' => (int) $invoice->user_id,
            'product_spec_snapshot' => (string) ($invoice->product_spec_snapshot ?? ''),
            'product_spec_display' => $productSpecDisplay,
            'product_display_name' => $productDisplayName,
            'combined_display_name' => $combinedDisplayName,
            'product_full_path' => $invoice->order ? $this->resolveOrderProductPath($invoice->order) : '',
            'user' => $this->adminUserPayload($invoice->user),
            'order_id' => (int) ($invoice->order_id ?? 0),
            'order' => $invoice->order ? [
                'id' => (int) $invoice->order->id,
                'order_no' => (string) $invoice->order->order_no,
                'status' => (int) $invoice->order->status,
                'type' => (string) $invoice->order->type,
                'service_id' => (int) ($invoice->order->service_id ?? 0),
                'paid_at' => $invoice->order->paid_at?->format('Y-m-d H:i:s'),
                'billing_cycle' => (string) ($invoice->order->billing_cycle ?? ''),
                'product' => $invoice->order->product ? [
                    'id' => (int) $invoice->order->product->id,
                    'name' => (string) $invoice->order->product->name,
                ] : null,
            ] : null,
            'product_id' => (int) ($invoice->product_id ?? 0),
            'product' => $invoice->product ? [
                'id' => (int) $invoice->product->id,
                'name' => (string) $invoice->product->name,
                'product_type' => (string) ($invoice->product->product_type ?? ''),
            ] : null,
            'service' => $invoice->service ? [
                'id' => (int) $invoice->service->id,
                'name' => (string) $invoice->service->name,
                'status' => (int) $invoice->service->status,
                'expires_at' => $invoice->service->expires_at?->format('Y-m-d H:i:s'),
            ] : null,
            'type' => (string) $invoice->type,
            'type_label' => $this->resolveInvoiceTypeLabel((string) $invoice->type, $invoice),
            'scene' => $scene,
            'amount' => number_format((float) $invoice->amount, 2, '.', ''),
            'discount' => number_format((float) ($invoice->discount ?? 0), 2, '.', ''),
            // 折扣分列下发：券减免（discount）与会员折扣（member_discount_amount）来源不同
            'member_discount_amount' => number_format((float) ($invoice->member_discount_amount ?? 0), 2, '.', ''),
            'member_discount_snapshot' => (array) ($invoice->member_discount_snapshot ?? []),
            'coupon_code' => (string) ($invoice->coupon_code ?? ''),
            'coupon_name' => $this->resolveInvoiceCouponName($invoice),
            'paid_amount' => number_format((float) ($invoice->paid_amount ?? 0), 2, '.', ''),
            'payable_amount' => Money::format($invoice->payableAmount()),
            'status' => (int) $displayStatus['status'],
            'status_label' => (string) $displayStatus['status_label'],
            'is_partial_paid' => $displayStatus['is_partial_paid'],
            'raw_status' => (int) $invoice->status,
            'raw_status_label' => InvoiceStatus::$labels[$invoice->status] ?? (string) $invoice->status,
            // 退款投影（详情完整口径）：退款累计与列表同一公式 max(已完成退款聚合, invoices.refund_amount)。
            // 只取聚合会让历史原路退款（payment 已 REFUNDED、finance_refunds 无行）在详情显示 ¥0.00（M-59）。
            'refund_amount' => number_format(max($this->refundCompletedTotal($invoice), (float) ($invoice->refund_amount ?? 0)), 2, '.', ''),
            'refunded_at' => $this->formatRefundTime($invoice->refunded_at),
            'refund_method' => (string) ($invoice->refund_method ?? ''),
            'refund_status' => $this->resolveRefundStatus($invoice),
            'refund_status_label' => RefundStatus::label($this->resolveRefundStatus($invoice)),
            'is_partially_refunded' => $this->resolveRefundStatus($invoice) === RefundStatus::PARTIAL,
            'refundable_amount' => number_format($refundableAmount, 2, '.', ''),
            // 退款到余额（唯一通道，动资金）能力位与可退额：
            // 金额取 InvoiceRefundService::markRefundableAmountOf 的单一真源口径
            // （实收基数 max(paid_amount, amount) − 已转余额的异常支付 − 已完成退款累计），
            // 与提交时的服务端守卫完全一致，拆分支付/重复支付转余额不再虚高。
            'can_refund_to_balance' => $this->canRefundToBalance($invoice, $refundableAmount),
            'refundable_amount_for_balance' => number_format($refundableAmount, 2, '.', ''),
            'refunds' => $invoice->refunds
                ->sortByDesc('id')
                ->values()
                ->map(fn (FinanceRefund $refund): array => [
                    'id' => (int) $refund->id,
                    'refund_no' => (string) $refund->refund_no,
                    'amount' => number_format((float) $refund->amount, 2, '.', ''),
                    'refund_method' => (string) (($refund->funds_moved ?? false) ? 'balance' : 'manual_mark'),
                    'refund_method_label' => (string) (($refund->funds_moved ?? false) ? '退回余额' : '线下补标记'),
                    'status' => (int) $refund->status,
                    'reason' => (string) ($refund->reason ?? ''),
                    'operator_name' => (string) ($refund->operator_name ?? ''),
                    'refunded_at' => $this->formatRefundTime($refund->refunded_at),
                ])->all(),
            'billing_cycle' => (string) ($invoice->billing_cycle ?? ''),
            'quantity' => (int) ($invoice->quantity ?? 1),
            'summary' => $this->buildInvoiceSummary($invoice, $scene),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'paid_at' => $invoice->paid_at?->format('Y-m-d H:i:s'),
            'created_at' => $invoice->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $invoice->updated_at?->format('Y-m-d H:i:s'),
            'trace_id' => (string) ($invoice->trace_id ?? ''),
            'refund_trace_id' => (string) ($invoice->refund_trace_id ?? ''),
            'config_snapshot' => (array) ($invoice->config_snapshot ?? []),
            'config_pricing_snapshot' => (array) ($invoice->config_pricing_snapshot ?? []),
            'coupon_snapshot' => (array) ($invoice->coupon_snapshot ?? []),
            'payment_summary' => $paymentSummary,
            'payments' => $this->channelPayments($invoice->payments)->map(fn ($p) => [
                'id' => (int) $p->id,
                'payment_no' => (string) $p->payment_no,
                'gateway' => $this->paymentGatewayKey($p),
                'gateway_key' => $this->paymentGatewayKey($p),
                'gateway_label' => PaymentChannelLabel::channelNameFromPayment($p),
                'plugin_name' => PaymentChannelLabel::pluginNameFromPayment($p),
                'channel_name' => PaymentChannelLabel::channelNameFromPayment($p),
                'amount' => number_format((float) $p->amount, 2, '.', ''),
                'status' => (int) $p->status,
                'status_label' => $this->resolvePaymentStatusLabel((int) $p->status),
                'paid_at' => $p->paid_at?->format('Y-m-d H:i:s'),
                'trace_id' => (string) ($p->trace_id ?? ''),
                'trade_no' => (string) ($p->trade_no ?? ''),
                'refund_method' => (string) data_get((array) ($p->callback_raw ?? []), 'refund.refund_method', ''),
                'refund_method_label' => (string) data_get((array) ($p->callback_raw ?? []), 'refund.refund_method_label', ''),
                'refund_reason' => (string) data_get((array) ($p->callback_raw ?? []), 'refund.refund_reason', ''),
                'refunded_at' => (string) data_get((array) ($p->callback_raw ?? []), 'refund.refunded_at', ''),
            ])->values()->all(),
            'items' => $this->buildInvoiceItems($invoice, $scene),
            'logs' => $this->buildInvoiceLogs($invoice, $scene),
            'can_cancel' => in_array((int) $invoice->status, [InvoiceStatus::UNPAID], true),
        ];
    }

    private function adminUserPayload(?User $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        $privacy = AdminPrivacy::current();

        return [
            'id' => (int) $user->id,
            'email' => $privacy->email($user->email),
            'nickname' => (string) ($user->nickname ?? ''),
            'phone' => $privacy->phone($user->phone ?? ''),
        ];
    }

    /**
     * 账单详情共用的派生上下文：场景、支付摘要、展示态、商品展示名三件套。
     *
     * 管理端详情与客户端详情此前各自重复计算这六个值，任何一处口径调整都得改两遍。
     *
     * @return array{
     *     scene: array<string, mixed>,
     *     payment_summary: ?array<string, mixed>,
     *     display_status: array<string, mixed>,
     *     product_display_name: string,
     *     product_spec_display: string,
     *     combined_display_name: string
     * }
     */
    private function resolveDetailContext(Invoice $invoice): array
    {
        $scene = $this->resolveInvoiceScene($invoice);
        $paymentSummary = $this->resolveInvoicePaymentSummary($invoice);
        $productDisplayName = $this->resolveInvoiceProductDisplayName($invoice);

        return [
            'scene' => $scene,
            'payment_summary' => $paymentSummary,
            'display_status' => $this->resolveInvoiceDisplayStatus($invoice, $paymentSummary),
            'product_display_name' => $productDisplayName,
            'product_spec_display' => $this->resolveInvoiceProductSpecDisplay($invoice, $scene, $productDisplayName),
            'combined_display_name' => $this->resolveInvoiceCombinedDisplayName($invoice, $productDisplayName),
        ];
    }

    public function clientDetail(Invoice $invoice): array
    {
        $invoice->loadMissing($this->invoiceRelations([
            'detail' => true,
            'withUser' => false,
            'productWithCustomDisplayName' => false,
            'orderWithAmounts' => true,
        ]));

        [
            'scene' => $scene,
            'payment_summary' => $paymentSummary,
            'display_status' => $displayStatus,
            'product_display_name' => $productDisplayName,
            'product_spec_display' => $productSpecDisplay,
            'combined_display_name' => $combinedDisplayName,
        ] = $this->resolveDetailContext($invoice);

        return [
            'id' => (int) $invoice->id,
            'invoice_no' => (string) $invoice->invoice_no,
            'product_spec_snapshot' => (string) ($invoice->product_spec_snapshot ?? ''),
            'product_spec_display' => $productSpecDisplay,
            'product_display_name' => $productDisplayName,
            'combined_display_name' => $combinedDisplayName,
            'product_full_path' => $invoice->order ? $this->resolveOrderProductPath($invoice->order) : '',
            'product_id' => (int) ($invoice->product_id ?? 0),
            'product' => $invoice->product ? [
                'id' => (int) $invoice->product->id,
                'name' => (string) $invoice->product->name,
                'product_type' => (string) ($invoice->product->product_type ?? ''),
            ] : null,
            'service' => $invoice->service ? [
                'id' => (int) $invoice->service->id,
                'name' => (string) $invoice->service->name,
                'status' => (int) $invoice->service->status,
                'expires_at' => $invoice->service->expires_at?->format('Y-m-d H:i:s'),
            ] : null,
            'type' => (string) $invoice->type,
            'type_label' => $this->resolveInvoiceTypeLabel((string) $invoice->type, $invoice),
            'scene' => $scene,
            'amount' => number_format((float) $invoice->amount, 2, '.', ''),
            'discount' => number_format((float) ($invoice->discount ?? 0), 2, '.', ''),
            'member_discount_amount' => number_format((float) ($invoice->member_discount_amount ?? 0), 2, '.', ''),
            'member_discount_snapshot' => (array) ($invoice->member_discount_snapshot ?? []),
            // 券标识：此前详情只给 coupon_snapshot，前端按 coupon_code 渲染的区块恒不显示
            'coupon_code' => (string) ($invoice->coupon_code ?? ''),
            'coupon_name' => $this->resolveInvoiceCouponName($invoice),
            'paid_amount' => number_format((float) ($invoice->paid_amount ?? 0), 2, '.', ''),
            'payable_amount' => Money::format($invoice->payableAmount()),
            // 派生展示状态（混付预扣后会派生为「部分已付」=2，见 resolveInvoiceDisplayStatus）。
            // 支付能力判定必须用 raw_status + can_pay，不得用 status：前端曾按 status=2
            // 判不可支付，混付预扣后刷新支付页即被锁死（M-23）。
            'status' => (int) $displayStatus['status'],
            'status_label' => (string) $displayStatus['status_label'],
            'is_partial_paid' => $displayStatus['is_partial_paid'],
            'raw_status' => (int) $invoice->status,
            // 可支付性只看库内真实状态与支付窗口，paid_amount>0（部分已付/混付预扣）不影响：
            // 剩余应付仍可通过余额支付/网关支付补足。
            'can_pay' => (int) $invoice->status === InvoiceStatus::UNPAID
                && ! app(CheckoutSecurityService::class)->isPaymentSessionExpired($invoice),
            'billing_cycle' => (string) ($invoice->billing_cycle ?? ''),
            'quantity' => (int) ($invoice->quantity ?? 1),
            'summary' => $this->buildInvoiceSummary($invoice, $scene),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'paid_at' => $invoice->paid_at?->format('Y-m-d H:i:s'),
            'created_at' => $invoice->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $invoice->updated_at?->format('Y-m-d H:i:s'),
            'config_snapshot' => (array) ($invoice->config_snapshot ?? ($invoice->order?->config_snapshot ?? [])),
            'config_pricing_snapshot' => (array) ($invoice->config_pricing_snapshot ?? ($invoice->order?->config_pricing_snapshot ?? [])),
            'coupon_snapshot' => (array) ($invoice->coupon_snapshot ?? []),
            'payment_summary' => $paymentSummary,
            'payments' => $this->channelPayments($invoice->payments)->map(fn ($p) => [
                'id' => (int) $p->id,
                'payment_no' => (string) $p->payment_no,
                'gateway' => $this->paymentGatewayKey($p),
                'gateway_key' => $this->paymentGatewayKey($p),
                'gateway_label' => PaymentChannelLabel::channelNameFromPayment($p),
                'plugin_name' => PaymentChannelLabel::pluginNameFromPayment($p),
                'channel_name' => PaymentChannelLabel::channelNameFromPayment($p),
                'amount' => number_format((float) $p->amount, 2, '.', ''),
                'status' => (int) $p->status,
                'status_label' => $this->resolvePaymentStatusLabel((int) $p->status),
                'paid_at' => $p->paid_at?->format('Y-m-d H:i:s'),
                'trade_no' => (string) ($p->trade_no ?? ''),
                'refund_method' => (string) data_get((array) ($p->callback_raw ?? []), 'refund.refund_method', ''),
                'refund_method_label' => (string) data_get((array) ($p->callback_raw ?? []), 'refund.refund_method_label', ''),
                'refund_reason' => (string) data_get((array) ($p->callback_raw ?? []), 'refund.refund_reason', ''),
                'refunded_at' => (string) data_get((array) ($p->callback_raw ?? []), 'refund.refunded_at', ''),
            ])->values()->all(),
            'items' => $this->buildInvoiceItems($invoice, $scene),
            'logs' => $this->buildInvoiceLogs($invoice, $scene),
        ];
    }

    public function adminListItem(Invoice $invoice): array
    {
        $invoice->loadMissing($this->invoiceRelations([
            'withUser' => true,
            'productWithCustomDisplayName' => false,
            'orderWithSnapshots' => true,
        ]));

        return $this->transformAdminInvoiceListItem($invoice);
    }

    private function transformAdminInvoiceListItem(Invoice $invoice): array
    {
        $paymentSummary = $this->resolveInvoicePaymentSummary($invoice);
        $displayStatus = $this->resolveInvoiceDisplayStatus($invoice, $paymentSummary);
        $scene = $this->resolveInvoiceScene($invoice);
        $productDisplayName = $this->resolveInvoiceProductDisplayName($invoice);
        $productSpecDisplay = $this->resolveInvoiceProductSpecDisplay($invoice, $scene, $productDisplayName);
        $combinedDisplayName = $this->resolveInvoiceCombinedDisplayName($invoice, $productDisplayName);

        return [
            'id' => (int) $invoice->id,
            'invoice_no' => (string) $invoice->invoice_no,
            'product_spec_snapshot' => (string) ($invoice->product_spec_snapshot ?? ''),
            'product_spec_display' => $productSpecDisplay,
            'product_display_name' => $productDisplayName,
            'combined_display_name' => $combinedDisplayName,
            'product_full_path' => $invoice->order ? $this->resolveOrderProductPath($invoice->order) : '',
            'user' => $this->adminUserPayload($invoice->user),
            'order_id' => (int) ($invoice->order_id ?? 0),
            'order' => $invoice->order ? [
                'id' => (int) $invoice->order->id,
                'order_no' => (string) $invoice->order->order_no,
                'status' => (int) $invoice->order->status,
                'service_id' => (int) ($invoice->order->service_id ?? 0),
                'billing_cycle' => (string) ($invoice->order->billing_cycle ?? ''),
                'paid_at' => $invoice->order->paid_at?->format('Y-m-d H:i:s'),
                'product' => $invoice->order->product ? [
                    'id' => (int) $invoice->order->product->id,
                    'name' => (string) $invoice->order->product->name,
                ] : null,
            ] : null,
            'product_id' => (int) ($invoice->product_id ?? 0),
            'product' => $invoice->product ? [
                'id' => (int) $invoice->product->id,
                'name' => (string) $invoice->product->name,
                'product_type' => (string) ($invoice->product->product_type ?? ''),
            ] : null,
            'type' => (string) $invoice->type,
            'type_label' => $this->resolveInvoiceTypeLabel((string) $invoice->type, $invoice),
            'scene' => $scene,
            'amount' => number_format((float) $invoice->amount, 2, '.', ''),
            'discount' => number_format((float) ($invoice->discount ?? 0), 2, '.', ''),
            // 折扣分列下发：券减免（discount）与会员折扣（member_discount_amount）来源不同
            'member_discount_amount' => number_format((float) ($invoice->member_discount_amount ?? 0), 2, '.', ''),
            'coupon_code' => (string) ($invoice->coupon_code ?? ''),
            'coupon_name' => $this->resolveInvoiceCouponName($invoice),
            'paid_amount' => number_format((float) ($invoice->paid_amount ?? 0), 2, '.', ''),
            'payable_amount' => Money::format($invoice->payableAmount()),
            'status' => (int) $displayStatus['status'],
            'status_label' => (string) $displayStatus['status_label'],
            'is_partial_paid' => $displayStatus['is_partial_paid'],
            'raw_status' => (int) $invoice->status,
            'raw_status_label' => InvoiceStatus::$labels[$invoice->status] ?? (string) $invoice->status,
            // 退款投影（列表轻量口径）：累计/状态由已完成退款聚合派生；
            // 可退额只在详情下发（列表逐行聚合会 N+1）。
            'refund_amount' => number_format(max($this->refundCompletedTotal($invoice), (float) ($invoice->refund_amount ?? 0)), 2, '.', ''),
            'refunded_at' => $this->formatRefundTime($invoice->refunded_at),
            'refund_status' => $this->resolveRefundStatus($invoice),
            'refund_status_label' => RefundStatus::label($this->resolveRefundStatus($invoice)),
            'is_partially_refunded' => $this->resolveRefundStatus($invoice) === RefundStatus::PARTIAL,
            // 计费周期：账单自身优先，迁移前的老账单为空时回落关联订单（下单时按商品周期写入）
            'billing_cycle' => (string) ($invoice->billing_cycle ?: $invoice->order?->billing_cycle ?: ''),
            'quantity' => (int) ($invoice->quantity ?? 1),
            'summary' => $this->buildInvoiceSummary($invoice, $scene),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'paid_at' => $invoice->paid_at?->format('Y-m-d H:i:s'),
            // 结束时间按状态取值：已支付=支付完成时间；已取消=账单过期（付款期限）；已退款=退款时间。
            // 待支付仍在进行中，不下发（前端显示占位符）。
            'finished_at' => $this->finishedAt($invoice),
            'created_at' => $invoice->created_at?->format('Y-m-d H:i:s'),
            'payment_summary' => $paymentSummary,
        ];
    }

    /**
     * 账单「结束时间」：状态决定取值口径，待支付（含未收到任何付款）不下发。
     * - 已支付 / 部分已付（paid_amount > 0 但状态仍为待支付）：支付完成时间；
     * - 已取消：记录最后更新时间（手动取消 / 过期回收的处理时间）；
     * - 已退款：退款时间，缺失时回落支付时间。
     */
    private function finishedAt(Invoice $invoice): ?string
    {
        $status = (int) $invoice->status;
        $partialPaid = $status === InvoiceStatus::UNPAID && (float) ($invoice->paid_amount ?? 0) > 0;

        if ($status === InvoiceStatus::PAID || $partialPaid) {
            return $invoice->paid_at?->format('Y-m-d H:i:s');
        }

        return match ($status) {
            InvoiceStatus::CANCELLED => $invoice->updated_at?->format('Y-m-d H:i:s'),
            InvoiceStatus::REFUNDED => ($invoice->refunded_at ?? $invoice->paid_at)?->format('Y-m-d H:i:s'),
            default => null,
        };
    }

    /**
     * 退款时间统一输出（兼容 Carbon/字符串，空值 null；不引入新的 ?->format 调用
     * 以免扰动 phpstan-baseline 对既有 format-on-string 的计数）。
     */
    private function formatRefundTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * 已完成退款累计（refunds 表口径，纯标记与真退款同表）。
     * 取值优先级：withSum 预加载属性 → 已加载关系 → 单查（退化路径）。
     */
    private function refundCompletedTotal(Invoice $invoice): float
    {
        $withSumValue = $invoice->getAttribute('refund_completed_total');
        if ($withSumValue !== null) {
            return round((float) $withSumValue, 2);
        }

        if ($invoice->relationLoaded('refunds')) {
            return round((float) $invoice->refunds
                ->where('status', FinanceRefund::STATUS_COMPLETED)
                ->sum('amount'), 2);
        }

        return round((float) FinanceRefund::query()
            ->where('invoice_id', (int) $invoice->id)
            ->where('status', FinanceRefund::STATUS_COMPLETED)
            ->sum('amount'), 2);
    }

    /**
     * 派生退款状态：NONE/PARTIAL/FULL（RefundStatus，不落库）。
     * 实收基数取 paid_amount（部分支付账单退化 amount，与收入口径一致）。
     */
    private function resolveRefundStatus(Invoice $invoice): int
    {
        $paidBase = (float) ($invoice->paid_amount ?? 0) > 0
            ? (float) $invoice->paid_amount
            : (float) $invoice->amount;
        if ((int) $invoice->status === InvoiceStatus::REFUNDED) {
            return RefundStatus::FULL;
        }

        return RefundStatus::derive($this->refundCompletedTotal($invoice), $paidBase);
    }

    /**
     * 「退款到余额」能力判定（轻量镜像，真服务守卫为准）：
     * 类型白名单（排除充值/补录——其资金从未离开系统）+ 账单/订单 PAID
     * + 存在真实支付单 + 可退额 > 0 + 与纯标记通道互斥。
     *
     * 可退额由调用方按 InvoiceRefundService::markRefundableAmountOf 的单一口径传入，
     * 本方法不再自带公式，避免两处口径分叉。
     * 类型白名单同样取自 InvoiceRefundService::isRefundToBalanceTypeAllowed（提交位真源），
     * 展示位不再内联一份副本——历史上两处各写一份，正是 referral_credit 能被退款的成因。
     */
    private function canRefundToBalance(Invoice $invoice, float $refundableAmount): bool
    {
        $refundService = app(InvoiceRefundService::class);
        if (! $refundService->isInvoiceRefundToBalanceAllowed($invoice)) {
            return false;
        }

        if ((int) $invoice->status !== InvoiceStatus::PAID) {
            return false;
        }

        if ($invoice->order instanceof Order && (int) $invoice->order->status !== OrderStatus::PAID) {
            return false;
        }

        // 补录账单由管理员从用户余额扣款支付，资金证据是余额台账而非 Payment 行；
        // 仅这一类账单允许跳过「必须有真实支付单」的展示门槛。
        $balancePaidManual = (string) ($invoice->type ?? '') === InvoiceType::MANUAL
            && $refundService->isPaidFromBalanceInvoice($invoice);
        if (! $balancePaidManual) {
            if (! $invoice->relationLoaded('payments') || $invoice->payments->isEmpty()) {
                return false;
            }

            // 系统外收款（补录订单/手工入账）的支付单固定为 manual，资金从未进入系统：
            // 能力位必须关闭，否则管理员看到的是一个点下去必然失败的入口。
            $realPaymentCount = $invoice->payments
                ->filter(fn ($payment): bool => $payment instanceof Payment
                    && $payment->gatewayKey() !== PaymentGatewayCode::MANUAL)
                ->count();
            if ($realPaymentCount === 0) {
                return false;
            }
        }

        return Money::greaterThan($refundableAmount, 0);
    }

    private function buildInvoiceItems(Invoice $invoice, array $scene): array
    {
        if (($scene['kind'] ?? '') === 'refund' && ! empty($scene['items']) && is_array($scene['items'])) {
            return collect($scene['items'])->map(function ($item, $index) use ($invoice) {
                return [
                    'id' => (int) ($invoice->id * 100 + $index + 1),
                    'description' => (string) ($item['description'] ?? ''),
                    'amount' => number_format((float) ($item['amount'] ?? 0), 2, '.', ''),
                ];
            })->values()->all();
        }

        if ($invoice->items->isNotEmpty()) {
            return $invoice->items->map(fn ($item) => [
                'id' => (int) $item->id,
                'description' => (string) ($item->description ?? $item->item_name ?? ''),
                'amount' => number_format((float) ($item->amount ?? $item->line_amount ?? 0), 2, '.', ''),
            ])->values()->all();
        }

        if (! empty($scene['items']) && is_array($scene['items'])) {
            return collect($scene['items'])->map(function ($item, $index) use ($invoice) {
                return [
                    'id' => (int) ($invoice->id * 100 + $index + 1),
                    'description' => (string) ($item['description'] ?? ''),
                    'amount' => number_format((float) ($item['amount'] ?? 0), 2, '.', ''),
                ];
            })->values()->all();
        }

        return [[
            'id' => (int) $invoice->id,
            'description' => $scene['description'] ?? $this->resolveInvoiceItemDescription($invoice),
            'amount' => number_format((float) $invoice->amount, 2, '.', ''),
        ]];
    }

    private function buildInvoiceSummary(Invoice $invoice, array $scene): array
    {
        $remark = trim((string) ($scene['remark'] ?? $invoice->config_snapshot['remark'] ?? ''));
        $subheadline = (string) ($scene['subheadline'] ?? '');
        $highlight = (string) ($scene['highlight'] ?? '');

        if ($subheadline === '') {
            $subheadline = $this->resolveInvoiceSceneFallbackSubheadline($invoice);
        }
        if ($highlight === '') {
            $highlight = $remark !== '' ? $remark : (string) ($invoice->order?->order_no ?? '');
        }

        return [
            'headline' => (string) ($scene['headline'] ?? $this->resolveInvoiceTypeLabel((string) $invoice->type, $invoice)),
            'subheadline' => $subheadline,
            'badge' => (string) ($scene['badge'] ?? $this->resolveInvoiceTypeLabel((string) $invoice->type, $invoice)),
            'highlight' => $highlight,
            'remark' => $remark,
        ];
    }

    private function resolveInvoiceItemDescription(Invoice $invoice): string
    {
        $productName = trim((string) ($invoice->display_product_name ?? ''));
        if ($productName !== '') {
            return $productName;
        }

        $orderProductName = trim((string) ($invoice->order?->display_product_name ?? ''));
        if ($orderProductName !== '') {
            return $orderProductName;
        }

        return $this->resolveInvoiceTypeLabel((string) $invoice->type, $invoice);
    }

    /**
     * 券名：优先取已加载的券关系，其次取账单券快照。
     *
     * 列表路径不加载 coupon 关系（避免 N+1），只能靠 coupon_snapshot 里的 name；
     * 券被删除后关系为空、快照仍在，快照兜底同时覆盖该场景。
     */
    private function resolveInvoiceCouponName(Invoice $invoice): string
    {
        if ($invoice->relationLoaded('coupon') && $invoice->coupon !== null) {
            $name = trim((string) ($invoice->coupon->name ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        $snapshot = $invoice->coupon_snapshot;
        $snapshot = is_array($snapshot) ? $snapshot : [];

        return trim((string) ($snapshot['name'] ?? ''));
    }

    private function resolveInvoiceScene(Invoice $invoice): array
    {
        $type = InvoiceType::normalize((string) $invoice->type);
        $remark = trim((string) ($invoice->config_snapshot['remark'] ?? ''));
        $productName = trim((string) ($invoice->order?->display_product_name ?? ''));
        $billingCycle = trim((string) ($invoice->billing_cycle ?? $invoice->order?->billing_cycle ?? ''));
        $paymentSummary = $this->resolveInvoicePaymentSummary($invoice);
        $refundScene = $this->resolveRefundScene($invoice);

        if ($refundScene !== null) {
            return $refundScene;
        }

        return match ($type) {
            InvoiceType::NEW_PURCHASE => [
                'kind' => 'new_purchase',
                'headline' => '新购账单',
                'subheadline' => '首次购买产生的账单，通常包含产品价格、配置附加费与优惠信息。',
                'badge' => '新购',
                'highlight' => $productName !== '' ? $productName : (string) ($invoice->order?->order_no ?? ''),
                'remark' => $remark !== '' ? $remark : '新购订单已生成',
                'fields' => $this->buildPurchaseLikeSceneFields($invoice, $paymentSummary),
                'items' => $this->buildOrderBasedSceneItems($invoice),
            ],
            InvoiceType::RENEW => [
                'kind' => 'renew',
                'headline' => '续费账单',
                'subheadline' => '用于延长现有服务周期，通常与已有实例关联。',
                'badge' => '续费',
                'highlight' => $billingCycle !== '' ? $billingCycle : (string) ($invoice->order?->order_no ?? ''),
                'remark' => $remark !== '' ? $remark : '续费订单已生成',
                'fields' => $this->buildPurchaseLikeSceneFields($invoice, $paymentSummary),
                'items' => $this->buildOrderBasedSceneItems($invoice),
            ],
            InvoiceType::RECHARGE => [
                'kind' => 'recharge',
                'headline' => '充值账单',
                'subheadline' => '余额充值到账后生成，通常直接完成支付。',
                'badge' => '充值',
                'highlight' => $remark !== '' ? $remark : '资金到账',
                'remark' => $remark !== '' ? $remark : '充值金额已入账',
                'fields' => [
                    ['label' => '入账方式', 'value' => '余额充值'],
                    ['label' => '到账金额', 'value' => number_format((float) $invoice->amount, 2, '.', '')],
                    ['label' => '到账状态', 'value' => InvoiceStatus::$labels[$invoice->status] ?? (string) $invoice->status],
                    ['label' => '支付时间', 'value' => $invoice->paid_at?->format('Y-m-d H:i:s') ?: '--'],
                ],
                'items' => [[
                    'description' => '账户充值入账',
                    'amount' => $invoice->amount,
                ]],
            ],
            InvoiceType::DEDUCTION => [
                'kind' => 'deduction',
                'headline' => '扣款账单',
                'subheadline' => '管理员或系统发起的余额扣减记录。',
                'badge' => '扣款',
                'highlight' => $remark !== '' ? $remark : '余额扣减',
                'remark' => $remark !== '' ? $remark : '余额已扣减',
                'fields' => [
                    ['label' => '扣款方式', 'value' => '余额扣减'],
                    ['label' => '扣款金额', 'value' => number_format((float) $invoice->amount, 2, '.', '')],
                    ['label' => '到账状态', 'value' => InvoiceStatus::$labels[$invoice->status] ?? (string) $invoice->status],
                    ['label' => '处理时间', 'value' => $invoice->paid_at?->format('Y-m-d H:i:s') ?: '--'],
                ],
                'items' => [[
                    'description' => '账户扣款',
                    'amount' => $invoice->amount,
                ]],
            ],
            InvoiceType::REFUND => [
                'kind' => 'refund',
                'headline' => '退款红字账单',
                'subheadline' => '用于冲抵原账单的退款单据，可通过原账单编号追溯退款来源。',
                'badge' => '退款',
                'highlight' => (string) ($invoice->originInvoice?->invoice_no ?? $invoice->config_snapshot['origin_invoice_no'] ?? ''),
                'remark' => $remark !== '' ? $remark : '退款冲抵',
                'fields' => [
                    ['label' => '原账单', 'value' => (string) ($invoice->originInvoice?->invoice_no ?? $invoice->config_snapshot['origin_invoice_no'] ?? '--')],
                    ['label' => '退款金额', 'value' => number_format((float) $invoice->amount, 2, '.', '')],
                    ['label' => '退款时间', 'value' => $invoice->paid_at?->format('Y-m-d H:i:s') ?: '--'],
                ],
                'items' => [[
                    'description' => '退款冲抵',
                    'amount' => $invoice->amount,
                ]],
            ],
            InvoiceType::REFERRAL_CREDIT => [
                'kind' => 'referral_credit',
                'headline' => '推荐奖励账单',
                'subheadline' => '推荐返利结算到账后生成，金额通常直接入账到余额。',
                'badge' => '推荐奖励',
                'highlight' => $remark !== '' ? $remark : '推广返利入账',
                'remark' => $remark !== '' ? $remark : '推荐奖励已发放',
                'fields' => [
                    ['label' => '奖励类型', 'value' => '推荐返利'],
                    ['label' => '奖励金额', 'value' => number_format((float) $invoice->amount, 2, '.', '')],
                    ['label' => '到账状态', 'value' => InvoiceStatus::$labels[$invoice->status] ?? (string) $invoice->status],
                    ['label' => '入账时间', 'value' => $invoice->paid_at?->format('Y-m-d H:i:s') ?: '--'],
                ],
                'items' => [[
                    'description' => '推荐奖励入账',
                    'amount' => $invoice->amount,
                ]],
            ],
            InvoiceType::MANUAL => [
                'kind' => 'manual',
                'headline' => '手工账单',
                'subheadline' => '后台人工创建或修正的账单。',
                'badge' => '手工',
                'highlight' => $remark !== '' ? $remark : '人工账单',
                'remark' => $remark !== '' ? $remark : '后台手工处理',
                'fields' => [
                    ['label' => '处理方式', 'value' => '后台人工'],
                    ['label' => '账单金额', 'value' => number_format((float) $invoice->amount, 2, '.', '')],
                    ['label' => '账单状态', 'value' => InvoiceStatus::$labels[$invoice->status] ?? (string) $invoice->status],
                ],
                'items' => [[
                    'description' => $remark !== '' ? $remark : '后台手工账单',
                    'amount' => $invoice->amount,
                ]],
            ],
            default => [
                'kind' => 'default',
                'headline' => '账单详情',
                'subheadline' => '',
                'badge' => $this->resolveInvoiceTypeLabel((string) $invoice->type, $invoice),
                'highlight' => $remark !== '' ? $remark : '',
                'remark' => $remark,
                'fields' => $this->buildPurchaseLikeSceneFields($invoice, $paymentSummary),
            ],
        };
    }

    private function buildPurchaseLikeSceneFields(Invoice $invoice, ?array $paymentSummary): array
    {
        return array_values(array_filter([
            ['label' => '账单编号', 'value' => (string) $invoice->invoice_no],
            ['label' => '订单编号', 'value' => (string) ($invoice->order?->order_no ?? '')],
            ['label' => '配置名称', 'value' => $this->resolveInvoiceProductDisplayName($invoice)],
            ['label' => '计费周期', 'value' => trim((string) ($invoice->billing_cycle ?? $invoice->order?->billing_cycle ?? ''))],
            ['label' => '账单金额', 'value' => number_format((float) $invoice->amount, 2, '.', '')],
            ['label' => '已付金额', 'value' => number_format((float) ($invoice->paid_amount ?? 0), 2, '.', '')],
            ['label' => '应付金额', 'value' => Money::format($invoice->payableAmount())],
            ['label' => '数量', 'value' => (string) (int) ($invoice->quantity ?? 1)],
            ['label' => '支付方式', 'value' => (string) ($paymentSummary['gateway_label'] ?? '')],
            ['label' => '支付状态', 'value' => (string) ($paymentSummary['status_label'] ?? '')],
            ['label' => '优惠抵扣', 'value' => number_format((float) ($invoice->discount ?? 0), 2, '.', '')],
        ], static fn (array $item): bool => trim((string) ($item['value'] ?? '')) !== ''));
    }

    private function resolveInvoicePaymentSummary(Invoice $invoice): ?array
    {
        if (! $invoice->relationLoaded('payments')) {
            $invoice->loadMissing(['payments' => fn ($query) => $query->orderByDesc('id')]);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Payment> $payments */
        $payments = $invoice->payments;
        $payment = $this->resolvePrimaryInvoicePayment($payments);

        if (! $payment instanceof Payment) {
            // 余额支付按架构约定不产生 Payment（ARCHITECTURE.md「payments 是第三方资金流入记录」），
            // 渠道事实只落在账单名下的扣款台账上。不回落这一层，余额支付的账单「支付方式」会整行为空。
            return InvoicePaymentSummary::fromBalanceLedger($invoice);
        }

        $refund = (array) data_get((array) ($payment->callback_raw ?? []), 'refund', []);

        return [
            'id' => (int) $payment->id,
            'payment_no' => (string) $payment->payment_no,
            'trade_no' => (string) ($payment->trade_no ?? ''),
            'gateway' => $this->paymentGatewayKey($payment),
            'gateway_key' => $this->paymentGatewayKey($payment),
            'gateway_label' => PaymentChannelLabel::channelNameFromPayment($payment),
            'plugin_name' => PaymentChannelLabel::pluginNameFromPayment($payment),
            'channel_name' => PaymentChannelLabel::channelNameFromPayment($payment),
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'status' => (int) $payment->status,
            'status_label' => $this->resolvePaymentStatusLabel((int) $payment->status),
            'paid_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
            'refund_method' => (string) ($refund['refund_method'] ?? ''),
            'refund_method_label' => (string) ($refund['refund_method_label'] ?? ''),
            'refund_reason' => (string) ($refund['refund_reason'] ?? ''),
            'refunded_at' => (string) ($refund['refunded_at'] ?? ($refund['gmt_refund_pay'] ?? '')),
        ];
    }

    /**
     * 主支付单：成功/已退款里取最新一条（跳过重复支付转余额的记录）。
     *
     * @param  iterable<Payment>  $payments
     */
    private function resolvePrimaryInvoicePayment(iterable $payments): ?Payment
    {
        $collection = $this->channelPayments($payments);

        return $collection
            ->first(fn (Payment $payment) => ! (bool) data_get((array) ($payment->callback_raw ?? []), 'duplicate_paid', false)
                && in_array((int) $payment->status, [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED], true))
            ?? $collection->first(fn (Payment $payment) => in_array((int) $payment->status, [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED], true));
    }

    /**
     * 可展示支付方式的支付单。
     *
     * 判据不是「是否第三方真实资金流入」（那是资金边界审计口径），
     * 而是「渠道能否还原」：旧库的 payments 没有渠道快照列，真实渠道写在 callback_raw 里，
     * 只用 isThirdPartyGateway() 会让这批历史账单的「支付方式」整行消失。
     *
     * @return Collection<int, Payment>
     */
    private function channelPayments(iterable $payments): Collection
    {
        return collect($payments)
            ->filter(fn (Payment $payment) => PaymentChannelLabel::hasKnownChannel($payment))
            ->values();
    }

    private function paymentGatewayKey(Payment $payment): string
    {
        return PaymentChannelLabel::resolveGatewayKey($payment);
    }

    /**
     * 派生展示状态：状态机值域不变（0/1/4/5），仅在待支付基础上派生「部分已付」。
     *
     * 派生条件（设计文档《财务B2能力设计-部分核销与红字冲抵》§2.2）：账单仍为待支付，
     * 且 0 < paid_amount < amount。已退款派生优先（支付单已退款或账单已退款）。
     *
     * @return array{status: int, status_label: string, is_partial_paid: bool}
     */
    private function resolveInvoiceDisplayStatus(Invoice $invoice, ?array $paymentSummary): array
    {
        if (($paymentSummary['status'] ?? null) === PaymentStatus::REFUNDED || (int) $invoice->status === InvoiceStatus::REFUNDED) {
            return [
                'status' => InvoiceStatus::REFUNDED,
                'status_label' => '已退款',
                'is_partial_paid' => false,
            ];
        }

        $paidAmount = round((float) ($invoice->paid_amount ?? 0), 2);
        $totalAmount = round((float) $invoice->amount, 2);

        if ((int) $invoice->status === InvoiceStatus::UNPAID && $totalAmount > 0 && $paidAmount > 0 && $paidAmount < $totalAmount) {
            return [
                'status' => InvoiceStatus::PARTIAL_PAID,
                'status_label' => '部分已付',
                'is_partial_paid' => true,
            ];
        }

        return [
            'status' => (int) $invoice->status,
            'status_label' => InvoiceStatus::$labels[$invoice->status] ?? (string) $invoice->status,
            'is_partial_paid' => false,
        ];
    }

    private function resolveRefundScene(Invoice $invoice): ?array
    {
        $refundInfo = $this->extractRefundInfo($invoice);

        if ($refundInfo === null) {
            return null;
        }

        return [
            'kind' => 'refund',
            'headline' => '退款账单',
            'subheadline' => $refundInfo['subheadline'],
            'badge' => '退款',
            'highlight' => $refundInfo['highlight'],
            'remark' => $refundInfo['remark'],
            'fields' => array_values(array_filter([
                ['label' => '退款方式', 'value' => $refundInfo['method_label']],
                ['label' => '原支付金额', 'value' => number_format((float) $refundInfo['original_amount'], 2, '.', '')],
                ['label' => '退款金额', 'value' => number_format((float) $refundInfo['refund_amount'], 2, '.', '')],
                ['label' => '退款时间', 'value' => $refundInfo['refunded_at'] !== '' ? $refundInfo['refunded_at'] : '--'],
                ['label' => '退款原因', 'value' => $refundInfo['remark'] !== '' ? $refundInfo['remark'] : ''],
            ], static fn (array $item): bool => trim((string) ($item['value'] ?? '')) !== '')),
            'items' => [
                [
                    'description' => '原支付金额',
                    'amount' => $refundInfo['original_amount'],
                ],
                [
                    'description' => '退款金额',
                    'amount' => -1 * $refundInfo['refund_amount'],
                ],
            ],
        ];
    }

    private function extractRefundInfo(Invoice $invoice): ?array
    {
        $payment = null;
        if ($invoice->relationLoaded('payments')) {
            $payment = $this->channelPayments($invoice->payments)
                ->first(fn (Payment $item) => (int) $item->status === PaymentStatus::REFUNDED
                    || is_array(data_get((array) ($item->callback_raw ?? []), 'refund')));
        }

        if (! $payment instanceof Payment && (int) $invoice->status !== InvoiceStatus::REFUNDED) {
            return null;
        }

        $refund = (array) data_get((array) ($payment?->callback_raw ?? []), 'refund', []);
        $refundAmount = (float) ($refund['refund_amount'] ?? $payment?->amount ?? $invoice->amount ?? 0);
        $originalAmount = (float) ($payment?->amount ?? $invoice->amount ?? 0);
        $refundMethodLabel = trim((string) ($refund['refund_method_label'] ?? ''));

        if ($refundMethodLabel === '') {
            $refundMethod = trim((string) ($refund['refund_method'] ?? ''));
            // 退款口径（2026-10-10 裁决）：只保留余额退回，原路退款不提供。
            // 存量历史记录若曾标记 original，按普通「已退款」展示，不再输出「原路退款」。
            $refundMethodLabel = match ($refundMethod) {
                'balance' => '退回余额',
                default => '已退款',
            };
        }

        $refundReason = trim((string) ($refund['refund_reason'] ?? ''));
        $refundedAt = trim((string) ($refund['refunded_at'] ?? ($refund['gmt_refund_pay'] ?? '')));

        return [
            'highlight' => $refundMethodLabel !== '' ? $refundMethodLabel : '已退款',
            'remark' => $refundReason,
            'subheadline' => $refundedAt !== '' ? "退款时间：{$refundedAt}" : '该账单已完成退款。',
            'refund_amount' => $refundAmount > 0 ? $refundAmount : $originalAmount,
            'original_amount' => $originalAmount,
            'refunded_at' => $refundedAt,
            'method_label' => $refundMethodLabel !== '' ? $refundMethodLabel : '已退款',
        ];
    }

    private function buildOrderBasedSceneItems(Invoice $invoice): array
    {
        $items = [];
        $productName = $this->resolveInvoiceProductDisplayName($invoice);
        $billingCycle = trim((string) ($invoice->billing_cycle ?? $invoice->order?->billing_cycle ?? ''));
        // 商品行按目录价展示（应付价 + 券减免 + 会员折扣），折扣各成负数行，行合计 = 应付价
        $grossAmount = (float) ($invoice->amount ?? 0)
            + (float) ($invoice->discount ?? 0)
            + (float) ($invoice->member_discount_amount ?? 0);

        if ($productName !== '') {
            $items[] = [
                'description' => $billingCycle !== '' ? "{$productName} / {$billingCycle}" : $productName,
                'amount' => $grossAmount,
            ];
        }

        if ((float) ($invoice->discount ?? 0) > 0) {
            $items[] = [
                'description' => '优惠券减免',
                'amount' => -1 * (float) $invoice->discount,
            ];
        }

        if ((float) ($invoice->member_discount_amount ?? 0) > 0) {
            $items[] = [
                'description' => '会员折扣',
                'amount' => -1 * (float) $invoice->member_discount_amount,
            ];
        }

        return $items;
    }

    private function resolveInvoiceSceneFallbackSubheadline(Invoice $invoice): string
    {
        return match (InvoiceType::normalize((string) $invoice->type)) {
            InvoiceType::RECHARGE => '该账单主要反映余额入账，不依赖订单或服务实例。',
            InvoiceType::DEDUCTION => '该账单主要反映余额扣减，不依赖订单或服务实例。',
            InvoiceType::REFUND => '该账单为退款红字凭证，用于冲抵原账单资金。',
            InvoiceType::REFERRAL_CREDIT => '该账单主要反映推荐奖励入账，不依赖订单或服务实例。',
            InvoiceType::MANUAL => '该账单由后台人工创建或修正，重点查看备注和支付方式。',
            default => '',
        };
    }

    private function resolveInvoiceTypeLabel(string $type, Invoice $invoice): string
    {
        return match (InvoiceType::normalize($type)) {
            InvoiceType::NEW_PURCHASE => '新购账单',
            InvoiceType::RENEW => '续费账单',
            InvoiceType::RECHARGE => '充值账单',
            InvoiceType::UPGRADE => '附加配置账单',
            InvoiceType::DEDUCTION => '扣款账单',
            InvoiceType::REFUND => '退款红字账单',
            InvoiceType::REFERRAL_CREDIT => '推荐奖励账单',
            InvoiceType::MANUAL => '手工账单',
            default => ($invoice->order?->display_product_name ?? '') !== '' ? '产品账单' : '普通账单',
        };
    }

    private function resolvePaymentStatusLabel(int $status): string
    {
        return match ($status) {
            PaymentStatus::SUCCESS => '已支付',
            PaymentStatus::REFUNDED => '已退款',
            PaymentStatus::CANCELLED => '已取消',
            default => '未支付',
        };
    }

    private function buildInvoiceLogs(Invoice $invoice, array $scene): array
    {
        if (in_array((string) $invoice->type, [InvoiceType::RECHARGE, InvoiceType::DEDUCTION, InvoiceType::REFERRAL_CREDIT], true)) {
            return [];
        }

        return is_array($scene['logs'] ?? null) ? $scene['logs'] : $this->resolveInvoiceLogs($invoice);
    }

    public function syncProjection(Invoice $invoice): Invoice
    {
        $invoice->syncInvoiceItemProjection();

        return $invoice->fresh([
            'items',
            'order',
            'product:id,product_type,service_type_code,product_group_id,remark,purchase_requires',
        ]) ?? $invoice;
    }

    private function resolveInvoiceProductDisplayName(Invoice $invoice): string
    {
        $orderDisplayName = trim((string) ($invoice->order?->display_product_name ?? ''));
        if ($orderDisplayName !== '') {
            return $orderDisplayName;
        }

        $snapshotDisplayName = trim((string) ($invoice->display_product_name ?? ''));
        if ($snapshotDisplayName !== '') {
            return $snapshotDisplayName;
        }

        if ($invoice->product instanceof Product) {
            $resolved = $this->resolveProductDisplayNameResolver()->resolveForProduct(
                $invoice->product,
                (array) ($invoice->config_snapshot ?? [])
            );
            $productDisplayName = trim((string) ($resolved['product_spec_display'] ?? $resolved['product_display_name'] ?? ''));
            if ($productDisplayName !== '') {
                return $productDisplayName;
            }
        }

        return $this->resolveInvoiceTypeLabel((string) $invoice->type, $invoice);
    }

    private function resolveOrderProductName(Order $order): string
    {
        $snapshot = trim((string) ($order->display_product_name ?? $order->product_spec_snapshot ?? ''));
        if ($snapshot !== '' && $snapshot !== '未配置规格') {
            return $snapshot;
        }

        if ($order->product instanceof Product) {
            $resolved = $this->resolveProductDisplayNameResolver()->resolveForProduct(
                $order->product,
                (array) ($order->config_snapshot ?? [])
            );
            $name = trim((string) ($resolved['combined_display_name'] ?? $resolved['product_spec_display'] ?? $resolved['product_display_name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        $productId = (int) ($order->product_id ?? 0);

        return $productId > 0 ? "产品 #{$productId}" : '未配置产品';
    }

    private function resolveOrderProductPath(Order $order): string
    {
        if (! ($order->product instanceof Product)) {
            return $this->resolveOrderProductName($order);
        }

        return app(ProductFullPathResolver::class)->pathForOrder($order);
    }

    private function resolveInvoiceProductSpecDisplay(Invoice $invoice, array $scene, string $productDisplayName): string
    {
        $standaloneSpecText = $this->resolveStandaloneInvoiceSpecText((string) $invoice->type);
        if ($standaloneSpecText !== '') {
            return $standaloneSpecText;
        }

        $snapshot = trim((string) ($invoice->product_spec_snapshot ?? ''));
        if ($snapshot !== '') {
            return $snapshot;
        }

        if ($productDisplayName !== '') {
            return $productDisplayName;
        }

        $subheadline = trim((string) ($scene['subheadline'] ?? ''));
        if ($subheadline !== '') {
            return $subheadline;
        }

        return $this->resolveInvoiceTypeLabel((string) $invoice->type, $invoice);
    }

    private function resolveStandaloneInvoiceSpecText(string $type): string
    {
        return match (InvoiceType::normalize($type)) {
            InvoiceType::RECHARGE => '余额充值',
            InvoiceType::UPGRADE => '附加配置',
            InvoiceType::DEDUCTION => '余额扣减',
            InvoiceType::REFERRAL_CREDIT => '推荐返利',
            InvoiceType::MANUAL => '后台人工',
            default => '',
        };
    }

    private function resolveInvoiceCombinedDisplayName(Invoice $invoice, string $fallbackDisplayName = ''): string
    {
        $product = $invoice->product instanceof Product
            ? $invoice->product
            : ($invoice->order?->product instanceof Product ? $invoice->order->product : null);
        if (! $product instanceof Product) {
            return $fallbackDisplayName !== ''
                ? $fallbackDisplayName
                : $this->resolveInvoiceTypeLabel((string) $invoice->type, $invoice);
        }

        $resolved = $this->resolveProductDisplayNameResolver()->resolveForProduct(
            $product,
            (array) ($invoice->config_snapshot ?? [])
        );

        $combinedDisplayName = trim((string) ($resolved['combined_display_name'] ?? ''));

        return $combinedDisplayName !== '' ? $combinedDisplayName : $fallbackDisplayName;
    }

    private function resolveProductDisplayNameResolver(): ProductDisplayNameResolver
    {
        // 必须与容器单例一致：列表预载写在单例上，这里若新建实例，预载会被架空、逐行解析仍各发一次查询。
        return $this->productDisplayNameResolver ?? app(ProductDisplayNameResolver::class);
    }

    /**
     * 按当页账单一次性预载展示名解析所需的商品数据（源商品 + custom_display_name）。
     *
     * @param  array<int, Model>  $invoices  分页器 items() 的静态类型是基类 Model
     */
    private function preloadInvoiceProductDisplayNames(array $invoices): void
    {
        $products = collect($invoices)
            ->filter(static fn ($invoice): bool => $invoice instanceof Invoice)
            ->flatMap(static function (Invoice $invoice): array {
                // 只读已预载关系，避免把一次预载退化成又一轮逐行查询。
                $products = [];

                $order = $invoice->getRelation('order');
                if ($order instanceof Order && $order->getRelation('product') instanceof Product) {
                    $products[] = $order->getRelation('product');
                }
                if ($invoice->getRelation('product') instanceof Product) {
                    $products[] = $invoice->getRelation('product');
                }

                return $products;
            })
            ->filter()
            ->unique(static fn (Product $product): int => (int) $product->id)
            ->values();

        if ($products->isEmpty()) {
            return;
        }

        $resolver = $this->resolveProductDisplayNameResolver();
        $resolver->preloadSourceProducts($products);
        $resolver->preloadCustomDisplayNames($products);
    }

    public function markPaidManually(Invoice $invoice, array $payload, array $context = []): Invoice
    {
        $paidAt = ! empty($payload['paid_at'])
            ? Carbon::parse((string) $payload['paid_at'])
            : now();
        $requestedAmount = round((float) ($payload['amount'] ?? $invoice->amount), 2);
        $paymentGateway = trim((string) ($payload['payment_gateway'] ?? 'manual')) ?: 'manual';
        throw_if(
            ! in_array($paymentGateway, array_merge(ManualPaymentGateway::values(), ['manual']), true),
            new BusinessException('不支持的支付方式')
        );
        $tradeNo = trim((string) ($payload['trade_no'] ?? ''));
        $sendEmail = (bool) ($payload['send_email'] ?? false);
        $remark = trim((string) ($payload['remark'] ?? ''));
        $syncBusinessFlow = (bool) ($payload['sync_business_flow'] ?? false);
        $traceId = trim((string) ($context['trace_id'] ?? ''));

        $isFullPayment = false;

        $updatedInvoice = DB::transaction(function () use ($invoice, $paidAt, $requestedAmount, $traceId, $paymentGateway, $tradeNo, $remark, $context, &$isFullPayment): Invoice {
            $lockedInvoice = Invoice::query()
                ->lockForUpdate()
                ->with('order')
                ->findOrFail((int) $invoice->id);

            throw_if(
                ! in_array((int) $lockedInvoice->status, [InvoiceStatus::UNPAID], true),
                new BusinessException('当前账单状态不支持手动入账')
            );

            $payableAmount = $lockedInvoice->payableAmount();
            throw_if($payableAmount <= 0, new BusinessException('当前账单无需再入账'));
            throw_if($requestedAmount <= 0, new BusinessException('入账金额必须大于 0'));
            throw_if(Money::greaterThan($requestedAmount, $payableAmount), new BusinessException('入账金额不能超过账单应付金额'));
            // 部分核销（B2 批次 1）：每次入账独立成 payments 行并累加 paid_amount；
            // 仅当本次结清剩余应付时才走 markInvoicePaid 终态（触发履约/服务开通，语义不变）。
            $isFullPayment = ! Money::greaterThan(abs($requestedAmount - $payableAmount), 0);

            // 每次手工入账都关闭同账单既有 PENDING 网关支付单：部分核销后应付额已变化，
            // 旧二维码的金额与新应付脱节，扫码整笔支付只能按金额不符转入余额（M-62）。
            // 预扣余额不在此回补——它已计入 paid_amount，成为本次结清的一部分。
            Payment::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->where('status', PaymentStatus::PENDING)
                ->lockForUpdate()
                ->get()
                ->each(function (Payment $payment) use ($traceId, $isFullPayment): void {
                    $callbackRaw = (array) ($payment->callback_raw ?? []);
                    $callbackRaw['closed_reason'] = $isFullPayment
                        ? 'invoice_paid_manually'
                        : 'invoice_partially_paid_manually';
                    $callbackRaw['trace_id'] = $traceId !== '' ? $traceId : (string) ($payment->trace_id ?? '');

                    $payment->forceFill([
                        'status' => PaymentStatus::CANCELLED,
                        'callback_raw' => $callbackRaw,
                    ])->save();
                    $this->callbackProjector->syncProjection($payment);
                });

            // 补一条 manual Payment 审计记录，保留 trade_no 与入账信息，供财务对账追溯。
            $manualPayment = new Payment([
                'payment_no' => Payment::generatePaymentNo(),
                'user_id' => (int) $lockedInvoice->user_id,
                'order_id' => (int) ($lockedInvoice->order_id ?? 0) ?: null,
                'invoice_id' => (int) $lockedInvoice->id,
                'gateway' => PaymentGatewayCode::MANUAL,
                'trade_no' => $tradeNo !== '' ? $tradeNo : null,
                'amount' => number_format($requestedAmount, 2, '.', ''),
                'status' => PaymentStatus::SUCCESS,
                'paid_at' => $paidAt,
                'remark' => $remark !== '' ? $remark : null,
                'operator' => trim((string) ($context['operator_name'] ?? '')) ?: null,
                'trace_id' => $traceId,
                'callback_raw' => [
                    'source' => 'admin_manual_entry',
                    'payment_gateway' => $paymentGateway,
                    'operator_id' => (int) ($context['operator_id'] ?? 0),
                    'operator_name' => (string) ($context['operator_name'] ?? ''),
                    'remark' => $remark,
                    'trade_no' => $tradeNo,
                    'partial' => ! $isFullPayment,
                ],
            ]);
            $manualPayment->allowNonThirdPartyGateway = true;
            $manualPayment->save();
            $this->callbackProjector->syncProjection($manualPayment);

            if ($isFullPayment) {
                $lockedInvoice = $this->tradeLifecycleService->markInvoicePaid($lockedInvoice, [
                    'paid_amount' => number_format(round((float) ($lockedInvoice->paid_amount ?? 0) + $requestedAmount, 2), 2, '.', ''),
                    'paid_at' => $paidAt,
                    'trace_id' => $traceId,
                ]);
            } else {
                // 部分核销只做投影累加（账单+订单 paid_amount），不改变状态、不触发履约。
                $this->tradeLifecycleService->reservePaidProjection($lockedInvoice, $requestedAmount);
            }

            return $lockedInvoice->fresh(['order', 'items', 'payments']) ?? $lockedInvoice;
        });

        if ($syncBusinessFlow && $isFullPayment) {
            // app() 延迟解析：本类经 InvoicePaidOrchestrator -> ServiceRenewService 反向依赖自身，
            // 构造器注入会形成容器环，与既有回调链路的解法保持一致。
            app(InvoicePaidOrchestrator::class)->handlePaidInvoice($updatedInvoice, $traceId !== '' ? 'manual:'.$traceId : 'manual:invoice:'.$updatedInvoice->id);
        }

        app(OperationLogService::class)->write(
            userId: ((int) ($context['operator_id'] ?? 0)) ?: null,
            userType: 'admin',
            action: 'invoice.payment.mark_paid',
            module: 'invoice',
            targetId: (int) $updatedInvoice->id,
            detail: [
                'invoice_no' => (string) $updatedInvoice->invoice_no,
                'paid_amount' => number_format((float) $updatedInvoice->paid_amount, 2, '.', ''),
                'entry_amount' => number_format($requestedAmount, 2, '.', ''),
                'partial' => ! $isFullPayment,
                'paid_at' => $paidAt->format('Y-m-d H:i:s'),
                'payment_gateway' => $paymentGateway,
                'trade_no' => trim((string) ($payload['trade_no'] ?? '')),
                'send_email' => $sendEmail,
                'sync_business_flow' => $syncBusinessFlow,
                'remark' => $remark,
                'actor_name' => (string) ($context['operator_name'] ?? ''),
                'trace_id' => $traceId,
            ],
            ipAddress: (string) ($context['ip_address'] ?? '') ?: null,
        );

        return $updatedInvoice;
    }

    private function resolveInvoiceLogs(Invoice $invoice): array
    {
        if (! $invoice->order_id) {
            return [];
        }

        return ActivityLog::query()
            ->where('module', 'order')
            ->where('subject_id', $invoice->order_id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => (int) $log->id,
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
                'action' => $log->action,
                'summary' => $this->stringifyOperationDetail((array) ($log->context ?? [])),
                'tone' => $this->resolveLogTone((string) $log->action),
            ])
            ->values()
            ->all();
    }

    private function resolveLogTone(string $action): string
    {
        return match (true) {
            str_contains($action, 'refund') => 'danger',
            str_contains($action, 'cancel') => 'warning',
            str_contains($action, 'paid') => 'success',
            default => 'info',
        };
    }

    private function stringifyOperationDetail(array $detail): string
    {
        if ($detail === []) {
            return '-';
        }

        $pairs = [];

        foreach ($detail as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $value = '';
            }

            $pairs[] = "{$key}: {$value}";
        }

        return implode(' | ', $pairs);
    }
}
