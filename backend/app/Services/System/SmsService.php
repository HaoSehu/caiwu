<?php

namespace App\Services\System;

use App\Models\IntegrationPlugin;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Sms\Contracts\SmsDriver;
use App\Services\Sms\Data\SmsSendRequest;
use App\Services\Sms\SmsDriverManager;
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
    ) {
        $this->driverManager = $driverManager;
    }

    public function sendVerifyCode(string $phone, string $code): void
    {
        $templateCode = $this->activeSmsTemplateCode();
        $templateParams = ['code' => $code, 'min' => '5'];
        $driver = $this->resolveDriverForLog();
        $logContext = $this->createSmsLog($phone, $templateCode, $templateParams, $driver?->key() ?? 'unconfigured');

        try {
            if (! $this->isEnabled()) {
                throw new \RuntimeException('短信通知未启用');
            }

            $driver ??= $this->driverManager->resolve();
            $result = $driver->sendVerifyCode(new SmsSendRequest($phone, $code));

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

    private function buildVerificationLogContent(): string
    {
        return '短信验证码已发送（内容已脱敏）';
    }

    private function buildVerificationLogParams(array $templateParams): array
    {
        return [
            'code' => '***',
            'min' => (string) ($templateParams['min'] ?? ''),
        ];
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
     * @return array{table: 'notification_logs'|'sms_logs'|null, id: int|null}
     */
    private function createSmsLog(string $phone, string $templateCode, array $templateParams, string $provider): array
    {
        $traceId = $this->notificationTraceId('sms', $templateCode);

        try {
            if (Schema::hasTable('notification_logs')) {
                $log = NotificationLog::create(array_merge([
                    'channel' => 'sms',
                    'recipient' => $phone,
                    'template_code' => $templateCode,
                    'content' => $this->buildVerificationLogContent(),
                    'params_json' => $this->buildVerificationLogParams($templateParams),
                    'provider' => $provider,
                    'status' => 'pending',
                    'origin_type' => 'sms_verify',
                    'origin_id' => 0,
                ], $this->smsAuditPayload('notification_logs', $traceId, $provider)));

                return ['table' => 'notification_logs', 'id' => (int) $log->getKey()];
            }

            if (Schema::hasTable('sms_logs')) {
                $log = SmsLog::create(array_merge([
                    'phone' => $phone,
                    'template_code' => $templateCode,
                    'content' => $this->buildVerificationLogContent(),
                    'params' => $this->buildVerificationLogParams($templateParams),
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

    private function activeSmsTemplateCode(): string
    {
        $context = $this->driverBindingResolver()->smsContext();
        $pluginId = (int) ($context['plugin_id'] ?? 0);

        if ($pluginId > 0 && Schema::hasTable('integration_plugins')) {
            $plugin = IntegrationPlugin::query()->whereKey($pluginId)->first();
            if ($plugin instanceof IntegrationPlugin) {
                $config = $this->pluginConfigRepository()->resolvedConfig($plugin);
                $templateCode = trim((string) ($config['template_code'] ?? ''));
                if ($templateCode !== '') {
                    return $templateCode;
                }
            }
        }

        return '100001';
    }

    private function pluginConfigRepository(): PluginConfigRepository
    {
        return $this->pluginConfigRepository ??= app(PluginConfigRepository::class);
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
                SmsLog::query()->whereKey($id)->update([
                    'status' => $attributes['status'] ?? 'pending',
                    'request_id' => $attributes['request_id'] ?? null,
                    'error_msg' => $attributes['error_msg'] ?? null,
                    'sent_at' => $attributes['sent_at'] ?? null,
                ]);
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
