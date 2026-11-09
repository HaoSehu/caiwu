<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\GatewayLog;
use App\Models\Payment;
use App\Services\Integrations\Plugins\PaymentGatewayBindingResolver;
use App\Support\AuditParamRedactor;
use App\Support\GatewayDetailFile;
use App\Support\PayloadLimiter;
use App\Support\SchemaMetadataCache;

class GatewayLogService
{
    /** 网关明细体积上限：先截叶子字符串，整体仍超限时降级为摘要，防止大报文撑爆 gateway_logs */
    private const GATEWAY_DETAIL_MAX_BYTES = 65536;

    private const GATEWAY_DETAIL_PREVIEW_BYTES = 4096;

    public function __construct(
        private readonly PaymentGatewayBindingResolver $paymentGatewayBindingResolver,
    ) {}

    public function record(
        string $gateway,
        string $action,
        ?string $outTradeNo = null,
        ?string $tradeNo = null,
        ?int $invoiceId = null,
        array $requestData = [],
        array $responseData = [],
        string $resultStatus = 'unknown',
        ?string $errorMsg = null,
        ?string $ipAddress = null,
        ?string $traceId = null,
    ): GatewayLog {
        $payment = $this->resolvePayment($outTradeNo, $tradeNo, $invoiceId);
        $context = $payment instanceof Payment
            ? $this->paymentGatewayBindingResolver->contextForPayment($payment)
            : $this->paymentGatewayBindingResolver->contextForGateway($gateway);
        $resolvedTraceId = trim((string) ($traceId ?? $payment?->trace_id ?? ''));

        // 先剔除凭据再限量：商户密钥等不允许以明文进入 gateway_logs 与按日明细文件
        $requestDetail = $this->redactGatewayDetail($requestData);
        $responseDetail = $this->redactGatewayDetail($responseData);
        // 统一限量治理（叶子截断+整体摘要），文件与库内降级共用同一份结果，避免热路径重复计算
        $limitedRequest = $this->limitGatewayDetail($requestDetail);
        $limitedResponse = $this->limitGatewayDetail($responseDetail);

        $payload = [
            'gateway' => $gateway,
            'action' => $action,
            'out_trade_no' => $outTradeNo,
            'trade_no' => $tradeNo,
            'invoice_id' => $invoiceId,
            'request_data' => $limitedRequest,
            'response_data' => $limitedResponse,
            'result_status' => $resultStatus,
            'error_msg' => $errorMsg,
            'ip_address' => $ipAddress,
        ];

        // 明细优先写按日文件，库行只留 locator（detail_key）；文件不可用时降级为库内截断摘要。
        if (SchemaMetadataCache::hasColumn('gateway_logs', 'detail_key')) {
            $locator = GatewayDetailFile::write($limitedRequest, $limitedResponse, $gateway);
            if ($locator !== null) {
                $payload['detail_key'] = $locator;
                $payload['request_data'] = null;
                $payload['response_data'] = null;
            }
        }

        if (SchemaMetadataCache::hasColumn('gateway_logs', 'plugin_id')) {
            $payload['plugin_id'] = $context['plugin_id'];
        }

        if (SchemaMetadataCache::hasColumn('gateway_logs', 'gateway_key')) {
            $payload['gateway_key'] = $context['gateway_key'];
        }

        // P-10：除 plugin_id 外单独留 plugin_key 快照，便于按插件聚合网关日志；
        // gateway 仍是插件上报原文，不做归一化。
        if (SchemaMetadataCache::hasColumn('gateway_logs', 'plugin_key')) {
            $pluginKey = trim((string) ($payment->plugin_key ?? ''));
            if ($pluginKey === '') {
                $snapshot = $this->paymentGatewayBindingResolver->pluginSnapshotForGateway($gateway);
                $pluginKey = trim((string) ($snapshot['plugin_key'] ?? ''));
            }

            $payload['plugin_key'] = $pluginKey !== '' ? $pluginKey : null;
        }

        if (SchemaMetadataCache::hasColumn('gateway_logs', 'trace_id')) {
            $payload['trace_id'] = $resolvedTraceId !== '' ? $resolvedTraceId : null;
        }

        return GatewayLog::query()->create($payload);
    }

    /**
     * 压住网关明细行均：先截超长叶子字符串；整体编码仍超限时降级为
     * 「截断标记 + 原始字节 + 预览」，保留可定位性而不保留全量报文。
     *
     * @return array<string, mixed>
     */
    private function limitGatewayDetail(mixed $detail): array
    {
        $data = is_array($detail) ? $detail : [];

        return PayloadLimiter::limit(
            $data,
            PayloadLimiter::DEFAULT_LEAF_MAX_BYTES,
            self::GATEWAY_DETAIL_MAX_BYTES,
            self::GATEWAY_DETAIL_PREVIEW_BYTES,
        );
    }

