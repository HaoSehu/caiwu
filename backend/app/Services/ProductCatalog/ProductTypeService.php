<?php

namespace App\Services\ProductCatalog;

use App\Constants\ProductType;
use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Models\ProductCategory;

class ProductTypeService
{
    public function list(): array
    {
        $items = ProductType::items();
        $usageMap = Product::query()
            ->join('product_groups', 'product_groups.id', '=', 'products.product_group_id')
            ->selectRaw('product_groups.product_type as product_type, COUNT(products.id) as total')
            ->groupBy('product_groups.product_type')
            ->pluck('total', 'product_type')
            ->all();

        $groupUsageMap = ProductCategory::query()
            ->selectRaw('product_groups.product_type as product_type, COUNT(product_groups.id) as total')
            ->groupBy('product_groups.product_type')
            ->pluck('total', 'product_type')
            ->all();

        return array_map(function (array $item, int $index) use ($usageMap, $groupUsageMap) {
            $value = (string) $item['value'];

            return [
                'internal_id' => (int) ($item['internal_id'] ?? 0),
                'value' => $value,
                'label' => (string) $item['label'],
                'icon' => (string) ($item['icon'] ?? ''),
                'is_builtin' => (bool) ($item['is_builtin'] ?? false),
                'is_hidden' => (bool) ($item['is_hidden'] ?? false),
                'sort_order' => $index + 1,
                'usage_count' => (int) ($usageMap[$value] ?? 0),
                'group_count' => (int) ($groupUsageMap[$value] ?? 0),
            ];
        }, $items, array_keys($items));
    }

    public function create(string $label, ?string $icon = null): array
    {
        $normalizedLabel = $this->normalizeLabel($label);
        $normalizedIcon = $this->normalizeIcon($icon);
        $items = ProductType::items();

        foreach ($items as $item) {
            if ((string) $item['label'] === $normalizedLabel) {
                throw new BusinessException('商品种类名称已存在');
            }
        }

        $existingValues = array_map(
            fn (array $item) => (string) $item['value'],
            $items
        );

        $baseValue = ProductType::normalizeValue($normalizedLabel);
        $value = $baseValue;
        $suffix = 1;

        while (in_array($value, $existingValues, true)) {
            $suffix++;
            $value = ProductType::normalizeValue($baseValue.'_'.$suffix);
        }

        $nextInternalId = collect($items)
            ->map(fn (array $item) => (int) ($item['internal_id'] ?? 0))
            ->max() + 1;

        $items[] = [
            'internal_id' => max(1, $nextInternalId),
            'value' => $value,
            'label' => $normalizedLabel,
            'icon' => $normalizedIcon,
            'is_builtin' => false,
            'is_hidden' => false,
        ];

        ProductType::saveItems($items);

        return $this->findOrFail($value);
    }

    public function update(string $value, string $label, ?bool $isHidden = null, ?string $icon = null): array
    {
        $normalizedValue = trim($value);
        $normalizedLabel = $this->normalizeLabel($label);
        $normalizedIcon = $this->normalizeIcon($icon);
        $items = ProductType::items();
        $matched = false;

        foreach ($items as $index => $item) {
            if ((string) $item['value'] !== $normalizedValue) {
                if ((string) $item['label'] === $normalizedLabel) {
                    throw new BusinessException('商品种类名称已存在');
                }

                continue;
            }

            $items[$index]['label'] = $normalizedLabel;
            $items[$index]['is_hidden'] = $isHidden ?? (bool) ($item['is_hidden'] ?? false);
            $items[$index]['icon'] = $normalizedIcon;
            $matched = true;
        }

        if (! $matched) {
            throw new BusinessException('商品种类不存在', 40400, 404);
        }

        ProductType::saveItems($items);

        return $this->findOrFail($normalizedValue);
    }

    public function delete(string $value): void
    {
        $normalizedValue = trim($value);
        $items = ProductType::items();
        $matched = collect($items)->first(
            fn (array $item) => (string) $item['value'] === $normalizedValue
        );

        if (! $matched) {
            throw new BusinessException('商品种类不存在', 40400, 404);
        }

        $groupCount = ProductCategory::query()
            ->where('product_type', $normalizedValue)
            ->count();
        if ($groupCount > 0) {
            throw new BusinessException("该种类下仍有 {$groupCount} 个分组，无法删除");
        }

        $usageCount = Product::query()
            ->join('product_groups', 'product_groups.id', '=', 'products.product_group_id')
            ->where('product_groups.product_type', $normalizedValue)
            ->count();
        if ($usageCount > 0) {
            throw new BusinessException("该种类下仍有 {$usageCount} 个商品，无法删除");
        }

        if (count($items) <= 1) {
            throw new BusinessException('至少保留一个商品种类');
        }

        $items = array_values(array_filter(
            $items,
            fn (array $item) => (string) $item['value'] !== $normalizedValue
        ));

        ProductType::saveItems($items);
    }

    public function reorder(array $values): array
    {
        $normalizedValues = collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->unique()
            ->values();

        $items = ProductType::items();
        $currentValues = collect($items)->pluck('value')->map(fn ($value) => (string) $value)->values();

        if (
            $normalizedValues->count() !== $currentValues->count()
            || $normalizedValues->sort()->values()->all() !== $currentValues->sort()->values()->all()
        ) {
            throw new BusinessException('商品种类列表已发生变化，请刷新后重新拖动排序');
        }

        $itemMap = collect($items)->keyBy(fn (array $item) => (string) $item['value']);
        $reorderedItems = $normalizedValues
            ->map(fn (string $value) => $itemMap->get($value))
            ->filter(fn ($item) => is_array($item))
            ->values()
            ->all();

        ProductType::saveItems($reorderedItems);

        return $this->list();
    }

    private function findOrFail(string $value): array
    {
        $matched = collect($this->list())->first(
            fn (array $item) => (string) $item['value'] === $value
        );

        if (! $matched) {
            throw new BusinessException('商品种类不存在', 40400, 404);
        }

        return $matched;
    }

    private function normalizeLabel(string $label): string
    {
        $normalizedLabel = trim($label);

        if ($normalizedLabel === '') {
            throw new BusinessException('商品种类名称不能为空');
        }

        return mb_substr($normalizedLabel, 0, 30);
    }

    private function normalizeIcon(?string $icon): string
    {
        return mb_substr(trim((string) $icon), 0, 50);
    }
}
