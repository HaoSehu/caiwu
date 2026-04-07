<?php

namespace App\Http\Controllers\Client;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\AlipayFaceToFaceService;
use App\Services\CheckoutSecurityService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\ServiceHostname;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private PaymentService $paymentService,
        private AlipayFaceToFaceService $alipayService,
        private CheckoutSecurityService $checkoutSecurityService,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Order::with(['product:id,name'])
            ->where('user_id', $request->user()->id)
            ->orderBy('id', 'desc');

        if (array_key_exists('status', $filters) && $filters['status'] !== null) {
            $query->where('status', (int) $filters['status']);
        }

        $perPage = (int) ($filters['page_size'] ?? $filters['per_page'] ?? 15);
        $list = $query->paginate($perPage);

        $items = collect($list->items())->map(fn ($order) => [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'product' => [
                'id' => $order->product_id,
                'name' => (string) $order->display_product_name,
            ],
            'amount' => number_format(max((float) $order->amount - (float) ($order->discount ?? 0), 0), 2, '.', ''),
            'original_amount' => number_format((float) $order->amount, 2, '.', ''),
            'discount' => number_format((float) ($order->discount ?? 0), 2, '.', ''),
            'status' => (int) $order->status,
            'status_label' => OrderStatus::$labels[$order->status] ?? (string) $order->status,
            'can_cancel' => (int) $order->status === OrderStatus::PENDING,
            'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
        ]);

        return $this->success([
            'list' => $items,
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'page_size' => $list->perPage(),
            'per_page' => $list->perPage(),
        ]);
    }

    public function summary(Request $request)
    {
        $userId = $request->user()->id;

        $total = Order::where('user_id', $userId)->count();
        $unpaid = Order::where('user_id', $userId)->where('status', OrderStatus::PENDING)->count();
        $completed = Order::where('user_id', $userId)->where('status', OrderStatus::PAID)->count();
        $unpaidAmount = Order::query()
            ->selectRaw('COALESCE(SUM(amount - discount), 0) as unpaid_amount')
            ->where('status', OrderStatus::PENDING)
            ->where('user_id', $userId)
            ->value('unpaid_amount');

        return $this->success([
            'total' => $total,
            'unpaid' => $unpaid,
            'completed' => $completed,
            'unpaid_amount' => number_format((float) $unpaidAmount, 2, '.', ''),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'billing_cycle' => ['required', 'string', 'max:30'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'config' => ['nullable', 'array'],
            'quote_token' => ['required', 'string', 'min:20', 'max:120'],
            'user_coupon_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $order = $this->orderService->create(
            $request->user()->id,
            $data,
            $this->buildOperationContext($request)
        );
        $order->loadMissing(['product:id,name,config_options', 'invoice', 'service']);

        return $this->success($this->transformOrder($order), '订单创建成功');
    }

    public function show(Request $request, int $id)
    {
        $order = Order::with(['product:id,name,config_options', 'invoice', 'service'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return $this->success($this->transformOrder($order));
    }

    public function cancel(Request $request, int $id)
    {
        $order = Order::with(['product:id,name,config_options', 'invoice', 'service'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $updatedOrder = $this->orderService->cancel(
            $order,
            array_merge($this->buildOperationContext($request), [
                'reason' => 'client_manual_cancel',
            ])
        );

        return $this->success($this->transformOrder($updatedOrder), '订单已取消');
    }

    public function payByBalance(Request $request, int $id)
    {
        $data = $request->validate([
            'payment_session_token' => ['required', 'string', 'min:20', 'max:120'],
        ]);

        $user = $request->user();
        $order = Order::with(['product:id,name,config_options', 'invoice', 'service'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        if (! $order->invoice) {
            return $this->error(40400, '账单不存在');
        }

        $this->checkoutSecurityService->assertPaymentSessionToken(
            (string) $data['payment_session_token'],
            $order,
            (int) $user->id
        );

        $payment = $this->paymentService->payByBalance(
            $order->invoice,
            $user,
            $this->buildOperationContext($request)
        );

        $order->refresh()->load(['product:id,name,config_options', 'invoice', 'service']);
        $user->refresh();

        return $this->success([
            'payment_no' => $payment->payment_no,
            'gateway' => $payment->gateway,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'paid_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
            'balance' => number_format((float) ($user->account?->cash_balance ?? $user->balance), 2, '.', ''),
            'order' => $this->transformOrder($order),
        ], '支付成功');
    }

    /**
     * 支付宝当面付 — 预下单
     */
    public function payByAlipay(Request $request, int $id)
    {
        $data = $request->validate([
            'payment_session_token' => ['required', 'string', 'min:20', 'max:120'],
        ]);

        $user  = $request->user();
        $order = Order::with(['product:id,name,config_options', 'invoice'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        if (! $order->invoice) {
            return $this->error(40400, '账单不存在');
        }

        $this->checkoutSecurityService->assertPaymentSessionToken(
            (string) $data['payment_session_token'],
            $order,
            (int) $user->id
        );

        $result = $this->paymentService->payByAlipay(
            $order->invoice,
            $user,
            $this->buildOperationContext($request)
        );

        $payment = Payment::query()
            ->where('payment_no', (string) ($result['payment_no'] ?? ''))
            ->where('invoice_id', $order->invoice->id)
            ->where('user_id', $user->id)
            ->where('gateway', 'alipay')
            ->first();

        if (! $payment) {
            return $this->error(40400, '支付记录不存在');
        }

        $pollSecurity = $this->checkoutSecurityService->issuePaymentPollToken($payment, $order, (int) $user->id);

        return $this->success(array_merge($result, $pollSecurity), '二维码生成成功');
    }

    /**
     * 轮询支付宝支付状态
     */
    public function queryAlipayStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'payment_no' => ['required', 'string', 'max:50'],
            'poll_token' => ['required', 'string', 'min:20', 'max:120'],
        ]);

        $user  = $request->user();
        $order = Order::with(['product:id,name,config_options', 'invoice', 'service'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        if (! $order->invoice) {
            return $this->error(40400, '账单不存在');
        }

        $payment = Payment::query()
            ->where('payment_no', (string) $data['payment_no'])
            ->where('invoice_id', $order->invoice->id)
            ->where('user_id', $user->id)
            ->where('gateway', 'alipay')
            ->first();

        if (! $payment) {
            return $this->error(40400, '未找到支付宝支付记录');
        }

        $this->checkoutSecurityService->assertPaymentPollToken(
            (string) $data['poll_token'],
            $payment,
            $order,
            (int) $user->id
        );

        $result = $this->paymentService->queryAlipayStatus($payment);

        $data = $result;
        if ($result['paid']) {
            $order->refresh()->load(['product:id,name,config_options', 'invoice', 'service']);
            $data['order'] = $this->transformOrder($order);
        }

        return $this->success($data);
    }

    private function transformOrder(Order $order): array
    {
        $invoice = $order->invoice;
        $service = $order->service;
        $configPricingSnapshot = $this->resolveConfigPricingSnapshot($order);
        $paymentSecurity = $this->checkoutSecurityService->issuePaymentSession($order, (int) $order->user_id);
        $canPay = in_array((int) $order->status, [OrderStatus::PENDING], true)
            && $invoice
            && in_array((int) $invoice->status, [InvoiceStatus::UNPAID, InvoiceStatus::OVERDUE], true)
            && (float) $invoice->amount - (float) ($invoice->paid_amount ?? 0) > 0;

        $payMethods = [['key' => 'balance', 'name' => '余额支付']];
        if ($this->alipayService->isEnabled()) {
            $alipayName = \App\Models\Setting::getValue('payment', 'alipay_name') ?: '支付宝支付';
            $payMethods[] = ['key' => 'alipay', 'name' => $alipayName];
        }

        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'type' => (string) $order->type,
            'service_id' => $order->service_id,
            'pay_methods' => $payMethods,
            'product' => [
                'id' => $order->product_id,
                'name' => (string) $order->display_product_name,
                'config_options' => is_array($order->product?->config_options ?? null)
                    ? array_values($order->product->config_options)
                    : [],
            ],
            'billing_cycle' => (string) $order->billing_cycle,
            'quantity' => (int) ($order->quantity ?? 1),
            'amount' => number_format((float) $order->amount, 2, '.', ''),
            'discount' => number_format((float) ($order->discount ?? 0), 2, '.', ''),
            'paid_amount' => number_format((float) ($order->paid_amount ?? 0), 2, '.', ''),
            'status' => (int) $order->status,
            'status_label' => OrderStatus::$labels[$order->status] ?? (string) $order->status,
            'can_cancel' => (int) $order->status === OrderStatus::PENDING,
            'config_snapshot' => (array) ($order->config_snapshot ?? []),
            'config_pricing_snapshot' => $configPricingSnapshot,
            'coupon' => (int) ($order->coupon_id ?? 0) > 0 ? [
                'id' => (int) ($order->coupon_id ?? 0),
                'name' => (string) ($order->coupon_snapshot['name'] ?? ''),
                'description' => (string) ($order->coupon_snapshot['description'] ?? ''),
                'discount_label' => (string) ($order->coupon_snapshot['discount_label'] ?? ''),
                'discount_amount' => number_format((float) ($order->discount ?? 0), 2, '.', ''),
            ] : null,
            'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
            'paid_at' => $order->paid_at?->format('Y-m-d H:i:s'),
            'invoice' => $invoice ? [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'amount' => number_format((float) $invoice->amount, 2, '.', ''),
                'paid_amount' => number_format((float) ($invoice->paid_amount ?? 0), 2, '.', ''),
                'payable_amount' => number_format(max((float) $invoice->amount - (float) ($invoice->paid_amount ?? 0), 0), 2, '.', ''),
                'status' => (int) $invoice->status,
                'status_label' => InvoiceStatus::$labels[$invoice->status] ?? (string) $invoice->status,
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'paid_at' => $invoice->paid_at?->format('Y-m-d H:i:s'),
            ] : null,
            'service' => $service ? [
                'id' => $service->id,
                'name' => $service->name,
                'domain' => ServiceHostname::resolveDisplayDomain($service, (array) ($service->provision_data ?? [])),
                'status' => (int) $service->status,
                'status_label' => ServiceStatus::$labels[$service->status] ?? (string) $service->status,
                'expires_at' => $service->expires_at?->format('Y-m-d H:i:s'),
                'suspended_reason' => $service->suspended_reason,
                'upstream_host_id' => (int) (($service->provision_data['upstream_host_id'] ?? 0) ?: 0),
                'provision_error' => (string) ($service->provision_data['provision_error'] ?? ''),
            ] : null,
            'payment_security' => [
                'can_pay' => (bool) $canPay,
                'session_token' => (string) ($paymentSecurity['session_token'] ?? ''),
                'expires_at' => $paymentSecurity['expires_at'] ?? null,
            ],
        ];
    }

    private function resolveConfigPricingSnapshot(Order $order): array
    {
        $snapshot = $order->config_pricing_snapshot;
        if (is_array($snapshot) && $snapshot !== []) {
            return $snapshot;
        }

        if ((string) $order->type !== 'new' || ! $order->product || ! $order->billing_cycle) {
            return [];
        }

        try {
            return $this->orderService->buildConfigPricingSnapshot(
                $order->product,
                (string) $order->billing_cycle,
                (array) ($order->config_snapshot ?? []),
                (int) ($order->quantity ?? 1)
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private function buildOperationContext(Request $request, string $actorType = 'client'): array
    {
        $user = $request->user();

        return [
            'actor_type' => $actorType,
            'actor_user_id' => (int) ($user?->id ?? 0),
            'actor_name' => (string) ($user?->display_name ?? $user?->nickname ?? $user?->email ?? ''),
            'ip_address' => (string) $request->ip(),
            'trace_id' => (string) $request->header('X-Request-Id', ''),
            'idempotency_key' => (string) $request->header('X-Idempotency-Key', ''),
        ];
    }
}
