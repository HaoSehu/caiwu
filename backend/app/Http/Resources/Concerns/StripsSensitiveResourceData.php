<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

/**
 * Resource 响应中的敏感字段剥离（唯一真源）。
 *
 * 所有 Resource 必须使用本 trait，不得各自内联实现，避免 needle 集合漂移
 * 导致某些响应泄露敏感字段。needle 集合为各历史实现的**并集**——
 * 取并集只增强剥离、绝不削弱；`has_password` 是唯一例外（布尔标记，非密钥本身）。
 */
trait StripsSensitiveResourceData
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function stripSensitiveKeys(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            $normalized = strtolower(trim((string) $key));
            if ($this->isSensitiveKey($normalized)) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->stripSensitiveKeys($value) : $value;
        }

        return $clean;
    }

    protected function isSensitiveKey(mixed $key): bool
    {
        if (! is_string($key)) {
            return false;
        }

        $normalized = strtolower(trim($key));
        if ($normalized === 'has_password') {
            return false;
        }

        foreach ([
            'password',
            'secret',
            'api_key',
            'token',
            'raw_response',
            'third_party_response',
            'authorization',
            'cookie',
            'signature',
            'private_key',
            'public_key',
        ] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
