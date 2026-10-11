<?php

namespace App\Services\Auth;

use App\Exceptions\BusinessException;
use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\Integrations\Plugins\PluginRuntimeRegistry;
use Illuminate\Support\Facades\Log;

/**
 * captcha 域的平台适配层（事实契约）。
 *
 * captcha 是六个插件域中唯一没有独立 adapter 类的域：本类即平台侧 adapter，
 * 把插件 action 协议（captcha.config / captcha.script / captcha.verify）包装成
 * 核心登录风控链路消费的语义（isEnabled/getCaptchaId/getScriptContent/verify）。
 * 插件必须逐字实现这三个 action 的语义与配置键（captcha_id/captcha_key），
 * captcha 为单启用域（PluginDomain::singleEnabledDomains）。
 *
 * 已内置完整降级链：插件异常全部捕获、验签失败降级 fallback 脚本、脚本代理路径固定，
 * 因此 2026-10-11 架构审查裁决暂不抽象独立 CaptchaDriverInterface——待出现第二个
 * 语义分歧的厂商需求时，再在本类收口接口化，避免为单一实现预建抽象层。
 */
class GeeTestService
{
    private const SCRIPT_PROXY_PATH = '/api/v2/client/auth/captcha-script';

    private ?array $captchaConfigCache = null;

    public function __construct(
        private ?PluginRuntimeRegistry $runtimeRegistry = null,
        private ?IntegrationDriverBindingResolver $bindingResolver = null,
    ) {}

    public function isEnabled(): bool
    {
        if ($this->activeDriver() === '') {
            return false;
        }

        $config = $this->captchaConfig();

        return (bool) ($config['enabled'] ?? false)
            && $this->getCaptchaId() !== '';
    }

    public function getCaptchaId(): string
    {
        return (string) ($this->captchaConfig()['captcha_id'] ?? '');
    }

    public function getScriptUrl(): string
    {
        return self::SCRIPT_PROXY_PATH;
    }

    public function getScriptContent(): string
    {
        $result = $this->executePlugin('captcha.script');
        if (! (bool) ($result['success'] ?? false)) {
            throw new \RuntimeException((string) ($result['message'] ?? '行为验证脚本暂时不可用'));
        }

        $content = (string) ($result['data']['content'] ?? '');
        if ($content === '') {
            throw new \RuntimeException('行为验证脚本内容为空');
        }

        return $content;
    }

    public function getFallbackScriptContent(): string
    {
        return <<<'JS'
window.initGeetest4 = window.initGeetest4 || function (options, callback) {
    var errorCallbacks = [];
    var instance = {
        appendTo: function () { return instance; },
        onReady: function (fn) { if (typeof fn === 'function') { fn(); } return instance; },
        onSuccess: function () { return instance; },
        onError: function (fn) { if (typeof fn === 'function') { errorCallbacks.push(fn); } return instance; },
        onClose: function () { return instance; },
        showCaptcha: function () {
            errorCallbacks.forEach(function (fn) { fn(new Error('行为验证脚本暂时不可用')); });
            return instance;
        },
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

    public function verify(mixed $payload, ?string $clientIp = null): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => true];
        }

        if (! is_array($payload)) {
            return ['ok' => false, 'message' => '请先完成行为验证'];
        }

        $payload['_client_ip'] = trim((string) ($clientIp ?? ''));

        $result = $this->executePlugin('captcha.verify', $payload);
        if (! (bool) ($result['success'] ?? false) || ! (bool) ($result['data']['verified'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($result['message'] ?? '行为验证未通过，请重试'),
            ];
        }

        return ['ok' => true];
    }

    private function captchaConfig(): array
    {
        if ($this->captchaConfigCache !== null) {
            return $this->captchaConfigCache;
        }

        if ($this->activeDriver() === '') {
            return $this->captchaConfigCache = [
                'enabled' => false,
                'captcha_id' => '',
                'script_url' => $this->getScriptUrl(),
            ];
        }

        $result = $this->executePlugin('captcha.config');
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];

        return $this->captchaConfigCache = [
            'enabled' => (bool) ($result['success'] ?? false) && (bool) ($data['enabled'] ?? false),
            'captcha_id' => (string) ($data['captcha_id'] ?? ''),
            'script_url' => $this->getScriptUrl(),
        ];
    }

    private function executePlugin(string $action, array $payload = []): array
    {
        $driver = $this->activeDriver();
        if ($driver === '') {
            return [
                'success' => false,
                'message' => '未启用人机验证插件',
                'data' => [],
            ];
        }

        try {
            return $this->runtime()->execute(
                domain: PluginDomain::CAPTCHA,
                slugOrKey: $driver,
                action: $action,
                payload: $payload,
            );
        } catch (BusinessException $exception) {
            Log::warning('[captcha] plugin business exception', [
                'driver' => $driver,
                'action' => $action,
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '行为验证服务暂时不可用，请稍后重试',
                'data' => [],
            ];
        } catch (\Throwable $exception) {
            Log::error('[captcha] plugin execute failed', [
                'driver' => $driver,
                'action' => $action,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return [
                'success' => false,
                'message' => '行为验证服务暂时不可用，请稍后重试',
                'data' => [],
            ];
        }
    }

    private function activeDriver(): string
    {
        return $this->bindingResolver()->captchaDriverKey();
    }

    private function runtime(): PluginRuntimeRegistry
    {
        if (! $this->runtimeRegistry instanceof PluginRuntimeRegistry) {
            $this->runtimeRegistry = app(PluginRuntimeRegistry::class);
        }

        return $this->runtimeRegistry;
    }

    private function bindingResolver(): IntegrationDriverBindingResolver
    {
        return $this->bindingResolver ??= app(IntegrationDriverBindingResolver::class);
    }
}
