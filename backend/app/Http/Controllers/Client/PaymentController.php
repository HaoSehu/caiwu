<?php

namespace App\Http\Controllers\Client;

use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Payment\IndexRequest;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(IndexRequest $request)
    {
        $thirdPartyGateways = PaymentGatewayCode::thirdPartyGateways();
        $filters = $request->validated();

        $query = Payment::with([
            'invoice:id,invoice_no,status,amount,type',
        ])
            ->where('user_id', $request->user()->id)
            ->whereIn('gateway', $thirdPartyGateways)
            ->orderByDesc('id');

        if (($filters['status'] ?? null) !== null) {
            $query->where('status', (int) $filters['status']);
        }
        if (! empty($filters['gateway'])) {
            $query->where('gateway', $filters['gateway']);
        }
        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword) {
                $builder->where('payment_no', 'like', '%'.$keyword.'%')
                    ->orWhere('trade_no', 'like', '%'.$keyword.'%')
                    ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_no', 'like', '%'.$keyword.'%'));
            });
        }

        $perPage = (int) ($filters['page_size'] ?? 15);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(fn (Payment $payment) => [
            'id' => (int) $payment->id,
            'payment_no' => (string) $payment->payment_no,
            'trade_no' => (string) ($payment->trade_no ?? ''),
            'gateway' => (string) $payment->gateway,
            'gateway_label' => $this->gatewayLabel((string) $payment->gateway),
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

    public function summary(Request $request)
    {
        $userId = (int) $request->user()->id;

        $row = Payment::query()
            ->where('user_id', $userId)
            ->whereIn('gateway', PaymentGatewayCode::thirdPartyGateways())
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending', [PaymentStatus::PENDING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS success', [PaymentStatus::SUCCESS])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS refunded', [PaymentStatus::REFUNDED])
            ->first();

        return $this->success([
            'total' => (int) ($row?->total ?? 0),
            'pending' => (int) ($row?->pending ?? 0),
            'success' => (int) ($row?->success ?? 0),
            'refunded' => (int) ($row?->refunded ?? 0),
        ]);
    }

    public function show(int $id, Request $request)
    {
        $userId = (int) $request->user()->id;

        $payment = Payment::with([
            'invoice:id,invoice_no,status,amount,paid_amount,type,created_at',
        ])
            ->where('user_id', $userId)
            ->whereIn('gateway', PaymentGatewayCode::thirdPartyGateways())
            ->findOrFail($id);

        return $this->success([
            'id' => (int) $payment->id,
            'payment_no' => (string) $payment->payment_no,
            'trade_no' => (string) ($payment->trade_no ?? ''),
            'gateway' => (string) $payment->gateway,
            'gateway_label' => $this->gatewayLabel((string) $payment->gateway),
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
            PaymentGatewayCode::WECHAT => PaymentGatewayCode::label(PaymentGatewayCode::WECHAT),
            default => $gateway,
        };
    }
}
