<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 客户端输出用邮箱掩码：仅保留首字符与域名，与管理端脱敏格式保持一致。
 */
class EmailMasker
{
    public static function mask(mixed $value): string
    {
        $email = trim((string) ($value ?? ''));
        if ($email === '' || ! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
