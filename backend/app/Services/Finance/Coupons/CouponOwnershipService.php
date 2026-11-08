<?php

declare(strict_types=1);

namespace App\Services\Finance\Coupons;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\ServiceStatus;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\UserCoupon;

/**
 * 优惠券持有与核销判定（首单资格、持有产品、待支付/已支付账单、核销计数 SQL 片段）。
 *
 * 从 CouponService 抽出（该类此前 2200+ 行）。全部方法无状态，由 CouponService 直接静态调用。
 */
final class CouponOwnershipService
{
    public static function hasUserPlacedOrder(int $userId): bool
    {
        // 首单资格只认「真实购买且已成交」的账单：充值/补录/扣款等非购买账单不算下过单，
        // UNPAID 未成交也不算，避免首单资格随未付账单的生命周期瞬时翻转。
        // 'normal' 是 createFromOrder 对新购订单的历史落值（InvoiceService match default），
        // 管理员开单/补单成交的真实购买仍是 normal 类型，必须计入。
        return Invoice::query()
            ->where('user_id', $userId)
            ->whereIn('type', [InvoiceType::NEW_PURCHASE, 'normal', InvoiceType::RENEW])
            ->whereIn('status', CouponUsageStatuses::usedInvoiceStatuses())
            ->exists();
    }

    /**
     * 用户名下是否持有任一指定产品的运行中服务（前置持有条件，P2-4）。
     *
     * @param  array<int, int>  $productIds
     */
    public static function userHoldsAnyProduct(int $userId, array $productIds): bool
    {
        if ($userId <= 0 || $productIds === []) {
            return false;
        }

        return Service::query()
            ->where('user_id', $userId)
            ->whereIn('product_id', $productIds)
            ->where('status', ServiceStatus::ACTIVE)
            ->exists();
    }

    /**
     * 该持有券是否已有待支付账单：占用拦截、结账可用券列表、持有券展示状态的共同判定。
     */
    public static function userCouponHasPendingInvoice(int $userCouponId, int $userId): bool
    {
        if ($userCouponId <= 0 || $userId <= 0) {
            return false;
        }

        return Invoice::query()
            ->where('user_id', $userId)
            ->where('user_coupon_id', $userCouponId)
            ->where('status', InvoiceStatus::UNPAID)
            ->exists();
    }

    /**
     * 该持有券是否已有已支付/已退款账单（核销已完成、退款后已消耗，或状态已变但异步同步尚未回写 user_coupon）。
     * 与 userCouponHasPendingInvoice 成对，供占用拦截与展示层使用同一判定。
     */
    public static function userCouponHasUsedInvoice(int $userCouponId, int $userId): bool
    {
        if ($userCouponId <= 0 || $userId <= 0) {
            return false;
        }

        return Invoice::query()
            ->where('user_id', $userId)
            ->where('user_coupon_id', $userCouponId)
            ->whereIn('status', CouponUsageStatuses::usedInvoiceStatuses())
            ->exists();
    }

    /**
     * 用户是否已有未付的首单券订单。
     *
     * hasUserPlacedOrder 只认已成交账单（避免资格随未付账单生命周期翻转），单靠它会让
     * 同一用户用两张不同首单券并占两张未付订单、两张都支付即各享一次首单优惠；
     * 这里只补「未付订单已消耗首单券」这一种并发场景，不改变普通未付订单的判定。
     */
    public static function hasPendingFirstOrderCouponUsage(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return Invoice::query()
            ->where('user_id', $userId)
            ->where('status', InvoiceStatus::UNPAID)
            ->whereNotNull('coupon_id')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('coupons')
                    ->whereColumn('coupons.id', 'invoices.coupon_id')
                    ->whereNull('coupons.deleted_at')
                    ->where('coupons.first_order_only', 1);
            })
            ->exists();
    }

    public static function countCouponUsedInvoices(int $couponId): int
    {
        return (int) Invoice::query()
            ->where('coupon_id', $couponId)
            ->whereIn('status', CouponUsageStatuses::usedInvoiceStatuses())
            ->count();
    }

    public static function resolveUserCouponLastUsedAt(int $userCouponId): mixed
    {
        $order = Invoice::query()
            ->select(['paid_at', 'updated_at'])
            ->where('user_coupon_id', $userCouponId)
            ->whereIn('status', CouponUsageStatuses::usedInvoiceStatuses())
            ->orderByDesc('paid_at')
            ->orderByDesc('updated_at')
            ->first();

        if (! $order instanceof Invoice) {
            return null;
        }

        return $order->paid_at ?: $order->updated_at;
    }

    public static function shouldKeepFirstOrderUserCouponUsed(UserCoupon $userCoupon): bool
    {
        return (bool) ($userCoupon->coupon?->first_order_only ?? false)
            && $userCoupon->used_at !== null;
    }

    public static function ownedCouponUsageCountSql(): string
    {
        return 'SELECT COUNT(*) FROM invoices WHERE invoices.user_id = ? AND invoices.coupon_id = user_coupons.coupon_id AND invoices.status != ?';
    }

    public static function publicCouponClaimCountSql(): string
    {
        return 'SELECT COUNT(*) FROM user_coupons WHERE user_coupons.coupon_id = coupons.id';
    }
}
