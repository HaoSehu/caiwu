<?php

namespace Caiwu\Plugins\Gateways\AliPay\Lib;

use App\Constants\PaymentGatewayCode;
use App\Exceptions\BusinessException;
use App\Services\Integrations\Payments\Concerns\BuildsGatewayHttpClient;
use App\Services\Integrations\Payments\Concerns\WrapsPemKeys;
use App\Services\System\GatewayLogService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class AlipayClient
{
    use BuildsGatewayHttpClient, WrapsPemKeys;

    private string $appId;

    private string $privateKey;

    private string $alipayPublicKey;

    private string $gateway;

    private string $signType;

    private string $charset;

    private string $notifyUrl;

    private string $timeout;

    private bool $enabled;

    private bool $sslVerify;

    private string $caBundle;

    /** 最近一次网关同步响应的原始报文（GBK 已转 UTF-8）：RSA2 签名覆盖的是节点原始 JSON 文本 */
    private string $lastRawResponseBody = '';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config = [])
    {
        // 配置唯一真源是插件配置（integration_plugin_configs），这里只做「插件配置为空时用 env 默认值」的兜底
        $this->appId = $this->configString('app_id', config('alipay.app_id', ''));
        $this->privateKey = $this->configString('private_key', config('alipay.private_key', ''));
        $this->alipayPublicKey = $this->configString('alipay_public_key', config('alipay.alipay_public_key', ''));
        $this->gateway = trim((string) config('alipay.gateway', 'https://openapi.alipay.com/gateway.do'));
        $this->notifyUrl = $this->resolveNotifyUrl();
        $this->timeout = config('alipay.timeout', '30m');
        $this->signType = 'RSA2';
        $this->charset = 'utf-8';
        $this->enabled = $this->configBool('alipay_enabled', false);
        $this->sslVerify = $this->configBool('ssl_verify', (bool) config('alipay.ssl_verify', true));
        $this->caBundle = $this->configString('ca_bundle', config('alipay.ca_bundle', ''));
    }

    /**
     * 解析支付宝异步通知回调 URL
     * 必须指向后端 API 地址（支付宝服务器直接 POST 到此地址）
     * 优先使用 ALIPAY_NOTIFY_URL，fallback 到后端 APP_URL，绝不指向前端域名
     */
    private function resolveNotifyUrl(): string
    {
        // 优先使用专门配置的回调 URL
        $notifyUrl = trim((string) config('alipay.notify_url', ''));
        if ($notifyUrl !== '') {
            return $notifyUrl;
        }

        return rtrim((string) config('app.url', ''), '/').'/api/v2/client/payment/alipay/notify';
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->appId !== '' && $this->privateKey !== '';
    }

    public function matchesAppId(?string $appId): bool
    {
        $expected = trim($this->appId);
        $actual = trim((string) $appId);

        // 商户号未配置时一律拒绝：避免公钥误配/换绑窗口内，第三方商户携带自身合法
        // 签名的回调因缺少 app_id 比对而被放行
        if ($expected === '') {
            return false;
        }

        return $actual !== '' && hash_equals($expected, $actual);
    }

    private function configString(string $configKey, mixed $default = ''): string
    {
        $value = $this->config[$configKey] ?? null;

        return trim((string) (($value !== null && $value !== '') ? $value : $default));
    }

    private function configBool(string $configKey, bool $default): bool
    {
        $value = $this->config[$configKey] ?? null;
        if ($value !== null && $value !== '') {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return $default;
    }

    /**
     * 预下单（生成二维码链接）
     */
    public function precreate(string $outTradeNo, float $amount, string $subject, ?string $timeoutExpress = null): array
    {
        $bizContent = [
            'out_trade_no' => $outTradeNo,
            'total_amount' => number_format($amount, 2, '.', ''),
            'subject' => $subject,
            'timeout_express' => $timeoutExpress ?: $this->timeout,
        ];

        $notifyUrlMeta = $this->describePrecreateNotifyUrl();
        $params = $this->buildRequestParams('alipay.trade.precreate', $bizContent);

        try {
            $result = $this->request($params, false);
        } catch (BusinessException $exception) {
            app(GatewayLogService::class)->recordFailure(
                gateway: PaymentGatewayCode::ALIPAY,
                action: 'precreate',
                errorMsg: $exception->getMessage(),
                outTradeNo: $outTradeNo,
                requestData: $bizContent,
                responseData: [],
            );
            throw $exception;
        }

        if (! $notifyUrlMeta['usable']) {
            Log::warning('[支付宝当面付] precreate 未附带异步回调地址，将依赖主动查询', [
                'out_trade_no' => $outTradeNo,
                'notify_url' => $notifyUrlMeta['notify_url'],
                'reason' => $notifyUrlMeta['reason'],
            ]);
        }

        Log::info('[支付宝当面付] precreate 响应', [
            'out_trade_no' => $outTradeNo,
            'response' => $result,
        ]);

        $data = $result['alipay_trade_precreate_response'] ?? [];

        if (($data['code'] ?? '') !== '10000') {
            Log::error('[支付宝当面付] 预下单失败', ['data' => $data]);
            app(GatewayLogService::class)->recordFailure(
                gateway: PaymentGatewayCode::ALIPAY,
                action: 'precreate',
                errorMsg: $data['sub_msg'] ?? $data['msg'] ?? '预下单失败',
                outTradeNo: $outTradeNo,
                requestData: $bizContent,
                responseData: $data,
            );
            throw new BusinessException('支付宝预下单失败，请稍后重试');
        }

        app(GatewayLogService::class)->recordSuccess(
            gateway: PaymentGatewayCode::ALIPAY,
            action: 'precreate',
            outTradeNo: $outTradeNo,
            requestData: $bizContent,
            responseData: $data,
        );

        return [
            'qr_code' => $data['qr_code'] ?? '',
            'out_trade_no' => $data['out_trade_no'] ?? $outTradeNo,
        ];
    }

    /**
     * 主动查询订单状态
     */
    public function query(string $outTradeNo): array
    {
        $bizContent = ['out_trade_no' => $outTradeNo];
        $params = $this->buildRequestParams('alipay.trade.query', $bizContent);
        $result = $this->request($params);

        $data = $result['alipay_trade_query_response'] ?? [];

        // 查询结果直接驱动入账，必须与异步通知同级验签；无签名时继续返回但留痕，
        // 验签失败则抛业务异常，由调用侧按「查询失败」处理而不是把钱记上账。
        $hasSignature = $this->hasResponseSignature($result);
        $verified = false;
        if ($hasSignature && trim((string) $this->alipayPublicKey) !== '') {
            if (! $this->verifySignedPayload($result)) {
                Log::warning('[支付宝主动查询] 查询响应验签失败', [
                    'out_trade_no' => $outTradeNo,
                    'trade_status' => (string) ($data['trade_status'] ?? ''),
                ]);

                throw new BusinessException('支付宝查询响应验签失败');
            }

            $verified = true;
        } elseif ($hasSignature) {
            Log::warning('[支付宝主动查询] 查询响应含签名但未配置公钥，无法验签', [
                'out_trade_no' => $outTradeNo,
            ]);
        } else {
            Log::warning('[支付宝主动查询] 查询响应无签名', [
                'out_trade_no' => $outTradeNo,
                'trade_status' => (string) ($data['trade_status'] ?? ''),
            ]);
        }

        return [
            'trade_status' => $data['trade_status'] ?? '',
            'trade_no' => $data['trade_no'] ?? '',
            'out_trade_no' => $data['out_trade_no'] ?? $outTradeNo,
            'total_amount' => $data['total_amount'] ?? '0.00',
            'raw' => $data,
            'verified' => $verified,
        ];
    }

    /**
     * 验证异步通知签名
     */
    public function verifyNotify(array $params): bool
    {
        $sign = (string) ($params['sign'] ?? '');
        $signType = $params['sign_type'] ?? 'RSA2';

        unset($params['sign'], $params['sign_type']);
        ksort($params);

        // 缺少签名或未配置支付宝公钥时直接判定失败，避免 openssl_verify 抛出 PHP 警告导致 500。
        if ($sign === '' || trim((string) $this->alipayPublicKey) === '') {
            return false;
        }

        $stringToSign = urldecode(http_build_query($params));

        // 公钥可能是裸 base64，也可能是完整 PEM：统一经 pemWrappedPublicKey 规范化，
        // 已带 BEGIN 头的 PEM 原样使用，避免二次包装导致 openssl 解析失败（M-35）。
        $publicKeyResource = openssl_pkey_get_public($this->pemWrappedPublicKey((string) $this->alipayPublicKey));
        if ($publicKeyResource === false) {
            Log::warning('[支付宝回调] 公钥无效，签名验证失败');

            return false;
        }

        $algorithm = $signType === 'RSA2' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;

        return openssl_verify($stringToSign, base64_decode($sign), $publicKeyResource, $algorithm) === 1;
    }

    /**
     * 检查同步响应是否携带签名；支付宝在顶层（sign）或 *-response 节点内（sign）给出。
     *
     * @param  array<string, mixed>  $result
     */
    private function hasResponseSignature(array $result): bool
    {
        return $this->extractResponseSignature($result) !== '';
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function extractResponseSignature(array $result): string
    {
        $sign = trim((string) ($result['sign'] ?? ''));
        if ($sign !== '') {
            return $sign;
        }

        foreach ($result as $key => $value) {
            if (str_ends_with($key, '_response') && is_array($value)) {
                $nodeSign = trim((string) ($value['sign'] ?? ''));
                if ($nodeSign !== '') {
                    return $nodeSign;
                }
            }
        }

        return '';
    }

    /**
     * 校验同步响应签名。
     *
     * 支付宝同步响应的 sign 覆盖的是 *_response 节点的原始 JSON 文本（与异步通知的
     * query-string 排序形态不同），不能用 json_decode 再 json_encode 重建——键序与
     * 转义会变，验签必然失败。因此优先对 request() 留档的响应体做节点文本截取后验签；
     * 节点文本不可用时退回与 verifyNotify 同口径的排序 query-string 验签。
     * 两条路径都要求持有平台公钥，不降低真实性强度。
     *
     * @param  array<string, mixed>  $result
     */
    private function verifySignedPayload(array $result): bool
    {
        $sign = $this->extractResponseSignature($result);
        if ($sign === '' || trim((string) $this->alipayPublicKey) === '') {
            return false;
        }

        $decodedSign = base64_decode($sign, true);
        $publicKeyResource = openssl_pkey_get_public($this->pemWrappedPublicKey($this->alipayPublicKey));
        if ($decodedSign === false || $publicKeyResource === false) {
            return false;
        }

        $nodeKey = '';
        foreach ($result as $key => $value) {
            if (str_ends_with($key, '_response') && is_array($value)) {
                $nodeKey = $key;
                break;
            }
        }

        if ($nodeKey === '' || $this->lastRawResponseBody === '') {
            return false;
        }

        $nodePattern = '/"'.preg_quote($nodeKey, '/').'"\s*:\s*\{/';
        if (preg_match($nodePattern, $this->lastRawResponseBody, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $nodeStart = (int) $matches[0][1] + strlen((string) $matches[0][0]) - 1;
            $nodeJson = $this->extractJsonNode(substr($this->lastRawResponseBody, $nodeStart));

            if ($nodeJson !== null && openssl_verify($nodeJson, $decodedSign, $publicKeyResource, OPENSSL_ALGO_SHA256) === 1) {
                return true;
            }
        }

        // 退回异步通知同口径（排序 query-string）：兼容按 notify 规范签名的网关/联调环境
        $node = $result[$nodeKey];
        if (is_array($node)) {
            unset($node['sign'], $node['sign_type']);
            ksort($node);
            $stringToSign = urldecode(http_build_query($node));

            foreach ([OPENSSL_ALGO_SHA256, OPENSSL_ALGO_SHA1] as $algorithm) {
                if (openssl_verify($stringToSign, $decodedSign, $publicKeyResource, $algorithm) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 从「{」起始位置截取一个完整 JSON 对象文本，正确处理字符串内的花括号与转义。
     */
    private function extractJsonNode(string $json): ?string
    {
        $depth = 0;
        $inString = false;
        $length = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];

            if ($inString) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($json, 0, $i + 1);
                }
            }
        }

        return null;
    }

    /**
     * 发送请求到支付宝网关，自动处理 GBK→UTF-8 转码
     *
     * @param  bool  $retryOnFailure  是否对连接失败自动重试 1 次；precreate 等写请求传 false，
     *                                连接异常时无法确认网关是否已受理，重试有重复提交风险
     */
    private function request(array $params, bool $retryOnFailure = true): array
    {
        // charset 必须放在 URL 查询字符串中，否则支付宝按 GBK 解码 POST body 导致中文签名不一致
        $url = $this->gateway.'?charset='.$this->charset;

        try {
            $response = $this->buildHttpClient($retryOnFailure)->post($url, $params);
        } catch (ConnectionException $exception) {
            Log::error('[支付宝当面付] 网关请求失败', [
                'gateway' => $this->gateway,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw new BusinessException('支付网关暂时不可用，请稍后重试', 42200, 422);
        } catch (RequestException $exception) {
            // 4xx/5xx 不能逃逸成 500：按网关响应异常转业务错误，响应体只留状态码与摘要
            Log::error('[支付宝当面付] 网关响应异常', [
                'gateway' => $this->gateway,
                'http_status' => $exception->response->status(),
                'body' => mb_substr($exception->response->body(), 0, 300),
            ]);

            throw new BusinessException('网关响应异常，请稍后重试', 42200, 422);
        }

        // 验签对象是 UTF-8 转码后的 *_response 节点文本：支付宝签名针对 UTF-8 内容，
        // 官方 SDK 同样是先 iconv 再截取节点，必须在转码后、json_decode 前留档
        $this->lastRawResponseBody = '';
        $body = $response->body();

        // 支付宝网关始终返回 GBK 编码，需转为 UTF-8 才能 json_decode
        if (! json_decode($body)) {
            $body = mb_convert_encoding($body, 'UTF-8', 'GBK');
        }

        $result = json_decode($body, true);

        if (! is_array($result)) {
            Log::error('[支付宝当面付] 响应解析失败', [
                'http_status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return [];
        }

        $this->lastRawResponseBody = $body;

        return $result;
    }

    /**
     * 支付宝出站支持 ssl_verify 开关与 CA 证书包配置，
     * 覆盖共享客户端构建的默认「恒校验」行为。
     */
    protected function httpClientVerifyOption(): bool|string
    {
        return $this->resolveGatewaySslVerifyOption($this->sslVerify, $this->caBundle);
    }

    /**
     * 构建请求公共参数
     */
    private function buildRequestParams(string $method, array $bizContent): array
    {
        $params = [
            'app_id' => $this->appId,
            'method' => $method,
            'format' => 'JSON',
            'charset' => $this->charset,
            'sign_type' => $this->signType,
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE),
        ];

        if ($method === 'alipay.trade.precreate') {
            $notifyUrl = $this->resolvePrecreateNotifyUrl();
            if ($notifyUrl !== null) {
                $params['notify_url'] = $notifyUrl;
            }
        }

        $params['sign'] = $this->generateSign($params);

        return $params;
    }

    private function describePrecreateNotifyUrl(): array
    {
        $notifyUrl = trim($this->notifyUrl);

        if ($notifyUrl === '') {
            return [
                'usable' => false,
                'notify_url' => '',
                'reason' => 'empty',
            ];
        }

        $scheme = strtolower((string) parse_url($notifyUrl, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return [
                'usable' => false,
                'notify_url' => $notifyUrl,
                'reason' => 'unsupported_scheme',
            ];
        }

        $host = strtolower((string) parse_url($notifyUrl, PHP_URL_HOST));
        if ($host === '') {
            return [
                'usable' => false,
                'notify_url' => $notifyUrl,
                'reason' => 'invalid_host',
            ];
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return [
                'usable' => false,
                'notify_url' => $notifyUrl,
                'reason' => 'loopback_host',
            ];
        }

        if (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return [
                'usable' => false,
                'notify_url' => $notifyUrl,
                'reason' => 'private_or_reserved_ip',
            ];
        }

        return [
            'usable' => true,
            'notify_url' => $notifyUrl,
            'reason' => 'ok',
        ];
    }

    private function resolvePrecreateNotifyUrl(): ?string
    {
        $notifyUrlMeta = $this->describePrecreateNotifyUrl();

        return $notifyUrlMeta['usable'] ? $notifyUrlMeta['notify_url'] : null;
    }

    /**
     * RSA2 签名
     */
    private function generateSign(array $params): string
    {
        ksort($params);
        $stringToSign = urldecode(http_build_query($params));

        // 私钥可能是完整 PEM 或裸 base64：完整 PEM 原样使用，裸 base64 按首字符
        // 自动检测 PKCS8 / PKCS1 后包装，避免对 PEM 二次包装导致 openssl 解析失败
        $key = trim($this->privateKey);
        if (str_contains($key, '-----BEGIN')) {
            $pemKey = $key;
        } else {
            $pemKey = str_starts_with($key, 'MIIEv')
                ? $this->wrapPemKey($key, 'PRIVATE KEY')
                : $this->wrapPemKey($key, 'RSA PRIVATE KEY');
        }

        $algorithm = $this->signType === 'RSA2' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;

        openssl_sign($stringToSign, $signature, $pemKey, $algorithm);

        return base64_encode($signature);
    }
}
