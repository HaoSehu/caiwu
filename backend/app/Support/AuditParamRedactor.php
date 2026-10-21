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
        // 网关/插件凭据族（2026-10-21 B-07）：私钥、公钥、商户密钥、签名密钥。
        // 网关私钥泄露等于可伪造该商户的一切支付/退款，必须与密码同等级剔除；
        // 仅剔除凭据值，其余业务字段原样保留（不违反「日志不脱敏」）。
        'merchant_private_key',
        'merchant_public_key',
        'platform_private_key',
        'platform_public_key',
        'private_key',
        'public_key',
        'secret_key',
        'secret',
        'app_secret',
        'app_key',
        'api_key',
        'api_secret',
        'sign_key',
        'sign_secret',
        'signing_key',
        'cert_key',
        'cert_pem',
        'key_content',
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
            if (self::isSensitiveKey((string) $key, $keys)) {
                $redacted[$key] = self::REDACTED_VALUE;

                continue;
            }

            $redacted[$key] = is_array($value) ? self::walk($value, $keys) : $value;
        }

        return $redacted;
    }

    /**
     * 命中显式名单，或命中凭据族后缀（*_key / *_secret / *_pem / *_cert）。
     *
     * 后缀规则是配置结构兜底：网关插件配置的键名由插件自定义（alipay_private_key、
     * wx_secret_v3 等），无法穷举；只按后缀认，避免误伤普通业务字段。
     */
    private static function isSensitiveKey(string $key, array $keys): bool
    {
        $normalized = strtolower(trim($key));

        if (in_array($normalized, $keys, true)) {
            return true;
        }

        // 按「下划线段」判定词根，而不是任意子串包含：
        // app_key / sign_pem / cert 命中（末段），wx_secret_v3 / app_secret 命中（任意段）；
        // secretary / keyword / monkey / key_metric 这类业务字段不误伤。
        $segments = explode('_', $normalized);
        $lastSegment = end($segments) ?: '';

        if (in_array($lastSegment, ['key', 'pem', 'cert'], true)) {
            return true;
        }

        foreach ($segments as $segment) {
            if (in_array($segment, ['secret', 'password'], true)) {
                return true;
            }
        }

        return false;
    }
}
