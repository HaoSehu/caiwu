<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\PaymentStatus;
use App\Models\Payment;
use App\Support\Concerns\FiltersDateRange;
use App\Support\LikeEscape;
use App\Support\Pagination;
use App\Support\PaymentChannelLabel;
use Illuminate\Database\Eloquent\Builder;

class ClientPaymentQueryService
{
    use FiltersDateRange;

    public function __construct(
        private readonly RechargePaymentService $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $expiredContext
     * @return array<string, mixed>
     */
    public function paginate(int $userId, array $filters, array $expiredContext): array
    {
        $this->payments->cancelExpiredPendingRechargesForUser($userId, $expiredContext);

        // callbacks 预取：渠道还原要读回调证据，不预取会让每行多一次查询（N+1）。
        $query = Payment::with([
            'invoice:id,invoice_no,status,amount,type,due_date,refunded_at',
            'callbacks:payment_id,callback_type,payload_json',
        ])
            ->where('user_id', $userId)
            ->where($this->visiblePaymentScope())
            ->orderByDesc('id');

        if (($filters['status'] ?? null) !== null) {
            $query->where('status', (int) $filters['status']);
        }
        $gateway = trim((string) ($filters['type'] ?? $filters['gateway'] ?? ''));
        if ($gateway !== '') {
            // 只按 gateway_key 列筛会漏掉「渠道写在回调里」的历史支付单：
            // 它们在列表里可见（可见性判据已认回调证据），却筛不出来。
            [$scopeSql, $scopeBindings] = PaymentChannelLabel::channelScopeSql('payments', $gateway);
            $query->whereRaw($scopeSql, $scopeBindings);
        }
        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword): void {
                $builder->where('payment_no', 'like', LikeEscape::contains($keyword))
                    ->orWhere('trade_no', 'like', LikeEscape::contains($keyword))
                    ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_no', 'like', LikeEscape::contains($keyword)));
            });
        }
        $this->applyDateFilter($query, $filters);

        $paginator = $query->paginate(Pagination::clamp((int) ($filters['page_size'] ?? 15)));

        return [
            'list' => collect($paginator->items())
                ->map(fn (Payment $payment): array => $this->listItem($payment))
                ->values()
                ->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    /**
     * @param  array<string, mixed>  $expiredContext
     * @return array<string, mixed>
     */
    public function detail(int $userId, int $paymentId, array $expiredContext): array
    {
        $payment = Payment::with([
            'invoice:id,invoice_no,status,amount,paid_amount,type,created_at',
            'callbacks:payment_id,callback_type,payload_json',
        ])
            ->where('user_id', $userId)
            ->where($this->visiblePaymentScope())
            ->findOrFail($paymentId);

        $payment = $this->payments->cancelExpiredPendingRecharge($payment, $expiredContext)
            ->fresh(['invoice:id,invoice_no,status,amount,paid_amount,type,created_at']) ?? $payment;

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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(Payment $payment): array
    {
        $gateway = PaymentChannelLabel::resolveGatewayKey($payment);

        return [
            'id' => (int) $payment->id,
            'payment_no' => (string) $payment->payment_no,
            'trade_no' => (string) ($payment->trade_no ?? ''),
            'gateway' => $gateway,
            'gateway_key' => $gateway,
            'gateway_label' => PaymentChannelLabel::channelNameFromPayment($payment),
            'plugin_name' => PaymentChannelLabel::pluginNameFromPayment($payment),
            'channel_name' => PaymentChannelLabel::channelNameFromPayment($payment),
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'status' => (int) $payment->status,
            'status_label' => PaymentStatus::$labels[(int) $payment->status] ?? '未知',
            'invoice_id' => (int) ($payment->invoice?->id ?? 0),
            'invoice_no' => (string) ($payment->invoice?->invoice_no ?? ''),
            'invoice_type' => (string) ($payment->invoice?->type ?? ''),
            'invoice_status' => $payment->invoice ? (int) $payment->invoice->status : null,
            'paid_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
            // 结束时间按状态取值：已支付=支付完成时间；已取消=关联账单过期（付款期限）；已退款=账单退款时间。
            // 待支付仍在进行中，不下发（前端显示占位符）。
            'finished_at' => $this->finishedAt($payment),
            'created_at' => $payment->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 「用户端可展示的支付单」范围：渠道能从快照或回调证据还原即可见。
     *
     * 旧库（IDC 版本）的 payments 没有渠道快照列，真实渠道写在 callback_raw 里，
     * 且旧链路只把余额/手工/SQL 侧没有的渠道事实留在回调报文里。此前用
     * whereGatewayKeyIn(thirdPartyGateways()) 过滤，会让这批历史支付单在用户端整条消失。
     *
     * 与 PaymentChannelLabel::hasKnownChannel() 同一判定，SQL 侧写成等价条件，
     * 保证列表、汇总、详情三处的可见范围一致；两处必须同时改。
     *
     * @return \Closure(Builder<Payment>): void
     */
    private function visiblePaymentScope(): \Closure
    {
        [$sql, $bindings] = PaymentChannelLabel::channelScopeSql();

        return static function ($query) use ($sql, $bindings): void {
            $query->whereRaw($sql, $bindings);
        };
    }

    /**
     * 支付单「结束时间」：状态决定取值口径，未结束（待支付）返回 null。
     * 支付单没有取消/过期时间列，已取消取记录最后更新时间，已退款取账单退款时间（缺失时同样回落更新时间）。
     */
    private function finishedAt(Payment $payment): ?string
    {
        $time = match ((int) $payment->status) {
            PaymentStatus::SUCCESS => $payment->paid_at,
            PaymentStatus::CANCELLED => $payment->updated_at,
            PaymentStatus::REFUNDED => $payment->invoice?->getAttribute('refunded_at') ?? $payment->updated_at,
            default => null,
        };

        return $this->formatTime($time);
    }

    /**
     * 时间统一输出（兼容 Carbon / 字符串，空值 null）。
     */
    private function formatTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
