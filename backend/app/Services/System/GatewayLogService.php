<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\GatewayLog;
use App\Models\Payment;
use App\Services\Integrations\Plugins\PaymentGatewayBindingResolver;
use App\Support\GatewayDetailFile;
use App\Support\PayloadLimiter;
use App\Support\SchemaMetadataCache;
use App\Support\SensitiveDataSanitizer;

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

        $requestDetail = SensitiveDataSanitizer::sanitize($requestData);
        $responseDetail = SensitiveDataSanitizer::sanitize($responseData);
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
