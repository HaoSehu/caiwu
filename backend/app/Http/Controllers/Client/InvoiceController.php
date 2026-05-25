<?php

namespace App\Http\Controllers\Client;

use App\Constants\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\CheckoutService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\PaymentGateway\AlipayFaceToFaceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
        private PaymentService $paymentService,
        private AlipayFaceToFaceService $alipayService,
        private CheckoutSecurityService $checkoutSecurityService,
        private CheckoutService $checkoutService,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'integer'],
            'type' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Invoice::with([
                'product:id,product_type,product_group_id,config_options,purchase_requires',
                'user:id,email,nickname',
                'service:id,name,status,expires_at',
                'payments',
                'items',
                'order:id,order_no,status,type,service_id,paid_at,product_id,billing_cycle',
                'order.product:id,product_type,product_group_id,config_options,purchase_requires',
            ])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id');

        if (($filters['status'] ?? null) === 5) {
            $query->where(function ($builder) {
                $builder->whereHas('payments', fn ($paymentQuery) => $paymentQuery->where('status', 3))
                    ->orWhere('status', InvoiceStatus::REFUNDED);
            });
        } elseif (array_key_exists('status', $filters) && $filters['status'] !== null) {
            $query->where('status', (int) $filters['status']);
        }
        if (! empty($filters['type'])) {
            $type = (string) $filters['type'];
            if (in_array($type, ['new', 'normal'], true)) {
                $query->whereIn('type', ['new', 'normal']);
            } else {
                $query->where('type', $type);
            }
        }

        $perPage = (int) ($filters['page_size'] ?? $filters['per_page'] ?? 15);
        $list = $query->paginate($perPage);

        $items = collect($list->items())
            ->map(fn (Invoice $invoice) => $this->buildClientListItem($invoice))
            ->values()
            ->all();

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
        $userId = (int) $request->user()->id;

        $row = Invoice::query()
            ->where('user_id', $userId)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS unpaid', [InvoiceStatus::UNPAID])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS paid', [InvoiceStatus::PAID])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS overdue', [InvoiceStatus::OVERDUE])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status IN (?,?) THEN amount - COALESCE(paid_amount,0) ELSE 0 END), 0) AS unpaid_amount',
                [InvoiceStatus::UNPAID, InvoiceStatus::OVERDUE]
            )
            ->first();

        return $this->success([
            'total' => (int) ($row?->total ?? 0),
            'unpaid' => (int) ($row?->unpaid ?? 0),
            'paid' => (int) ($row?->paid ?? 0),
            'overdue' => (int) ($row?->overdue ?? 0),
            'unpaid_amount' => number_format((float) ($row?->unpaid_amount ?? 0), 2, '.', ''),
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

        $idempotencyKey = trim((string) $request->header('X-Idempotency-Key', ''));
        $context = array_merge($this->buildOperationContext($request), [
            'idempotency_key' => $idempotencyKey,
        ]);

        $invoice = $this->checkoutService->create($request->user()->id, $data, $context);
        $invoice->loadMissing([
            'product:id,product_type,product_group_id,config_options,purchase_requires',
            'order.product:id,product_type,product_group_id,config_options,purchase_requires',
            'service',
            'payments',
        ]);

        return $this->success($this->transformInvoice($invoice, $request->user()), '账单创建成功');
    }

    public function show(Request $request, int $id)
    {
        $invoice = Invoice::with([
            'product:id,product_type,product_group_id,config_options,purchase_requires',
            'order.product:id,product_type,product_group_id,config_options,purchase_requires',
            'service',
            'payments',
        ])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return $this->success($this->transformInvoice($invoice, $request->user()));
    }

    public function cancel(Request $request, int $id)
    {
        $invoice = Invoice::with([
            'product:id,product_type,product_group_id,config_options,purchase_requires',
            'order.product:id,product_type,product_group_id,config_options,purchase_requires',
            'service',
            'payments',
        ])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $updated = $this->checkoutService->cancel(
            $invoice,
            array_merge($this->buildOperationContext($request), [
                'reason' => 'client_manual_cancel',
            ])
        );
        $updated->loadMissing([
            'product:id,product_type,product_group_id,config_options,purchase_requires',
            'order.product:id,product_type,product_group_id,config_options,purchase_requires',
            'service',
            'payments',
        ]);

        return $this->success($this->transformInvoice($updated, $request->user()), '账单已取消');
    }

    public function payByBalance(Request $request, int $id)
    {
        $data = $request->validate([
            'payment_session_token' => ['required', 'string', 'min:20', 'max:120'],
        ]);

        $user = $request->user();
        $invoice = Invoice::with([
            'product:id,product_type,product_group_id,config_options,purchase_requires',
            'order.product:id,product_type,product_group_id,config_options,purchase_requires',
            'service',
            'payments',
        ])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $this->checkoutSecurityService->assertInvoicePaymentSessionToken(
            (string) $data['payment_session_token'],
            $invoice,
            (int) $user->id
        );

        $payment = $this->paymentService->payByBalance(
            $invoice,
            $user,
            $this->buildOperationContext($request)
        );

        $invoice->refresh()->load(['product:id,product_type,product_group_id,config_options,purchase_requires', 'service', 'payments']);
        $user->refresh();

        return $this->success([
            'payment_no' => (string) $payment->payment_no,
            'gateway' => (string) $payment->gateway,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'paid_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
            'balance' => number_format((float) $user->balance, 2, '.', ''),
            'invoice' => $this->transformInvoice($invoice, $user),
        ], '支付成功');
    }

    public function payByBalanceAndAlipay(Request $request, int $id)
    {
        $data = $request->validate([
            'payment_session_token' => ['required', 'string', 'min:20', 'max:120'],
            'balance_amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $user = $request->user();
        $invoice = Invoice::with([
            'product:id,product_type,product_group_id,config_options,purchase_requires',
            'order.product:id,product_type,product_group_id,config_options,purchase_requires',
            'service',
            'payments',
        ])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $this->checkoutSecurityService->assertInvoicePaymentSessionToken(
            (string) $data['payment_session_token'],
            $invoice,
            (int) $user->id
        );

        $result = $this->paymentService->payByBalanceAndAlipay(
            $invoice,
            $user,
            (float) $data['balance_amount'],
            $this->buildOperationContext($request)
        );

        $invoice->refresh()->load(['product:id,product_type,product_group_id,config_options,purchase_requires', 'service', 'payments']);
        $payment = Payment::query()
            ->where('payment_no', (string) ($result['payment_no'] ?? ''))
            ->where('invoice_id', $invoice->id)
            ->where('user_id', $user->id)
            ->where('gateway', 'alipay')
            ->first();

        if (! $payment) {
            return $this->error(40400, '支付记录不存在');
        }

        $pollSecurity = $this->checkoutSecurityService->issueInvoicePaymentPollToken($payment, $invoice, (int) $user->id);

        return $this->success([
            ...$result,
            ...$pollSecurity,
            'invoice' => $this->transformInvoice($invoice, $user),
        ], '组合支付二维码生成成功');
    }

    public function payByAlipay(Request $request, int $id)
    {
        $data = $request->validate([
            'payment_session_token' => ['required', 'string', 'min:20', 'max:120'],
        ]);

        $user = $request->user();
        $invoice = Invoice::with(['product:id,product_type,product_group_id,config_options,purchase_requires', 'payments'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $this->checkoutSecurityService->assertInvoicePaymentSessionToken(
            (string) $data['payment_session_token'],
            $invoice,
            (int) $user->id
        );

        $result = $this->paymentService->payByAlipay(
            $invoice,
            $user,
            $this->buildOperationContext($request)
        );

        $payment = Payment::query()
            ->where('payment_no', (string) ($result['payment_no'] ?? ''))
            ->where('invoice_id', $invoice->id)
            ->where('user_id', $user->id)
            ->where('gateway', 'alipay')
            ->first();

        if (! $payment) {
            return $this->error(40400, '支付记录不存在');
        }

        $pollSecurity = $this->checkoutSecurityService->issueInvoicePaymentPollToken($payment, $invoice, (int) $user->id);

        return $this->success(array_merge($result, $pollSecurity), '二维码生成成功');
    }

    public function queryAlipayStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'payment_no' => ['required', 'string', 'max:50'],
            'poll_token' => ['required', 'string', 'min:20', 'max:120'],
        ]);

        $user = $request->user();
        $invoice = Invoice::with([
            'product:id,product_type,product_group_id,config_options,purchase_requires',
            'order.product:id,product_type,product_group_id,config_options,purchase_requires',
            'service',
            'payments',
        ])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $payment = Payment::query()
            ->where('payment_no', (string) $data['payment_no'])
            ->where('invoice_id', $invoice->id)
            ->where('user_id', $user->id)
            ->where('gateway', 'alipay')
            ->first();

        if (! $payment) {
            return $this->error(40400, '未找到支付宝支付记录');
        }

        $this->checkoutSecurityService->assertInvoicePaymentPollToken(
            (string) $data['poll_token'],
            $payment,
            $invoice,
            (int) $user->id
        );

        $result = $this->paymentService->queryAlipayStatus($payment);
        $responseData = $result;

        if (($result['paid'] ?? false) === true) {
            $invoice->refresh()->load(['product:id,product_type,product_group_id,config_options,purchase_requires', 'service', 'payments']);
            $responseData['invoice'] = $this->transformInvoice($invoice, $user);
        }

        return $this->success($responseData);
    }

    private function transformInvoice(Invoice $invoice, ?\App\Models\User $viewer = null): array
    {
        $invoiceDetail = $this->invoiceService->adminDetail((int) $invoice->id);
        $payableAmount = (string) ($invoiceDetail['payable_amount'] ?? number_format($this->resolveInvoicePayableAmount($invoice), 2, '.', ''));
        $paymentSecurity = $this->checkoutSecurityService->issueInvoicePaymentSession($invoice, (int) $invoice->user_id);
        $canCancel = in_array((int) $invoice->status, [InvoiceStatus::UNPAID, InvoiceStatus::OVERDUE], true);
        $canPay = $canCancel;

        $payMethods = [['key' => 'balance', 'name' => '余额支付']];
        if ((float) $payableAmount <= 0) {
            $payMethods = [['key' => 'free', 'name' => '确认支付']];
        } elseif ($this->alipayService->isEnabled()) {
            $alipayName = Setting::getValue('payment', 'alipay_name') ?: '支付宝支付';
            $payMethods[] = ['key' => 'alipay', 'name' => $alipayName];
        }

        $configPricingSnapshot = is_array($invoice->config_pricing_snapshot) && $invoice->config_pricing_snapshot !== []
            ? $invoice->config_pricing_snapshot
            : [];

        return [
            ...$invoiceDetail,
            'service_id' => (int) ($invoiceDetail['service']['id'] ?? $invoice->service_id ?? 0),
            'pay_methods' => $payMethods,
            'can_cancel' => (bool) $canCancel,
            'payable_amount' => $payableAmount,
            'product' => array_merge(is_array($invoiceDetail['product'] ?? null) ? $invoiceDetail['product'] : [], [
                'config_options' => (array) ($invoice->product?->config_options ?? $invoice->order?->product?->config_options ?? []),
            ]),
            'config_snapshot' => (array) ($invoice->config_snapshot ?? []),
            'config_pricing_snapshot' => $configPricingSnapshot,
            'coupon' => (int) ($invoice->coupon_id ?? 0) > 0 ? [
                'id' => (int) $invoice->coupon_id,
                'name' => (string) ($invoice->coupon_snapshot['name'] ?? ''),
                'description' => (string) ($invoice->coupon_snapshot['description'] ?? ''),
                'discount_label' => (string) ($invoice->coupon_snapshot['discount_label'] ?? ''),
                'discount_amount' => number_format((float) ($invoice->discount ?? 0), 2, '.', ''),
            ] : null,
            'payment_security' => [
                'can_pay' => (bool) $canPay,
                'session_token' => (string) ($paymentSecurity['session_token'] ?? ''),
                'expires_at' => $paymentSecurity['expires_at'] ?? null,
            ],
        ];
    }

    private function resolveInvoicePayableAmount(Invoice $invoice): float
    {
        return round(max((float) ($invoice->amount ?? 0) - (float) ($invoice->paid_amount ?? 0), 0), 2);
    }

    private function buildClientListItem(Invoice $invoice): array
    {
        $detail = $this->invoiceService->adminListItem($invoice);

        return [
            'id' => (int) $detail['id'],
            'invoice_no' => (string) $detail['invoice_no'],
            'type' => (string) $detail['type'],
            'type_label' => (string) $detail['type_label'],
            'product_spec_display' => (string) ($detail['product_spec_display'] ?? ''),
            'product_display_name' => (string) ($detail['product_display_name'] ?? ''),
            'combined_display_name' => (string) ($detail['combined_display_name'] ?? ''),
            'product' => $detail['product'],
            'amount' => (string) $detail['amount'],
            'discount' => (string) $detail['discount'],
            'paid_amount' => (string) $detail['paid_amount'],
            'payable_amount' => (string) $detail['payable_amount'],
            'status' => (int) $detail['status'],
            'status_label' => (string) $detail['status_label'],
            'scene' => $detail['scene'],
            'summary' => $detail['summary'],
            'payment_summary' => $detail['payment_summary'],
            'due_date' => $detail['due_date'],
            'created_at' => $detail['created_at'],
            'paid_at' => $detail['paid_at'],
        ];
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
        ];
    }
}
