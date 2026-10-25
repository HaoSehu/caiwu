<?php

declare(strict_types=1);

namespace App\Services\Finance\Concerns;

use App\Constants\CouponStatus;
use App\Constants\UserCouponStatus;
use App\Models\Coupon;
use App\Models\UserCoupon;
use Carbon\CarbonImmutable;

/**
 * 优惠券状态判定（管理端展示状态 / 用户持有券状态 / 公开可领取状态）。
 *
 * 归属说明：三处判定共享同一套「有效期 + 占用 + 用量」语义，
 * 与券的增删改、折扣计算无关，抽成 trait 以保证三端展示口径一致。
 */
trait ResolvesCouponDisplayStatus
{
    private function resolveAdminDisplayStatus(Coupon $coupon): array
    {
        $now = now();

        if ((int) $coupon->status !== CouponStatus::ACTIVE) {
            return ['status' => 'disabled', 'label' => '已停用', 'reason' => '管理员已手动停用'];
        }

        if ($coupon->starts_at && $coupon->starts_at->gt($now)) {
            return ['status' => 'pending', 'label' => '未开始', 'reason' => '未到生效时间'];
        }

        if ($coupon->expires_at && $coupon->expires_at->lt($now)) {
            return ['status' => 'expired', 'label' => '已过期', 'reason' => '已超过结束时间'];
        }

        return ['status' => 'active', 'label' => '生效中', 'reason' => '当前可正常使用'];
    }

    private function resolveOwnedCouponStatus(
        Coupon $coupon,
        UserCoupon $userCoupon,
        int $userUsedCount,
        bool $hasPlacedOrder,
        bool $hasPendingInvoice = false,
        bool $hasPaidInvoice = false,
        bool $hasPendingFirstOrderUsage = false,
    ): array {
        $now = now();
        if ((int) $userCoupon->status === UserCouponStatus::USED) {
            return ['status' => 'used_up', 'label' => '已使用', 'reason' => '优惠券已被核销'];
        }

        if ((int) $userCoupon->status === UserCouponStatus::REVOKED) {
            return ['status' => 'expired', 'label' => '已作废', 'reason' => '管理员已作废'];
        }

        if ((string) ($coupon->distribution_type ?? 'public') === 'private'
            && (string) ($userCoupon->receive_type ?? 'claim') !== 'grant') {
            return ['status' => 'expired', 'label' => '已失效', 'reason' => '当前优惠券已改为私有发放'];
        }

        if ((int) $userCoupon->status !== UserCouponStatus::OWNED || (int) $coupon->status !== CouponStatus::ACTIVE) {
            return ['status' => 'expired', 'label' => '已失效', 'reason' => '当前优惠券已停用或失效'];
        }

        if ($coupon->starts_at && $coupon->starts_at->gt($now)) {
            return ['status' => 'expired', 'label' => '未开始', 'reason' => '未到可使用时间'];
        }

        if ($coupon->expires_at && $coupon->expires_at->lt($now)) {
            return ['status' => 'expired', 'label' => '已过期', 'reason' => '有效期已结束'];
        }

        // 已支付但异步核销尚未回写 user_coupon（仍为 OWNED）：按真实状态显示「已使用」，
        // 与占用拦截的「已有已支付账单」兜底同一判定，避免「显示可使用但结账必败」。
        // 置于有效性判定之后：券已失效/过期时仍归「已过期」，与分组归属保持一致。
        if ($hasPaidInvoice) {
            return ['status' => 'used_up', 'label' => '已使用', 'reason' => '优惠券已被核销'];
        }

        // 已占用：同券存在待支付账单或预留仍在窗口内。与结账可用券列表、占用拦截同一判定，
        // 避免「列表显示可使用但结账必败」，也避免该券掉出全部三个分组
        if ($hasPendingInvoice) {
            return ['status' => 'used_up', 'label' => '已占用', 'reason' => '该优惠券已有待支付账单，请先完成支付或取消'];
        }

        $reservationActive = $userCoupon->reserved_until !== null
            && CarbonImmutable::parse((string) $userCoupon->reserved_until)->gte($now);
        if ($reservationActive) {
            return ['status' => 'used_up', 'label' => '已占用', 'reason' => '该优惠券正在结算占用中，请稍后重试'];
        }

        if ($coupon->first_order_only && ($hasPlacedOrder || $hasPendingFirstOrderUsage)) {
            return ['status' => 'used_up', 'label' => '已用完', 'reason' => '仅限首单用户使用'];
        }

        if ($coupon->per_user_limit && $userUsedCount >= (int) $coupon->per_user_limit) {
            return ['status' => 'used_up', 'label' => '已用完', 'reason' => '已达到该优惠券使用上限'];
        }

        return ['status' => 'available', 'label' => '可使用', 'reason' => '满足条件后可直接抵扣'];
    }

    private function resolvePublicCouponStatus(Coupon $coupon, int $claimedCount): array
    {
        $now = now();

        if ((int) $coupon->status !== CouponStatus::ACTIVE) {
            return ['status' => 'expired', 'label' => '已失效', 'reason' => '当前优惠券已停用'];
        }

        if ($coupon->starts_at && $coupon->starts_at->gt($now)) {
            return ['status' => 'expired', 'label' => '未开始', 'reason' => '未到领取时间'];
        }

        if ($coupon->expires_at && $coupon->expires_at->lt($now)) {
            return ['status' => 'expired', 'label' => '已过期', 'reason' => '已超过领取时间'];
        }

        if ($coupon->total_usage_limit && $claimedCount >= (int) $coupon->total_usage_limit) {
            return ['status' => 'used_up', 'label' => '已领完', 'reason' => '公开优惠券已被领取完'];
        }

        return ['status' => 'available', 'label' => '可领取', 'reason' => '领取后会进入你的优惠券账户'];
    }
}
