<?php

declare(strict_types=1);

namespace App\Services\ZjmfBridge;

use App\Constants\ProductType;
use App\Exceptions\BusinessException;
use App\Models\FirstProductGroup;
use App\Models\Product;
use App\Models\SecondProductGroup;
use App\Models\ThirdProductGroup;
use App\Services\Order\Concerns\HandlesOrderCalculation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ZjmfProductCatalogService
{
    use HandlesOrderCalculation {
        quote as private calculateProductQuote;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function products(array $filters): array
    {
        $page = $this->page($filters);
        $pageSize = $this->pageSize($filters, 20, 50);
        $paginator = $this->saleProductQuery($filters)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($pageSize, ['*'], 'page', $page);

        return [
            'list' => collect($paginator->items())
                ->filter(fn (mixed $product): bool => $product instanceof Product)
                ->map(fn (Product $product): array => $this->productPayload($product))
                ->values()
                ->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function productConfig(array $input): array
    {
        $product = $this->findSaleProduct($this->productId($input));

        return [
            'product' => $this->productPayload($product, includeConfig: true),
            'config_options' => $this->cleanConfigOptions($product->config_options),
            'pricing' => $this->pricing($product),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function quote(array $input): array
    {
        $product = $this->findSaleProduct($this->productId($input));
        $billingCycle = $this->billingCycle($input, $product);
        $quantity = max((int) ($input['quantity'] ?? $input['num'] ?? 1), 1);
        $config = is_array($input['config'] ?? null) ? (array) $input['config'] : [];
        $quote = $this->calculateProductQuote($product, $billingCycle, $config, $quantity);

        return [
            'product_id' => (int) $product->id,
            'billing_cycle' => $billingCycle,
            'quantity' => $quantity,
            'base_amount' => $quote['base_amount'],
            'config_amount' => $quote['config_amount'],
            'setup_fee' => $quote['setup_fee'],
            'total_amount' => $quote['total_amount'],
            'items' => $quote['items'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function categories(array $filters): array
    {
        $productType = $this->productTypeFilter($filters);
        $firstGroupId = $this->positiveInt($filters['first_product_group_id'] ?? $filters['first_group_id'] ?? null);
        $firstGroups = FirstProductGroup::query()
            ->where('is_visible', 1)
            ->when($firstGroupId !== null, fn (Builder $query) => $query->whereKey($firstGroupId))
            ->when($this->firstGroupHasProductType(), function (Builder $query) use ($productType): void {
                $query
                    ->whereIn('product_type', ProductType::businessAllowedValues())
                    ->when($productType !== null, fn (Builder $typeQuery) => $typeQuery->where('product_type', $productType));
            })
            ->with([
                'secondProductGroups' => fn ($query) => $query
                    ->where('is_visible', 1)
                    ->with([
                        'thirdProductGroups' => fn ($thirdQuery) => $thirdQuery
                            ->where('is_visible', 1)
                            ->orderBy('sort_order')
                            ->orderBy('id'),
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (FirstProductGroup $group): bool => $this->businessProductTypeMatches($group, $productType));

        $list = $firstGroups
            ->map(fn (FirstProductGroup $group): array => $this->firstGroupPayload($group))
            ->values()
            ->all();

        return [
            'list' => $list,
            'cate' => $list,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function saleProductQuery(array $filters): Builder
    {
        $productType = $this->productTypeFilter($filters);
        $firstGroupId = $this->positiveInt($filters['first_product_group_id'] ?? $filters['first_group_id'] ?? null);
        $secondGroupId = $this->positiveInt($filters['second_product_group_id'] ?? null);
        $thirdGroupId = $this->positiveInt($filters['third_product_group_id'] ?? null);
        $effectiveGroupId = $this->positiveInt($filters['effective_product_group_id'] ?? $filters['group_id'] ?? null);

        return Product::query()
            ->onSale()
            ->whereNotNull('first_product_group_id')
            ->whereNotNull('second_product_group_id')
            ->with([
                'firstProductGroup',
                'secondProductGroup',
                'thirdProductGroup',
            ])
            ->whereHas('firstProductGroup', function (Builder $query) use ($productType): void {
                $query->where('is_visible', 1);

                if ($this->firstGroupHasProductType()) {
                    $query
                        ->whereIn('product_type', ProductType::businessAllowedValues())
                        ->when($productType !== null, fn (Builder $typeQuery) => $typeQuery->where('product_type', $productType));
                }
            })
            ->when(
                $productType !== null && ! $this->firstGroupHasProductType(),
                fn (Builder $query) => $query->where('product_type', $productType)
            )
            ->whereHas('secondProductGroup', fn (Builder $query) => $query->where('is_visible', 1))
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('third_product_group_id')
                    ->orWhereHas('thirdProductGroup', fn (Builder $thirdQuery) => $thirdQuery->where('is_visible', 1));
            })
            ->when($firstGroupId !== null, fn (Builder $query) => $query->where('first_product_group_id', $firstGroupId))
            ->when($secondGroupId !== null, fn (Builder $query) => $query->where('second_product_group_id', $secondGroupId))
            ->when($thirdGroupId !== null, fn (Builder $query) => $query->where('third_product_group_id', $thirdGroupId))
            ->when($effectiveGroupId !== null, function (Builder $query) use ($effectiveGroupId): void {
                $query->where(function (Builder $groupQuery) use ($effectiveGroupId): void {
                    $groupQuery
                        ->where('third_product_group_id', $effectiveGroupId)
                        ->orWhere(function (Builder $secondQuery) use ($effectiveGroupId): void {
                            $secondQuery
                                ->where('second_product_group_id', $effectiveGroupId)
                                ->whereNull('third_product_group_id');
                        });
                });
            });
    }

    private function findSaleProduct(int $productId): Product
    {
        if ($productId <= 0) {
            throw new BusinessException('商品ID不能为空', 42200, 422);
        }

        $product = $this->saleProductQuery([])
            ->whereKey($productId)
            ->first();

        if (! $product instanceof Product) {
            throw new BusinessException('商品不存在', 40400, 404);
        }

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(Product $product, bool $includeConfig = false): array
    {
        $firstGroup = $product->relationLoaded('firstProductGroup') ? $product->firstProductGroup : null;
        $secondGroup = $product->relationLoaded('secondProductGroup') ? $product->secondProductGroup : null;
        $thirdGroup = $product->relationLoaded('thirdProductGroup') ? $product->thirdProductGroup : null;
        $productType = $this->businessProductType($firstGroup, $product);
        $pricing = $this->pricing($product);
        $primaryCycle = $this->primaryCycle($pricing);

        $payload = [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'display_name' => (string) $product->name,
            'product_type' => $productType,
            'type' => $productType,
            'product_type_label' => ProductType::businessLabelOf($productType),
            'menu_code' => (string) ($firstGroup?->code ?? ''),
            'first_product_group_id' => $firstGroup?->id ? (int) $firstGroup->id : null,
            'first_product_group_code' => (string) ($firstGroup?->code ?? ''),
            'first_product_group_name' => $firstGroup?->name,
            'second_product_group_id' => $secondGroup?->id ? (int) $secondGroup->id : null,
            'second_product_group_name' => $secondGroup?->name,
            'third_product_group_id' => $thirdGroup?->id ? (int) $thirdGroup->id : null,
            'third_product_group_name' => $thirdGroup?->name,
            'effective_product_group_id' => $thirdGroup?->id ? (int) $thirdGroup->id : ($secondGroup?->id ? (int) $secondGroup->id : null),
            'effective_product_group_level' => $thirdGroup?->id ? 3 : ($secondGroup?->id ? 2 : null),
            'primary_cycle' => $primaryCycle,
            'primary_price' => $primaryCycle !== '' ? $pricing[$primaryCycle] : '0.00',
            'setup_fee' => $this->money($product->setup_fee ?? 0),
            'stock' => (int) ($product->stock ?? -1),
            'auto_setup' => (int) ($product->auto_setup ?? 0),
            'pricing' => $pricing,
            'first_group' => [
                'id' => $firstGroup?->id ? (int) $firstGroup->id : null,
                'name' => $firstGroup?->name,
                'code' => (string) ($firstGroup?->code ?? ''),
                'product_type' => $productType,
                'product_type_label' => ProductType::businessLabelOf($productType),
            ],
            'group' => [
                'id' => $thirdGroup?->id ? (int) $thirdGroup->id : ($secondGroup?->id ? (int) $secondGroup->id : null),
                'level' => $thirdGroup?->id ? 3 : ($secondGroup?->id ? 2 : null),
                'name' => $thirdGroup?->name ?? $secondGroup?->name,
                'parent_id' => $thirdGroup?->id ? (int) ($secondGroup?->id ?? 0) : (int) ($firstGroup?->id ?? 0),
            ],
        ];

        if ($includeConfig) {
            $payload['config_options'] = $this->cleanConfigOptions($product->config_options);
            $payload['purchase_requires'] = $this->removeSensitiveKeys($product->purchase_requires);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function firstGroupPayload(FirstProductGroup $group): array
    {
        $productType = $this->businessProductType($group);

        return [
            'id' => (int) $group->id,
            'name' => (string) $group->name,
            'code' => (string) $group->code,
            'menu_code' => (string) $group->code,
            'product_type' => $productType,
            'product_type_label' => ProductType::businessLabelOf($productType),
            'level' => 1,
            'children' => $group->secondProductGroups
                ->map(fn (SecondProductGroup $child): array => $this->secondGroupPayload($child, $group))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function secondGroupPayload(SecondProductGroup $group, FirstProductGroup $firstGroup): array
    {
        $productType = $this->businessProductType($firstGroup);

        return [
            'id' => (int) $group->id,
            'parent_id' => (int) $firstGroup->id,
            'name' => (string) $group->name,
            'product_type' => $productType,
            'product_type_label' => ProductType::businessLabelOf($productType),
            'first_product_group_id' => (int) $firstGroup->id,
            'first_product_group_code' => (string) $firstGroup->code,
            'effective_product_group_id' => (int) $group->id,
            'effective_product_group_level' => 2,
            'level' => 2,
            'children' => $group->thirdProductGroups
                ->map(fn (ThirdProductGroup $child): array => $this->thirdGroupPayload($child, $group, $firstGroup))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function thirdGroupPayload(ThirdProductGroup $group, SecondProductGroup $secondGroup, FirstProductGroup $firstGroup): array
    {
        $productType = $this->businessProductType($firstGroup);

        return [
            'id' => (int) $group->id,
            'parent_id' => (int) $secondGroup->id,
            'name' => (string) $group->name,
            'product_type' => $productType,
            'product_type_label' => ProductType::businessLabelOf($productType),
            'first_product_group_id' => (int) $firstGroup->id,
            'first_product_group_code' => (string) $firstGroup->code,
            'second_product_group_id' => (int) $secondGroup->id,
            'effective_product_group_id' => (int) $group->id,
            'effective_product_group_level' => 3,
            'level' => 3,
            'children' => [],
        ];
    }

    private function businessProductType(?FirstProductGroup $firstGroup, ?Product $product = null): string
    {
        $candidate = trim((string) ($firstGroup?->product_type ?? ''));
        if ($candidate !== '') {
            return ProductType::normalizeBusinessValue($candidate);
        }

        $candidate = trim((string) ($product?->product_type ?? ''));
        if ($candidate !== '') {
            return ProductType::normalizeBusinessValue($candidate);
        }

        $code = trim((string) ($firstGroup?->legacy_product_type ?? $firstGroup?->code ?? $product?->service_type_code ?? ''));

        return ProductType::normalizeBusinessValueFromMenuCode($code);
    }

    private function businessProductTypeMatches(FirstProductGroup $group, ?string $productType): bool
    {
        $resolved = $this->businessProductType($group);

        return in_array($resolved, ProductType::businessAllowedValues(), true)
            && ($productType === null || $resolved === $productType);
    }

    private function firstGroupHasProductType(): bool
    {
        static $hasColumn = null;

        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn('first_product_groups', 'product_type');
        }

        return $hasColumn;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function productTypeFilter(array $filters): ?string
    {
        $raw = trim((string) ($filters['product_type'] ?? $filters['type'] ?? ''));

        return $raw !== '' ? ProductType::normalizeBusinessValue($raw) : null;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function productId(array $input): int
    {
        return $this->positiveInt($input['product_id'] ?? $input['id'] ?? null) ?? 0;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function billingCycle(array $input, Product $product): string
    {
        $billingCycle = trim((string) ($input['billing_cycle'] ?? $input['cycle'] ?? ''));
        $pricing = $this->pricing($product);

        if ($billingCycle === '') {
            return $this->primaryCycle($pricing);
        }

        if (! array_key_exists($billingCycle, $pricing)) {
            throw new BusinessException('无效的计费周期', 42200, 422);
        }

        return $billingCycle;
    }

    /**
     * @return array<string, string>
     */
    private function pricing(Product $product): array
    {
        $pricing = [];

        foreach ((array) ($product->pricing ?? []) as $cycle => $amount) {
            $cycle = (string) $cycle;
            if (! in_array($cycle, ['monthly', 'quarterly', 'semiannually', 'annually'], true)) {
                continue;
            }

            $pricing[$cycle] = $this->money($amount);
        }

        return $pricing;
    }

    /**
     * @param  array<string, string>  $pricing
     */
    private function primaryCycle(array $pricing): string
    {
        foreach (['monthly', 'quarterly', 'semiannually', 'annually'] as $cycle) {
            if (isset($pricing[$cycle]) && (float) $pricing[$cycle] > 0) {
                return $cycle;
            }
        }

        return (string) (array_key_first($pricing) ?? '');
    }

    private function cleanConfigOptions(mixed $configOptions): array
    {
        if (! is_array($configOptions)) {
            return [];
        }

        return collect($configOptions)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => $this->removeSensitiveKeys($item))
            ->values()
            ->all();
    }

    private function removeSensitiveKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $clean = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && preg_match('/password|secret|api_key|raw_response|third_party_response/i', $key) === 1) {
                continue;
            }

            $clean[$key] = $this->removeSensitiveKeys($item);
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function page(array $filters): int
    {
        return max((int) ($filters['page'] ?? 1), 1);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function pageSize(array $filters, int $default, int $max): int
    {
        $value = (int) ($filters['page_size'] ?? $filters['limit'] ?? $default);

        return min(max($value, 1), $max);
    }

    private function positiveInt(mixed $value): ?int
    {
        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }

    private function money(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : '0.00';
    }
}
