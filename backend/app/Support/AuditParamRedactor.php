<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 审计日志落库前的凭据剔除：密码、验证码、令牌不是应进审计的展示要素，
 * 失败尝试的凭据同样不允许明文落库。仅做键级剔除，其余业务字段原样保留
 * （管理端审计仍需完整业务信息，与"日志不脱敏"规定不冲突）。
 */
final class AuditParamRedactor
{
    private const REDACTED_VALUE = '[REDACTED]';

    /** 全模块通用：密码族与令牌族凭据键（匹配前统一转小写）。 */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'old_password',
        'oldpassword',
        'new_password',
        'newpassword',
        'new_password_confirmation',
        'newpassword_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'api_token',
        'authorization',
    ];

    /** 仅认证模块：验证码/行为验证码；业务模块的 code 是编码类业务标识，不剔除。 */
    private const AUTH_ONLY_KEYS = [
        'code',
        'verification_code',
        'verify_code',
        'email_code',
        'sms_code',
        'captcha',
    ];

    public static function redact(array $params, string $module = ''): array
    {
        $keys = self::SENSITIVE_KEYS;
        if (trim($module) === 'auth') {
            $keys = array_merge($keys, self::AUTH_ONLY_KEYS);
        }

        return self::walk($params, $keys);
    }

    private static function walk(array $params, array $keys): array
    {
        $redacted = [];

        foreach ($params as $key => $value) {
            if (in_array(strtolower((string) $key), $keys, true)) {
                $redacted[$key] = self::REDACTED_VALUE;

                continue;
            }

            $redacted[$key] = is_array($value) ? self::walk($value, $keys) : $value;
        }

        return $redacted;
    }
}
