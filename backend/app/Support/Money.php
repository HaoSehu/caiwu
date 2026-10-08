<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Invoice;

/**
 * 金额运算集中工具。
 *
 * 统一按分位（2 位小数）舍入，避免各业务散落的 float 隐式运算产生精度偏差。
 * 所有金额加法/乘法/减法在中间使用高精度再舍入，比较使用 epsilon。
 */
final class Money
{
    private const SCALE = 2;

    /**
     * 从账单还原目录价（应收基数）。
     *
     * 唯一还原口径：目录价 = 应付价 + 优惠券减免 + 会员折扣减免。
     * 账单是资金域唯一真源，目录价还原只接受账单（方案 2 职责重划：
     * 订单金额列降级为创建时快照与生命周期投影，不再参与资金还原）。
     * services.amount 与 locked_pricing 只允许写入本方法的结果，
     * 直接写入 amount（折后价）会让续费对折后价二次打折，产生 0.75^n 式复利衰减。
     */
    public static function catalogAmountOf(Invoice $invoice): float
    {
        return self::add($invoice->amount, $invoice->discount, $invoice->member_discount_amount);
    }

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
