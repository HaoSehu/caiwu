<?php

declare(strict_types=1);

namespace App\Services\Finance\Coupons;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;

/**
 * 优惠券核销的状态口径单点。
 *
 * 此前 CouponService / CouponOwnershipService / CouponAdminGuardService 各自定义
 * 一份同名常量，三份内容一致但相互独立——任何一处调整「什么算已消耗」都会漏改，
 * 导致「列表显示可复用、下单时却被拦截」这类矛盾。收敛到这里。
 */
final class CouponUsageStatuses
{
    private function __construct() {}

    /**
     * 视为「已消耗」的账单状态：已支付 = 核销完成；已退款 = 全额退款落地后券不返还
     * （防「购-付-退」循环薅券，取消路径不受影响仍回落可复用）。
     * 占用拦截、展示状态、核销计数（used_count）、删除拦截共用本判定。
     *
     * @return list<int>
     */
    public static function usedInvoiceStatuses(): array
    {
        return [
            InvoiceStatus::PAID,
            InvoiceStatus::REFUNDED,
        ];
    }

    /**
     * 视为「已消耗」的订单状态：与 usedInvoiceStatuses() 同口径
     * （券按账单核销，订单侧只做汇总展示）。
     *
     * @return list<int>
     */
    public static function usedOrderStatuses(): array
    {
        return [
            OrderStatus::PAID,
            OrderStatus::REFUNDED,
        ];
    }

    /**
     * 使持有券不可再次使用的账单状态：待支付 = 结算占用（挂账）；已支付 = 已核销，
     * 或已核销但异步同步尚未完成；已退款 = 退款后券已消耗（同步未完成的窗口内同样拦截）。
     * 列表排除、分组归属、展示状态、占用拦截四处共用。
     *
     * @return list<int>
     */
    public static function blockingInvoiceStatuses(): array
    {
        return [
            InvoiceStatus::UNPAID,
            InvoiceStatus::PAID,
            InvoiceStatus::REFUNDED,
        ];
    }
}
