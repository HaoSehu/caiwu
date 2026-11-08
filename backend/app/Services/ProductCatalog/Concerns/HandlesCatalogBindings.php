<?php

declare(strict_types=1);

namespace App\Services\ProductCatalog\Concerns;

use App\Models\Product;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Support\SchemaMetadataCache;

/**
 * 规格目录（CPU 型号 / 实例规格）共用的绑定解析与展示名回填逻辑。
 *
 * 两个目录服务的绑定行结构一致：都是「规格 → 商品绑定 + 价格 + 展示名」，
 * 此前各写一遍，改展示名回退顺序时必须同步改两处，容易漂移。
 *
 * 使用方需提供：
 * - $productDisplayNameResolver（可为 null，回退容器实例）
 * - limitText()（截断文案）
 */
trait HandlesCatalogBindings
{
    /**
     * 绑定行的展示名回填：优先自定义名，其次 CPU/内存 slug、规格文案、组合名，最后兜底。
     *
     * @param  array<int, Product>  $displayProducts
     * @return array{
     *     display_name: string,
     *     custom_display_name: string,
     *     cpu_memory_display: string,
     *     cpu_memory_slug_display: string,
     *     product_spec_display: string,
     *     combined_display_name: string
     * }
     */
    private function resolveBindingDisplayPayload(int $productId, array $displayProducts): array
    {
        $fallback = $productId > 0 ? '未配置规格 #'.$productId : '';
        if ($productId <= 0) {
            return $this->emptyBindingDisplayPayload($fallback);
        }

        $product = $displayProducts[$productId] ?? null;

        if (! $product instanceof Product) {
            return $this->emptyBindingDisplayPayload($fallback);
        }

        $resolved = $this->resolveProductDisplayNameResolver()->resolveForProduct($product);
        $customDisplayName = trim((string) ($resolved['custom_display_name'] ?? ''));
        $cpuMemorySlugDisplay = trim((string) ($resolved['cpu_memory_slug_display'] ?? ''));
        $productSpecDisplay = trim((string) ($resolved['product_spec_display'] ?? ''));
        $cpuMemoryDisplay = trim((string) ($resolved['cpu_memory_display'] ?? ''));
        $combinedDisplayName = trim((string) ($resolved['combined_display_name'] ?? ''));
        $displayName = $customDisplayName
            ?: ($cpuMemorySlugDisplay ?: ($productSpecDisplay ?: ($cpuMemoryDisplay ?: ($combinedDisplayName ?: $fallback))));

        return [
            'display_name' => $displayName,
            'custom_display_name' => $customDisplayName,
            'cpu_memory_display' => $cpuMemoryDisplay,
            'cpu_memory_slug_display' => $cpuMemorySlugDisplay,
            'product_spec_display' => $productSpecDisplay,
            'combined_display_name' => $combinedDisplayName,
        ];
    }

    /**
     * @return array{
     *     display_name: string,
     *     custom_display_name: string,
     *     cpu_memory_display: string,
     *     cpu_memory_slug_display: string,
     *     product_spec_display: string,
     *     combined_display_name: string
     * }
     */
    private function emptyBindingDisplayPayload(string $fallback): array
    {
        return [
            'display_name' => $fallback,
            'custom_display_name' => '',
            'cpu_memory_display' => $fallback,
            'cpu_memory_slug_display' => '',
            'product_spec_display' => '',
            'combined_display_name' => '',
        ];
    }

    /**
     * 按商品 id 批量取展示名解析所需的列，并一次性预载拆分商品的源商品。
     *
     * 拆分商品的展示名依赖 upstream_split.source_product_id 回源，必须整批预载；
     * 逐条解析时解析器会各自 find() 一次，绑定行数越多越慢。
     *
     * @param  array<int, mixed>  $productIds
     * @return array<int, Product>
     */
    private function loadDisplayProducts(array $productIds): array
    {
        if ($productIds === [] || ! SchemaMetadataCache::hasTable('products')) {
            return [];
        }

        $products = Product::query()
            ->select(['id', 'purchase_requires', 'config_options', 'custom_display_name'])
            ->whereIn('id', array_keys($productIds))
            ->get()
            ->keyBy('id')
            ->all();

        $this->resolveProductDisplayNameResolver()->preloadSourceProducts($products);

        return $products;
    }

    private function resolveProductDisplayNameResolver(): ProductDisplayNameResolver
    {
        return $this->productDisplayNameResolver ?? app(ProductDisplayNameResolver::class);
    }

    /**
     * @return array{cycle: string, amount: string}
     */
    private function normalizeBindingPrice(mixed $price): array
    {
        if (! is_array($price)) {
            return [
                'cycle' => '',
                'amount' => '0.00',
            ];
        }

        $cycle = $this->limitText((string) ($price['cycle'] ?? ''), 40);
        $amount = trim((string) ($price['amount'] ?? '0.00'));

        if ($amount === '') {
            $amount = '0.00';
        } elseif (is_numeric($amount)) {
            $amount = number_format((float) $amount, 2, '.', '');
        } else {
            $amount = $this->limitText($amount, 40);
        }

        return [
            'cycle' => $cycle,
            'amount' => $amount,
        ];
    }
}
