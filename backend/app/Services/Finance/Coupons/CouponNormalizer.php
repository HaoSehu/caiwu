<?php

declare(strict_types=1);

namespace App\Services\Finance\Coupons;

use App\Constants\CouponStatus;
use App\Exceptions\BusinessException;
use App\Models\Coupon;
use App\Services\Finance\CouponLabelFormatter;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * 优惠券归一化与折扣折算的单一真源。
 *
 * 从 CouponService 抽出（该类此前 2200+ 行）。全部方法无状态，静态调用即可；
 * computeDiscountFromSnapshot 是券模板与服务端持续优惠绑定的共同折算口径，
 * CouponService 仍以实例方法转发，外部调用点（如 ServiceRenewService）无需改动。
 */
final class CouponNormalizer
{
    /**
     * 允许的折扣类型。请求层（Store/UpdateCouponRequest）已限制，此处再校验一次：
     * calculateDiscountAmount 对未知类型返回 0，会被判「当前优惠券不可用」，
     * 落库即成永久不可用的死券，故服务层必须与请求层同口径拦截。
     */
    private const DISCOUNT_TYPES = ['fixed', 'percentage', 'override'];

    public static function normalizeAdminCouponPayload(array $payload, array $context = [], ?Coupon $coupon = null): array
    {
        $lockedFields = $coupon ? CouponAdminGuardService::resolveCouponLockedFields($coupon) : [];
        $lockedFieldSet = array_flip($lockedFields);

        // D5B-01：更新路径（$coupon 非空）按「部分更新」语义处理——非必填字段未提交时保持原值，
        // 用 array_key_exists 区分「未提交」与「显式提交空值」，禁止未提交字段静默写默认值；
        // 创建路径（$coupon 为空）保持全量默认值语义。
        $isUpdate = $coupon !== null;

        $discountType = trim((string) ($payload['discount_type'] ?? ''));
        if ($discountType === '' && isset($lockedFieldSet['discount_type']) && $coupon) {
            $discountType = (string) ($coupon->discount_type ?? '');
        }

        $distributionType = trim((string) ($payload['distribution_type'] ?? ''));
        if ($distributionType === '' && isset($lockedFieldSet['distribution_type']) && $coupon) {
            $distributionType = (string) ($coupon->distribution_type ?? 'public');
        }
        if ($distributionType === '') {
            $distributionType = 'public';
        }

        $discountScope = trim((string) ($payload['discount_scope'] ?? ''));
        if ($discountScope === '' && isset($lockedFieldSet['discount_scope']) && $coupon) {
            $discountScope = (string) ($coupon->discount_scope ?? 'first_month');
        }
        if ($discountScope === '') {
            $discountScope = 'first_month';
        }

        $discountValue = round((float) ($payload['discount_value'] ?? 0), 2);
        if ($discountValue <= 0 && isset($lockedFieldSet['discount_value']) && $coupon) {
            $discountValue = round((float) ($coupon->discount_value ?? 0), 2);
        }

        $minAmount = $isUpdate && ! array_key_exists('min_amount', $payload)
            ? $coupon->min_amount
            : round((float) ($payload['min_amount'] ?? 0), 2);

        $maxDiscountAmount = $isUpdate && ! array_key_exists('max_discount_amount', $payload)
            ? $coupon->max_discount_amount
            : ($payload['max_discount_amount'] ?? null);

        $startsAt = $isUpdate && ! array_key_exists('starts_at', $payload)
            ? $coupon->starts_at
            : CouponLabelFormatter::normalizeDateTimeValue($payload['starts_at'] ?? null);

        $expiresAt = $isUpdate && ! array_key_exists('expires_at', $payload)
            ? $coupon->expires_at
            : CouponLabelFormatter::normalizeDateTimeValue($payload['expires_at'] ?? null);

        $providedCode = trim((string) ($payload['code'] ?? ''));
        $code = $providedCode !== '' ? $providedCode : ($coupon?->code ?: self::generateInternalCouponCode());

        throw_if($discountType === '', new BusinessException('优惠类型不能为空'));
        throw_if(! in_array($discountType, self::DISCOUNT_TYPES, true), new BusinessException('优惠类型不正确'));
        throw_if(! in_array($distributionType, ['public', 'private'], true), new BusinessException('发放方式不正确'));
        throw_if(! in_array($discountScope, ['first_month', 'recurring', 'renew'], true), new BusinessException('优惠阶段不正确'));
        throw_if($discountValue <= 0, new BusinessException('优惠值必须大于 0'));

        if ($providedCode !== '') {
            $duplicateCodeQuery = Coupon::query()->where('code', $providedCode);

            if ($coupon?->id) {
                $duplicateCodeQuery->whereKeyNot((int) $coupon->id);
            }

            throw_if($duplicateCodeQuery->exists(), new BusinessException('优惠券编码已存在'));
        }

        if ($discountType === 'percentage') {
            throw_if($discountValue > 100, new BusinessException('折扣百分比不能大于 100'));
        }

        // 覆盖价券：覆盖价即用户实付，最低 0.01（与封顶 amount-0.01 同口径，不产生 0 元账单）
        if ($discountType === 'override') {
            throw_if($discountValue < 0.01, new BusinessException('覆盖价不能小于 0.01'));
        }

        if ($maxDiscountAmount !== null && $maxDiscountAmount !== '') {
            $maxDiscountAmount = round((float) $maxDiscountAmount, 2);
            throw_if($maxDiscountAmount < 0, new BusinessException('最高优惠金额不能小于 0'));
        } else {
            $maxDiscountAmount = null;
        }

        $billingCycles = $isUpdate && ! array_key_exists('billing_cycles', $payload)
            ? CouponLabelFormatter::normalizeBillingCycles((array) ($coupon->billing_cycles ?? []))
            : CouponLabelFormatter::normalizeBillingCycles((array) ($payload['billing_cycles'] ?? []));

        $productIds = $isUpdate && ! array_key_exists('product_ids', $payload)
            ? self::normalizeProductIds((array) ($coupon->product_ids ?? []))
            : self::normalizeProductIds((array) ($payload['product_ids'] ?? []));

        $firstOrderOnly = $isUpdate && ! array_key_exists('first_order_only', $payload)
            ? (bool) $coupon->first_order_only
            : (bool) ($payload['first_order_only'] ?? false);

        $onlyOldClient = $isUpdate && ! array_key_exists('only_old_client', $payload)
            ? (bool) $coupon->only_old_client
            : (bool) ($payload['only_old_client'] ?? false);

        // is_discount 缺省=可叠加（与历史数据/现状口径一致）；显式提交 false 才互斥
        $isDiscount = $isUpdate && ! array_key_exists('is_discount', $payload)
            ? (bool) ($coupon->is_discount ?? true)
            : (bool) ($payload['is_discount'] ?? true);

        // 首单券 × 仅续费 是静默死配置：首单资格要求用户无任何成交订单，而续费必然
        // 发生在成交之后，组合后该券在续费场景永远不可用。创建/更新时直接拦截。
        if ($firstOrderOnly && $discountScope === 'renew') {
            throw new BusinessException('首单券不支持「仅续费」优惠阶段：续费必然发生在首单之后，二者组合的券永远无法使用');
        }

        // 仅新客首单 × 仅老客 是语义互斥：两者用户集合不相交
        if ($firstOrderOnly && $onlyOldClient) {
            throw new BusinessException('「仅首单」与「仅老客」互斥：首单券面向从未成交的用户，仅老客面向已有成交订单的用户');
        }

        $requiresProductIds = $isUpdate && ! array_key_exists('requires_product_ids', $payload)
            ? self::normalizeProductIds((array) ($coupon->requires_product_ids ?? []))
            : self::normalizeProductIds((array) ($payload['requires_product_ids'] ?? []));

        $totalUsageLimit = $isUpdate && ! array_key_exists('total_usage_limit', $payload)
            ? $coupon->total_usage_limit
            : CouponLabelFormatter::normalizePositiveInteger($payload['total_usage_limit'] ?? null);

        $perUserLimit = $isUpdate && ! array_key_exists('per_user_limit', $payload)
            ? $coupon->per_user_limit
            : CouponLabelFormatter::normalizePositiveInteger($payload['per_user_limit'] ?? null);

        // 注意 per_user_limit 的真实语义是「同一张券对该用户的核销次数上限」
        // （见 ownedCouponUsageCountSql 的使用次数口径），不是「可领取张数」：
        // 领取张数由表结构 UK(coupon_id,user_id) 固定为每人 1 张，两者不冲突。
        // 历史文档/表单文案容易混淆，此处显式记录以免后续误判为重复领取限制。

        $status = $isUpdate && ! array_key_exists('status', $payload)
            ? (int) $coupon->status
            : (int) ($payload['status'] ?? CouponStatus::ACTIVE);

        $sortOrder = $isUpdate && ! array_key_exists('sort_order', $payload)
            ? (int) ($coupon->sort_order ?? 0)
            : max((int) ($payload['sort_order'] ?? 0), 0);

        return [
            // D5B-01：活动生成的券不允许经更新接口改绑或脱钩活动，更新路径始终保留原值
            'coupon_campaign_id' => $isUpdate
                ? $coupon->coupon_campaign_id
                : (isset($payload['coupon_campaign_id']) && (int) $payload['coupon_campaign_id'] > 0
                    ? (int) $payload['coupon_campaign_id']
                    : null),
            'name' => trim((string) ($payload['name'] ?? '')),
            'code' => $code,
            'description' => $isUpdate && ! array_key_exists('description', $payload)
                ? $coupon->description
                : (trim((string) ($payload['description'] ?? '')) ?: null),
            'distribution_type' => $distributionType,
            'discount_scope' => $discountScope,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'min_amount' => $minAmount,
            'max_discount_amount' => $maxDiscountAmount,
            'billing_cycles' => $billingCycles,
            'product_ids' => $productIds,
            'requires_product_ids' => $requiresProductIds,
            'first_order_only' => $firstOrderOnly,
            'only_old_client' => $onlyOldClient,
            'is_discount' => $isDiscount,
            'total_usage_limit' => $totalUsageLimit,
            'per_user_limit' => $perUserLimit,
            'status' => $status,
            'sort_order' => $sortOrder,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'remark' => $isUpdate && ! array_key_exists('remark', $payload)
                ? $coupon->remark
                : (trim((string) ($payload['remark'] ?? '')) ?: null),
            'operator' => (string) ($context['operator'] ?? ''),
            'trace_id' => (string) ($context['trace_id'] ?? ''),
        ];
    }

