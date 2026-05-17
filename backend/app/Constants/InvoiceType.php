<?php

namespace App\Constants;

class InvoiceType
{
    const NEW_PURCHASE = 'new';       // 新购

    const RENEW = 'renew';     // 续费

    const RECHARGE = 'recharge';  // 充值

    const DEDUCTION = 'deduction'; // 扣款

    const REFERRAL_CREDIT = 'referral_credit'; // 推荐奖励

    const MANUAL = 'manual';    // 手工账单

    public static array $labels = [
        self::NEW_PURCHASE => '新购',
        self::RENEW => '续费',
        self::RECHARGE => '充值',
        self::DEDUCTION => '扣款',
        self::REFERRAL_CREDIT => '推荐奖励账单',
        self::MANUAL => '手工账单',
    ];

    /** 兼容旧值 normal → new */
    public static function normalize(string $type): string
    {
        return $type === 'normal' ? self::NEW_PURCHASE : $type;
    }

    public static function label(string $type): string
    {
        $type = self::normalize($type);

        return self::$labels[$type] ?? $type;
    }
}
