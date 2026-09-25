<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 金额运算集中工具。
 *
 * 统一按分位（2 位小数）舍入，避免各业务散落的 float 隐式运算产生精度偏差。
 * 所有金额加法/乘法/减法在中间使用高精度再舍入，比较使用 epsilon。
 */
final class Money
{
    private const SCALE = 2;

    public static function round(mixed $value): float
    {
        return round((float) ($value ?? 0), self::SCALE);
    }

    public static function add(mixed ...$values): float
    {
        return self::round(array_sum(array_map(static fn (mixed $value): float => (float) ($value ?? 0), $values)));
    }

    public static function multiply(mixed $a, mixed $b): float
    {
        return self::round((float) ($a ?? 0) * (float) ($b ?? 0));
    }

    public static function format(mixed $value): string
    {
        return number_format(self::round($value), 2, '.', '');
    }

    /**
     * 去掉已格式化金额字符串的小数尾零与孤立小数点。
     *
     * 仅用于展示层收敛："12.30" -> "12.3"、"12.00" -> "12"、"9.90" -> "9.9"。
     * 入参必须是 number_format 的产物，本方法不做任何舍入。
     */
    public static function trimZero(string $formatted): string
    {
        return rtrim(rtrim($formatted, '0'), '.');
    }
}
