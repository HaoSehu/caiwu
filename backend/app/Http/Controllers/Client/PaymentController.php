<?php

namespace App\Http\Controllers\Client;

use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    private const RECHARGE_GATEWAYS = [PaymentGatewayCode::ALIPAY, PaymentGatewayCode::WECHAT];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'integer'],
            'gateway' => ['nullable', 'string', Rule::in(self::RECHARGE_GATEWAYS)],
            'keyword' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Payment::with([
                'invoice:id,invoice_no,status,amount,type',
            ])
            ->where('user_id', $request->user()->id)
            ->whereIn('gateway', self::RECHARGE_GATEWAYS)
            ->whereNull('order_id')
            ->where(function ($builder) {
                $builder->whereNull('invoice_id')
                    ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('type', 'recharge'));
            })
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
            'gateway' => (string) $payment->gateway,
            'gateway_label' => $this->gatewayLabel((string) $payment->gateway),
            'trade_no' => (string) ($payment->trade_no ?? ''),
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'status' => (int) $payment->status,
            'status_label' => PaymentStatus::$labels[(int) $payment->status] ?? '未知',
            'invoice_id' => (int) ($payment->invoice?->id ?? 0),
            'invoice_no' => (string) ($payment->invoice?->invoice_no ?? ''),
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
            ->whereIn('gateway', self::RECHARGE_GATEWAYS)
            ->whereNull('order_id')
            ->where(function ($builder) {
                $builder->whereNull('invoice_id')
                    ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('type', 'recharge'));
            })
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

    private function gatewayLabel(string $gateway): string
    {
        return match ($gateway) {
            PaymentGatewayCode::ALIPAY => '支付宝',
            PaymentGatewayCode::WECHAT => PaymentGatewayCode::label(PaymentGatewayCode::WECHAT),
            default => $gateway,
        };
    }
}
