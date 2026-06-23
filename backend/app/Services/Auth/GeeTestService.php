<?php

namespace App\Services\Auth;

use App\Models\Setting;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeeTestService
{
    private const API_SERVER = 'https://gcaptcha4.geetest.com';

    private const SCRIPT_PROXY_PATH = '/api/client/auth/captcha-script';

    private const SCRIPT_UPSTREAM_URL = 'https://static.geetest.com/v4/gt4.js';

    private const SCRIPT_CACHE_TTL_SECONDS = 43200;

    private ?array $settingCache = null;

    private array $serviceConfig;

    public function __construct()
    {
        $defaultConfig = config('idc.geetest', []);
        $this->serviceConfig = [
            'ssl_verify' => $this->normalizeBoolean($defaultConfig['ssl_verify'] ?? true),
            'ca_bundle' => (string) ($defaultConfig['ca_bundle'] ?? ''),
        ];
    }

    public function isEnabled(): bool
    {
        return $this->config('enabled') && $this->getCaptchaId() !== '' && $this->getCaptchaKey() !== '';
    }

    public function getCaptchaId(): string
    {
        return (string) $this->config('captcha_id', '');
    }

    public function getScriptUrl(): string
    {
        return self::SCRIPT_PROXY_PATH;
    }

    public function getScriptContent(): string
    {
        $cachedScript = Cache::get(CacheKey::geeTestScript());
        if (is_string($cachedScript) && $cachedScript !== '') {
            return $cachedScript;
        }

        $scriptContent = $this->fetchScriptContent();
        Cache::put(
            CacheKey::geeTestScript(),
            $scriptContent,
            now()->addSeconds(self::SCRIPT_CACHE_TTL_SECONDS)
        );

        return $scriptContent;
    }

    public function getFallbackScriptContent(): string
    {
        return <<<'JS'
window.initGeetest4 = window.initGeetest4 || function (options, callback) {
    var instance = {
        appendTo: function () { return instance; },
        onReady: function (fn) { if (typeof fn === 'function') { fn(); } return instance; },
        onSuccess: function () { return instance; },
        onError: function (fn) { if (typeof fn === 'function') { fn(new Error('GeeTest script unavailable')); } return instance; },
        onClose: function () { return instance; },
        getValidate: function () { return null; },
        reset: function () { return instance; },
        destroy: function () { return instance; }
    };

    if (typeof callback === 'function') {
        callback(instance);
    }

    return instance;
};
JS;
    }

    public function verify(?array $payload): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => true];
        }

        if (! is_array($payload)) {
            return ['ok' => false, 'message' => '请先完成行为验证'];
        }

        $lotNumber = (string) ($payload['lot_number'] ?? '');
        $captchaOutput = (string) ($payload['captcha_output'] ?? '');
        $passToken = (string) ($payload['pass_token'] ?? '');
        $genTime = (string) ($payload['gen_time'] ?? '');

        if ($lotNumber === '' || $captchaOutput === '' || $passToken === '' || $genTime === '') {
            return ['ok' => false, 'message' => '行为验证参数不完整'];
        }

        $signToken = hash_hmac('sha256', $lotNumber, $this->getCaptchaKey());
        $endpoint = self::API_SERVER.'/validate';

        try {
            $http = Http::asForm()
                ->timeout(10);

            $verifyOption = app()->environment('production') ? true : $this->serviceConfig['ssl_verify'];
            if ($this->serviceConfig['ca_bundle'] !== '' && is_file($this->serviceConfig['ca_bundle'])) {
                $verifyOption = $this->serviceConfig['ca_bundle'];
            }

            $response = $http
                ->withOptions(['verify' => $verifyOption])
                ->post($endpoint, [
                    'lot_number' => $lotNumber,
                    'captcha_output' => $captchaOutput,
                    'pass_token' => $passToken,
                    'gen_time' => $genTime,
                    'captcha_id' => $this->getCaptchaId(),
                    'sign_token' => $signToken,
                ]);

            if (! $response->successful()) {
                return $this->handleFailure('GeeTest validate request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            $data = $response->json();
            $result = ($data['result'] ?? '') === 'success';

            if (! $result) {
                return ['ok' => false, 'message' => '行为验证未通过，请重试'];
            }

            return ['ok' => true];
        } catch (\Throwable $exception) {
            if ($this->isSslCertificateError($exception->getMessage())) {
                $message = $this->serviceConfig['ssl_verify']
                    ? '行为验证服务证书校验失败，请配置 CA 证书，或在本地环境关闭 GEETEST_SSL_VERIFY'
                    : '行为验证服务连接失败，请检查本机代理或证书链配置';

                return $this->handleFailure('GeeTest validate exception', [
                    'message' => $exception->getMessage(),
                ], $message);
            }

            return $this->handleFailure('GeeTest validate exception', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function handleFailure(string $message, array $context = [], ?string $userMessage = null): array
    {
        Log::warning($message, $context);

        return ['ok' => false, 'message' => $userMessage ?? '行为验证服务暂时不可用，请稍后重试'];
    }

    private function getCaptchaKey(): string
    {
        return (string) $this->config('captcha_key', '');
    }

    private function config(string $key, mixed $default = null): mixed
    {
        $settingKey = "geetest_{$key}";
        $settingValue = $this->getSettingValue($settingKey);

        if ($settingValue === null || $settingValue === '') {
            $settingValue = config("idc.geetest.{$key}", $default);
        }

        if ($key === 'enabled') {
            return $this->normalizeBoolean($settingValue);
        }

        return $settingValue;
    }

    private function getSettingValue(string $key): mixed
    {
        if ($this->settingCache === null) {
            $this->settingCache = collect([
                'geetest_enabled',
                'geetest_captcha_id',
                'geetest_captcha_key',
            ])->mapWithKeys(fn (string $settingKey) => [
                $settingKey => Setting::getValue('system', $settingKey, null),
            ])->all();
        }

        return $this->settingCache[$key] ?? null;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }

        return (bool) $value;
    }

    private function isSslCertificateError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'ssl certificate problem')
            || str_contains($message, 'certificate verify failed')
            || str_contains($message, 'self-signed certificate');
    }

    private function fetchScriptContent(): string
    {
        $http = Http::timeout(10);

        $verifyOption = app()->environment('production') ? true : $this->serviceConfig['ssl_verify'];
        if ($this->serviceConfig['ca_bundle'] !== '' && is_file($this->serviceConfig['ca_bundle'])) {
            $verifyOption = $this->serviceConfig['ca_bundle'];
        }

        $response = $http
            ->withOptions(['verify' => $verifyOption])
            ->get(self::SCRIPT_UPSTREAM_URL);

        if (! $response->successful()) {
            throw new \RuntimeException('GeeTest 脚本拉取失败，状态码：'.$response->status());
        }

        $scriptContent = (string) $response->body();
        if ($scriptContent === '') {
            throw new \RuntimeException('GeeTest 脚本内容为空');
        }

        return $scriptContent;
    }
}
