<?php

declare(strict_types=1);

namespace App\Services\ProductCatalog;

use App\Constants\ProductType;
use App\Models\FirstProductGroup;
use App\Models\Product;
use App\Models\SecondProductGroup;
use App\Models\ThirdProductGroup;
use App\Services\ProductCatalog\Concerns\HandlesProductCatalogHelpers;
use App\Support\ProductGroupHierarchyFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ProductSiteService
{
    use HandlesProductCatalogHelpers;

    private const SITE_CATALOG_CACHE_TTL_SECONDS = 900; // 优化：从 300s 提升到 900s（15分钟）

    /**
     * @var array<int, array{cpu_model_name: string, cpu_base_frequency: string, cpu_turbo_frequency: string}>|null
     */
    private ?array $cpuModelPayloadByProductId = null;

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

                $firstGroups = FirstProductGroup::query()
                    ->whereIn('code', $visibleProductTypes)
                    ->get()
                    ->keyBy('code');

                $groupCounts = SecondProductGroup::query()
                    ->join('first_product_groups', 'first_product_groups.id', '=', 'second_product_groups.first_product_group_id')
                    ->where('second_product_groups.is_visible', 1)
                    ->where('first_product_groups.is_visible', 1)
                    ->whereIn('first_product_groups.code', $visibleProductTypes)
                    ->selectRaw('first_product_groups.code as product_type, COUNT(second_product_groups.id) as group_count')
                    ->groupBy('first_product_groups.code')
                    ->pluck('group_count', 'product_type');

                $productCounts = Product::query()
                    ->onSale()
                    ->join('first_product_groups', 'first_product_groups.id', '=', 'products.first_product_group_id')
                    ->join('second_product_groups', 'second_product_groups.id', '=', 'products.second_product_group_id')
                    ->leftJoin('third_product_groups', 'third_product_groups.id', '=', 'products.third_product_group_id')
                    ->where('first_product_groups.is_visible', 1)
                    ->where('second_product_groups.is_visible', 1)
                    ->where(function (Builder $query): void {
                        $query
                            ->whereNull('products.third_product_group_id')
                            ->orWhere('third_product_groups.is_visible', 1);
                    })
                    ->whereIn('first_product_groups.code', $visibleProductTypes)
                    ->selectRaw('first_product_groups.code as product_type, COUNT(products.id) as product_count')
                    ->groupBy('first_product_groups.code')
                    ->pluck('product_count', 'product_type');

                return collect(ProductType::visibleItems())
                    ->map(function (array $item) use ($firstGroups, $groupCounts, $productCounts) {
                        $value = (string) ($item['value'] ?? '');
                        $firstGroup = $firstGroups->get($value);
                        $groupCount = (int) ($groupCounts[$value] ?? 0);
                        $label = $firstGroup instanceof FirstProductGroup
                            ? (string) $firstGroup->name
                            : (string) ($item['label'] ?? ProductType::labelOf($value));

                        return [
                            'id' => (int) ($item['internal_id'] ?? ProductType::routeIdOf($value)),
                            'value' => $value,
                            'label' => $label,
                            'first_product_group_id' => $firstGroup instanceof FirstProductGroup ? (int) $firstGroup->id : null,
                            'first_product_group_code' => $value,
                            'first_product_group_name' => $label,
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

                return $this->visibleSecondProductGroupQuery($productType)
                    ->select([
                        'second_product_groups.id',
                        'second_product_groups.first_product_group_id',
                        'second_product_groups.name',
                        'second_product_groups.description',
                        'second_product_groups.slug',
                        'second_product_groups.sort_order',
                    ])
                    ->with(['firstProductGroup'])
                    ->withCount([
                        'thirdProductGroups as children_count' => fn (Builder $query) => $query->where('is_visible', 1),
                        'products as direct_product_count' => fn (Builder $query) => $query->onSale()->whereNull('third_product_group_id'),
                    ])
                    ->selectSub(
                        Product::query()
                            ->selectRaw('COUNT(*)')
                            ->join('third_product_groups', 'third_product_groups.id', '=', 'products.third_product_group_id')
                            ->whereColumn('third_product_groups.second_product_group_id', 'second_product_groups.id')
                            ->where('third_product_groups.is_visible', 1)
                            ->where('products.status', 1),
                        'child_product_count'
                    )
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (SecondProductGroup $group) => $this->transformSiteRootGroup($group))
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
                $secondGroup = $this->resolveVisibleSecondProductGroup($groupId);
                if (! $secondGroup) {
                    return [];
                }

                return ThirdProductGroup::query()
                    ->select([
                        'id',
                        'second_product_group_id',
                        'name',
                        'description',
                        'slug',
                        'sort_order',
                    ])
                    ->where('is_visible', 1)
                    ->where('second_product_group_id', (int) $secondGroup->id)
                    ->with(['secondProductGroup.firstProductGroup'])
                    ->withCount([
                        'products as product_count' => fn (Builder $query) => $query->onSale(),
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (ThirdProductGroup $group) => $this->transformSiteChildGroup($group))
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
                $secondGroup = $this->resolveVisibleSecondProductGroup($groupId);
                if (! $secondGroup) {
                    return [
                        'effective_product_group_id' => $groupId,
                        'effective_product_group_level' => null,
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
                    'effective_product_group_id' => (int) $secondGroup->id,
                    'effective_product_group_level' => 2,
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

        $visibleGroupIds = $this->resolveVisibleProductGroupIds($normalizedGroupIds);

        if ($visibleGroupIds['second'] === [] && $visibleGroupIds['third'] === []) {
            return [];
        }

        $productsByGroup = Product::query()
            ->onSale()
            ->where(function (Builder $query) use ($visibleGroupIds): void {
                if ($visibleGroupIds['second'] !== []) {
                    $query->orWhere(function (Builder $secondQuery) use ($visibleGroupIds): void {
                        $secondQuery
                            ->whereIn('second_product_group_id', $visibleGroupIds['second'])
                            ->whereNull('third_product_group_id');
                    });
                }

                if ($visibleGroupIds['third'] !== []) {
                    $query->orWhereIn('third_product_group_id', $visibleGroupIds['third']);
                }
            })
            ->with([
                'firstProductGroup',
                'secondProductGroup',
                'thirdProductGroup',
            ])
            ->select([
                'id',
                'product_type',
                ...Product::optionalSelectColumns([
                    'custom_display_name',
                    'first_product_group_id',
                    'second_product_group_id',
                    'third_product_group_id',
                    'service_type_code',
                ]),
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
            ->groupBy(fn (Product $product) => (int) ($product->third_product_group_id ?: $product->second_product_group_id));

        $instanceSpecMap = $this->instanceSpecCatalogService->resolveProductSpecMap(
            collect($productsByGroup)
                ->flatten(1)
                ->map(fn (Product $product) => (int) $product->id)
                ->values()
                ->all()
        );

        return collect($normalizedGroupIds)
            ->filter(fn (int $groupId) => in_array($groupId, $visibleGroupIds['second'], true) || in_array($groupId, $visibleGroupIds['third'], true))
            ->map(function (int $groupId) use ($productsByGroup, $instanceSpecMap) {
                return [
                    'effective_product_group_id' => $groupId,
                    'products' => $productsByGroup
                        ->get($groupId, collect())
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
            fn () => $this->visibleSecondProductGroupQuery()
                ->select('second_product_groups.*')
                ->with([
                    'firstProductGroup',
                    'thirdProductGroups' => fn ($query) => $query
                        ->where('is_visible', 1)
                        ->with(['products' => fn ($productQuery) => $productQuery
                            ->where('status', 1)
                            ->orderBy('sort_order')
                            ->orderBy('id')])
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                    'products' => fn ($query) => $query
                        ->where('status', 1)
                        ->whereNull('third_product_group_id')
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
            ->whereNotNull('first_product_group_id')
            ->whereNotNull('second_product_group_id')
            ->whereHas('firstProductGroup', fn (Builder $query) => $query
                ->where('is_visible', 1)
                ->whereIn('code', $visibleProductTypes))
            ->whereHas('secondProductGroup', fn (Builder $query) => $query->where('is_visible', 1))
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('third_product_group_id')
                    ->orWhereHas('thirdProductGroup', fn (Builder $thirdQuery) => $thirdQuery->where('is_visible', 1));
            });
    }

    private function findSaleProductForDetail(int $productId): ?Product
    {
        return $this->saleProductQuery()
            ->select([
                'id',
                'supplier_id',
                'product_type',
                ...Product::optionalSelectColumns([
                    'custom_display_name',
                    'first_product_group_id',
                    'second_product_group_id',
                    'third_product_group_id',
                    'service_type_code',
                ]),
                'pricing',
                'setup_fee',
                'config_options',
                'purchase_requires',
                'stock',
                'auto_setup',
                'provision_module',
            ])
            ->with([
                'firstProductGroup',
                'secondProductGroup',
                'thirdProductGroup',
            ])
            ->whereKey($productId)
            ->first();
    }

    private function transformSiteRootGroup(SecondProductGroup $group): array
    {
        $directProductCount = (int) ($group->direct_product_count ?? 0);
        $childProductCount = (int) ($group->child_product_count ?? 0);
        $firstGroup = $group->firstProductGroup;
        $productType = trim((string) ($firstGroup?->code ?? ''));

        return [
            'id' => (int) $group->id,
            'product_type' => $productType,
            'product_type_id' => ProductType::routeIdOf($productType),
            'product_type_label' => ProductType::labelOf($productType),
            'first_product_group_id' => $firstGroup instanceof FirstProductGroup ? (int) $firstGroup->id : null,
            'first_product_group_code' => $productType,
            'first_product_group_name' => (string) ($firstGroup?->name ?? ProductType::labelOf($productType)),
            'second_product_group_id' => (int) $group->id,
            'second_product_group_name' => (string) $group->name,
            'second_product_group_parent_id' => $firstGroup instanceof FirstProductGroup ? (int) $firstGroup->id : null,
            'second_product_group_parent_name' => (string) ($firstGroup?->name ?? ProductType::labelOf($productType)),
            'third_product_group_id' => null,
            'third_product_group_name' => null,
            'effective_product_group_id' => (int) $group->id,
            'effective_product_group_level' => 2,
            'service_type_code' => $productType,
            'name' => (string) $group->name,
            'slogan' => (string) ($group->description ?? ''),
            'slug' => (string) ($group->slug ?? ''),
            'children_count' => (int) ($group->children_count ?? 0),
            'direct_product_count' => $directProductCount,
            'product_count' => $directProductCount + $childProductCount,
        ];
    }

    private function transformSiteChildGroup(ThirdProductGroup $group): array
    {
        $secondGroup = $group->secondProductGroup;
        $firstGroup = $secondGroup?->firstProductGroup;
        $productType = trim((string) ($firstGroup?->code ?? ''));

        return [
            'id' => (int) $group->id,
            'parent_id' => $secondGroup instanceof SecondProductGroup ? (int) $secondGroup->id : null,
            'product_type' => $productType,
            'product_type_id' => ProductType::routeIdOf($productType),
            'product_type_label' => ProductType::labelOf($productType),
            'first_product_group_id' => $firstGroup instanceof FirstProductGroup ? (int) $firstGroup->id : null,
            'first_product_group_code' => $productType,
            'first_product_group_name' => (string) ($firstGroup?->name ?? ProductType::labelOf($productType)),
            'second_product_group_id' => $secondGroup instanceof SecondProductGroup ? (int) $secondGroup->id : null,
            'second_product_group_name' => (string) ($secondGroup?->name ?? ''),
            'second_product_group_parent_id' => $firstGroup instanceof FirstProductGroup ? (int) $firstGroup->id : null,
            'second_product_group_parent_name' => (string) ($firstGroup?->name ?? ProductType::labelOf($productType)),
            'third_product_group_id' => (int) $group->id,
            'third_product_group_name' => (string) $group->name,
            'effective_product_group_id' => (int) $group->id,
            'effective_product_group_level' => 3,
            'service_type_code' => $productType,
            'name' => (string) $group->name,
            'slogan' => (string) ($group->description ?? ''),
            'slug' => (string) ($group->slug ?? ''),
            'product_count' => (int) ($group->product_count ?? 0),
        ];
    }

    private function transformSiteProductCard(Product $product, array $instanceSpec = []): array
    {
        $hierarchyFields = ProductGroupHierarchyFields::fromProduct($product);
        $productType = (string) ($hierarchyFields['service_type_code'] ?? $product->product_type);
        $pricing = (array) ($product->pricing ?? []);
        $primaryCycle = '';
        $primaryPrice = '0.00';
        $displayNamePayload = $this->resolveProductDisplayNameResolver()->resolveForProduct($product, [
            'instance_spec_text' => (string) ($instanceSpec['instance_spec_text'] ?? ''),
        ]);
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
            ...$this->resolveCpuModelPayload($product),
            'product_type' => $productType,
            'type' => $productType,
            'type_label' => ProductType::labelOf($productType),
            ...$hierarchyFields,
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
        $hierarchyFields = ProductGroupHierarchyFields::fromProduct($product);
        $productType = (string) ($hierarchyFields['service_type_code'] ?? $product->product_type);
        $instanceSpec = $this->instanceSpecCatalogService->resolveProductSpecMap([(int) $product->id]);
        $instanceSpecItem = $instanceSpec[(int) $product->id] ?? [];
        $displayNamePayload = $this->resolveProductDisplayNameResolver()->resolveForProduct($product, [
            'instance_spec_text' => (string) ($instanceSpecItem['instance_spec_text'] ?? ''),
        ]);
        $displayName = trim((string) ($displayNamePayload['product_display_name'] ?? ''));
        $combinedDisplayName = trim((string) ($displayNamePayload['combined_display_name'] ?? ''));
        $cpuMemoryDisplay = trim((string) ($displayNamePayload['cpu_memory_display'] ?? ''));
        $instanceSpecText = trim((string) ($displayNamePayload['instance_spec_text'] ?? ''));
        $cpuDisplay = trim((string) ($displayNamePayload['cpu_display'] ?? ''));
        $memoryDisplay = trim((string) ($displayNamePayload['memory_display'] ?? ''));

        // 商品所在分组及其上级分组的标语，按三级结构（second/third）取 description。
        $effectiveGroup = $product->relationLoaded('thirdProductGroup') && $product->thirdProductGroup
            ? $product->thirdProductGroup
            : ($product->relationLoaded('secondProductGroup') ? $product->secondProductGroup : null);
        $parentGroup = ($product->thirdProductGroup ?? null)
            ? ($product->relationLoaded('secondProductGroup') ? $product->secondProductGroup : null)
            : ($product->relationLoaded('firstProductGroup') ? $product->firstProductGroup : null);
        $groupSlogan = trim((string) ($effectiveGroup?->description ?? ''));
        $parentSlogan = trim((string) ($parentGroup?->description ?? ''));

        foreach ($pricing as $cycle => $amount) {
            if ((float) $amount > 0) {
                $primaryCycle = (string) $cycle;
                $primaryPrice = number_format((float) $amount, 2, '.', '');
                break;
            }
        }

        $siblings = Product::query()
            ->onSale()
            ->where('second_product_group_id', (int) ($product->second_product_group_id ?? 0))
            ->when(
                (int) ($product->third_product_group_id ?? 0) > 0,
                fn (Builder $query) => $query->where('third_product_group_id', (int) $product->third_product_group_id),
                fn (Builder $query) => $query->whereNull('third_product_group_id')
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'product_type',
                'service_type_code',
                'first_product_group_id',
                'second_product_group_id',
                'third_product_group_id',
                ...Product::optionalSelectColumns(['custom_display_name']),
                'purchase_requires',
                'config_options',
            ]);

        $siblingsSpecMap = $this->instanceSpecCatalogService->resolveProductSpecMap(
            $siblings->pluck('id')->map(fn ($item) => (int) $item)->all()
        );

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
            ...$this->resolveCpuModelPayload($product),
            'product_type' => $productType,
            'type' => $productType,
            'type_label' => ProductType::labelOf($productType),
            ...$hierarchyFields,
            'pricing' => $pricing,
            'pricing_entries' => $this->buildPricingEntries($pricing, $setupFee),
            'primary_cycle' => $primaryCycle,
            'primary_price' => $primaryPrice,
            'setup_fee' => $setupFee,
            'setup_fee_display' => $setupFee,
            'stock' => (int) ($product->stock ?? 0),
            'auto_setup' => (int) ($product->auto_setup ?? 0),
            'group' => [
                'id' => $hierarchyFields['effective_product_group_id'],
                'product_type' => $productType,
                'product_type_id' => ProductType::routeIdOf($productType),
                'name' => $hierarchyFields['third_product_group_name'] ?? $hierarchyFields['second_product_group_name'],
                'display_name' => $displayName !== '' ? $displayName : (string) ($hierarchyFields['third_product_group_name'] ?? $hierarchyFields['second_product_group_name'] ?? ''),
                'slogan' => $groupSlogan,
                'slug' => null,
                'parent_id' => $hierarchyFields['second_product_group_id'],
                'parent_product_type' => $productType,
                'parent_product_type_id' => ProductType::routeIdOf($productType),
                'parent_name' => $hierarchyFields['second_product_group_name'],
                'parent_display_name' => $hierarchyFields['second_product_group_name'],
                'parent_slogan' => $parentSlogan,
                'parent_slug' => null,
                ...$hierarchyFields,
                'full_name' => $this->resolveHierarchyFullName($hierarchyFields),
            ],
            'config_options' => $this->trimSiteProductConfigOptions($product->config_options),
            'provision_module' => (string) ($product->provision_module ?? ''),
            'siblings' => $siblings
                ->map(function (Product $item) use ($siblingsSpecMap) {
                    $itemSpecItem = $siblingsSpecMap[(int) $item->id] ?? [];
                    $resolved = $this->resolveProductDisplayNameResolver()->resolveForProduct($item, [
                        'instance_spec_text' => (string) ($itemSpecItem['instance_spec_text'] ?? ''),
                    ]);
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

    private function visibleSecondProductGroupQuery(?string $productType = null): Builder
    {
        $visibleProductTypes = ProductType::visibleValues();

        if ($visibleProductTypes === []) {
            return SecondProductGroup::query()->whereRaw('1 = 0');
        }

        return SecondProductGroup::query()
            ->select('second_product_groups.*')
            ->join('first_product_groups', 'first_product_groups.id', '=', 'second_product_groups.first_product_group_id')
            ->where('second_product_groups.is_visible', 1)
            ->where('first_product_groups.is_visible', 1)
            ->whereIn('first_product_groups.code', $visibleProductTypes)
            ->when($productType, fn (Builder $query) => $query->where('first_product_groups.code', $productType));
    }

    private function resolveVisibleSecondProductGroup(int $groupId): ?SecondProductGroup
    {
        if ($groupId <= 0) {
            return null;
        }

        $group = $this->visibleSecondProductGroupQuery()
            ->with(['firstProductGroup'])
            ->where('second_product_groups.id', $groupId)
            ->first();

        return $group instanceof SecondProductGroup ? $group : null;
    }

    /**
     * @param  array<int, int>  $groupIds
     * @return array{second: array<int, int>, third: array<int, int>}
     */
    private function resolveVisibleProductGroupIds(array $groupIds): array
    {
        if ($groupIds === []) {
            return ['second' => [], 'third' => []];
        }

        $secondIds = $this->visibleSecondProductGroupQuery()
            ->whereIn('second_product_groups.id', $groupIds)
            ->pluck('second_product_groups.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $thirdIds = ThirdProductGroup::query()
            ->join('second_product_groups', 'second_product_groups.id', '=', 'third_product_groups.second_product_group_id')
            ->join('first_product_groups', 'first_product_groups.id', '=', 'second_product_groups.first_product_group_id')
            ->where('third_product_groups.is_visible', 1)
            ->where('second_product_groups.is_visible', 1)
            ->where('first_product_groups.is_visible', 1)
            ->whereIn('first_product_groups.code', ProductType::visibleValues())
            ->whereIn('third_product_groups.id', $groupIds)
            ->pluck('third_product_groups.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return [
            'second' => $secondIds,
            'third' => $thirdIds,
        ];
    }

    private function resolveHierarchyFullName(array $hierarchyFields): string
    {
        return collect([
            $hierarchyFields['first_product_group_name'] ?? '',
            $hierarchyFields['second_product_group_name'] ?? '',
            $hierarchyFields['third_product_group_name'] ?? '',
        ])
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->implode(' / ');

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
    private function resolveCpuModelPayload(Product $product): array
    {
        $productId = (int) $product->id;
        if ($productId <= 0) {
            return [
                'cpu_model_name' => '',
                'cpu_base_frequency' => '',
                'cpu_turbo_frequency' => '',
            ];
        }

        $lookupIds = array_values(array_unique(array_filter([
            $productId,
            $this->resolveSplitSourceProductId($product),
        ], static fn (int $id): bool => $id > 0)));

        $cpuModelPayloads = $this->cpuModelPayloadByProductId();
        foreach ($lookupIds as $lookupId) {
            if (isset($cpuModelPayloads[$lookupId])) {
                return $cpuModelPayloads[$lookupId];
            }
        }

        return [
            'cpu_model_name' => '',
            'cpu_base_frequency' => '',
            'cpu_turbo_frequency' => '',
        ];
    }

    private function resolveSplitSourceProductId(Product $product): int
    {
        $split = (array) (($product->purchase_requires ?? [])['upstream_split'] ?? []);
        $sourceProductId = (int) ($split['source_product_id'] ?? 0);

        return $sourceProductId !== (int) $product->id ? $sourceProductId : 0;
    }

    /**
     * @return array<int, array{cpu_model_name: string, cpu_base_frequency: string, cpu_turbo_frequency: string}>
     */
    private function cpuModelPayloadByProductId(): array
    {
        if ($this->cpuModelPayloadByProductId !== null) {
            return $this->cpuModelPayloadByProductId;
        }

        $payloads = [];
        foreach ($this->cpuModelCatalogService->getCatalog() as $group) {
            $models = is_array($group['models'] ?? null) ? $group['models'] : [];

            foreach ($models as $model) {
                if (! is_array($model)) {
                    continue;
                }

                $payload = [
                    'cpu_model_name' => trim((string) ($model['name'] ?? '')),
                    'cpu_base_frequency' => trim((string) ($model['base_frequency'] ?? '')),
                    'cpu_turbo_frequency' => trim((string) ($model['turbo_frequency'] ?? '')),
                ];
                $bindings = is_array($model['bindings'] ?? null) ? $model['bindings'] : [];

                foreach ($bindings as $binding) {
                    if (! is_array($binding)) {
                        continue;
                    }

                    $productId = (int) ($binding['product_id'] ?? 0);
                    if ($productId <= 0 || isset($payloads[$productId])) {
                        continue;
                    }

                    $payloads[$productId] = $payload;
                }
            }
        }

        return $this->cpuModelPayloadByProductId = $payloads;
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
