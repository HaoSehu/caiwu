<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 嵌套明细（日志 context / 网关报文 / 插件运行时 meta）的统一限量治理。
 *
 * 策略：先递归截断超长字符串叶子；整体编码仍超限时降级为
 * 「截断标记 + 原始字节 + 哈希 + 预览」，保留可定位性而不保留全量内容。
 */
final class PayloadLimiter
{
    /** 叶子字符串字节上限默认值，供各调用方共用同一治理边界 */
    public const DEFAULT_LEAF_MAX_BYTES = 8192;

    /** 摘要固定键带双下划线前缀，避免与业务报文中的同名键冲突 */
    private const PREFIXED_KEYS = [
        '__truncated',
        '__original_bytes',
        '__sha256',
        '__preview',
    ];

    /**
     * 递归截断超长字符串叶子，返回与原结构相同的数组。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function truncateLeaves(array $payload, int $leafMaxBytes): array
    {
        foreach ($payload as $key => $value) {
            if (is_string($value) && strlen($value) > $leafMaxBytes) {
                $payload[$key] = mb_strcut($value, 0, $leafMaxBytes).'...[truncated '.strlen($value).' bytes]';

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = self::truncateLeaves($value, $leafMaxBytes);
            }
        }

        return $payload;
    }

    /**
     * 叶子截断后整体编码仍超限时，降级为摘要结构。
     *
     * 未超限返回前同样做一次编码→解码往返，替换非法 UTF-8，避免下游
     * json 序列化抛 JsonException 导致整行丢失。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function limit(array $payload, int $leafMaxBytes, int $totalMaxBytes, int $previewBytes): array
    {
        $payload = self::truncateLeaves($payload, $leafMaxBytes);

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        if (strlen($encoded) <= $totalMaxBytes) {
            $normalized = json_decode($encoded, true);

            return is_array($normalized) ? $normalized : $payload;
        }

        return [
            self::PREFIXED_KEYS[0] => true,
            self::PREFIXED_KEYS[1] => strlen($encoded),
            self::PREFIXED_KEYS[2] => hash('sha256', $encoded),
            self::PREFIXED_KEYS[3] => mb_strcut($encoded, 0, $previewBytes),
        ];
    }
}
