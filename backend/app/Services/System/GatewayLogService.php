<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\GatewayLog;
use App\Support\SensitiveDataSanitizer;

class GatewayLogService
{
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
    ): GatewayLog {
        return GatewayLog::query()->create([
            'gateway' => $gateway,
            'action' => $action,
            'out_trade_no' => $outTradeNo,
            'trade_no' => $tradeNo,
            'invoice_id' => $invoiceId,
            'request_data' => SensitiveDataSanitizer::sanitize($requestData),
            'response_data' => SensitiveDataSanitizer::sanitize($responseData),
            'result_status' => $resultStatus,
            'error_msg' => $errorMsg,
            'ip_address' => $ipAddress,
        ]);
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
        );
    }
}
