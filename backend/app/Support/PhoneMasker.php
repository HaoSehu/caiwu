<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 手机号掩码小工具。
 *
 * 收敛 stay33 与 aliyun 短信客户端中逐字重复的掩码实现：
 * 长度超过 7 位时保留前 3 位与后 4 位，中间固定四颗星（如 138****1234），
 * 输出与原插件内部实现逐字节一致。
 */
final class PhoneMasker
{
    public static function mask(string $phone): string
    {
        if (mb_strlen($phone) <= 7) {
            return $phone;
        }

        return mb_substr($phone, 0, 3).'****'.mb_substr($phone, -4);
    }
}
