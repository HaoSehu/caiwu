<?php

declare(strict_types=1);

namespace App\Support\Security;

use InvalidArgumentException;

/**
 * RFC 6238 TOTP（基于时间的一次性密码）实现。
 *
 * 刻意不引入第三方依赖：算法本身只有「Base32 解码 + HMAC-SHA1 + 动态截断」
 * 三步，引一个包反而增加供应链面。参数与主流认证器一致：
 * SHA-1、30 秒步长、6 位数字、Base32 密钥（RFC 4648，无填充字符）。
 *
 * 校验容忍前后各一个步长（±30 秒），抵消客户端时钟漂移；
 * 攻击者命中窗口是 3 个候选值而非 1 个，配合登录失败锁定（5 次/30 分钟）
 * 后暴力枚举成功率可以忽略。
 */
class TotpService
{
    public const PERIOD_SECONDS = 30;

    public const DIGITS = 6;

    /** 密钥字节数：160 bit，与 RFC 4226 推荐一致。 */
    public const SECRET_BYTES = 20;

    /** 校验窗口：前后各 1 步。 */
    public const DEFAULT_WINDOW = 1;

    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * 生成新的 Base32 密钥（32 字符，可直接手工录入认证器）。
     */
    public function generateSecret(): string
    {
        return self::base32Encode(random_bytes(self::SECRET_BYTES));
    }

    /**
     * 计算指定时间片的动态验证码。
     *
     * @param  int|null  $timestamp  Unix 时间戳，空表示当前时间（测试可注入）
     */
    public function code(string $secret, ?int $timestamp = null): string
    {
        $key = self::base32Decode($secret);
        if ($key === '') {
            throw new InvalidArgumentException('TOTP 密钥不能为空');
        }

        $counter = intdiv($timestamp ?? time(), self::PERIOD_SECONDS);
        $hash = hash_hmac('sha1', pack('N*', 0, $counter), $key, true);

        // RFC 4226 动态截断：取末字节低 4 位作为偏移，读取 4 字节并抹掉符号位。
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * 校验动态验证码，容忍前后各 $window 个时间片。
     *
     * 使用 hash_equals 做常量时间比较，避免按位比较泄露时序。
     */
    public function verify(string $secret, string $code, int $window = self::DEFAULT_WINDOW): bool
    {
        $candidate = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($candidate) !== self::DIGITS) {
            return false;
        }

        $now = time();
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->code($secret, $now + $offset * self::PERIOD_SECONDS), $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 生成认证器可识别的 otpauth:// 链接。
     *
     * 管理端不内置二维码渲染依赖，直接把密钥与链接以明文展示，
     * 由管理员手工录入或粘贴到认证器。
     */
    public function provisioningUri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($account);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD_SECONDS,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/'.$label.'?'.$query;
    }

    public static function base32Encode(string $bytes): string
    {
        $encoded = '';
        $buffer = 0;
        $bits = 0;

        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::BASE32_ALPHABET[($buffer >> $bits) & 0x1F];
            }
        }

        if ($bits > 0) {
            $encoded .= self::BASE32_ALPHABET[($buffer << (5 - $bits)) & 0x1F];
        }

        return $encoded;
    }

    public static function base32Decode(string $encoded): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $encoded) ?? '');
        if ($normalized === '') {
            return '';
        }

        $decoded = '';
        $buffer = 0;
        $bits = 0;

        $length = strlen($normalized);
        for ($i = 0; $i < $length; $i++) {
            $index = strpos(self::BASE32_ALPHABET, $normalized[$i]);
            if ($index === false) {
                throw new InvalidArgumentException('TOTP 密钥包含非法 Base32 字符');
            }

            $buffer = ($buffer << 5) | $index;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($buffer >> $bits) & 0xFF);
            }
        }

        return $decoded;
    }
}
