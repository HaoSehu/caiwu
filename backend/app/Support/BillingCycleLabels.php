<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 计费周期英文键 → 中文展示标签的唯一映射。
 *
 * AdminOrderNotificationService / BillingAutomationService / CheckoutService 的
 * 邮件变量 billing_cycle_label 共用此来源，避免多处文案漂移。
 */
final class BillingCycleLabels
{
    private const MAP = [
        'monthly' => '月付',
        'quarterly' => '季付',
        'semiannually' => '半年付',
        'annually' => '年付',
        'biennially' => '两年付',
        'triennially' => '三年付',
        'one_time' => '一次性',
        'onetime' => '一次性',
    ];

    public static function label(string $cycle): string
    {
        return self::MAP[$cycle] ?? ($cycle !== '' ? $cycle : '-');
    }
}
