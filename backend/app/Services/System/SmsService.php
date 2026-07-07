<?php

namespace App\Services\System;

use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Sms\Contracts\SmsDriver;
use App\Services\Sms\Data\SmsMessageRequest;
use App\Services\Sms\Data\SmsSendRequest;
use App\Services\Sms\SmsDriverManager;
use App\Support\SmsTemplateCatalog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SmsService
{
    private SmsDriverManager $driverManager;

    public function __construct(
        SmsDriverManager $driverManager,
        private ?IntegrationDriverBindingResolver $driverBindingResolver = null,
        private ?PluginConfigRepository $pluginConfigRepository = null,
        private ?NotificationTemplateService $notificationTemplateService = null,
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
            'sensitive_params' => ['code'],
            'fallback_provider_template_id' => $this->legacyVerificationProviderTemplateId(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function sendAliyunVerifyCode(string $phone, string $code, array $options, ?SmsDriver $driver): void
    {
        $purpose = $this->normalizeVerifyCodePurpose($options);
        $sendOptions = array_merge($options, [
            'purpose' => $purpose,
            'min' => (string) ($options['min'] ?? '5'),
        ]);
        $logParams = [
            'code' => '***',
            'min' => (string) $sendOptions['min'],
            'purpose' => $purpose,
        ];

        $logContext = $this->createSmsLog(
            $phone,
            'aliyun_verify_code',
            '阿里云短信验证码已发送（内容已脱敏）',
            $logParams,
            'aliyun',
            'sms_verify'
        );

        try {
            if (! $this->isEnabled()) {
                throw new \RuntimeException('短信通知未启用');
            }

            $driver ??= $this->driverManager->resolve('aliyun');
            $result = $driver->sendVerifyCode(new SmsSendRequest($phone, $code, $sendOptions));
            $providerTemplateCode = trim($result->templateCode);
            $updatedParams = $providerTemplateCode !== ''
                ? array_merge($logParams, ['provider_template_id' => $providerTemplateCode])
                : $logParams;

            $this->updateSmsLog($logContext, [
                'status' => 'success',
                'request_id' => $result->requestId,
                'sent_at' => now(),
                'template_code' => $providerTemplateCode !== '' ? $providerTemplateCode : 'aliyun_verify_code',
                'params_json' => $updatedParams,
            ]);
        } catch (\Exception $e) {
            $this->updateSmsLog($logContext, [
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

        $providerTemplateId = $this->resolveProviderTemplateId($templateCode, $template, $options);
        $driver = $this->resolveDriverForLog();
        $provider = $driver?->key() ?? 'unconfigured';
        $logParams = $this->buildLogParams($renderParams, $options, $providerTemplateId);
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
            if (! $this->isEnabled()) {
                throw new \RuntimeException('短信通知未启用');
            }

            $driver ??= $this->driverManager->resolve();
            $result = $driver->sendMessage(new SmsMessageRequest($phone, $providerTemplateId, $content, [
                'business_template_code' => $templateCode,
                'provider_template_id' => $providerTemplateId,
            ]));

            $this->updateSmsLog($logContext, [
                'status' => 'success',
                'request_id' => $result->requestId,
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            $this->updateSmsLog($logContext, [
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

    private function isEnabled(): bool
    {
        $value = Setting::getValue('notification', 'sms_enabled', '0');

        return in_array((string) $value, ['1', 'true', 'on'], true);
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
    private function resolveProviderTemplateId(string $templateCode, array $template, array $options): string
    {
        $explicit = trim((string) ($options['provider_template_id'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
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
     * @param  array<string, mixed>  $options
     * @return array<string, string>
     */
    private function buildLogParams(array $renderParams, array $options, string $providerTemplateId): array
    {
        $sensitive = array_values(array_filter(
            array_map('strval', (array) ($options['sensitive_params'] ?? [])),
            static fn (string $key): bool => trim($key) !== ''
        ));
        $logParams = [];

        foreach ($renderParams as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $logParams[$normalizedKey] = in_array($normalizedKey, $sensitive, true)
                ? '***'
                : (string) $value;
        }

        $logParams['provider_template_id'] = $providerTemplateId;

        return $logParams;
    }

    private function maskPhoneForLog(string $phone): string
    {
        $normalized = trim($phone);
        if ($normalized === '' || mb_strlen($normalized) <= 7) {
            return $normalized;
        }

        return mb_substr($normalized, 0, 3).'****'.mb_substr($normalized, -4);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{table: 'notification_logs'|'sms_logs'|null, id: int|null}
     */
    private function createSmsLog(string $phone, string $templateCode, string $content, array $params, string $provider, string $originType): array
    {
        $traceId = $this->notificationTraceId('sms', $templateCode);

        try {
            if (Schema::hasTable('notification_logs')) {
                $log = NotificationLog::create(array_merge([
                    'channel' => 'sms',
                    'recipient' => $phone,
                    'template_code' => $templateCode,
                    'content' => $content,
                    'params_json' => $params,
                    'provider' => $provider,
                    'status' => 'pending',
                    'origin_type' => $originType,
                    'origin_id' => 0,
                ], $this->smsAuditPayload('notification_logs', $traceId, $provider)));

                return ['table' => 'notification_logs', 'id' => (int) $log->getKey()];
            }

            if (Schema::hasTable('sms_logs')) {
                $log = SmsLog::create(array_merge([
                    'phone' => $phone,
                    'template_code' => $templateCode,
                    'content' => $content,
                    'params' => $params,
                    'provider' => $provider,
                    'status' => 'pending',
                    'error_msg' => null,
                    'sent_at' => null,
                ], $this->smsAuditPayload('sms_logs', $traceId, $provider)));

                return ['table' => 'sms_logs', 'id' => (int) $log->getKey()];
            }
        } catch (\Throwable $exception) {
            Log::warning('短信日志写入失败，已跳过日志写入继续发送', [
                'phone' => $this->maskPhoneForLog($phone),
                'template_code' => $templateCode,
                'message' => $exception->getMessage(),
            ]);
        }

        return ['table' => null, 'id' => null];
    }

    /**
     * @return array<string, mixed>
     */
    private function smsAuditPayload(string $table, string $traceId, string $driverKey): array
    {
        $context = $this->driverBindingResolver()->smsContext($driverKey !== 'unconfigured' ? $driverKey : null);
        $payload = [];

        if (Schema::hasColumn($table, 'plugin_id')) {
            $payload['plugin_id'] = $context['plugin_id'];
        }

        if (Schema::hasColumn($table, 'driver_key')) {
            $payload['driver_key'] = $context['driver_key'];
        }

        if (Schema::hasColumn($table, 'trace_id')) {
            $payload['trace_id'] = $traceId;
        }

        return $payload;
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

    private function notificationTraceId(string $channel, string $templateCode): string
    {
        $template = trim($templateCode) !== '' ? trim($templateCode) : 'none';

        return substr($channel.':'.$template.':'.str_replace('-', '', (string) Str::uuid()), 0, 64);
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
     * @return array<string, string>
     */
    private function stringifyParams(array $params): array
    {
        $normalized = [];

        foreach ($params as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            $normalized[trim($key)] = match (true) {
                is_string($value) => $value,
                is_int($value), is_float($value) => (string) $value,
                is_bool($value) => $value ? '1' : '',
                $value === null => '',
                default => (string) $value,
            };
        }

        return $normalized;
    }

    private function resolveSiteName(): string
    {
        $siteName = trim((string) Setting::getValue(
            'basic',
            'site_name',
            config('idc.site_name', config('app.name', '创欧云'))
        ));

        return $siteName !== '' ? $siteName : (string) config('app.name', '创欧云');
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

    private function hasTemplateValue(mixed $value): bool
    {
        return ! in_array($value, [null, '', false], true);
    }

    /**
     * @param  array{table: 'notification_logs'|'sms_logs'|null, id: int|null}  $logContext
     */
    private function updateSmsLog(array $logContext, array $attributes): void
    {
        $table = $logContext['table'] ?? null;
        $id = isset($logContext['id']) ? (int) $logContext['id'] : 0;

        if ($table === null || $id <= 0) {
            return;
        }

        try {
            if ($table === 'notification_logs') {
                NotificationLog::query()->whereKey($id)->update($attributes);

                return;
            }

            if ($table === 'sms_logs') {
                $payload = [
                    'status' => $attributes['status'] ?? 'pending',
                    'request_id' => $attributes['request_id'] ?? null,
                    'error_msg' => $attributes['error_msg'] ?? null,
                    'sent_at' => $attributes['sent_at'] ?? null,
                ];

                if (array_key_exists('template_code', $attributes)) {
                    $payload['template_code'] = $attributes['template_code'];
                }

                if (array_key_exists('params_json', $attributes)) {
                    $payload['params'] = $attributes['params_json'];
                }

                if (array_key_exists('content', $attributes)) {
                    $payload['content'] = $attributes['content'];
                }

                SmsLog::query()->whereKey($id)->update($payload);
            }
        } catch (\Throwable $exception) {
            Log::warning('短信日志状态更新失败，已忽略以避免阻断发送流程', [
                'table' => $table,
                'id' => $id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
