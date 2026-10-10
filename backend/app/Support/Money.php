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

    /**
     * 金额转整数分（half-up，与 round()/number_format() 既有口径逐字节一致）。
     *
     * 整数分收敛（V2-10）的入口：资金链路以分为单位做加减乘与比较，避免 float
     * 中间值误差；仅在边界处做 分 ↔ 元 转换，DB/API 落点仍是两位小数。
     */
    public static function toCents(mixed $value): int
    {
        return (int) round(self::round($value) * 100);
    }

    /**
     * 整数分转元（保持 2 位小数的 float 表示，与既有金额列/签名一致）。
     */
    public static function fromCents(int $cents): float
    {
        return round($cents / 100, self::SCALE);
    }

    /**
     * 以分为单位比较两个金额：前者小于/等于/大于后者分别返回 -1 / 0 / 1。
     */
    public static function compare(mixed $a, mixed $b): int
    {
        return self::toCents($a) <=> self::toCents($b);
    }

    /**
     * 以分为单位判断金额相等（0.1 + 0.2 与 0.3 视为相等）。
     */
    public static function equals(mixed $a, mixed $b): bool
    {
        return self::compare($a, $b) === 0;
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
