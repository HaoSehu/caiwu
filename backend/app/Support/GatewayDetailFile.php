<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * gateway 明细（请求/响应报文）的按日轮转文件读写。
 *
 * 明细不再整段入库，而是经 logging.channels.gateway-json（RotatingFileHandler +
 * JsonFormatter）按日写入文件；库行只保留 locator（gateway-json-{date}.log:{detailKey}），
 * 列表/筛选不依赖明细，详情需要时再按 locator 回读文件。
 */
class GatewayDetailFile
{
    private const PREFIX = 'gateway-json';

    /**
     * @param  array<string, mixed>  $requestData
     * @param  array<string, mixed>  $responseData
     */
    public static function write(array $requestData, array $responseData, string $gateway): ?string
    {
        // 测试环境 logging.gateway-json 走 NullHandler，不产生真实文件；
        // 显式返回 null，让新行走库内截断摘要降级路径，避免测试拿到 locator 却断言为空明细。
        if (app()->environment('testing')) {
            return null;
        }

        try {
            $detailKey = (string) Str::ulid();
            // 固定同一时刻，避免跨午夜时 locator 文件名与 handler 实际写入文件不一致
            $writtenAt = now();

            Log::channel('gateway-json')->info('gateway.detail', [
                'detail_key' => $detailKey,
                'gateway' => $gateway,
                'request_data' => $requestData !== [] ? $requestData : null,
                'response_data' => $responseData !== [] ? $responseData : null,
            ]);

            return self::fileNameFor($writtenAt).':'.$detailKey;
        } catch (\Throwable) {
            // 明细落文件失败不阻断支付主流程；调用方据此降级为库内截断摘要。
            return null;
        }
    }

    /**
     * 按 locator 回读明细条目。
     *
     * 返回 null 表示本无明细（locator 缺失或格式非法，调用方不展示明细）；
     * 返回 detail_unavailable 标记表示明细通道本身失效——locator 合法但对应
     * 的按日文件已丢失（GATEWAY_LOG_DAYS 超过 90 天被轮转/归档删除或损坏），
     * 供管理端区分「本无明细」与「明细已过保留期或文件缺失」。
     *
     * @return array<string, mixed>|null
     */
    public static function read(string $locator): ?array
    {
        [$fileToken, $detailKey] = array_pad(explode(':', trim($locator), 2), 2, '');
        if ($fileToken === '' || $detailKey === '') {
            return null;
        }

        // 仅允许系统自产的按日网关明细文件名，防止异常 locator 解析到任意日志文件
        if (! preg_match('/^gateway-json-\d{4}-\d{2}-\d{2}\.log$/', $fileToken)) {
            return null;
        }

        $path = storage_path('logs/'.$fileToken);
        if (! is_file($path)) {
            return ['detail_unavailable' => true];
        }

        try {
            $file = new \SplFileObject($path, 'r');

            while (! $file->eof()) {
                $line = trim((string) $file->current());
                $file->next();

                if ($line === '' || ! str_contains($line, $detailKey)) {
                    continue;
                }

                $decoded = json_decode($line, true);
                $context = is_array($decoded['context'] ?? null) ? $decoded['context'] : [];
                if ((string) ($context['detail_key'] ?? '') !== $detailKey) {
                    continue;
                }

                return [
                    'detail_key' => $detailKey,
                    'gateway' => (string) ($context['gateway'] ?? ''),
                    'request_data' => $context['request_data'] ?? [],
                    'response_data' => $context['response_data'] ?? [],
                ];
            }

            // 文件存在但未命中条目（轮转删除或损坏后残留），同样视为明细不可用
            return ['detail_unavailable' => true];
        } catch (\Throwable) {
            return ['detail_unavailable' => true];
        }
    }

    private static function fileNameFor(\DateTimeInterface $date): string
    {
        return self::PREFIX.'-'.$date->format('Y-m-d').'.log';
    }
}
