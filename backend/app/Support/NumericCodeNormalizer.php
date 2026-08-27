<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 六位数字验证码归一化小工具。
 *
 * 收敛 aliyun/stay33/demo 短信插件与邮件插件测试流程中逐字重复的兜底逻辑：
 * 输入已是六位数字则原样返回，否则生成六位随机码（历史实现均以字符串返回，保持一致）。
 */
final class NumericCodeNormalizer
{
    public static function normalizeSixDigit(string $code): string
    {
        $code = trim($code);

        return preg_match('/^\d{6}$/', $code) === 1 ? $code : (string) random_int(100000, 999999);
    }
}