    /**
     * 网关明细落库/落文件前的凭据剔除。
     *
     * 敏感键的判定完全交给 AuditParamRedactor（与审计日志共用同一份名单与后缀规则：
     * key/secret/token/password/private_key 族）；命中时输出保留前后各 4 字符的掩码，
     * 而非整值占位，便于值班核对是哪一份凭据。字符串叶子覆盖「响应体原样塞进数组」
     * 的形态，按 query-string / JSON 键值对掩码 sign/key 的取值。
     *
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private function redactGatewayDetail(array $detail): array
    {
        /** @var array<string, mixed> $masked */
        $masked = $this->maskAgainstRedacted($detail, AuditParamRedactor::redact($detail));

        return $masked;
    }

    /**
     * 以脱敏器的结果为敏感性判据，再把命中值替换成保留前后各 4 字符的掩码。
     */
    private function maskAgainstRedacted(mixed $original, mixed $redacted): mixed
    {
        if (is_array($redacted)) {
            $originals = is_array($original) ? $original : [];
            $result = [];
            foreach ($redacted as $key => $value) {
                $result[$key] = $this->maskAgainstRedacted($originals[$key] ?? null, $value);
            }

            return $result;
        }

        // 脱敏器只会在敏感键上改写值：与原文不同即命中，用部分掩码替代整值占位
        if ($redacted !== $original && is_scalar($original) && (string) $original !== '') {
            return $this->maskSecretValue((string) $original);
        }

        if (is_string($redacted)) {
            return $this->maskCredentialPairs($redacted);
        }

        return $redacted;
    }

    private function maskSecretValue(string $value): string
    {
        if (strlen($value) <= 8) {
            return '****';
        }

        return substr($value, 0, 4).'****'.substr($value, -4);
    }

    /**
     * 只掩码 query-string / JSON 形态的 sign、key 取值；保留前后各 4 字符以便核对
     * 是哪一份凭据。按「下划线边界/引号边界」匹配，避免误伤 monkey/hotkey 这类业务词。
     */
    private function maskCredentialPairs(string $value): string
    {
        return (string) preg_replace_callback(
            '/(^|[?&"\'])(sign|key)(["\']?\s*[=:]\s*["\']?)([^&"\'\s]+)/i',
            static function (array $matches): string {
                $secret = $matches[4];
                $keep = strlen($secret) > 8 ? 4 : 0;

                return $matches[1].$matches[2].$matches[3].($keep > 0
                    ? substr($secret, 0, $keep).'****'.substr($secret, -$keep)
                    : '****');
            },
            $value,
        );
    }

    public function recordSuccess(
        string $gateway,
        string $action,
        ?string $outTradeNo = null,
        ?string $tradeNo = null,
        ?int $invoiceId = null,
        array $requestData = [],
        array $responseData = [],
        ?string $ipAddress = null,
        ?string $traceId = null,
    ): GatewayLog {
        return $this->record(
            gateway: $gateway,
            action: $action,
            outTradeNo: $outTradeNo,
            tradeNo: $tradeNo,
            invoiceId: $invoiceId,
            requestData: $requestData,
            responseData: $responseData,
            resultStatus: 'success',
            ipAddress: $ipAddress,
            traceId: $traceId,
        );
    }

    public function recordFailure(
        string $gateway,
        string $action,
        string $errorMsg,
        ?string $outTradeNo = null,
        ?int $invoiceId = null,
        array $requestData = [],
        array $responseData = [],
        ?string $ipAddress = null,
        ?string $traceId = null,
    ): GatewayLog {
        return $this->record(
            gateway: $gateway,
            action: $action,
            outTradeNo: $outTradeNo,
            invoiceId: $invoiceId,
            requestData: $requestData,
            responseData: $responseData,
            resultStatus: 'failed',
            errorMsg: $errorMsg,
            ipAddress: $ipAddress,
            traceId: $traceId,
        );
    }

    private function resolvePayment(?string $outTradeNo, ?string $tradeNo, ?int $invoiceId): ?Payment
    {
        if (! SchemaMetadataCache::hasTable('payments')) {
            return null;
        }

        $resolvedOutTradeNo = trim((string) $outTradeNo);
        $resolvedTradeNo = trim((string) $tradeNo);
        $resolvedInvoiceId = (int) ($invoiceId ?? 0);

        if ($resolvedOutTradeNo === '' && $resolvedTradeNo === '' && $resolvedInvoiceId <= 0) {
            return null;
        }

        return Payment::query()
            ->where(static function ($query) use ($resolvedOutTradeNo, $resolvedTradeNo, $resolvedInvoiceId): void {
                if ($resolvedOutTradeNo !== '') {
                    $query->orWhere('payment_no', $resolvedOutTradeNo);
                }

                if ($resolvedTradeNo !== '') {
                    $query->orWhere('trade_no', $resolvedTradeNo);
                }

                if ($resolvedInvoiceId > 0) {
                    $query->orWhere('invoice_id', $resolvedInvoiceId);
                }
            })
            ->latest('id')
            ->first();
    }
}
