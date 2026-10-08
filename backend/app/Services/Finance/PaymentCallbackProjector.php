<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\PaymentStatus;
use App\Models\Payment;
use App\Services\Integrations\Plugins\PaymentGatewayBindingResolver;
use App\Support\SchemaMetadataCache;
use App\Support\VersionedJson;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * 支付回调凭证（payment_callbacks）唯一投影写入口。
 * 充值/账单/混合/退款各流的成功回调、主动查询与取消路径都经由本类落审计投影，
 * 保证 callback_type、验签标记与网关流水号口径一致。
 */
class PaymentCallbackProjector
{
    public function __construct(
        private ?PaymentGatewayBindingResolver $paymentGatewayBindingResolver = null,
    ) {}

    /**
     * 按 callback_raw 当前值刷新支付单的 payment / refund 两类投影行。
     */
    public function syncProjection(Payment $payment): void
    {
        if (! SchemaMetadataCache::hasTable('payment_callbacks')) {
            return;
        }

        $callbackRaw = PaymentCallbackRaw::rawDecode($payment, true);
        if ($callbackRaw !== []) {
            $this->recordPaymentCallback(
                $payment,
                'payment',
                $callbackRaw,
                $this->resolveCallbackVerified($payment, $callbackRaw),
                (string) ($callbackRaw['trade_no'] ?? $payment->trade_no ?? ''),
                (string) ($callbackRaw['trace_id'] ?? $payment->trace_id ?? '')
            );

            $refundPayload = is_array($callbackRaw['refund'] ?? null) ? $callbackRaw['refund'] : [];
            if ($refundPayload !== []) {
                $this->recordPaymentCallback(
                    $payment,
                    'refund',
                    $refundPayload,
                    true,
                    (string) ($refundPayload['trade_no'] ?? ''),
                    (string) ($refundPayload['trace_id'] ?? $callbackRaw['trace_id'] ?? $payment->trace_id ?? '')
                );
            }
        }

        // 投影同步为纯写操作，全部调用方均语句调用不消费返回值，不再 fresh 触发额外查询
    }

    public function recordPaymentCallback(
        Payment $payment,
        string $callbackType,
        array $payload,
        bool $isVerified = true,
        ?string $gatewayTradeNo = null,
        ?string $traceId = null,
    ): void {
        if (! SchemaMetadataCache::hasTable('payment_callbacks') || ! $payment->exists) {
            return;
        }

        $now = now();
        $resolvedTraceId = trim((string) ($traceId ?? $payload['trace_id'] ?? $payment->trace_id ?? ''));
        if ($resolvedTraceId !== '') {
            $payload['trace_id'] = $resolvedTraceId;
        }

        $row = [
            'gateway_trade_no' => $this->nullableString($gatewayTradeNo ?? $payload['trade_no'] ?? $payment->trade_no ?? null),
            'payload_json' => $this->encodeJson(VersionedJson::paymentCallback($payload, $callbackType)),
            'is_verified' => $isVerified ? 1 : 0,
            'received_at' => $payload['send_pay_date'] ?? $payload['refunded_at'] ?? $payload['gmt_refund_pay'] ?? $now,
            'updated_at' => $now,
        ];
        $gatewayContext = $this->paymentGatewayBindingResolver()->contextForPayment($payment);

        if (SchemaMetadataCache::hasColumn('payment_callbacks', 'plugin_id')) {
            $row['plugin_id'] = $gatewayContext['plugin_id'];
        }

        if (SchemaMetadataCache::hasColumn('payment_callbacks', 'gateway_key')) {
            $row['gateway_key'] = $gatewayContext['gateway_key'];
        }

        if (SchemaMetadataCache::hasColumn('payment_callbacks', 'trace_id')) {
            $row['trace_id'] = $this->nullableString($resolvedTraceId);
        }

        if (SchemaMetadataCache::hasColumn('payment_callbacks', 'operator')) {
            $row['operator'] = $this->nullableString($payload['operator'] ?? null);
        }

        if (SchemaMetadataCache::hasColumn('payment_callbacks', 'remark')) {
            $row['remark'] = $this->nullableString($payload['remark'] ?? $payload['refund_reason'] ?? null);
        }

        DB::table('payment_callbacks')->updateOrInsert(
            [
                'payment_id' => (int) $payment->id,
                'callback_type' => $callbackType,
            ],
            array_merge($row, [
                'created_at' => $payment->created_at ?? $now,
            ])
        );
    }

