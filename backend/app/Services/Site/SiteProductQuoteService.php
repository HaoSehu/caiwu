<?php

namespace App\Services\Site;

use App\Constants\ProductType;
use App\Models\Product;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\CheckoutService;
use App\Services\Finance\CouponService;
use App\Services\Pricing\MemberGroupDiscountService;
use Illuminate\Database\Eloquent\Builder;

class SiteProductQuoteService
{
    public function __construct(
        private CheckoutService $checkoutService,
        private CheckoutSecurityService $checkoutSecurityService,
        private CouponService $couponService,
        private MemberGroupDiscountService $memberGroupDiscountService,
    ) {}

    public function resolveQuotePayload(int $productId, array $validated, array $requestContext = []): ?array
    {
        $product = $this->findSaleProductForQuote($productId);

        if (! $product) {
            return null;
        }

        return $this->quote($product, $validated, $requestContext);
    }

    public function quote(Product $product, array $validated, array $requestContext = []): array
    {
        $billingCycle = (string) $validated['billing_cycle'];
        $quantity = max((int) ($validated['quantity'] ?? 1), 1);
        $userId = (int) ($requestContext['user_id'] ?? 0);
        $normalizedConfig = $this->checkoutService->normalizeConfig($product, (array) ($validated['config'] ?? []));
        // M7 配置联动：预览与下单共用 quoteWithLinkage，服务端按规则重算为最终价（前端仅 UX 预览）
        $linkageQuote = $this->checkoutService->quoteWithLinkage($product, $billingCycle, $normalizedConfig, $quantity);
        $quote = $linkageQuote['quote'];
        // 会员折扣层：目录价先按「等级 × 营销组」折算，优惠券以折后价为基数
        $catalogAmount = round((float) ($quote['total_amount'] ?? 0), 2);
        $memberDiscount = $userId > 0
            ? $this->memberGroupDiscountService->applyForProduct($userId, (int) $product->id, $catalogAmount)
            : null;
        $memberDiscountAmount = round((float) ($memberDiscount['discount_amount'] ?? 0), 2);
        $couponBaseAmount = max((float) ($memberDiscount['final_amount'] ?? $catalogAmount), 0.0);
        // P3-11 叠加开关：本单实际享受会员折扣减免且券声明互斥（is_discount=0）时，券不参与
        $memberDiscountApplied = $memberDiscountAmount > 0;
        $coupon = $userId > 0
            ? $this->couponService->previewOwnedCoupon(
                isset($validated['user_coupon_id']) ? (int) $validated['user_coupon_id'] : null,
                $userId,
                $product,
                $billingCycle,
                $couponBaseAmount,
                'new',
                $memberDiscountApplied
            )
            : null;
        $availableCoupons = $userId > 0
            ? $this->couponService->availableCouponsForCheckout(
                $userId,
                $product,
                $billingCycle,
                $couponBaseAmount,
                'new',
                $memberDiscountApplied
            )
            : [];

        $discountAmount = (float) ($coupon['discount_amount'] ?? 0);
        $quote['subtotal_amount'] = number_format($catalogAmount, 2, '.', '');
        $quote['member_level_id'] = $memberDiscount['level_id'] ?? null;
        $quote['member_level_name'] = $memberDiscount['level_name'] ?? null;
        $quote['member_discount_amount'] = number_format($memberDiscountAmount, 2, '.', '');
        $quote['discount_amount'] = number_format($discountAmount, 2, '.', '');
        $quote['total_amount'] = number_format(max($catalogAmount - $memberDiscountAmount - $discountAmount, 0), 2, '.', '');
        $quote['coupon'] = $coupon;
        $quote['user_coupon_id'] = (int) ($coupon['user_coupon_id'] ?? 0);
        $quote['available_coupons'] = $availableCoupons;

        // M7 联动投影：visibility/disabled/adjustments/price_delta/applied_rule_ids + rules_hash，
        // 仅在商品配置了联动规则时追加，无规则商品响应结构不变（回归红线）
        if ($linkageQuote['linkage'] !== null) {
            $quote['linkage'] = $linkageQuote['linkage'];
            $quote['rules_hash'] = $linkageQuote['rules_hash'];
        }

        $securityPayload = $this->checkoutSecurityService->issueQuoteToken(
            (int) $product->id,
            $billingCycle,
            $normalizedConfig,
            $quote,
            [
                'request_id' => (string) ($requestContext['request_id'] ?? ''),
                'ip_address' => (string) ($requestContext['ip_address'] ?? ''),
                'user_id' => $userId,
            ]
        );

        return array_merge($quote, $securityPayload);
    }

    private function saleProductQuery(): Builder
    {
        $visibleProductTypes = ProductType::visibleValues();

        if ($visibleProductTypes === []) {
            return Product::query()->whereRaw('1 = 0');
        }

        return Product::query()
            ->onSale()
            ->whereNotNull('product_group_id')
            ->withVisibleProductGroupPath($visibleProductTypes);
    }

    private function findSaleProductForQuote(int $productId): ?Product
    {
        return $this->saleProductQuery()
            ->select([
                'id',
                'product_group_id',
                'service_type_code',
                'product_type',
                'pricing',
                'setup_fee',
                'config_options',
                'purchase_requires',
                // M7：联动规则列（optionalSelectColumns 在迁移未跑的库上自动剔除，避免 SQL 报错）
                ...Product::optionalSelectColumns(['config_linkage_rules']),
            ])
            ->whereKey($productId)
            ->first();
    }
}
