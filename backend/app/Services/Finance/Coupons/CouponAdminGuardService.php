<?php

declare(strict_types=1);

namespace App\Services\Finance\Coupons;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\UserCoupon;
use App\Services\Finance\CouponLabelFormatter;
use App\Support\SchemaMetadataCache;

/**
 * 优惠券管理端守卫：可改/可删判定、锁定字段、表结构可用性。
 *
 * 从 CouponService 抽出（该类此前 2200+ 行）。全部方法无状态，由 CouponService 直接静态调用。
 */
final class CouponAdminGuardService
{
    public static function loadCouponForAdmin(?Coupon $coupon): Coupon
    {
        throw_if(! $coupon, new BusinessException('优惠券不存在或已删除'));

        return $coupon
            // D1B-03：详情不再全量加载 userCoupons 关系，user_ids 由 resolveGrantUserIdsForCoupon 按需单列获取
            ->loadMissing(['couponCampaign:id,name'])
            ->loadCount([
                'orders',
                'userCoupons',
                'orders as used_orders_count' => fn ($query) => $query->whereIn('status', CouponUsageStatuses::usedOrderStatuses()),
                'invoices as used_invoices_count' => fn ($query) => $query->whereIn('status', CouponUsageStatuses::usedInvoiceStatuses()),
                'userCoupons as used_user_coupons_count' => fn ($query) => $query->whereNotNull('last_used_at'),
                'orders as deletion_blocking_orders_count' => fn ($query) => $query->where('status', '!=', OrderStatus::CANCELLED),
                'invoices as deletion_blocking_invoices_count' => fn ($query) => $query->where('status', '!=', InvoiceStatus::CANCELLED),
            ]);
    }

    public static function assertCouponCanBeUpdated(Coupon $coupon, array $payload = []): void
    {
        $reason = self::resolveCouponLockReason($coupon);

        if ($reason === '') {
            return;
        }

        if ($payload === []) {
            return;
        }

        foreach (self::resolveCouponLockedFields($coupon) as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            $currentValue = $coupon->{$field} ?? null;
            $newValue = $payload[$field];

            if ($field === 'product_ids') {
                $currentValue = CouponNormalizer::normalizeProductIds((array) ($currentValue ?? []));
                $newValue = CouponNormalizer::normalizeProductIds((array) ($newValue ?? []));
            } elseif ($field === 'billing_cycles') {
                $currentValue = CouponLabelFormatter::normalizeBillingCycles((array) ($currentValue ?? []));
                $newValue = CouponLabelFormatter::normalizeBillingCycles((array) ($newValue ?? []));
            } elseif (in_array($field, ['discount_value', 'min_amount', 'max_discount_amount'], true)) {
                $currentValue = round((float) ($currentValue ?? 0), 2);
                $newValue = round((float) ($newValue ?? 0), 2);
            } elseif ($field === 'first_order_only') {
                $currentValue = (bool) $currentValue;
                $newValue = (bool) $newValue;
            }

            if ($currentValue != $newValue) {
                throw new BusinessException("{$reason}，无法修改「{$field}」");
            }
        }
    }

    public static function couponCanBeUpdatedForAdmin(Coupon $coupon): bool
    {
        return true;
    }

    public static function couponCanBeDeletedForAdmin(Coupon $coupon): bool
    {
        return ! self::couponHasRecordedUsage($coupon)
            && ! self::couponHasDeletionBlockingReferences($coupon);
    }

    public static function resolveCouponLockReason(Coupon $coupon): string
    {
        if ((int) ($coupon->coupon_campaign_id ?? 0) > 0) {
            return '活动生成的优惠券';
        }

        if (self::couponHasIssuedRecords($coupon)) {
            return '已发放的优惠券';
        }

        return '';
    }

    /**
     * @return array<string>
     */
    public static function resolveCouponLockedFields(Coupon $coupon): array
    {
        $isLocked = self::resolveCouponLockReason($coupon) !== '';

        if (! $isLocked) {
            return [];
        }

        return [
            'distribution_type',
            'discount_type',
            'discount_scope',
        ];
    }

