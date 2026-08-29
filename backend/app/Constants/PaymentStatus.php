<?php

namespace App\Constants;

class PaymentStatus
{
    const PENDING = 0; // 待支付

    const SUCCESS = 1; // 已支付

    const REFUNDED = 5; // 已退款

    const CANCELLED = 4; // 已取消

    public static array $labels = [
        self::PENDING => '待支付',
        self::SUCCESS => '已支付',
        self::REFUNDED => '已退款',
        self::CANCELLED => '已取消',
    ];
}
