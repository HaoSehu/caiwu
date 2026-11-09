<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Gateways\YiPay\Lib;

use App\Constants\PaymentGatewayCode;
use App\Exceptions\BusinessException;
use App\Services\Integrations\Payments\Concerns\BuildsGatewayHttpClient;
use App\Services\Integrations\Payments\Concerns\WrapsPemKeys;
use App\Services\System\GatewayLogService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class YiPayClient
{
    use BuildsGatewayHttpClient, WrapsPemKeys;

    private const PAYMENT_TYPE_LABELS = [
        'alipay' => '支付宝',
        'wxpay' => '微信支付',
    ];

    private const SIGN_TYPE_MD5 = 'MD5';

    private const SIGN_TYPE_RSA = 'RSA';

    private const SUPPORTED_SIGN_TYPES = [
        self::SIGN_TYPE_MD5,
        self::SIGN_TYPE_RSA,
    ];

    private string $merchantId;

    private string $merchantKey;

    private string $merchantPrivateKey;

    private string $platformPublicKey;

    private string $signType;

    /**
     * @var array<int, string>
     */
    private array $paymentTypes;

    private string $apiBaseUrl;

    private string $notifyUrl;

    private string $returnUrl;

    private string $siteName;

    private bool $enabled;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config = [])
    {
        $this->merchantId = $this->configString('merchant_id');
        $this->merchantKey = $this->configString('merchant_key');
        $this->merchantPrivateKey = $this->configString('merchant_private_key');
        $this->platformPublicKey = $this->configString('platform_public_key');
        $this->signType = $this->normalizeSignType($this->configString('sign_type', self::SIGN_TYPE_MD5));
        $this->paymentTypes = $this->resolvePaymentTypes();
        $this->apiBaseUrl = $this->resolveApiBaseUrl();
        $this->notifyUrl = $this->resolveNotifyUrl();
        $this->returnUrl = $this->resolveReturnUrl();
        $this->siteName = $this->resolveSiteName();
        $this->enabled = $this->configBool('enabled', true);
    }

    public function isEnabled(): bool
    {
        return $this->enabled
            && $this->merchantId !== ''
            && $this->hasSigningCredentials()
            && $this->apiBaseUrl !== ''
            && $this->notifyUrl !== ''
            && $this->returnUrl !== ''
            && $this->paymentTypes !== [];
    }

    public function matchesMerchantId(?string $merchantId): bool
    {
        $actual = trim((string) $merchantId);

        // 商户号未配置时一律拒绝，与 ali_pay 保持同口径，避免空配置下任意来源回调被放行
        if ($this->merchantId === '') {
            return false;
        }

        return $actual !== '' && hash_equals($this->merchantId, $actual);
    }

    /**
     * @return array<string, mixed>
     */
    public function precreate(string $outTradeNo, float $amount, string $subject, ?string $paymentType = null): array
    {
        $selectedPaymentType = $this->selectPaymentType($paymentType);
        $payload = [
            'pid' => $this->merchantId,
            'type' => $selectedPaymentType,
            'out_trade_no' => $outTradeNo,
            'notify_url' => $this->notifyUrl,
            'return_url' => $this->returnUrl,
            'name' => mb_substr($subject !== '' ? $subject : $outTradeNo, 0, 100),
            'money' => number_format($amount, 2, '.', ''),
            'clientip' => $this->resolveClientIp(),
            'device' => $this->resolveDevice($selectedPaymentType),
            'param' => $outTradeNo,
            'sign_type' => $this->signType,
        ];

        if ($this->siteName !== '') {
            $payload['sitename'] = $this->siteName;
        }

        $payload['sign'] = $this->sign($payload);
        $result = $this->request('post', $this->endpoint('mapi.php'), $payload);

        if (! $this->isSuccessCode($result['code'] ?? null)) {
            $this->recordFailure('precreate', (string) ($result['msg'] ?? '预下单失败'), $outTradeNo, $this->maskedForLog($payload), $this->maskedForLog($result));
            throw new BusinessException('易支付预下单失败，请稍后重试');
        }

        $qrCode = $this->firstString($result, ['qrcode', 'img', 'payurl', 'urlscheme']);
        if ($qrCode === '') {
            $this->recordFailure('precreate', '预下单响应缺少支付链接', $outTradeNo, $this->maskedForLog($payload), $this->maskedForLog($result));
            throw new BusinessException('易支付预下单响应异常，请联系管理员处理');
        }

        $this->recordSuccess('precreate', $outTradeNo, $this->maskedForLog($payload), $this->maskedForLog($result));

        return [
            'qr_code' => $qrCode,
            'out_trade_no' => $outTradeNo,
            'raw' => $result,
        ];
    }

    /**
     * 只自报「支付渠道」，插件名称由宿主按插件清单补齐（plugin_name / name）：
     * 两者是独立维度，禁止在此拼成「易支付 - 支付宝」这类单串。
     *
     * @return array<int, array<string, string>>
     */
    public function paymentOptions(): array
    {
        return collect($this->paymentTypes)
            ->map(fn (string $paymentType): array => [
                'key' => PaymentGatewayCode::YIPAY,
                'channel_name' => self::PAYMENT_TYPE_LABELS[$paymentType],
                'label' => self::PAYMENT_TYPE_LABELS[$paymentType],
                'option_key' => PaymentGatewayCode::YIPAY.':'.$paymentType,
                'payment_type' => $paymentType,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function query(string $outTradeNo): array
    {
        $payload = [
            'act' => 'order',
            'pid' => $this->merchantId,
            'out_trade_no' => $outTradeNo,
        ];
        $payload = $this->withApiCredentials($payload);

        $result = $this->request('get', $this->endpoint('api.php'), $payload);

        if ((string) ($result['code'] ?? '') !== '1') {
            $this->recordFailure('query', (string) ($result['msg'] ?? '订单查询失败'), $outTradeNo, $this->maskedForLog($payload), $this->maskedForLog($result));
            throw new BusinessException('易支付订单查询失败，请稍后重试');
        }

        $tradeStatus = (int) ($result['status'] ?? 0) === 1 ? 'TRADE_SUCCESS' : 'WAIT_BUYER_PAY';

        return [
            'trade_status' => $tradeStatus,
            'trade_no' => (string) ($result['trade_no'] ?? ''),
            'out_trade_no' => (string) ($result['out_trade_no'] ?? $outTradeNo),
            'total_amount' => number_format((float) ($result['money'] ?? 0), 2, '.', ''),
            'raw' => $result,
            // 查询响应若携带可验证签名（sign/sign_type）则按已验签透传；
            // 易支付查询接口不保证签名，无签名时保持 false，由异步回调完成入账。
            'verified' => $this->verifyNotify($result),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyNotify(array $payload): bool
    {
        $sign = trim((string) ($payload['sign'] ?? ''));
        $signType = $this->normalizeSignType((string) ($payload['sign_type'] ?? $this->signType));

        if ($sign === '') {
            return false;
        }

        if ($signType === self::SIGN_TYPE_MD5) {
            return $this->merchantKey !== '' && hash_equals(strtolower($sign), $this->sign($payload, self::SIGN_TYPE_MD5));
        }

        return $this->platformPublicKey !== '' && $this->verifyRsaSignature($payload, $sign);
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

    private function normalizeSignType(string $signType): string
    {
        $normalized = strtoupper(trim($signType));

        return in_array($normalized, self::SUPPORTED_SIGN_TYPES, true) ? $normalized : self::SIGN_TYPE_MD5;
    }

    private function hasSigningCredentials(): bool
    {
        if ($this->signType === self::SIGN_TYPE_MD5) {
            return $this->merchantKey !== '';
        }

        return $this->merchantPrivateKey !== '' && $this->platformPublicKey !== '';
    }

    /**
     * @return array<int, string>
     */
    private function resolvePaymentTypes(): array
    {
        if (array_key_exists('payment_types', $this->config)) {
            return $this->normalizePaymentTypes($this->config['payment_types']);
        }

        $legacyPaymentType = $this->normalizePaymentType($this->configString('payment_type', 'alipay'));

        return $legacyPaymentType !== null ? [$legacyPaymentType] : ['alipay'];
    }

    /**
     * @return array<int, string>
     */
    private function normalizePaymentTypes(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($item): ?string => $this->normalizePaymentType((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function selectPaymentType(?string $paymentType): string
    {
        $requestedPaymentType = $this->normalizePaymentType((string) $paymentType);
        if ($requestedPaymentType !== null) {
            if (in_array($requestedPaymentType, $this->paymentTypes, true)) {
                return $requestedPaymentType;
            }

            throw new BusinessException('当前易支付未启用该支付方式');
        }

        $selectedPaymentType = $this->paymentTypes[0] ?? null;
        if ($selectedPaymentType !== null) {
            return $selectedPaymentType;
        }

        throw new BusinessException('当前易支付未配置可用支付方式');
    }

    private function normalizePaymentType(string $paymentType): ?string
    {
        $normalized = strtolower(trim($paymentType));

        return array_key_exists($normalized, self::PAYMENT_TYPE_LABELS) ? $normalized : null;
    }

    private function resolveNotifyUrl(): string
    {
        $baseUrl = trim((string) config('app.url', ''));

        return $baseUrl !== '' ? rtrim($baseUrl, '/').'/api/v2/client/payment/notify/yipay' : '';
    }

    private function resolveReturnUrl(): string
    {
        $baseUrl = trim((string) config('app.client_console_url', ''));
        if ($baseUrl === '') {
            $baseUrl = trim((string) config('app.frontend_url', ''));
        }
        if ($baseUrl === '') {
            $baseUrl = trim((string) config('app.url', ''));
        }

        return $baseUrl !== '' ? rtrim($baseUrl, '/').'/client/recharge' : '';
    }

    private function resolveSiteName(): string
    {
        return mb_substr(trim((string) config('app.name', 'Caiwu')), 0, 50);
    }

    private function resolveApiBaseUrl(): string
    {
        $apiEndpoint = $this->configString('api_endpoint', $this->configString('api_base_url'));
        if ($apiEndpoint === '') {
            return '';
        }

        $parts = parse_url($apiEndpoint);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = trim((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return '';
        }

        // 出站地址只允许公网主机：私网/回环地址等于把商户密钥发往内网，属于 SSRF 面
        if ($this->isPrivateOrLoopbackHost($host)) {
            throw new BusinessException('易支付接口地址不合法');
        }

        // https 强制：明文 http 会把签名请求暴露在链路中；仅本地与测试环境放行
        if ($scheme !== 'https' && ! app()->environment(['local', 'testing'])) {
            throw new BusinessException('易支付接口地址不合法');
        }

        return rtrim($apiEndpoint, '/');
    }

    /**
     * IPv6 字面量在 parse_url 结果中带方括号（[::1]），这里统一去括号后判定。
     *
     * 除字面量外还必须解析域名：只比对字面 IP 时，配置一个解析到内网的域名
     * （nip.io、DNS rebinding）即可把商户密钥发进内网，SSRF 闸形同虚设。
     */
    private function isPrivateOrLoopbackHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        if ($host === '' || $host === 'localhost') {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return ! filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        foreach ($this->resolveHostIps($host) as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                continue;
            }

            if (! filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * 解析域名对应的全部 IP（A + AAAA）。解析失败返回空数组：
     * 离线/测试环境不因 DNS 不可用而阻断，但只要能解析出私网地址就必须拒绝。
     *
     * @return array<int, string>
     */
    protected function resolveHostIps(string $host): array
    {
        $ips = @gethostbynamel($host);
        $ips = is_array($ips) ? $ips : [];

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['ipv6'])) {
                        $ips[] = (string) $record['ipv6'];
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($ips)));
    }

    private function resolveClientIp(): string
    {
        try {
            $ip = request()->ip();
        } catch (\Throwable) {
            $ip = null;
        }

        return trim((string) $ip) !== '' ? trim((string) $ip) : '127.0.0.1';
    }

    /**
     * 下游设备口径：网关按 device 决定「扫码」还是「手机跳转」。
     *
     * 微信通道固定上报 pc 时，网关只会下发 pay/jspay 公众号收银台；该页面在
     * 任何浏览器都会 302 到微信 OAuth，微信外打开会被微信拒绝（页面提示
     * invalid_source 一类来源校验失败），手机端因此无法完成支付。
     *
     * 因此微信通道按本次下单请求的 UA 判定：微信内置浏览器上报 wechat
     * （网关转 mobile 并保留 mdevice=wechat，直接给公众号 JSAPI 收银台），
     * 其余移动端上报 mobile，桌面端维持 pc。
     *
     * 支付宝通道维持 pc：其 mapi 在 device=pc 且未开扫码时同样走收银台跳转，
     * 现有线上流程正常，不随本修复扩大改动面。
     */
    private function resolveDevice(string $paymentType): string
    {
        if ($paymentType !== 'wxpay') {
            return 'pc';
        }

        try {
            $userAgent = strtolower((string) (request()->userAgent() ?? ''));
        } catch (\Throwable) {
            $userAgent = '';
        }

        if ($userAgent === '') {
            return 'pc';
        }

        if (str_contains($userAgent, 'micromessenger')) {
            return 'wechat';
        }

        return $this->isMobileUserAgent($userAgent) ? 'mobile' : 'pc';
    }

    private function isMobileUserAgent(string $userAgent): bool
    {
        foreach (['android', 'iphone', 'ipad', 'ipod', 'windows phone', 'harmonyos', 'mobile'] as $keyword) {
            if (str_contains($userAgent, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function endpoint(string $path): string
    {
        if (preg_match('#/[^/]+\.php$#i', $this->apiBaseUrl) === 1) {
            return preg_replace('#/[^/]+$#', '/'.ltrim($path, '/'), $this->apiBaseUrl) ?: $this->apiBaseUrl;
        }

        return $this->apiBaseUrl.'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $payload): array
    {
        try {
            // GET（订单查询）无副作用可安全重试；POST（precreate）连接异常时
            // 无法确认网关是否已受理，重试有重复提交风险，不自动重试。
            $response = $method === 'get'
                ? $this->buildHttpClient()->get($url, $payload)
                : $this->buildHttpClient(false)->post($url, $payload);
        } catch (ConnectionException $exception) {
            Log::error('[易支付] 网关请求失败', [
                'url' => $url,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw new BusinessException('易支付网关暂时不可用，请稍后重试', 42200, 422);
        } catch (RequestException $exception) {
            // 4xx/5xx 同样属于网关侧异常：不向上逃逸成 500，按统一业务错误返回
            Log::error('[易支付] 网关响应异常', [
                'url' => $url,
                'http_status' => $exception->response->status(),
                'body' => mb_substr($this->maskSecretInText($exception->response->body()), 0, 300),
            ]);

            throw new BusinessException('网关响应异常，请稍后重试', 42200, 422);
        }

        $result = $response->json();
        if (! is_array($result)) {
            $result = json_decode($response->body(), true);
        }

        if (! is_array($result)) {
            Log::error('[易支付] 响应解析失败', [
                'http_status' => $response->status(),
                'body' => mb_substr($this->maskSecretInText($response->body()), 0, 500),
            ]);

            throw new BusinessException('易支付响应异常，请稍后重试', 42200, 422);
        }

        Log::info('[易支付] 网关响应', [
            'url' => $url,
            'method' => $method,
            'out_trade_no' => (string) ($payload['out_trade_no'] ?? ''),
            'response' => $this->maskedForLog($result),
        ]);

        return $result;
    }

    private function isSuccessCode(mixed $code): bool
    {
        return in_array((string) $code, ['1', '200'], true);
    }

    /**
     * 交给日志层（Log::info / recordFailure）之前的本地脱敏：商户密钥不允许原样
     * 进入应用日志或 gateway_logs。除键名 key/secret/sign 外，值恰好等于商户密钥
     * 的字段同样掩码，防止换名字段绕过。保留前后各 4 字符便于核对凭据来源。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function maskedForLog(array $payload): array
    {
        $masked = [];
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $masked[$key] = $this->maskedForLog($value);

                continue;
            }

            $normalizedKey = strtolower(trim((string) $key));
            // sign_type 的取值（MD5/RSA）是签名口径而非密文，维持原样；
            // sign 本身是密文，虽无法反推密钥，泄漏签名仍等于泄漏一次可重放凭证。
            $isCredentialKey = in_array($normalizedKey, ['key', 'sign', 'secret', 'merchant_key'], true)
                || str_ends_with($normalizedKey, '_key')
                || str_ends_with($normalizedKey, '_secret');

            if (is_scalar($value) && (string) $value !== ''
                && ($isCredentialKey || ($this->merchantKey !== '' && (string) $value === $this->merchantKey))) {
                $masked[$key] = $this->maskSecret((string) $value);

                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }

    private function maskSecret(string $value): string
    {
        if (strlen($value) <= 8) {
            return '****';
        }

        return substr($value, 0, 4).'****'.substr($value, -4);
    }

    /**
     * 自由文本（如网关错误响应体）中的商户密钥替换为掩码，防止异常日志泄漏明文。
     */
    private function maskSecretInText(string $text): string
    {
        if ($this->merchantKey !== '' && str_contains($text, $this->merchantKey)) {
            return str_replace($this->merchantKey, $this->maskSecret($this->merchantKey), $text);
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withApiCredentials(array $payload): array
    {
        if ($this->merchantKey !== '') {
            $payload['key'] = $this->merchantKey;
        }

        if ($this->signType === self::SIGN_TYPE_RSA) {
            $payload['sign_type'] = $this->signType;
            $payload['sign'] = $this->sign($payload);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sign(array $payload, ?string $signType = null): string
    {
        $selectedSignType = $this->normalizeSignType($signType ?? (string) ($payload['sign_type'] ?? $this->signType));
        $content = $this->canonicalString($payload);

        return $selectedSignType === self::SIGN_TYPE_RSA
            ? $this->signRsa($content)
            : md5($content.$this->merchantKey);
    }

    private function signRsa(string $content): string
    {
        // 私钥支持粘贴裸 base64，出站前统一规范成 PEM
        $privateKey = openssl_pkey_get_private($this->pemWrappedPrivateKey($this->merchantPrivateKey));
        if ($privateKey === false) {
            throw new BusinessException('易支付 RSA 商户私钥无效');
        }

        $signature = '';
        $signed = openssl_sign($content, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $signed) {
            throw new BusinessException('易支付 RSA 签名失败');
        }

        return base64_encode($signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function verifyRsaSignature(array $payload, string $sign): bool
    {
        // 平台公钥同样统一走 PEM 规范化，保证与签名侧使用同一包装规则
        $publicKey = openssl_pkey_get_public($this->pemWrappedPublicKey($this->platformPublicKey));
        if ($publicKey === false) {
            return false;
        }

        $decodedSign = base64_decode($sign, true);
        if ($decodedSign === false) {
            return false;
        }

        return openssl_verify($this->canonicalString($payload), $decodedSign, $publicKey, OPENSSL_ALGO_SHA256) === 1;
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
            requestData: $requestData,
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
            requestData: $requestData,
            responseData: $responseData,
        );
    }
}