    public static function resolveCouponDeleteBlockReason(Coupon $coupon): string
    {
        if (self::couponHasRecordedUsage($coupon)) {
            return '该优惠券已有人使用，不能删除';
        }

        if (self::couponHasDeletionBlockingReferences($coupon)) {
            return '该优惠券仍有关联订单或账单，不能删除';
        }

        return '';
    }

    public static function couponHasIssuedRecords(Coupon $coupon): bool
    {
        $count = self::loadedCountValue($coupon, 'user_coupons_count');
        if ($count !== null) {
            return $count > 0;
        }

        return self::hasUserCouponsTable()
            && UserCoupon::query()->where('coupon_id', (int) $coupon->id)->exists();
    }

    public static function couponHasRecordedUsage(Coupon $coupon): bool
    {
        if ((int) ($coupon->used_count ?? 0) > 0) {
            return true;
        }

        $usedOrdersCount = self::loadedCountValue($coupon, 'used_orders_count');
        $usedInvoicesCount = self::loadedCountValue($coupon, 'used_invoices_count');
        $usedUserCouponsCount = self::loadedCountValue($coupon, 'used_user_coupons_count');
        if ($usedOrdersCount !== null && $usedInvoicesCount !== null && $usedUserCouponsCount !== null) {
            return ($usedOrdersCount + $usedInvoicesCount + $usedUserCouponsCount) > 0;
        }

        $couponId = (int) $coupon->id;

        if ($couponId <= 0) {
            return false;
        }

        if (self::hasUserCouponsTable()
            && UserCoupon::query()->where('coupon_id', $couponId)->whereNotNull('last_used_at')->exists()
        ) {
            return true;
        }

        return Order::query()
            ->where('coupon_id', $couponId)
            ->whereIn('status', CouponUsageStatuses::usedOrderStatuses())
            ->exists()
            || Invoice::query()
                ->where('coupon_id', $couponId)
                ->whereIn('status', CouponUsageStatuses::usedInvoiceStatuses())
                ->exists();
    }

    public static function couponHasDeletionBlockingReferences(Coupon $coupon, bool $forUpdate = false): bool
    {
        $blockingOrdersCount = self::loadedCountValue($coupon, 'deletion_blocking_orders_count');
        $blockingInvoicesCount = self::loadedCountValue($coupon, 'deletion_blocking_invoices_count');
        if ($blockingOrdersCount !== null && $blockingInvoicesCount !== null) {
            return ($blockingOrdersCount + $blockingInvoicesCount) > 0;
        }

        $couponId = (int) $coupon->id;

        if ($couponId <= 0) {
            return false;
        }

        $orderQuery = Order::query()
            ->where('coupon_id', $couponId)
            ->where('status', '!=', OrderStatus::CANCELLED);
        $invoiceQuery = Invoice::query()
            ->where('coupon_id', $couponId)
            ->where('status', '!=', InvoiceStatus::CANCELLED);

        if ($forUpdate) {
            $orderQuery->lockForUpdate();
            $invoiceQuery->lockForUpdate();
        }

        return $orderQuery->exists()
            || $invoiceQuery->exists();
    }

    public static function loadedCountValue(Coupon $coupon, string $attribute): ?int
    {
        if (! array_key_exists($attribute, $coupon->getAttributes())) {
            return null;
        }

        return (int) ($coupon->getAttribute($attribute) ?? 0);
    }

    public static function hasUserCouponsTable(): bool
    {
        // 缓存真源是 SchemaMetadataCache（自带表缓存与 flush()）。此处不再叠加静态变量：
        // 迁移建表、测试换库后 flush() 只清前者，本类的静态副本会永久钉住旧结论。
        return SchemaMetadataCache::hasTable('user_coupons');
    }

    public static function hasCouponsTable(): bool
    {
        return SchemaMetadataCache::hasTable('coupons');
    }

    public static function assertCouponsTableAvailable(): void
    {
        throw_if(! self::hasCouponsTable(), new BusinessException('当前系统未启用优惠券功能'));
    }
}