    public static function calculateDiscountAmount(Coupon $coupon, float $amount): float
    {
        return self::computeDiscountFromSnapshot([
            'discount_type' => $coupon->discount_type,
            'discount_value' => $coupon->discount_value,
            'max_discount_amount' => $coupon->max_discount_amount,
        ], $amount);
    }

    /**
     * 按优惠参数快照计算减免额（券模板与服务端持续优惠绑定的单一折算真源）。
     *
     * - fixed：立减 discount_value；
     * - percentage：discount_value 为折扣率（90=9 折），减免 = amount×(100-率)/100；
     * - override：discount_value 即用户实付（覆盖价），减免 = amount-覆盖价；
     * - 统一封顶 min(减免, max_discount_amount[若配置], amount-0.01)——最低应收 0.01，不产生 0 元账单。
     *
     * @param  array<string, mixed>  $snapshot  discount_type/discount_value/max_discount_amount
     */
    public static function computeDiscountFromSnapshot(array $snapshot, float $amount): float
    {
        $discountAmount = 0.0;
        $discountType = trim((string) ($snapshot['discount_type'] ?? ''));
        $discountValue = (float) ($snapshot['discount_value'] ?? 0);

        if ($discountType === 'fixed') {
            $discountAmount = $discountValue;
        }

        if ($discountType === 'percentage') {
            $discountRate = max(min($discountValue, 100), 0);
            // 金额乘除收敛到 Money 分位精度，避免多级折扣叠加时 float 中间值分位漂移
            $discountAmount = Money::round($amount * (100 - $discountRate) / 100);
        }

        if ($discountType === 'override') {
            // 覆盖价券：discount_value 即用户实付（应付 = 覆盖价）。
            // 减免额 = 原价 - 覆盖价；覆盖价 ≥ 原价时无优惠（返回 0 被判不可用）。
            // 末尾统一封顶 min(..., amount-0.01) 将最低应收收敛到 0.01，不产生 0 元账单。
            $discountAmount = max($amount - $discountValue, 0);

            return Money::round(min($discountAmount, max($amount - 0.01, 0)));
        }

        $maxDiscountAmount = $snapshot['max_discount_amount'] ?? null;
        if ($maxDiscountAmount !== null && (float) $maxDiscountAmount > 0) {
            $discountAmount = min($discountAmount, (float) $maxDiscountAmount);
        }

        return Money::round(min($discountAmount, max($amount - 0.01, 0)));
    }

    public static function normalizeProductIds(array $productIds): array
    {
        return collect($productIds)
            ->map(function ($id) {
                if (is_string($id) && str_starts_with($id, 'group-')) {
                    return null;
                }

                return (int) $id;
            })
            ->filter(fn (?int $id) => $id !== null && $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function normalizeUserIds(array $userIds): array
    {
        return collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function generateInternalCouponCode(): string
    {
        do {
            $code = 'CPN'.Str::upper(Str::random(10));
        } while (Coupon::query()->where('code', $code)->exists());

        return $code;
    }
}
