<?php

namespace App\Services\System;

use App\Models\MessageLog;
use App\Models\Setting;
use App\Models\SmsProviderTemplate;
use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Sms\Contracts\ProvidesVerifyCodeTemplate;
use App\Services\Sms\Contracts\SmsDriver;
use App\Services\Sms\Data\SmsMessageRequest;
use App\Services\Sms\Data\SmsSendRequest;
use App\Services\Sms\SmsDriverManager;
use App\Services\Sms\SmsTemplateResolver;
use App\Services\System\Concerns\InteractsWithMessageLogs;
use App\Support\SmsTemplateCatalog;
use Illuminate\Support\Facades\Log;

class SmsService
{
    use InteractsWithMessageLogs;

    private SmsDriverManager $driverManager;

    public function __construct(
        SmsDriverManager $driverManager,
        private ?IntegrationDriverBindingResolver $driverBindingResolver = null,
        private ?PluginConfigRepository $pluginConfigRepository = null,
        private ?NotificationTemplateService $notificationTemplateService = null,
        private ?SmsTemplateResolver $templateResolver = null,
    ) {
        $this->driverManager = $driverManager;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function sendVerifyCode(string $phone, string $code, array $options = []): void
    {
        if (! $this->notificationTemplates()->isEnabled('sms', SmsTemplateCatalog::TEMPLATE_VERIFY_CODE)) {
            return;
        }

        $driver = $this->resolveDriverForLog();
        if (($driver?->key() ?? '') === 'aliyun') {
            $this->sendAliyunVerifyCode($phone, $code, $options, $driver);

            return;
        }

        $this->sendTemplateSms($phone, SmsTemplateCatalog::TEMPLATE_VERIFY_CODE, [
            'code' => $code,
            'min' => '5',
            'expire_minutes' => '5',
        ], array_merge($options, [
            'origin_type' => 'sms_verify',
            'fallback_provider_template_id' => $this->legacyVerificationProviderTemplateId(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function sendAliyunVerifyCode(string $phone, string $code, array $options, ?SmsDriver $driver): void
    {
        $purpose = $this->normalizeVerifyCodePurpose($options);
        $expireMinutes = (string) ($options['min'] ?? '5');
        $sendOptions = array_merge($options, [
            'purpose' => $purpose,
            'min' => $expireMinutes,
        ]);
        $logParams = [
            'code' => $code,
            'min' => $expireMinutes,
            'purpose' => $purpose,
        ];

        // 短信日志含验证码明文，管理端需完整真实审计信息，不做脱敏（项目红线）

        // 文案模板是短信驱动的协议能力：所有插件驱动都经 PluginSmsDriver 实现
        // ProvidesVerifyCodeTemplate，阿里云插件的 verifyCodeTemplate 匹配含 default
        // 分支必然非空。模板文案以插件侧为唯一权威来源，系统层不保留回退副本。
        $templateText = $driver instanceof ProvidesVerifyCodeTemplate
            ? trim((string) $driver->verifyCodeTemplate($purpose))
            : '';

        // 防御性校验：驱动返回空模板时拒绝发送（避免记录空正文或发送空短信）
        if ($templateText === '') {
            throw new \RuntimeException('短信模板文本为空，无法发送验证码');
        }

        $content = str_replace(['${code}', '${min}'], [$code, $expireMinutes], $templateText);
        $logContext = $this->createSmsLog(
            $phone,
            'aliyun_verify_code',
            $content,
            $logParams,
            'aliyun',
            'sms_verify'
        );

        try {
            if (! $this->channelSwitchEnabled('sms_enabled')) {
                throw new \RuntimeException('短信通知未启用');
            }

            $driver ??= $this->driverManager->resolve('aliyun');
            $result = $driver->sendVerifyCode(new SmsSendRequest($phone, $code, $sendOptions));
            $providerTemplateCode = trim($result->templateCode);
            $updatedParams = $providerTemplateCode !== ''
                ? array_merge($logParams, ['provider_template_id' => $providerTemplateCode])
                : $logParams;

            $this->updateMessageLog($logContext, [
                'status' => 'success',
                'request_id' => $result->bizId ?? $result->requestId,
                'sent_at' => now(),
                'template_code' => $providerTemplateCode !== '' ? $providerTemplateCode : 'aliyun_verify_code',
                'params_json' => $updatedParams,
            ]);
        } catch (\Exception $e) {
            $this->updateMessageLog($logContext, [
                'status' => 'failed',
                'error_msg' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $options
     */
    public function sendTemplateSms(string $phone, string $templateCode, array $params = [], array $options = []): void
    {
        $templateCode = trim($templateCode);
        $template = $this->notificationTemplates()->find('sms', $templateCode);
        if (! is_array($template)) {
            throw new \RuntimeException('短信模板不存在');
        }

        if (! $this->templatePayloadIsEnabled($template)) {
            return;
        }

        $contentTemplate = $this->resolveTemplateContent($template);
        $renderParams = array_merge([
            'site_name' => $this->resolveSiteName(),
        ], $this->stringifyParams($params));
        $content = $this->renderTemplateText($contentTemplate, $renderParams);

        if ($content === '') {
            throw new \RuntimeException('短信模板内容为空');
        }

        // 解析顺序（短信模板管理机制 2026-10-11）：driver 必须先于映射解析——
        // 映射按 plugin_key=driverKey 过滤；driver 未配置时跳过映射层走旧回落链。
        $driver = $this->resolveDriverForLog();
        $provider = $driver?->key() ?? 'unconfigured';
        $mapping = $this->resolveProviderMapping($templateCode, $driver);
        $providerTemplateId = $this->resolveProviderTemplateId($templateCode, $template, $options, $mapping);
        $sendVariables = $this->validatedMappingVariables($templateCode, $mapping, $renderParams);
        $logParams = $this->buildLogParams($renderParams, $providerTemplateId);
        $logContent = $this->renderTemplateText($contentTemplate, $logParams);
        $logContext = $this->createSmsLog(
            $phone,
            $templateCode,
            $logContent !== '' ? $logContent : '短信已发送',
            $logParams,
            $provider,
            (string) ($options['origin_type'] ?? 'sms_template')
        );

        try {
            if (! $this->channelSwitchEnabled('sms_enabled')) {
                throw new \RuntimeException('短信通知未启用');
            }

            $driver ??= $this->driverManager->resolve();
            $result = $driver->sendMessage(new SmsMessageRequest($phone, $providerTemplateId, $content, [
                'business_template_code' => $templateCode,
                'provider_template_id' => $providerTemplateId,
                'template_variables' => $sendVariables,
            ]));

            $this->updateMessageLog($logContext, [
                'status' => 'success',
                'request_id' => $result->requestId,
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            $this->updateMessageLog($logContext, [
                'status' => 'failed',
                'error_msg' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function getDriverManager(): SmsDriverManager
    {
        return $this->driverManager;
    }

    /**
     * message_logs 告警文案中的渠道名。
     */
    protected function messageChannelLabel(): string
    {
        return '短信';
    }

    /**
     * @param  array<string, mixed>  $template
     */
    private function resolveTemplateContent(array $template): string
    {
        return (string) ($template['content'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $options
     */
    private function resolveProviderTemplateId(
        string $templateCode,
        array $template,
        array $options,
        ?SmsProviderTemplate $mapping = null,
    ): string {
        $explicit = trim((string) ($options['provider_template_id'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        // 映射层（短信模板管理机制 2026-10-11）：同步所得的 approved 映射比
        // 人工旧列更新鲜，优先级插在显式指定之后、旧列之前（决策日志已记）。
        $mapped = trim((string) ($mapping->provider_template_id ?? ''));
        if ($mapped !== '') {
            return $mapped;
        }

        $stored = trim((string) ($template['provider_template_id'] ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        $fallback = trim((string) ($options['fallback_provider_template_id'] ?? ''));
        if ($fallback !== '') {
            return $fallback;
        }

        return $templateCode;
    }

    /**
     * 业务码在当前绑定 driver 下的 approved 运营商模板映射行。
     */
    private function resolveProviderMapping(string $templateCode, ?SmsDriver $driver): ?SmsProviderTemplate
    {
        if ($driver === null) {
            return null;
        }

        return $this->templateResolver()->resolve($templateCode, $driver->key());
    }

    /**
     * 命中映射时，用运营商模板声明的变量与渲染参数求交集校验。
     *
     * 声明变量缺失 → fail-fast：避免带着残缺 TemplateParam 打运营商换回
     * isv.TEMPLATE_MISSING_PARAMETERS 之类含糊错误。多出的渲染参数不阻断，
     * 由插件侧按声明变量过滤后构造 TemplateParam。
     *
     * @param  array<string, string>  $renderParams
     * @return array<string, string> 声明变量 → 渲染值（传给插件构造 TemplateParam）
     */
    private function validatedMappingVariables(string $templateCode, ?SmsProviderTemplate $mapping, array $renderParams): array
    {
        if ($mapping === null) {
            return [];
        }

        $declared = array_values(array_filter(
            array_map('strval', (array) ($mapping->variables_json ?? [])),
            static fn (string $name): bool => $name !== ''
        ));

        if ($declared === []) {
            return [];
        }

        $missing = array_values(array_diff($declared, array_keys($renderParams)));
        if ($missing !== []) {
            throw new \RuntimeException(
                '短信模板 '.$templateCode.' 挂接的运营商模板缺少变量：'.implode('、', $missing)
                .'，请检查模板文案或重新挂接'
            );
        }

        $variables = [];
        foreach ($declared as $name) {
            $variables[$name] = (string) $renderParams[$name];
        }

        return $variables;
    }

    private function templateResolver(): SmsTemplateResolver
    {
        return $this->templateResolver ??= app(SmsTemplateResolver::class);
    }

    private function notificationTemplates(): NotificationTemplateService
    {
        return $this->notificationTemplateService ??= app(NotificationTemplateService::class);
    }

    /**
     * @param  array<string, mixed>  $template
     */
    private function templatePayloadIsEnabled(array $template): bool
    {
        return ! array_key_exists('is_enabled', $template) || (bool) $template['is_enabled'];
    }

    /**
     * @param  array<string, mixed>  $renderParams
     * @return array<string, string>
     */
    private function buildLogParams(array $renderParams, string $providerTemplateId): array
    {
        $logParams = [];

        foreach ($renderParams as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $logParams[$normalizedKey] = (string) $value;
        }

        $logParams['provider_template_id'] = $providerTemplateId;

        return $logParams;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{id: int|null}
     */
    private function createSmsLog(string $phone, string $templateCode, string $content, array $params, string $provider, string $originType): array
    {
        $traceId = $this->notificationTraceId('sms', $templateCode);

        try {
            $log = MessageLog::create(array_merge([
                'channel' => 'sms',
                'recipient' => $phone,
                'template_code' => $templateCode,
                'content' => $content,
                'params_json' => $params,
                'provider' => $provider,
                'status' => 'pending',
                'origin_type' => $originType,
                'origin_id' => 0,
            ], $this->smsAuditPayload($traceId, $provider)));

            return ['id' => (int) $log->getKey()];
        } catch (\Throwable $exception) {
            Log::warning('短信日志写入失败，已跳过日志写入继续发送', [
                'phone' => $phone,
                'template_code' => $templateCode,
                'message' => $exception->getMessage(),
            ]);
        }

        return ['id' => null];
    }

    /**
     * @return array<string, mixed>
     */
    private function smsAuditPayload(string $traceId, string $driverKey): array
    {
        $context = $this->driverBindingResolver()->smsContext($driverKey !== 'unconfigured' ? $driverKey : null);

        return [
            'plugin_id' => $context['plugin_id'],
            'driver_key' => $context['driver_key'],
            'trace_id' => $traceId,
        ];
    }

    private function driverBindingResolver(): IntegrationDriverBindingResolver
    {
        return $this->driverBindingResolver ??= app(IntegrationDriverBindingResolver::class);
    }

    private function legacyVerificationProviderTemplateId(): string
    {
        $settingTemplateCode = trim((string) Setting::getValue('notification', 'sms_template_code', ''));
        if ($settingTemplateCode !== '') {
            return $settingTemplateCode;
        }

        return SmsTemplateCatalog::TEMPLATE_VERIFY_CODE;
    }

    private function resolveDriverForLog(): ?SmsDriver
    {
        try {
            return $this->driverManager->resolve();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function normalizeVerifyCodePurpose(array $options): string
    {
        $purpose = strtolower(trim((string) (
            $options['purpose']
            ?? $options['scene']
            ?? $options['type']
            ?? 'generic'
        )));

        return match ($purpose) {
            'login', 'register', 'reset', 'reset_password', 'password_reset',
            'change_phone', 'phone_change', 'update_phone', 'bind_phone', 'new_phone',
            'verify_bound_phone', 'verify_phone' => $purpose,
            default => 'generic',
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function renderTemplateText(string $template, array $params): string
    {
        $rendered = preg_replace_callback(
            '/\{\{#([a-zA-Z0-9_]+)\}\}(.*?)\{\{\/\1\}\}/su',
            function (array $matches) use ($params) {
                $key = (string) ($matches[1] ?? '');
                $value = $params[$key] ?? '';

                return $this->hasTemplateValue($value) ? (string) ($matches[2] ?? '') : '';
            },
            $template
        );

        $rendered = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/u',
            static fn (array $matches): string => (string) ($params[(string) ($matches[1] ?? '')] ?? ''),
            $rendered ?? $template
        );

        $rendered = preg_replace_callback(
            '/(?<!\{)\{([a-zA-Z0-9_]+)\}(?!\})/u',
            static fn (array $matches): string => (string) ($params[(string) ($matches[1] ?? '')] ?? ''),
            (string) $rendered
        );

        $rendered = preg_replace("/[ \t]+\n/u", "\n", (string) $rendered) ?? (string) $rendered;
        $rendered = preg_replace("/\n{3,}/u", "\n\n", (string) $rendered) ?? (string) $rendered;

        return trim($rendered);
    }
}
