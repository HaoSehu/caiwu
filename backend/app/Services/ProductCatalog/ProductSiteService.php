<?php

declare(strict_types=1);

namespace App\Services\ProductCatalog;

use App\Constants\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductCatalog\Concerns\HandlesProductCatalogHelpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ProductSiteService
{
    use HandlesProductCatalogHelpers;

    private const SITE_CATALOG_CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly CpuModelCatalogService $cpuModelCatalogService,
        private readonly InstanceSpecCatalogService $instanceSpecCatalogService,
        private readonly ?ProductDisplayNameResolver $productDisplayNameResolver = null,
    ) {}

    public function siteProductTypes(): array
    {
        return $this->rememberSitePayload(
            self::SITE_PRODUCT_TYPES_CACHE_KEY,
            self::SITE_PRODUCT_TYPES_CACHE_TTL_SECONDS,
            function () {
                $visibleProductTypes = ProductType::visibleValues();
                if ($visibleProductTypes === []) {
                    return [];
                }

                $groupCounts = ProductCategory::query()
                    ->whereNull('product_groups.parent_group_id')
                    ->where('product_groups.is_visible', 1)
                    ->whereIn('product_groups.product_type', $visibleProductTypes)
                    ->selectRaw('product_groups.product_type as product_type, COUNT(product_groups.id) as group_count')
                    ->groupBy('product_groups.product_type')
                    ->pluck('group_count', 'product_type');

                $productCounts = Product::query()
                    ->onSale()
                    ->whereNotNull('products.product_group_id')
                    ->join('product_groups', 'product_groups.id', '=', 'products.product_group_id')
                    ->where('product_groups.is_visible', 1)
                    ->whereIn('product_groups.product_type', $visibleProductTypes)
                    ->selectRaw('product_groups.product_type as product_type, COUNT(products.id) as product_count')
                    ->groupBy('product_groups.product_type')
                    ->pluck('product_count', 'product_type');

                return collect(ProductType::visibleItems())
                    ->map(function (array $item) use ($groupCounts, $productCounts) {
                        $value = (string) ($item['value'] ?? '');
                        $groupCount = (int) ($groupCounts[$value] ?? 0);

                        return [
                            'id' => (int) ($item['internal_id'] ?? ProductType::routeIdOf($value)),
                            'value' => $value,
                            'label' => (string) ($item['label'] ?? ProductType::labelOf($value)),
                            'icon' => (string) ($item['icon'] ?? ProductType::iconOf($value)),
                            'group_count' => $groupCount,
                            'product_count' => (int) ($productCounts[$value] ?? 0),
                        ];
                    })
                    ->filter(fn (array $item) => $item['id'] > 0 && $item['value'] !== '' && $item['group_count'] > 0)
                    ->values()
                    ->all();
            }
        );
    }

    public function siteRootGroups(?string $productType = null): array
    {
        $cacheSuffix = self::SITE_ROOT_GROUPS_CACHE_KEY.':'.($productType ?: 'all');

        return $this->rememberSitePayload(
            $cacheSuffix,
            self::SITE_GROUPS_CACHE_TTL_SECONDS,
            function () use ($productType) {
                $visibleProductTypes = ProductType::visibleValues();
                if ($visibleProductTypes === []) {
                    return [];
                }

                if ($productType && ! in_array($productType, $visibleProductTypes, true)) {
                    return [];
                }

                return $this->visibleSiteCategoryQuery($productType)
                    ->root()
                    ->select([
                        'product_groups.id',
                        'product_groups.parent_group_id',
                        'product_groups.product_type',
                        'product_groups.name',
                        'product_groups.slogan',
                        'product_groups.slug',
                        'product_groups.sort_order',
                    ])
                    ->withCount([
                        'children as children_count' => fn (Builder $query) => $query->visible(),
                        'products as direct_product_count' => fn (Builder $query) => $query->onSale(),
                    ])
                    ->selectSub(
                        Product::query()
                            ->selectRaw('COUNT(*)')
                            ->join('product_groups as child_groups', 'child_groups.id', '=', 'products.product_group_id')
                            ->whereColumn('child_groups.parent_group_id', 'product_groups.id')
                            ->where('child_groups.is_visible', 1)
                            ->where('products.status', 1),
                        'child_product_count'
                    )
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (ProductCategory $group) => $this->transformSiteRootGroup($group))
                    ->values()
                    ->all();
            }
        );
    }

    public function siteChildGroups(int $groupId): array
    {
        $cacheSuffix = self::SITE_CHILD_GROUPS_CACHE_KEY.':'.$groupId;

        return $this->rememberSitePayload(
            $cacheSuffix,
            self::SITE_GROUPS_CACHE_TTL_SECONDS,
            function () use ($groupId) {
                $category = $this->resolveVisibleCategory($groupId);
                if (! $category) {
                    return [];
                }

                return $this->visibleSiteCategoryQuery()
                    ->select([
                        'id',
                        'parent_group_id',
                        'product_type',
                        'name',
                        'slogan',
                        'slug',
                        'sort_order',
                    ])
                    ->where('parent_group_id', (int) $category->id)
                    ->withCount([
                        'products as product_count' => fn (Builder $query) => $query->onSale(),
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (ProductCategory $group) => $this->transformSiteChildGroup($group))
                    ->values()
                    ->all();
            }
        );
    }

    public function siteGroupCatalog(int $groupId): array
    {
        $cacheSuffix = self::SITE_GROUP_CATALOG_CACHE_KEY.':'.$groupId;

        return $this->rememberSitePayload(
            $cacheSuffix,
            self::SITE_PRODUCTS_CACHE_TTL_SECONDS,
            function () use ($groupId) {
                $category = $this->resolveVisibleCategory($groupId);
                if (! $category) {
                    return [
                        'group_id' => $groupId,
                        'category_id' => null,
                        'children' => [],
                        'items_by_group' => [],
                    ];
                }

                $children = $this->siteChildGroups($groupId);
                $groupIds = array_values(array_unique([
                    $groupId,
                    ...array_map(
                        static fn (array $item): int => (int) ($item['id'] ?? 0),
                        $children
                    ),
                ]));

                return [
                    'group_id' => $groupId,
                    'category_id' => (int) $category->id,
                    'children' => $children,
                    'items_by_group' => $this->siteProductsByGroupIds($groupIds),
                ];
            }
        );
    }

    public function siteProductsByGroupIds(array $groupIds): array
    {
        $normalizedGroupIds = $this->normalizeSiteGroupIds($groupIds);
        $visibleProductTypes = ProductType::visibleValues();

        if ($normalizedGroupIds === [] || $visibleProductTypes === []) {
            return [];
        }

        $visibleGroupIds = $this->resolveVisibleCategoryIdMapByInputs($normalizedGroupIds);

        if ($visibleGroupIds === []) {
            return [];
        }

        $productsByGroup = Product::query()
            ->onSale()
            ->whereIn('product_group_id', array_values($visibleGroupIds))
            ->with([
                'categoryMapping.parent',
            ])
            ->select([
                'id',
                'product_group_id',
                'product_type',
                'purchase_requires',
                'config_options',
                'pricing',
                'setup_fee',
                'stock',
                'auto_setup',
                'sort_order',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Product $product) => (int) ($product->product_group_id ?? 0));

        $instanceSpecMap = $this->instanceSpecCatalogService->resolveProductSpecMap(
            collect($productsByGroup)
                ->flatten(1)
                ->map(fn (Product $product) => (int) $product->id)
                ->values()
                ->all()
        );

        return collect($normalizedGroupIds)
            ->filter(fn (int $groupId) => isset($visibleGroupIds[$groupId]))
            ->map(function (int $groupId) use ($productsByGroup, $visibleGroupIds, $instanceSpecMap) {
                $categoryId = (int) $visibleGroupIds[$groupId];

                return [
                    'category_id' => $categoryId,
                    'product_group_id' => $groupId,
                    'group_id' => $groupId,
                    'products' => $productsByGroup
                        ->get($categoryId, collect())
                        ->map(fn (Product $product) => $this->transformSiteProductCard($product, $instanceSpecMap[(int) $product->id] ?? []))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function siteProductDetail(int $productId): ?array
    {
        $cacheSuffix = self::SITE_PRODUCT_DETAIL_CACHE_KEY.':'.max($productId, 0);
        $payload = $this->rememberSitePayload(
            $cacheSuffix,
            self::SITE_PRODUCT_DETAIL_CACHE_TTL_SECONDS,
            function () use ($productId) {
                $product = $this->findSaleProductForDetail($productId);

                return [
                    'exists' => $product instanceof Product,
                    'product' => $product instanceof Product
                        ? $this->transformSiteProductDetail($product)
                        : null,
                ];
            }
        );

        $product = $payload['product'] ?? null;

        return ($payload['exists'] ?? false) === true && is_array($product)
            ? $product
            : null;
    }

    public function siteCatalog(): Collection
    {
        $visibleProductTypes = ProductType::visibleValues();

        if ($visibleProductTypes === []) {
            return collect();
        }

        return Cache::remember(
            self::SITE_CATALOG_CACHE_KEY,
            now()->addSeconds(self::SITE_CATALOG_CACHE_TTL_SECONDS),
            fn () => $this->visibleSiteCategoryQuery()
                ->root()
                ->with([
                    'children' => fn ($query) => $query
                        ->visible()
                        ->whereIn('product_type', $visibleProductTypes)
                        ->with(['products' => fn ($productQuery) => $productQuery
                            ->where('status', 1)
                            ->orderBy('sort_order')
                            ->orderBy('id')])
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                    'products' => fn ($query) => $query
                        ->where('status', 1)
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                ])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
        );
    }

    private function saleProductQuery(): Builder
    {
        $visibleProductTypes = ProductType::visibleValues();

        if ($visibleProductTypes === []) {
            return Product::query()->whereRaw('1 = 0');
        }

        return Product::query()
            ->onSale()
            ->whereHas('categoryMapping', function ($query) use ($visibleProductTypes) {
                $query
                    ->visible()
                    ->whereIn('product_type', $visibleProductTypes)
                    ->where(function ($groupQuery) {
                        $groupQuery
                            ->whereNull('parent_group_id')
                            ->orWhereHas('parent', fn ($parentQuery) => $parentQuery->visible());
                    });
            });
    }

    private function findSaleProductForDetail(int $productId): ?Product
    {
        return $this->saleProductQuery()
            ->select([
                'id',
                'product_group_id',
                'supplier_id',
                'product_type',
                'meta_title',
                'meta_description',
                'meta_keywords',
                'pricing',
                'setup_fee',
                'config_options',
                'purchase_requires',
                'stock',
                'auto_setup',
                'provision_module',
            ])
            ->with([
                'categoryMapping:id,parent_group_id,product_type,name,slogan,slug',
                'categoryMapping.parent:id,parent_group_id,product_type,name,slogan,slug',
            ])
            ->whereKey($productId)
            ->first();
    }

    private function transformSiteRootGroup(ProductCategory $group): array
    {
        $directProductCount = (int) ($group->direct_product_count ?? 0);
        $childProductCount = (int) ($group->child_product_count ?? 0);
        $productType = $this->resolveCategoryTypeCode($group);

        return [
            'id' => $this->resolvePublicCategoryId($group),
            'category_id' => (int) $group->id,
            'product_type' => $productType,
            'product_type_id' => ProductType::routeIdOf($productType),
            'product_type_label' => ProductType::labelOf($productType),
            'name' => (string) $group->name,
            'slogan' => (string) ($group->slogan ?? ''),
            'slug' => (string) ($group->slug ?? ''),
            'children_count' => (int) ($group->children_count ?? 0),
            'direct_product_count' => $directProductCount,
            'product_count' => $directProductCount + $childProductCount,
        ];
    }

    private function transformSiteChildGroup(ProductCategory $group): array
    {
        $productType = $this->resolveCategoryTypeCode($group);

        return [
            'id' => $this->resolvePublicCategoryId($group),
            'category_id' => (int) $group->id,
            'parent_id' => $this->resolvePublicCategoryId($group->parent),
            'parent_category_id' => $group->parent ? (int) $group->parent->id : null,
            'product_type' => $productType,
            'product_type_id' => ProductType::routeIdOf($productType),
            'product_type_label' => ProductType::labelOf($productType),
            'name' => (string) $group->name,
            'slogan' => (string) ($group->slogan ?? ''),
            'slug' => (string) ($group->slug ?? ''),
            'product_count' => (int) ($group->product_count ?? 0),
        ];
    }

    private function transformSiteProductCard(Product $product, array $instanceSpec = []): array
    {
        $group = $product->categoryMapping;
        $productType = $group ? $this->resolveCategoryTypeCode($group) : (string) $product->product_type;
        $pricing = (array) ($product->pricing ?? []);
        $primaryCycle = '';
        $primaryPrice = '0.00';
        $displayNamePayload = $this->resolveProductDisplayNameResolver()->resolveForProduct($product);
        $displayName = trim((string) ($displayNamePayload['product_display_name'] ?? ''));
        $combinedDisplayName = trim((string) ($displayNamePayload['combined_display_name'] ?? ''));
        $cpuMemoryDisplay = trim((string) ($displayNamePayload['cpu_memory_display'] ?? ''));
        $instanceSpecText = trim((string) ($displayNamePayload['instance_spec_text'] ?? ''));
        $cpuDisplay = trim((string) ($displayNamePayload['cpu_display'] ?? ''));
        $memoryDisplay = trim((string) ($displayNamePayload['memory_display'] ?? ''));

        foreach ($pricing as $cycle => $amount) {
            if ((float) $amount > 0) {
                $primaryCycle = (string) $cycle;
                $primaryPrice = number_format((float) $amount, 2, '.', '');
                break;
            }
        }

        return [
            'id' => (int) $product->id,
            'category_id' => (int) (($product->product_group_id ?? 0) ?: ($product->category_id ?? 0)),
            'group_id' => (int) ($group ? $this->resolvePublicCategoryId($group) : ($product->group_id ?? 0)),
            'name' => (string) $product->name,
            'display_name' => $displayName !== '' ? $displayName : ('未配置规格 #'.(int) $product->id),
            'product_display_name' => $displayName !== '' ? $displayName : ('未配置规格 #'.(int) $product->id),
            'combined_display_name' => $combinedDisplayName !== '' ? $combinedDisplayName : ($displayName !== '' ? $displayName : ('未配置规格 #'.(int) $product->id)),
            'cpu_memory_display' => $cpuMemoryDisplay,
            'instance_spec_id' => (string) ($instanceSpec['instance_spec_id'] ?? ''),
            'instance_spec_value' => (string) ($instanceSpec['instance_spec_value'] ?? ''),
            'instance_spec_text' => $instanceSpecText,
            'instance_spec_alias' => (string) ($instanceSpec['instance_spec_alias'] ?? ''),
            'instance_spec_note' => (string) ($instanceSpec['instance_spec_note'] ?? ''),
            'cpu_display' => $cpuDisplay,
            'memory_display' => $memoryDisplay,
            ...$this->resolveCpuModelPayload((int) $product->id),
            'product_type' => $productType,
            'type' => $productType,
            'type_label' => ProductType::labelOf($productType),
            'pricing' => $pricing,
            'pricing_entries' => $this->buildPricingEntries($pricing, number_format((float) ($product->setup_fee ?? 0), 2, '.', '')),
            'primary_cycle' => $primaryCycle,
            'primary_price' => $primaryPrice,
            'setup_fee' => number_format((float) ($product->setup_fee ?? 0), 2, '.', ''),
            'stock' => $this->resolveDisplayStock($product),
            'auto_setup' => (int) ($product->auto_setup ?? 0),
        ];
    }

    private function transformSiteProductDetail(Product $product): array
    {
        $pricing = $this->formatPricingMap((array) ($product->pricing ?? []));
        $primaryCycle = '';
        $primaryPrice = '0.00';
        $setupFee = $this->formatAmount((float) ($product->setup_fee ?? 0));
        $group = $product->categoryMapping;
        $parentGroup = $group?->parent;
        $productType = $group ? $this->resolveCategoryTypeCode($group) : (string) $product->product_type;
        $parentProductType = $parentGroup ? $this->resolveCategoryTypeCode($parentGroup) : '';
        $displayNamePayload = $this->resolveProductDisplayNameResolver()->resolveForProduct($product);
        $displayName = trim((string) ($displayNamePayload['product_display_name'] ?? ''));
        $combinedDisplayName = trim((string) ($displayNamePayload['combined_display_name'] ?? ''));
        $cpuMemoryDisplay = trim((string) ($displayNamePayload['cpu_memory_display'] ?? ''));
        $instanceSpecText = trim((string) ($displayNamePayload['instance_spec_text'] ?? ''));
        $cpuDisplay = trim((string) ($displayNamePayload['cpu_display'] ?? ''));
        $memoryDisplay = trim((string) ($displayNamePayload['memory_display'] ?? ''));

        foreach ($pricing as $cycle => $amount) {
            if ((float) $amount > 0) {
                $primaryCycle = (string) $cycle;
                $primaryPrice = number_format((float) $amount, 2, '.', '');
                break;
            }
        }

        $siblings = Product::query()
            ->onSale()
            ->where('product_group_id', (int) (($product->product_group_id ?? 0) ?: ($product->category_id ?? 0)))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'product_type', 'purchase_requires', 'config_options']);

        $siblingsSpecMap = $this->instanceSpecCatalogService->resolveProductSpecMap(
            $siblings->pluck('id')->map(fn ($item) => (int) $item)->all()
        );
        $instanceSpec = $this->instanceSpecCatalogService->resolveProductSpecMap([(int) $product->id]);
        $instanceSpecItem = $instanceSpec[(int) $product->id] ?? [];

        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'display_name' => $displayName !== '' ? $displayName : ('未配置规格 #'.(int) $product->id),
            'product_display_name' => $displayName !== '' ? $displayName : ('未配置规格 #'.(int) $product->id),
            'combined_display_name' => $combinedDisplayName !== '' ? $combinedDisplayName : ($displayName !== '' ? $displayName : ('未配置规格 #'.(int) $product->id)),
            'cpu_memory_display' => $cpuMemoryDisplay,
            'instance_spec_id' => (string) ($instanceSpecItem['instance_spec_id'] ?? ''),
            'instance_spec_value' => (string) ($instanceSpecItem['instance_spec_value'] ?? ''),
            'instance_spec_text' => $instanceSpecText,
            'instance_spec_alias' => (string) ($instanceSpecItem['instance_spec_alias'] ?? ''),
            'instance_spec_note' => (string) ($instanceSpecItem['instance_spec_note'] ?? ''),
            'cpu_display' => $cpuDisplay,
            'memory_display' => $memoryDisplay,
            ...$this->resolveCpuModelPayload((int) $product->id),
            'product_type' => $productType,
            'type' => $productType,
            'type_label' => ProductType::labelOf($productType),
            'meta_title' => $this->normalizeNullableString($product->meta_title ?? null),
            'meta_description' => $this->normalizeNullableString($product->meta_description ?? null),
            'meta_keywords' => $this->normalizeNullableString($product->meta_keywords ?? null),
            'pricing' => $pricing,
            'pricing_entries' => $this->buildPricingEntries($pricing, $setupFee),
            'primary_cycle' => $primaryCycle,
            'primary_price' => $primaryPrice,
            'setup_fee' => $setupFee,
            'setup_fee_display' => $setupFee,
            'stock' => (int) ($product->stock ?? 0),
            'auto_setup' => (int) ($product->auto_setup ?? 0),
            'group' => [
                'id' => $this->resolvePublicCategoryId($group),
                'product_type' => $productType,
                'product_type_id' => ProductType::routeIdOf($productType),
                'name' => $group?->name,
                'display_name' => $displayName !== '' ? $displayName : ($group?->name ?? ''),
                'slogan' => (string) ($group?->slogan ?? ''),
                'slug' => $group?->slug,
                'parent_id' => $this->resolvePublicCategoryId($parentGroup),
                'parent_product_type' => $parentProductType,
                'parent_product_type_id' => ProductType::routeIdOf($parentProductType),
                'parent_name' => $parentGroup?->name,
                'parent_display_name' => $parentGroup?->name,
                'parent_slogan' => (string) ($parentGroup?->slogan ?? ''),
                'parent_slug' => $parentGroup?->slug,
                'full_name' => $parentGroup
                    ? $parentGroup->name.' / '.($group?->name ?? '')
                    : ($group?->name ?? ''),
            ],
            'config_options' => $this->trimSiteProductConfigOptions($product->config_options),
            'provision_module' => (string) ($product->provision_module ?? ''),
            'siblings' => $siblings
                ->map(function (Product $item) use ($siblingsSpecMap) {
                    $itemSpecItem = $siblingsSpecMap[(int) $item->id] ?? [];
                    $resolved = $this->resolveProductDisplayNameResolver()->resolveForProduct($item);
                    $displayName = trim((string) ($resolved['product_display_name'] ?? ''));
                    $combinedDisplayName = trim((string) ($resolved['combined_display_name'] ?? ''));
                    $instanceSpecText = trim((string) ($resolved['instance_spec_text'] ?? ''));

                    return [
                        'id' => (int) $item->id,
                        'name' => (string) $item->name,
                        'display_name' => $displayName !== '' ? $displayName : ('未配置规格 #'.(int) $item->id),
                        'product_display_name' => $displayName !== '' ? $displayName : ('未配置规格 #'.(int) $item->id),
                        'combined_display_name' => $combinedDisplayName !== '' ? $combinedDisplayName : ($displayName !== '' ? $displayName : ('未配置规格 #'.(int) $item->id)),
                        'instance_spec_text' => $instanceSpecText !== '' ? $instanceSpecText : (string) ($itemSpecItem['instance_spec_text'] ?? ''),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    private function resolveProductDisplayNameResolver(): ProductDisplayNameResolver
    {
        if ($this->productDisplayNameResolver instanceof ProductDisplayNameResolver) {
            return $this->productDisplayNameResolver;
        }

        return new ProductDisplayNameResolver($this->instanceSpecCatalogService);
    }

    private function normalizeSiteGroupIds(array $groupIds): array
    {
        return collect($groupIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function visibleSiteCategoryQuery(?string $productType = null): Builder
    {
        $visibleProductTypes = ProductType::visibleValues();

        if ($visibleProductTypes === []) {
            return ProductCategory::query()->whereRaw('1 = 0');
        }

        return ProductCategory::query()
            ->visible()
            ->whereIn('product_type', $visibleProductTypes)
            ->when($productType, fn (Builder $query) => $query->where('product_type', $productType));
    }

    private function resolveVisibleCategory(int $groupId): ?ProductCategory
    {
        if ($groupId <= 0) {
            return null;
        }

        $category = $this->visibleSiteCategoryQuery()
            ->with(['parent'])
            ->whereKey($groupId)
            ->first();

        return $category instanceof ProductCategory ? $category : null;
    }

    /**
     * @param  array<int, int>  $groupIds
     * @return array<int, int>
     */
    private function resolveVisibleCategoryIdMapByInputs(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        $categories = $this->visibleSiteCategoryQuery()
            ->select(['id'])
            ->whereIn('id', $groupIds)
            ->get();

        $categoriesById = $categories->keyBy(fn (ProductCategory $category) => (string) $category->id);

        $resolved = [];
        foreach ($groupIds as $groupId) {
            $lookupKey = (string) $groupId;
            $category = $categoriesById->get($lookupKey);
            if ($category instanceof ProductCategory) {
                $resolved[$groupId] = (int) $category->id;
            }
        }

        return $resolved;
    }

    private function resolvePublicCategoryId(?ProductCategory $category): ?int
    {
        return $category ? (int) $category->id : null;
    }

    private function resolveCategoryTypeCode(?ProductCategory $category): string
    {
        return trim((string) ($category?->product_type ?? ''));
    }

    private function trimSiteProductConfigOptions(mixed $configOptions): array
    {
        if (! is_array($configOptions)) {
            return [];
        }

        return collect($configOptions)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item, int $index) {
                return [
                    'id' => isset($item['id']) ? (int) $item['id'] : 0,
                    'field' => trim((string) ($item['field'] ?? '')),
                    'spec_key' => trim((string) ($item['spec_key'] ?? '')),
                    'name' => trim((string) ($item['name'] ?? $item['option_name'] ?? '')),
                    'description' => trim((string) ($item['description'] ?? '')),
                    'hidden' => (int) ($item['hidden'] ?? 0),
                    'required' => (int) ($item['required'] ?? 0),
                    'sort_order' => (int) ($item['sort_order'] ?? $item['order'] ?? ($index + 1)),
                    'option_type' => isset($item['option_type']) ? (int) $item['option_type'] : null,
                    'option_mode' => trim((string) ($item['option_mode'] ?? '')),
                    'parameter' => trim((string) ($item['parameter'] ?? '')),
                    'qty_minimum' => $item['qty_minimum'] ?? null,
                    'qty_maximum' => $item['qty_maximum'] ?? null,
                    'qty_step' => $item['qty_step'] ?? null,
                    'qty_stage' => $item['qty_stage'] ?? null,
                    'suffix_text' => trim((string) ($item['suffix_text'] ?? '')),
                    'sub' => $this->trimSiteProductConfigSubOptions($item['sub'] ?? []),
                ];
            })
            ->values()
            ->all();
    }

    private function trimSiteProductConfigSubOptions(mixed $subOptions): array
    {
        if (! is_array($subOptions)) {
            return [];
        }

        return collect($subOptions)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item, int $index) {
                return [
                    'id' => isset($item['id']) ? (string) $item['id'] : '',
                    'label' => trim((string) ($item['label'] ?? '')),
                    'version' => trim((string) ($item['version'] ?? '')),
                    'option_name' => trim((string) ($item['option_name'] ?? '')),
                    'hidden' => (int) ($item['hidden'] ?? 0),
                    'sort_order' => (int) ($item['sort_order'] ?? $item['order'] ?? $index),
                    'qty_minimum' => $item['qty_minimum'] ?? null,
                    'qty_maximum' => $item['qty_maximum'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function formatPricingMap(array $pricing): array
    {
        return collect($pricing)
            ->mapWithKeys(fn ($amount, $cycle) => [(string) $cycle => $this->formatAmount((float) $amount)])
            ->all();
    }

    private function buildPricingEntries(array $pricing, string $setupFee): array
    {
        return collect($pricing)
            ->map(function ($amount, $cycle) use ($setupFee) {
                $normalizedAmount = $this->formatAmount((float) $amount);

                return [
                    'cycle' => (string) $cycle,
                    'label' => match ((string) $cycle) {
                        'monthly' => '月付',
                        'quarterly' => '季付',
                        'semiannually' => '半年付',
                        'annually' => '年付',
                        'biennially' => '两年付',
                        'triennially' => '三年付',
                        'one_time', 'onetime' => '一次性',
                        default => (string) $cycle,
                    },
                    'amount' => $normalizedAmount,
                    'setup_fee' => $setupFee,
                    'total_amount' => $this->formatAmount((float) $normalizedAmount + (float) $setupFee),
                ];
            })
            ->values()
            ->all();
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * @return array{cpu_model_name: string, cpu_base_frequency: string, cpu_turbo_frequency: string}
     */
    private function resolveCpuModelPayload(int $productId): array
    {
        if ($productId <= 0) {
            return [
                'cpu_model_name' => '',
                'cpu_base_frequency' => '',
                'cpu_turbo_frequency' => '',
            ];
        }

        foreach ($this->cpuModelCatalogService->getCatalog() as $group) {
            $models = is_array($group['models'] ?? null) ? $group['models'] : [];

            foreach ($models as $model) {
                if (! is_array($model)) {
                    continue;
                }

                $bindings = is_array($model['bindings'] ?? null) ? $model['bindings'] : [];

                foreach ($bindings as $binding) {
                    if (! is_array($binding)) {
                        continue;
                    }

                    if ((int) ($binding['product_id'] ?? 0) !== $productId) {
                        continue;
                    }

                    return [
                        'cpu_model_name' => trim((string) ($model['name'] ?? '')),
                        'cpu_base_frequency' => trim((string) ($model['base_frequency'] ?? '')),
                        'cpu_turbo_frequency' => trim((string) ($model['turbo_frequency'] ?? '')),
                    ];
                }
            }
        }

        return [
            'cpu_model_name' => '',
            'cpu_base_frequency' => '',
            'cpu_turbo_frequency' => '',
        ];
    }

    private function rememberSitePayload(string $cacheSuffix, int $ttlSeconds, callable $resolver): array
    {
        return Cache::remember(
            $this->siteCacheKey($cacheSuffix),
            now()->addSeconds($ttlSeconds),
            $resolver
        );
    }
}
