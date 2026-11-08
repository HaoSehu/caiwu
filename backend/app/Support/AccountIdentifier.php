<?php

declare(strict_types=1);

namespace App\Support;

final class AccountIdentifier
{
    /**
     * 中国大陆手机号的锚定全串匹配正则，供各处统一引用。
     */
    public const PHONE_PATTERN = '/^1[3-9]\d{9}$/';

    public static function detectType(?string $value): ?string
    {
        $email = self::normalizeEmail($value);
        if (self::isValidEmail($email)) {
            return 'email';
        }

        $phone = self::normalizePhone($value);
        if ($phone !== '' && preg_match(self::PHONE_PATTERN, $phone) === 1) {
            return 'phone';
        }

        return null;
    }

    /**
     * 邮箱地址是否可投递。
     *
     * 收件人合法性判断散落在服务、传输层、请求校验多处，各自写一份
     * filter_var 很容易漏（管理员收件人解析就漏了两处）。
     * 统一从这里取，新增发送入口时不必重新判断一遍。
     */
    public static function isValidEmail(?string $value): bool
    {
        $email = trim((string) $value);

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function normalizeAccount(?string $value): string
    {
        return match (self::detectType($value)) {
            'email' => self::normalizeEmail($value),
            'phone' => self::normalizePhone($value),
            default => trim((string) $value),
        };
    }

    public static function normalizeEmail(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    public static function normalizeOptionalEmail(?string $value): ?string
    {
        $email = self::normalizeEmail($value);

        return $email !== '' ? $email : null;
    }

    public static function normalizePhone(?string $value): string
    {
        $phone = preg_replace('/\D+/', '', trim((string) $value)) ?? '';

        if (str_starts_with($phone, '86') && strlen($phone) === 13) {
            $phone = substr($phone, 2);
        }

        return $phone;
    }

    public static function normalizeOptionalPhone(?string $value): ?string
    {
        $phone = self::normalizePhone($value);

        return $phone !== '' ? $phone : null;
    }
}
