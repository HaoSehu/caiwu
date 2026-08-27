<?php

namespace App\Services\System\Concerns;

use App\Models\MessageLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * NotificationService 与 SmsService 的公共骨架。
 *
 * 两个服务在 trace_id 生成、渠道开关解析、站点名解析、模板参数字符串化、
 * 模板占位符判定与 message_logs 状态更新上逐字相同，统一收敛到本 trait；
 * 存在历史行为差异的点通过受保护钩子区分，不做强行统一：
 * - 布尔模板参数的 false 字面量：邮件侧输出 '0'，短信侧输出空串；
 * - message_logs 更新失败告警中的渠道文案（邮件/短信）。
 */
trait InteractsWithMessageLogs
{
    /**
     * message_logs 告警文案中的渠道中文名（“邮件”“短信”）。
     */
    abstract protected function messageChannelLabel(): string;

    /**
     * 生成截断后的发送链路追踪 ID：{channel}:{template}:{uuid32}。
     */
    private function notificationTraceId(string $channel, ?string $templateCode): string
    {
        $template = trim((string) $templateCode) !== '' ? trim((string) $templateCode) : 'none';

        return substr($channel.':'.$template.':'.str_replace('-', '', (string) Str::uuid()), 0, 64);
    }

    /**
     * 解析 notification 分组的渠道开关（1/true/on 视为开启）。
     */
    private function channelSwitchEnabled(string $settingKey): bool
    {
        $value = Setting::getValue('notification', $settingKey, '0');

        return in_array((string) $value, ['1', 'true', 'on'], true);
    }

    /**
     * 模板布尔参数的 false 字面量。邮件与短信的历史输出不同：
     * 邮件侧为 '0'、短信侧为 ''；子类按需覆写，禁止合并为单一行为。
     */
    protected function stringifyBoolFalse(): string
    {
        return '';
    }

    /**
     * 将模板参数统一转为字符串键值对；跳过非法键，保留合法键的 trim 结果。
     *
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
                is_bool($value) => $value ? '1' : $this->stringifyBoolFalse(),
                $value === null => '',
                default => (string) $value,
            };
        }

        return $normalized;
    }

    /**
     * 判断模板区块变量是否有值（null/空串/false 视为无值）。
     */
    private function hasTemplateValue(mixed $value): bool
    {
        return ! in_array($value, [null, '', false], true);
    }

    /**
     * 解析站点名称：basic.site_name → idc.site_name → app.name → “创欧云”。
     */
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
     * message_logs 三段式写入的状态更新段：id 无效时静默返回，
     * 更新失败仅告警不阻断发送流程。
     *
     * @param  array{id: int|null}  $logContext
     * @param  array<string, mixed>  $attributes
     */
    private function updateMessageLog(array $logContext, array $attributes): void
    {
        $id = isset($logContext['id']) ? (int) $logContext['id'] : 0;

        if ($id <= 0) {
            return;
        }

        try {
            MessageLog::query()->whereKey($id)->update($attributes);
        } catch (\Throwable $exception) {
            Log::warning($this->messageChannelLabel().'日志状态更新失败，已忽略以避免阻断发送流程', [
                'table' => 'message_logs',
                'id' => $id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