    /**
     * 记录被拒绝的回调（验签失败/商户号不匹配/金额不符），is_verified=0 留痕。
     * 已验签的真实回调投影（is_verified=1）不可被拒绝载荷覆盖；预创建/历史拒绝行
     * 允许被最新的拒绝痕迹替换，保证支付单的回调投影始终反映最近一次审计结论。
     */
    public function recordRejectedCallback(Payment $payment, array $payload, string $reason): void
    {
        if (! SchemaMetadataCache::hasTable('payment_callbacks') || ! $payment->exists) {
            return;
        }

        $verifiedExists = DB::table('payment_callbacks')
            ->where('payment_id', (int) $payment->id)
            ->where('callback_type', 'payment')
            ->where('is_verified', 1)
            ->exists();
        if ($verifiedExists) {
            return;
        }

        $now = now();
        $resolvedTraceId = trim((string) ($payload['trace_id'] ?? $payment->trace_id ?? ''));
        $row = [
            'gateway_trade_no' => $this->nullableString($payload['trade_no'] ?? $payment->trade_no ?? null),
            'payload_json' => $this->encodeJson(VersionedJson::paymentCallback(
                array_merge($payload, ['reject_reason' => $reason]),
                'payment'
            )),
            'is_verified' => 0,
            'received_at' => $now,
            'updated_at' => $now,
        ];
        $gatewayContext = $this->paymentGatewayBindingResolver()->contextForPayment($payment);

        if (SchemaMetadataCache::hasColumn('payment_callbacks', 'plugin_id')) {
            $row['plugin_id'] = $gatewayContext['plugin_id'];
        }

        if (SchemaMetadataCache::hasColumn('payment_callbacks', 'gateway_key')) {
            $row['gateway_key'] = $gatewayContext['gateway_key'];
        }

        if (SchemaMetadataCache::hasColumn('payment_callbacks', 'trace_id')) {
            $row['trace_id'] = $this->nullableString($resolvedTraceId);
        }

        if (SchemaMetadataCache::hasColumn('payment_callbacks', 'remark')) {
            $row['remark'] = $reason;
        }

        // 竞态防护：SELECT（verified 检查）与 UPSERT 之间真实回调可能并发落行。
        // update 只命中未验签行（预创建/历史拒绝），永不触碰已验签投影；
        // 未命中时 insert，若间隙里已生成真实回调行则被唯一键拦截后静默放弃。
        $updated = DB::table('payment_callbacks')
            ->where('payment_id', (int) $payment->id)
            ->where('callback_type', 'payment')
            ->where('is_verified', 0)
            ->update($row);

        if ($updated > 0) {
            return;
        }

        try {
            DB::table('payment_callbacks')->insert(array_merge($row, [
                'payment_id' => (int) $payment->id,
                'callback_type' => 'payment',
                'created_at' => $now,
            ]));
        } catch (QueryException $e) {
            // 并发窗口内已出现同键投影行（真实回调或另一拒绝痕迹），保留既有行。
        }
    }

    private function resolveCallbackVerified(Payment $payment, array $callbackRaw): bool
    {
        if (($callbackRaw['code'] ?? null) === '10000') {
            return true;
        }

        if (in_array(trim((string) ($callbackRaw['trade_status'] ?? '')), ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
            return true;
        }

        return (int) $payment->status === PaymentStatus::SUCCESS;
    }

    private function paymentGatewayBindingResolver(): PaymentGatewayBindingResolver
    {
        return $this->paymentGatewayBindingResolver ??= app(PaymentGatewayBindingResolver::class);
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodeJson(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
