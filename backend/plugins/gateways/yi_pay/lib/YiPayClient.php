<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Gateways\YiPay\Lib;

use App\Constants\PaymentGatewayCode;
use App\Exceptions\BusinessException;
use App\Services\Integrations\Payments\Data\PaymentRefundRequest;
use App\Services\System\GatewayLogService;
use App\Support\SensitiveDataSanitizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class YiPayClient
{
    private const API_BASE_URL = 'https://zpayz.cn';

    private string $merchantId;

    private string $merchantKey;

    private string $paymentType;

    private string $channelId;

    private string $apiBaseUrl;

    private string $notifyUrl;

    private string $device;

    private bool $enabled;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config = [])
    {
        $this->merchantId = $this->configString('merchant_id');
        $this->merchantKey = $this->configString('merchant_key');
        $this->paymentType = $this->normalizePaymentType($this->configString('payment_type', 'alipay'));
        $this->channelId = $this->configString('channel_id');
        $this->apiBaseUrl = self::API_BASE_URL;
        $this->notifyUrl = $this->resolveNotifyUrl();
        $this->device = $this->configString('device', 'pc') === 'mobile' ? 'mobile' : 'pc';
        $this->enabled = $this->configBool('enabled', true);
    }

    public function isEnabled(): bool
    {
        return $this->enabled
            && $this->merchantId !== ''
            && $this->merchantKey !== ''
            && $this->notifyUrl !== ''
            && in_array($this->paymentType, ['alipay', 'wxpay'], true);
    }

    public function matchesMerchantId(?string $merchantId): bool
    {
        $actual = trim((string) $merchantId);

        return $this->merchantId === '' || ($actual !== '' && hash_equals($this->merchantId, $actual));
    }

    /**
     * @return array<string, mixed>
     */
    public function precreate(string $outTradeNo, float $amount, string $subject): array
    {
        $payload = [
            'pid' => $this->merchantId,
            'type' => $this->paymentType,
            'out_trade_no' => $outTradeNo,
            'notify_url' => $this->notifyUrl,
            'name' => mb_substr($subject !== '' ? $subject : $outTradeNo, 0, 100),
            'money' => number_format($amount, 2, '.', ''),
            'clientip' => $this->resolveClientIp(),
            'device' => $this->device,
            'param' => $outTradeNo,
            'sign_type' => 'MD5',
        ];

        if ($this->channelId !== '') {
            $payload['cid'] = $this->channelId;
        }

        $payload['sign'] = $this->sign($payload);
        $result = $this->request('post', $this->endpoint('mapi.php'), $payload);

        if ((string) ($result['code'] ?? '') !== '1') {
            $this->recordFailure('precreate', (string) ($result['msg'] ?? '预下单失败'), $outTradeNo, $payload, $result);
            throw new BusinessException('易支付预下单失败，请稍后重试');
        }

        $qrCode = $this->firstString($result, ['qrcode', 'img', 'payurl']);
        if ($qrCode === '') {
            $this->recordFailure('precreate', '预下单响应缺少支付链接', $outTradeNo, $payload, $result);
            throw new BusinessException('易支付预下单响应异常，请联系管理员处理');
        }

        $this->recordSuccess('precreate', $outTradeNo, $payload, $result);

        return [
            'qr_code' => $qrCode,
            'out_trade_no' => $outTradeNo,
            'raw' => $result,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function query(string $outTradeNo): array
    {
        $payload = [
            'act' => 'order',
            'pid' => $this->merchantId,
            'key' => $this->merchantKey,
            'out_trade_no' => $outTradeNo,
        ];

        $result = $this->request('get', $this->endpoint('api.php'), $payload);

        if ((string) ($result['code'] ?? '') !== '1') {
            $this->recordFailure('query', (string) ($result['msg'] ?? '订单查询失败'), $outTradeNo, $payload, $result);
            throw new BusinessException('易支付订单查询失败，请稍后重试');
        }

        $tradeStatus = (int) ($result['status'] ?? 0) === 1 ? 'TRADE_SUCCESS' : 'WAIT_BUYER_PAY';

        return [
            'trade_status' => $tradeStatus,
            'trade_no' => (string) ($result['trade_no'] ?? ''),
            'out_trade_no' => (string) ($result['out_trade_no'] ?? $outTradeNo),
            'total_amount' => number_format((float) ($result['money'] ?? 0), 2, '.', ''),
            'raw' => $result,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function refund(PaymentRefundRequest $request): array
    {
        $payload = [
            'act' => 'refund',
            'pid' => $this->merchantId,
            'key' => $this->merchantKey,
            'out_trade_no' => $request->outTradeNo,
            'money' => number_format($request->refundAmount, 2, '.', ''),
        ];

        if ($request->tradeNo !== null && trim($request->tradeNo) !== '') {
            $payload['trade_no'] = trim($request->tradeNo);
        }

        $result = $this->request('post', $this->endpoint('api.php'), $payload);

        if ((string) ($result['code'] ?? '') !== '1') {
            $this->recordFailure('refund', (string) ($result['msg'] ?? '退款失败'), $request->outTradeNo, $payload, $result);
            throw new BusinessException('易支付退款失败，请稍后重试');
        }

        $this->recordSuccess('refund', $request->outTradeNo, $payload, $result);

        return [
            'trade_no' => (string) ($result['trade_no'] ?? $request->tradeNo ?? ''),
            'out_trade_no' => $request->outTradeNo,
            'refund_fee' => number_format($request->refundAmount, 2, '.', ''),
            'fund_change' => '',
            'gmt_refund_pay' => '',
            'raw' => $result,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyNotify(array $payload): bool
    {
        $sign = strtolower(trim((string) ($payload['sign'] ?? '')));
        $signType = strtoupper(trim((string) ($payload['sign_type'] ?? 'MD5')));

        if ($sign === '' || $this->merchantKey === '' || ($signType !== '' && $signType !== 'MD5')) {
            return false;
        }

        return hash_equals($sign, $this->sign($payload));
    }

    private function configString(string $key, string $default = ''): string
    {
        $value = $this->config[$key] ?? null;

        return trim((string) (($value !== null && $value !== '') ? $value : $default));
    }

    private function configBool(string $key, bool $default): bool
    {
        $value = $this->config[$key] ?? null;
        if ($value !== null && $value !== '') {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return $default;
    }

    private function normalizePaymentType(string $paymentType): string
    {
        $normalized = strtolower(trim($paymentType));

        return in_array($normalized, ['alipay', 'wxpay'], true) ? $normalized : 'alipay';
    }

    private function resolveNotifyUrl(): string
    {
        $baseUrl = trim((string) config('app.frontend_url', ''));
        if ($baseUrl === '') {
            $baseUrl = trim((string) config('app.url', ''));
        }

        return $baseUrl !== '' ? rtrim($baseUrl, '/').'/api/client/payment/notify/yipay' : '';
    }

    private function resolveClientIp(): string
    {
        try {
            $ip = request()?->ip();
        } catch (\Throwable) {
            $ip = null;
        }

        return trim((string) $ip) !== '' ? trim((string) $ip) : '127.0.0.1';
    }

    private function endpoint(string $path): string
    {
        return $this->apiBaseUrl.'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $payload): array
    {
        try {
            $response = $method === 'get'
                ? $this->buildHttpClient()->get($url, $payload)
                : $this->buildHttpClient()->post($url, $payload);
        } catch (ConnectionException $exception) {
            Log::error('[易支付] 网关请求失败', [
                'url' => $url,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw new BusinessException('易支付网关暂时不可用，请稍后重试', 42200, 422);
        }

        $result = $response->json();
        if (! is_array($result)) {
            $result = json_decode($response->body(), true);
        }

        if (! is_array($result)) {
            Log::error('[易支付] 响应解析失败', [
                'http_status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new BusinessException('易支付响应异常，请稍后重试', 42200, 422);
        }

        Log::info('[易支付] 网关响应', SensitiveDataSanitizer::sanitize([
            'url' => $url,
            'method' => $method,
            'out_trade_no' => (string) ($payload['out_trade_no'] ?? ''),
            'response' => $result,
        ]));

        return $result;
    }

    private function buildHttpClient(): PendingRequest
    {
        return Http::asForm()
            ->timeout(15)
            ->retry(1, 200);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sign(array $payload): string
    {
        return md5($this->canonicalString($payload).$this->merchantKey);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function canonicalString(array $payload): string
    {
        unset($payload['sign'], $payload['sign_type']);
        ksort($payload, SORT_STRING);

        $pairs = [];
        foreach ($payload as $key => $value) {
            if ($value === null || $value === '' || is_array($value) || is_object($value)) {
                continue;
            }

            $pairs[] = $key.'='.(string) $value;
        }

        return implode('&', $pairs);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     */
    private function firstString(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $requestData
     * @param  array<string, mixed>  $responseData
     */
    private function recordSuccess(string $action, string $outTradeNo, array $requestData, array $responseData): void
    {
        if (! Schema::hasTable('gateway_logs')) {
            return;
        }

        app(GatewayLogService::class)->recordSuccess(
            gateway: PaymentGatewayCode::YIPAY,
            action: $action,
            outTradeNo: $outTradeNo,
            tradeNo: (string) ($responseData['trade_no'] ?? ''),
            requestData: $this->sanitizeGatewayRequestData($requestData),
            responseData: $responseData,
        );
    }

    /**
     * @param  array<string, mixed>  $requestData
     * @param  array<string, mixed>  $responseData
     */
    private function recordFailure(string $action, string $message, string $outTradeNo, array $requestData, array $responseData): void
    {
        if (! Schema::hasTable('gateway_logs')) {
            return;
        }

        app(GatewayLogService::class)->recordFailure(
            gateway: PaymentGatewayCode::YIPAY,
            action: $action,
            errorMsg: $message,
            outTradeNo: $outTradeNo,
            requestData: $this->sanitizeGatewayRequestData($requestData),
            responseData: $responseData,
        );
    }

    /**
     * 易支付查询/退款 API 的商户密钥字段名就是 key，通用脱敏器不会把普通 key
     * 全局视为敏感字段，这里在插件边界显式遮蔽。
     *
     * @param  array<string, mixed>  $requestData
     * @return array<string, mixed>
     */
    private function sanitizeGatewayRequestData(array $requestData): array
    {
        $sanitized = SensitiveDataSanitizer::sanitize($requestData);
        if (! is_array($sanitized)) {
            return [];
        }

        if (array_key_exists('key', $sanitized) && trim((string) $sanitized['key']) !== '') {
            $sanitized['key'] = '[REDACTED]';
        }

        return $sanitized;
    }
}
