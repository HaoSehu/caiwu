<?php

declare(strict_types=1);

namespace App\Services\Finance\Concerns;

use App\Constants\ProductType;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Support\ProductGroupHierarchyFields;
use Illuminate\Support\Collection;

/**
 * 优惠券列表的商品展示数据装配。
 *
 * 归属说明：这里只负责「券 → 商品名 / 商品层级」的读模型拼装，
 * 不参与券的可用性判定、核销与金额计算，因此可以与 CouponService 主流程解耦。
 */
trait ResolvesCouponProductDisplay
{
    private function resolveProductNameMapFromCoupons(Collection $coupons): array
    {
        $productIds = $coupons
            ->filter()
            ->flatMap(fn (Coupon $coupon) => $this->normalizeProductIds((array) ($coupon->product_ids ?? [])))
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            return [];
        }

        $products = Product::query()
            ->select(['id', 'pricing', 'purchase_requires', 'config_options'])
            ->whereIn('id', $productIds)
            ->get();

        // 展示名解析要回源拆分商品的源商品，并补齐投影里没有的 custom_display_name，
        // 不预载会随券数量逐行回源（管理端券列表 / 客户端券列表同一条链）。
        $resolver = $this->resolveProductDisplayNameResolver();
        $resolver->preloadSourceProducts($products);
        $resolver->preloadCustomDisplayNames($products);

        return $products
            ->mapWithKeys(function (Product $product) {
                $displayName = (string) ($this->resolveProductDisplayNameResolver()->resolveForProduct($product)['product_display_name'] ?? '');

                return [
                    (int) $product->id => $displayName !== '' ? $displayName : '未配置规格 #'.(int) $product->id,
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, int>  $productIds
     * @return array<int, array{id: int, name: string, type_label: string, parent_group_name: string, group_name: string}>
     */
    private function resolveProductHierarchyList(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $products = Product::query()
            ->select([
                'id',
                'product_group_id',
                'service_type_code',
                'product_type',
                'custom_display_name',
                'pricing',
                'purchase_requires',
                'config_options',
            ])
            ->whereIn('id', $productIds)
            ->with([
                'productGroup.secondProductGroup.firstProductGroup',
            ])
            ->get();

        $specMap = $this->instanceSpecCatalogService
            ? $this->instanceSpecCatalogService->resolveProductSpecMap($productIds)
            : [];

        return $products
            ->map(function (Product $product) use ($specMap) {
                $hierarchy = ProductGroupHierarchyFields::fromProduct($product);
                $typeCode = (string) ($hierarchy['product_type'] ?? ProductType::OTHER);
                $instanceSpecText = (string) (($specMap[(int) $product->id] ?? [])['instance_spec_text'] ?? '');
                $displayNamePayload = $this->resolveProductDisplayNameResolver()->resolveForProduct($product, [
                    'instance_spec_text' => $instanceSpecText,
                ]);
                $combinedName = trim((string) ($displayNamePayload['combined_display_name'] ?? ''));
                $productName = trim((string) ($displayNamePayload['product_display_name'] ?? ''));

                return [
                    'id' => (int) $product->id,
                    'name' => $combinedName !== '' ? $combinedName : ($productName !== '' ? $productName : '未配置规格 #'.(int) $product->id),
                    'product_type' => $typeCode,
                    'product_type_label' => ProductType::businessLabelOf($typeCode),
                    'service_type_code' => $typeCode,
                    'service_type_label' => ProductType::businessLabelOf($typeCode),
                    'first_product_group_id' => $hierarchy['first_product_group_id'] ?? null,
                    'first_product_group_code' => trim((string) ($hierarchy['first_product_group_code'] ?? '')),
                    'first_product_group_name' => trim((string) ($hierarchy['first_product_group_name'] ?? '')),
                    'second_product_group_id' => $hierarchy['second_product_group_id'] ?? null,
                    'second_product_group_name' => trim((string) ($hierarchy['second_product_group_name'] ?? '')),
                    'third_product_group_id' => $hierarchy['third_product_group_id'] ?? null,
                    'third_product_group_name' => trim((string) ($hierarchy['third_product_group_name'] ?? '')),
                    'effective_product_group_id' => $hierarchy['effective_product_group_id'] ?? null,
                    'effective_product_group_level' => $hierarchy['effective_product_group_level'] ?? null,
                    '_sort_type' => $typeCode,
                    '_sort_parent' => trim((string) ($hierarchy['second_product_group_name'] ?? '')),
                    '_sort_group' => trim((string) ($hierarchy['third_product_group_name'] ?? '')),
                ];
            })
            ->sortBy([
                ['_sort_type', 'asc'],
                ['_sort_parent', 'asc'],
                ['_sort_group', 'asc'],
                ['name', 'asc'],
            ])
            ->map(fn (array $item) => array_diff_key($item, ['_sort_type' => 0, '_sort_parent' => 0, '_sort_group' => 0]))
            ->values()
            ->all();
    }

    private function resolveProductDisplayNameResolver(): ProductDisplayNameResolver
    {
        // 必须与容器单例一致：列表预载写在单例上，这里若新建实例，预载会被架空、逐行解析仍各发一次查询。
        return $this->productDisplayNameResolver ?? app(ProductDisplayNameResolver::class);
    }
}
