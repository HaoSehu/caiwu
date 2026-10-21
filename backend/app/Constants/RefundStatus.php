<?php

declare(strict_types=1);

namespace App\Constants;

use App\Support\Money;

/**
 * 退款状态（派生态，不落库）。
 *
 * invoices.status / orders.status 的落库值域固定为 0/1/4/5（铁律：
 * 历史 6=部分退款 已被 2026_08_29_110000 迁移移除，不得复用）。
 * 「部分退款」表达为账单保持 PAID + refund_amount 累计，由本枚举在
 * VO 层派生（refund_status / refund_status_label），供列表与详情展示。
 */
class RefundStatus
{
    /** 未退款 */
    const NONE = 0;

    /** 部分退款（累计已退 > 0 且未退完，账单/订单仍 PAID） */
    const PARTIAL = 1;

    /** 已全额退款（状态已收口 REFUNDED） */
    const FULL = 2;

    public static array $labels = [
        self::NONE => '未退款',
        self::PARTIAL => '部分退款',
        self::FULL => '已全额退款',
    ];

    public static function label(int $status): string
    {
        return self::$labels[$status] ?? (string) $status;
    }

    /**
     * 由累计退款额与实收基数派生退款状态。
     *
     * @param  float  $refundedTotal  累计已完成退款金额（refunds 表口径）
     * @param  float  $paidBase  实收基数（paid_amount，缺失时退化为 amount）
     */
    public static function derive(float $refundedTotal, float $paidBase): int
    {
        if (Money::lessThanOrEqual($refundedTotal, 0)) {
            return self::NONE;
        }

        return Money::greaterThan($paidBase, $refundedTotal) ? self::PARTIAL : self::FULL;
    }
}
