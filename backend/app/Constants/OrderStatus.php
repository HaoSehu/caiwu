<?php

namespace App\Constants;

class OrderStatus
{
    const PENDING = 0; // 待支付

    const PAID = 1; // 已支付

    const PROCESSING = 2; // 已支付（内部：开通中）

    const COMPLETED = 3; // 已支付（内部：已完成）

    const CANCELLED = 4; // 已取消

    const REFUNDED = 5; // 已退款

    public static array $labels = [
        self::PENDING => '待支付',
        self::PAID => '已支付',
        self::PROCESSING => '已支付',
        self::COMPLETED => '已支付',
        self::CANCELLED => '已取消',
        self::REFUNDED => '已退款',
    ];

    /**
     * 对外仅 4 态：选中"已支付"时一并匹配开通中/已完成内部子状态。
     *
     * @return list<int>
     */
    public static function filterValues(int $status): array
    {
        return $status === self::PAID
            ? [self::PAID, self::PROCESSING, self::COMPLETED]
            : [$status];
    }
}
