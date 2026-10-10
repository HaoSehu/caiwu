<?php

namespace App\Constants;

class InvoiceStatus
{
    const UNPAID = 0; // 待支付

    const PAID = 1; // 已支付

    /**
     * 派生展示状态「部分已付」：0 < paid_amount < amount 时由 resolveInvoiceDisplayStatus 派生。
     *
     * 注意：该值仅用于展示派生，**不落库**（invoices.status 值域仍为 0/1/4/5），
     * 也不进入 $labels（raw_status_label 只反映真实库内状态），故此处不写入 $labels。
     */
    const PARTIAL_PAID = 2; // 部分已付（派生展示，不落库）

    const CANCELLED = 4; // 已取消

    const REFUNDED = 5; // 已退款

    public static array $labels = [
        self::UNPAID => '待支付',
        self::PAID => '已支付',
        self::CANCELLED => '已取消',
        self::REFUNDED => '已退款',
    ];

    /**
     * 展示态标签：在落库状态标签基础上补充派生展示状态（部分已付）。
     *
     * @return array<int, string>
     */
    public static function displayLabels(): array
    {
        return self::$labels + [self::PARTIAL_PAID => '部分已付'];
    }
}
