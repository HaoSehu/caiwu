<?php

namespace App\Http\Controllers\Client\V2;

use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\V2\Payment\ListPaymentsRequest;
use App\Http\Requests\Client\V2\Payment\ShowPaymentRequest;
use App\Http\Requests\Client\V2\Payment\SummarizePaymentsRequest;
use App\Models\Payment;
use App\Services\Finance\PaymentService;
use Carbon\CarbonImmutable;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function index(ListPaymentsRequest $request)
    {
        $thirdPartyGateways = PaymentGatewayCode::thirdPartyGateways();
        $filters = $request->validated();
        $userId = (int) $request->user()->id;

        $this->paymentService->cancelExpiredPendingRechargesForUser($userId, $this->paymentWindowExpiredContext());

        $query = Payment::with([
            'invoice:id,invoice_no,status,amount,type',
        ])
            ->where('user_id', $userId)
            ->whereGatewayKeyIn($thirdPartyGateways)
            ->orderByDesc('id');

        if (($filters['status'] ?? null) !== null) {
            $query->where('status', (int) $filters['status']);
        }
        $gateway = trim((string) ($filters['type'] ?? $filters['gateway'] ?? ''));
        if ($gateway !== '') {
            $query->whereGatewayKey($gateway);
        }
        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword) {
                $builder->where('payment_no', 'like', '%'.$keyword.'%')
                    ->orWhere('trade_no', 'like', '%'.$keyword.'%')
                    ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_no', 'like', '%'.$keyword.'%'));
            });
        }
        $this->applyDateFilter($query, $filters);

        $perPage = (int) ($filters['page_size'] ?? 15);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(fn (Payment $payment) => [
            'id' => (int) $payment->id,
            'payment_no' => (string) $payment->payment_no,
            'trade_no' => (string) ($payment->trade_no ?? ''),
            'gateway' => $this->gatewayKey($payment),
            'gateway_key' => $this->gatewayKey($payment),
            'gateway_label' => $this->gatewayLabel($this->gatewayKey($payment)),
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'status' => (int) $payment->status,
            'status_label' => PaymentStatus::$labels[(int) $payment->status] ?? '未知',
            'invoice_id' => (int) ($payment->invoice?->id ?? 0),
            'invoice_no' => (string) ($payment->invoice?->invoice_no ?? ''),
            'invoice_type' => (string) ($payment->invoice?->type ?? ''),
            'invoice_status' => $payment->invoice ? (int) $payment->invoice->status : null,
            'paid_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
            'created_at' => $payment->created_at?->format('Y-m-d H:i:s'),
        ])->values()->all();

        return $this->success([
            'list' => $items,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ]);
    }

    public function summary(SummarizePaymentsRequest $request)
    {
        $userId = (int) $request->user()->id;

        $this->paymentService->cancelExpiredPendingRechargesForUser($userId, $this->paymentWindowExpiredContext());

        $row = Payment::query()
            ->where('user_id', $userId)
            ->whereGatewayKeyIn(PaymentGatewayCode::thirdPartyGateways())
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending', [PaymentStatus::PENDING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS success', [PaymentStatus::SUCCESS])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS refunded', [PaymentStatus::REFUNDED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cancelled', [PaymentStatus::CANCELLED])
            ->first();

        return $this->success([
            'total' => (int) ($row?->total ?? 0),
            'pending' => (int) ($row?->pending ?? 0),
            'success' => (int) ($row?->success ?? 0),
            'refunded' => (int) ($row?->refunded ?? 0),
            'cancelled' => (int) ($row?->cancelled ?? 0),
        ]);
    }

    public function show(int $id, ShowPaymentRequest $request)
    {
        $userId = (int) $request->user()->id;

        $payment = Payment::with([
            'invoice:id,invoice_no,status,amount,paid_amount,type,created_at',
        ])
            ->where('user_id', $userId)
            ->whereGatewayKeyIn(PaymentGatewayCode::thirdPartyGateways())
            ->findOrFail($id);

        $payment = $this->paymentService->cancelExpiredPendingRecharge($payment, $this->paymentWindowExpiredContext())
            ->fresh(['invoice:id,invoice_no,status,amount,paid_amount,type,created_at']) ?? $payment;

        return $this->success([
            'id' => (int) $payment->id,
            'payment_no' => (string) $payment->payment_no,
            'trade_no' => (string) ($payment->trade_no ?? ''),
            'gateway' => $this->gatewayKey($payment),
            'gateway_key' => $this->gatewayKey($payment),
            'gateway_label' => $this->gatewayLabel($this->gatewayKey($payment)),
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'status' => (int) $payment->status,
            'status_label' => PaymentStatus::$labels[(int) $payment->status] ?? '未知',
            'invoice' => $payment->invoice ? [
                'id' => (int) $payment->invoice->id,
                'invoice_no' => (string) $payment->invoice->invoice_no,
                'status' => (int) $payment->invoice->status,
                'amount' => number_format((float) $payment->invoice->amount, 2, '.', ''),
                'paid_amount' => number_format((float) $payment->invoice->paid_amount, 2, '.', ''),
                'type' => (string) ($payment->invoice->type ?? ''),
            ] : null,
            'paid_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
            'created_at' => $payment->created_at?->format('Y-m-d H:i:s'),
        ]);
    }

    private function gatewayLabel(string $gateway): string
    {
        return match ($gateway) {
            PaymentGatewayCode::ALIPAY => '支付宝',
            PaymentGatewayCode::YIPAY => PaymentGatewayCode::label(PaymentGatewayCode::YIPAY),
            PaymentGatewayCode::WECHAT => PaymentGatewayCode::label(PaymentGatewayCode::WECHAT),
            default => $gateway,
        };
    }

    private function gatewayKey(Payment $payment): string
    {
        return $payment->gatewayKey();
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

    private function paymentWindowExpiredContext(): array
    {
        return [
            'actor_type' => 'system',
            'actor_name' => 'payment-window-expired',
            'reason' => 'payment_window_expired',
        ];
    }
}
