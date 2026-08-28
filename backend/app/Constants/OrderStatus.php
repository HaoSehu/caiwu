<?php

namespace App\Constants;

class OrderStatus
{
    const PENDING = 0; // 待支付

    const PAID = 1; // 已支付

    const CANCELLED = 4; // 已取消

    const REFUNDED = 5; // 已退款

    public static array $labels = [
        self::PENDING => '待支付',
        self::PAID => '已支付',
        self::CANCELLED => '已取消',
        self::REFUNDED => '已退款',
    ];
}
