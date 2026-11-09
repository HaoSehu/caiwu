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
        // 会话类凭据（2026-11-09）：上游供应商/第三方控制台的登录态 cookie 与 session token
        // 等价于账号密码（如 web_session_cookie 可直接接管供应商控制台），必须同等级剔除。
        'cookie',
        'cookies',
        'session',
        'session_id',
        'session_token',
        'web_session_cookie',
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

    /** 验证码掩码占位（M-50）：日志只需证明「发过码」，不能保留可登录的明文。 */
    public const VERIFY_CODE_MASK = '******';

    /** 验证码族参数键（小写比较），用于短信正文/参数与读回侧的历史数据兜底掩码。 */
    private const VERIFY_CODE_KEYS = [
        'code',
        'verification_code',
        'verify_code',
        'email_code',
        'sms_code',
    ];

    public static function redact(array $params, string $module = ''): array
    {
        $keys = self::SENSITIVE_KEYS;
        if (trim($module) === 'auth') {
            $keys = array_merge($keys, self::AUTH_ONLY_KEYS);
        }

        return self::walk($params, $keys);
    }

    /**
     * 插件运行日志（payload/context）的凭据递归掩码（M-44）。
     *
     * 与 redact() 的整体替换不同，运行日志需要保留定位线索：凭据键的字符串值
     * 保留前 4 位与后 4 位、中间打码；其余业务字段原样保留。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function maskCredentialFields(array $data): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            if (self::isCredentialFieldKey((string) $key)) {
                $masked[$key] = match (true) {
                    is_string($value) && $value !== '' => self::maskKeepingEnds($value),
                    is_array($value) => self::maskCredentialFields($value),
                    default => $value,
                };

                continue;
            }

            $masked[$key] = is_array($value) ? self::maskCredentialFields($value) : $value;
        }

        return $masked;
    }

    /**
     * 把参数里的验证码族键值替换为掩码，其余参数原样保留（M-50 写库/读回共用）。
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function maskVerifyCodeParams(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_scalar($value) && (string) $value !== '' && self::isVerifyCodeKey((string) $key)) {
                $params[$key] = self::VERIFY_CODE_MASK;
            }
        }

        return $params;
    }

    /**
     * 落库前按「本次验证码」字面值替换正文（M-50）：验证码可能被插件模板包在
     * 任意文案里，只有拿到实际值才能保证正文不留明文。
     *
     * @param  array<string, mixed>  $params
     */
    public static function maskVerifyCodeValues(string $text, array $params): string
    {
        $values = [];

        foreach ($params as $key => $value) {
            if (is_string($value) && $value !== '' && self::isVerifyCodeKey((string) $key)) {
                $values[] = $value;
            }
        }

        foreach (array_unique($values) as $value) {
            // 纯数字验证码按数字边界替换，避免误伤正文里恰好包含同一数字片段的其他数值
            // （金额、时长、订单号）；其他形态按字面替换。
            $pattern = preg_match('/^\d+$/', $value) === 1
                ? '/(?<!\d)'.preg_quote($value, '/').'(?!\d)/u'
                : '/'.preg_quote($value, '/').'/u';
            // 非法 UTF-8 等极端输入下 preg_replace 返回 null，保留原文而不是清空正文。
            $text = preg_replace($pattern, self::VERIFY_CODE_MASK, $text) ?? $text;
        }

        return $text;
    }

    /**
     * 读回侧的历史数据兜底（M-50）：正文里「验证码」上下文中的 6 位数字替换为掩码。
     * 只认数字边界，不碰模板里的金额、时长等其他数值。
     */
    public static function maskVerifyCodeText(string $text): string
    {
        return preg_replace('/(验证码\D{0,20}?)(\d{6})(?!\d)/u', '$1'.self::VERIFY_CODE_MASK, $text) ?? $text;
    }

    private static function isVerifyCodeKey(string $key): bool
    {
        return in_array(strtolower(trim($key)), self::VERIFY_CODE_KEYS, true);
    }

    private static function isCredentialFieldKey(string $key): bool
    {
        if (self::isSensitiveKey($key, self::SENSITIVE_KEYS)) {
            return true;
        }

        // token 在既有名单里只是固定全名（access_token 等），jwt_token / token_value
        // 这类自定义命名靠段词根兜底；仍按整段匹配，max_tokens 一类业务字段不误伤。
        return in_array('token', explode('_', strtolower(trim($key))), true);
    }

    /**
     * 保留前 4 位与后 4 位、中间固定打码；不足 8 位时首尾窗口已覆盖全文，整体打码。
     */
    private static function maskKeepingEnds(string $value): string
    {
        if (mb_strlen($value) <= 8) {
            return str_repeat('*', 8);
        }

        return mb_substr($value, 0, 4).str_repeat('*', 8).mb_substr($value, -4);
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

        if (in_array($lastSegment, ['key', 'pem', 'cert', 'cookie'], true)) {
            return true;
        }

        foreach ($segments as $segment) {
            if (in_array($segment, ['secret', 'password', 'session'], true)) {
                return true;
            }
        }

        return false;
    }
}
