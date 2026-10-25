<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Coupon;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * 优惠券展示与归一化的纯函数集合。
 *
 * 从 CouponService 抽出的无状态逻辑：优惠券文案、周期/商品范围展示、
 * 以及入参归一化。不依赖容器、数据库或任何实例状态，因此全部为静态方法。
 */
final class CouponLabelFormatter
{
    private const SUPPORTED_BILLING_CYCLE_LABELS = [
        'monthly' => '月付',
        'quarterly' => '季付',
        'semiannually' => '半年付',
        'annually' => '年付',
    ];

    public static function buildDiscountLabel(Coupon $coupon): string
    {
        $discountType = trim((string) $coupon->discount_type);
        $discountValue = (float) ($coupon->discount_value ?? 0);

        if ($discountType === 'fixed') {
            return '立减 ¥'.Money::format($discountValue);
        }

        if ($discountType === 'percentage') {
            return Money::trimZero(number_format($discountValue / 10, 1, '.', '')).' 折优惠';
        }

        if ($discountType === 'override') {
            return '一口价 ¥'.Money::format($discountValue);
        }

        return '优惠券';
    }

    public static function resolveDiscountTypeLabel(string $discountType): string
    {
        return match ($discountType) {
            'fixed' => '满减券',
            'percentage' => '折扣券',
            'override' => '覆盖价券',
            default => '优惠券',
        };
    }

    public static function resolveDistributionTypeLabel(string $distributionType): string
    {
        return match ($distributionType) {
            'private' => '私有优惠券',
            default => '公开优惠券',
        };
    }

    public static function resolveDiscountScopeLabel(string $discountScope): string
    {
        return match ($discountScope) {
            'recurring' => '持续优惠',
            'renew' => '续费优惠',
            // first_month 实际语义是「新购专属」（对年付等整单打折，与自然月无关），标签如实描述
            default => '新购优惠',
        };
    }

    public static function resolveReceiveTypeLabel(string $receiveType): string
    {
        return match ($receiveType) {
            'grant' => '系统直发',
            default => '手动领取',
        };
    }

    public static function isCouponScopeAllowedForOrderType(string $discountScope, string $orderType): bool
    {
        $normalizedOrderType = trim($orderType) === 'renew' ? 'renew' : 'new';

        return match ($discountScope) {
            'renew' => $normalizedOrderType === 'renew',
            'recurring' => in_array($normalizedOrderType, ['new', 'renew'], true),
            default => $normalizedOrderType === 'new',
        };
    }

    public static function formatBillingCycleText(array $billingCycles): string
    {
        if (! self::hasConfiguredBillingCycles($billingCycles)) {
            return '全部周期可用';
        }

        $normalizedBillingCycles = self::normalizeBillingCycles($billingCycles);
        if ($normalizedBillingCycles === []) {
            return '未配置可用周期';
        }

        return collect($normalizedBillingCycles)
            ->map(fn (string $cycle) => self::SUPPORTED_BILLING_CYCLE_LABELS[$cycle])
            ->implode(' / ');
    }

    public static function formatProductScopeText(array $productIds, array $productNameMap): string
    {
        if ($productIds === []) {
            return '全站商品可用';
        }

        $productNames = array_values(array_filter(array_map(
            fn (int $productId) => $productNameMap[$productId] ?? '',
            $productIds
        )));

        if ($productNames === []) {
            return '指定商品可用';
        }

        return implode(' / ', $productNames);
    }

    public static function formatValidityText(Coupon $coupon): string
    {
        // 模型 casts 声明为 datetime，但列类型在静态分析中仍是 string，
        // 统一用 CarbonImmutable::parse 归一化，避免对属性类型做无依据的断言。
        $startsAt = $coupon->starts_at;
        $expiresAt = $coupon->expires_at;
        $start = $startsAt !== null && $startsAt !== ''
            ? CarbonImmutable::parse($startsAt)->format('Y-m-d H:i')
            : null;
        $end = $expiresAt !== null && $expiresAt !== ''
            ? CarbonImmutable::parse($expiresAt)->format('Y-m-d H:i')
            : null;

        if ($start && $end) {
            return $start.' - '.$end;
        }

        if ($end) {
            return '截止至 '.$end;
        }

        if ($start) {
            return '自 '.$start.' 起';
        }

        return '长期有效';
    }

    public static function normalizeBillingCycles(array $billingCycles): array
    {
        return collect($billingCycles)
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn (string $cycle) => $cycle !== '' && array_key_exists($cycle, self::SUPPORTED_BILLING_CYCLE_LABELS))
            ->unique()
            ->values()
            ->all();
    }

    public static function hasConfiguredBillingCycles(array $billingCycles): bool
    {
        return collect($billingCycles)
            ->map(fn ($item) => trim((string) $item))
            ->contains(fn (string $cycle) => $cycle !== '');
    }

    public static function normalizePositiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (int) $value;

        return $number > 0 ? $number : null;
    }

    public static function normalizeDateTimeValue(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }
}
