<?php

declare(strict_types=1);

namespace App\Services\ProductCatalog;

use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductCatalog\Concerns\HandlesProductCatalogHelpers;
use App\Services\System\OperationLogService;
use App\Services\System\SettingService;
use App\Support\ProductProvisionHostname;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductAdminService
{
    use HandlesProductCatalogHelpers;

    public function __construct(
        private readonly SettingService $settingService,
        private readonly OperationLogService $operationLogService,
        private readonly ?ProductDisplayNameResolver $productDisplayNameResolver = null,
    ) {}

    public function adminProductList(array $filters, int $perPage = 20)
    {
        $query = $this->applyAdminProductFilters(
            Product::query()
                ->select([
                    'id',
                    'product_group_id',
                    'product_type',
                    'remark',
                    'pricing',
                    'config_options',
                    'purchase_requires',
                    'stock',
                    'status',
                    'sort_order',
                    'provision_module',
                    'auto_setup',
                    'supplier_id',
                    'supplier_product_id',
                    'updated_at',
                ])
                ->with(['categoryMapping.parent'])
                ->withCount(['orders', 'services']),
            $filters
        );

        return $query
            ->orderBy('sort_order')
            ->orderByDesc('status')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function reorderAdminProducts(array $filters, int $page, int $pageSize, array $productIds): array
    {
        $orderedProductIds = collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        throw_if($orderedProductIds->count() < 2, new BusinessException('至少需要两个商品才能拖动排序'));

        $sortedIds = $this->applyAdminProductFilters(Product::query(), $filters)
            ->orderBy('sort_order')
            ->orderByDesc('status')
            ->orderByDesc('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $sliceOffset = max($page - 1, 0) * max($pageSize, 1);
        $currentPageIds = array_slice($sortedIds, $sliceOffset, count($productIds));

        throw_if(empty($currentPageIds), new BusinessException('当前页没有可排序的商品'));
        throw_if(
            count($currentPageIds) !== $orderedProductIds->count()
            || collect($currentPageIds)->sort()->values()->all() !== $orderedProductIds->sort()->values()->all(),
            new BusinessException('商品列表已发生变化，请刷新后重新拖动排序')
        );

        $reorderedIds = $sortedIds;
        array_splice($reorderedIds, $sliceOffset, count($currentPageIds), $orderedProductIds->all());

        $sortMap = [];
        foreach ($reorderedIds as $index => $productId) {
            $sortMap[(int) $productId] = $index + 1;
        }

        DB::transaction(function () use ($sortMap) {
            $bindings = [];
            $caseSql = collect($sortMap)
                ->map(function (int $sortOrder, int $productId) use (&$bindings) {
                    $bindings[] = $productId;
                    $bindings[] = $sortOrder;

                    return 'WHEN ? THEN ?';
                })
                ->implode(' ');
            $productPlaceholders = implode(',', array_fill(0, count($sortMap), '?'));
            $bindings[] = now();
            array_push($bindings, ...array_keys($sortMap));

            DB::statement(
                "UPDATE products SET sort_order = CASE id {$caseSql} END, updated_at = ? WHERE id IN ({$productPlaceholders})",
                $bindings
            );
        });

        $this->forgetSiteCatalogCache();

        return [
            'updated_count' => count($sortMap),
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    public function moveAdminProduct(
        Product $product,
        int $targetCategoryId,
        ?int $referenceProductId,
        string $position = 'append',
    ): array {
        $targetCategory = ProductCategory::query()->find($targetCategoryId);
        throw_if(! $targetCategory, new BusinessException('目标分组不存在'));
        throw_if(
            $targetCategory->parent_id === null && $targetCategory->children()->exists(),
            new BusinessException('商品必须拖到最终可售菜单下')
        );
        throw_if(! in_array($position, ['before', 'after', 'append'], true), new BusinessException('拖动位置参数不正确'));

        $sourceCategoryId = (int) ($product->category_id ?? 0);
        $sameScope = $sourceCategoryId === (int) $targetCategory->id;
        $sourceIds = $this->resolveProductScopeIds($sourceCategoryId);
        $targetIds = $sameScope ? $sourceIds : $this->resolveProductScopeIds((int) $targetCategory->id);

        $reorderedTargetIds = $this->buildReorderedIds(
            $targetIds,
            (int) $product->id,
            $referenceProductId,
            $position,
            '商品'
        );
        $remainingSourceIds = $sameScope
            ? []
            : array_values(array_filter($sourceIds, fn (int $id) => $id !== (int) $product->id));

        DB::transaction(function () use (
            $product,
            $targetCategory,
            $sourceCategoryId,
            $sameScope,
            $reorderedTargetIds,
            $remainingSourceIds,
        ) {
            if ($sourceCategoryId !== (int) $targetCategory->id) {
                Product::withoutEvents(function () use ($product, $targetCategory): void {
                    $payload = [
                        'category_id' => (int) $targetCategory->id,
                        'product_type' => (string) $targetCategory->product_type,
                    ];

                    $product->update($payload);
                });
            }

            $this->resequenceProductIds($reorderedTargetIds);

            if (! $sameScope) {
                $this->resequenceProductIds($remainingSourceIds);
            }
        });

        $this->forgetSiteCatalogCache();

        return [
            'product_id' => (int) $product->id,
            'target_category_id' => (int) $targetCategory->id,
            'target_group_id' => (int) (($targetCategory->legacy_group_id ?? 0) ?: $targetCategory->id),
            'position' => $position,
        ];
    }

    public function adminProductOwners(Product $product, array $filters, int $perPage = 20): array
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        $serviceQuery = Service::query()->where('product_id', $product->id);
        $ownerQuery = User::query()
            ->whereHas('services', fn ($query) => $query->where('product_id', $product->id))
            ->when($keyword !== '', fn ($query) => $query->search($keyword))
            ->withCount([
                'services as product_services_count' => fn ($query) => $query->where('product_id', $product->id),
                'services as active_product_services_count' => fn ($query) => $query
                    ->where('product_id', $product->id)
                    ->where('status', ServiceStatus::ACTIVE),
            ])
            ->withMax([
                'services as latest_product_service_created_at' => fn ($query) => $query->where('product_id', $product->id),
            ], 'created_at')
            ->withMax([
                'services as latest_product_service_expires_at' => fn ($query) => $query->where('product_id', $product->id),
            ], 'expires_at')
            ->orderByDesc('latest_product_service_created_at')
            ->orderByDesc('id');

        $paginator = $ownerQuery->paginate($perPage);

        return [
            'list' => collect($paginator->items())
                ->map(fn (User $user) => $this->transformProductOwnerItem($user))
                ->values()
                ->all(),
            'summary' => [
                'owners_total' => (clone $serviceQuery)->distinct('user_id')->count('user_id'),
                'services_total' => (clone $serviceQuery)->count(),
                'active_services_total' => (clone $serviceQuery)
                    ->where('status', ServiceStatus::ACTIVE)
                    ->count(),
                'latest_service_created_at' => $this->formatDateTimeValue((clone $serviceQuery)->max('created_at')),
            ],
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    public function createProduct(array $data): Product
    {
        $product = DB::transaction(function () use ($data) {
            $prepared = $this->prepareProductPayload($data);

            /** @var Product $product */
            $product = Product::withoutEvents(
                fn () => Product::query()->create($prepared['base'])
            );

            return $this->loadProductSnapshot($product);
        });

        $this->forgetSiteCatalogCache();

        return $product;
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $updatedProduct = DB::transaction(function () use ($product, $data) {
            $prepared = $this->prepareProductPayload($data);
            Product::withoutEvents(fn () => $product->update($prepared['base']));

            return $this->loadProductSnapshot($product);
        });

        $this->forgetSiteCatalogCache();

        return $updatedProduct;
    }

    public function batchUpdateProvisionHostname(array $data, array $context = []): array
    {
        $productIds = collect((array) ($data['product_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        throw_if($productIds->isEmpty(), new BusinessException('请选择需要设置的商品'));

        $rule = $this->normalizeProvisionHostnameRule($data['provision_hostname'] ?? []);
        $products = Product::query()
            ->whereIn('id', $productIds->all())
            ->get()
            ->keyBy('id');

        throw_if(
            $products->count() !== $productIds->count(),
            new BusinessException('存在无效商品，请刷新后重试')
        );

        $orderedProducts = $productIds
            ->map(fn (int $id) => $products->get($id))
            ->filter(fn ($product) => $product instanceof Product)
            ->values();

        DB::transaction(function () use ($products, $rule): void {
            foreach ($products as $product) {
                $purchaseRequires = is_array($product->purchase_requires ?? null)
                    ? $product->purchase_requires
                    : [];

                if ($rule === null) {
                    unset($purchaseRequires['provision_hostname']);
                } else {
                    $purchaseRequires['provision_hostname'] = $rule;
                }

                Product::withoutEvents(function () use ($product, $purchaseRequires): void {
                    $product->forceFill([
                        'purchase_requires' => $purchaseRequires,
                    ])->save();
                });
            }
        });

        $this->forgetSiteCatalogCache();

        $resolvedRule = $rule ?? [
            'mode' => ProductProvisionHostname::MODE_SYSTEM,
            'value' => '',
            'length' => 12,
        ];

        $this->operationLogService->write(
            userId: ((int) ($context['operator_id'] ?? 0)) ?: null,
            userType: 'admin',
            action: 'product.provision_hostname.batch_update',
            module: 'product',
            targetId: $orderedProducts->count() === 1 ? (int) ($orderedProducts->first()?->id ?? 0) : null,
            detail: [
                'updated_count' => $orderedProducts->count(),
                'product_ids' => $orderedProducts->map(fn (Product $product) => (int) $product->id)->all(),
                'product_names' => $orderedProducts->map(fn (Product $product) => (string) $product->name)->filter()->values()->all(),
                'mode' => (string) ($resolvedRule['mode'] ?? ProductProvisionHostname::MODE_SYSTEM),
                'value' => (string) ($resolvedRule['value'] ?? ''),
                'length' => (int) ($resolvedRule['length'] ?? 12),
                'operator_name' => (string) ($context['operator_name'] ?? ''),
                'trace_id' => (string) ($context['trace_id'] ?? ''),
            ],
            ipAddress: ($context['ip_address'] ?? '') !== '' ? (string) $context['ip_address'] : null,
        );

        return [
            'updated_count' => $productIds->count(),
            'provision_hostname' => $resolvedRule,
        ];
    }

    public function batchUpdateCategory(array $data): array
    {
        $productIds = collect((array) ($data['product_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        throw_if($productIds->isEmpty(), new BusinessException('请选择需要调整分类的商品'));

        $targetCategory = $this->resolveTargetCategory([
            'category_id' => $data['target_category_id'] ?? null,
        ]);
        $targetCategory->loadMissing('parent');

        $products = Product::query()
            ->whereIn('id', $productIds->all())
            ->get()
            ->keyBy('id');

        throw_if(
            $products->count() !== $productIds->count(),
            new BusinessException('存在无效商品，请刷新后重试')
        );

        $orderedProducts = $productIds
            ->map(fn (int $id) => $products->get($id))
            ->filter(fn ($product) => $product instanceof Product)
            ->values();

        $targetCategoryId = (int) $targetCategory->id;
        $selectedIdSet = array_fill_keys($productIds->all(), true);
        $updatedCount = $orderedProducts
            ->filter(fn (Product $product) => (int) ($product->category_id ?? 0) !== $targetCategoryId)
            ->count();

        if ($updatedCount === 0) {
            return $this->buildBatchCategoryResult($productIds->count(), 0, $targetCategory);
        }

        $sourceScopeMap = $orderedProducts
            ->map(fn (Product $product) => (int) ($product->category_id ?? 0))
            ->filter(fn (int $categoryId) => $categoryId > 0 && $categoryId !== $targetCategoryId)
            ->unique()
            ->mapWithKeys(fn (int $categoryId) => [$categoryId => $this->resolveProductScopeIds($categoryId)])
            ->all();

        $targetScopeIds = array_values(array_filter(
            $this->resolveProductScopeIds($targetCategoryId),
            fn (int $productId) => ! isset($selectedIdSet[$productId])
        ));
        $reorderedTargetIds = array_merge($targetScopeIds, $productIds->all());

        DB::transaction(function () use (
            $productIds,
            $targetCategoryId,
            $targetCategory,
            $reorderedTargetIds,
            $sourceScopeMap,
            $selectedIdSet,
        ): void {
            Product::query()
                ->whereIn('id', $productIds->all())
                ->update([
                    'product_group_id' => $targetCategoryId,
                    'product_type' => (string) $targetCategory->product_type,
                    'updated_at' => now(),
                ]);

            $this->resequenceProductIds($reorderedTargetIds);

            foreach ($sourceScopeMap as $sourceScopeIds) {
                $remainingIds = array_values(array_filter(
                    $sourceScopeIds,
                    fn (int $productId) => ! isset($selectedIdSet[$productId])
                ));
                $this->resequenceProductIds($remainingIds);
            }
        });

        $this->forgetSiteCatalogCache();

        return $this->buildBatchCategoryResult($productIds->count(), $updatedCount, $targetCategory);
    }

    public function splitProducts(array $data): array
    {
        $productIds = $this->normalizeSplitProductIds($data);
        $products = $this->loadSplitProducts($productIds);

        $result = [
            'requested_count' => $productIds->count(),
            'created_count' => 0,
            'updated_count' => 0,
            'skipped_count' => 0,
            'items' => [],
        ];

        DB::transaction(function () use ($productIds, $products, &$result): void {
            foreach ($productIds as $productId) {
                /** @var Product $source */
                $source = $products->get($productId);
                $variants = $this->buildSplitProductVariants($source);

                if ($variants === []) {
                    $result['skipped_count']++;
                    $result['items'][] = [
                        'source_product_id' => (int) $source->id,
                        'source_display_name' => (string) ($this->resolveProductDisplayNameResolver()->resolveForProduct($source)['product_display_name'] ?? ''),
                        'action' => 'skipped',
                        'reason' => '未找到可拆分的 CPU 或内存子选项',
                    ];

                    continue;
                }

                $sourceVariantKey = $this->resolveSourceSplitVariantKey($source, $variants);

                foreach ($variants as $variant) {
                    $payload = $this->buildSplitProductPayload($source, $variant);
                    $existing = $sourceVariantKey !== ''
                        && $sourceVariantKey === (string) ($variant['variant_key'] ?? '')
                        ? $source
                        : $this->findExistingSplitProduct($source, (string) $variant['variant_key']);

                    if ($existing instanceof Product) {
                        Product::withoutEvents(fn () => $existing->forceFill($payload)->save());
                        $product = $existing->refresh();
                        $action = 'updated';
                        $result['updated_count']++;
                    } else {
                        $product = Product::withoutEvents(fn () => Product::query()->create($payload));
                        $action = 'created';
                        $result['created_count']++;
                    }

                    $result['items'][] = [
                        'source_product_id' => (int) $source->id,
                        'source_display_name' => (string) ($this->resolveProductDisplayNameResolver()->resolveForProduct($source)['product_display_name'] ?? ''),
                        'product_id' => (int) $product->id,
                        'display_name' => (string) ($this->resolveProductDisplayNameResolver()->resolveForProduct($product)['product_display_name'] ?? ''),
                        'variant_key' => (string) $variant['variant_key'],
                        'action' => $action,
                    ];
                }
            }
        });

        if (($result['created_count'] + $result['updated_count']) > 0) {
            $this->forgetSiteCatalogCache();
        }

        return $result;
    }

    private function normalizeSplitProductIds(array $data)
    {
        $productIds = collect((array) ($data['product_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        throw_if($productIds->isEmpty(), new BusinessException('请选择需要拆分的商品'));

        return $productIds;
    }

    private function loadSplitProducts($productIds)
    {
        $products = Product::query()
            ->whereIn('id', $productIds->all())
            ->get()
            ->keyBy('id');

        throw_if(
            $products->count() !== $productIds->count(),
            new BusinessException('存在无效商品，请刷新后重试')
        );

        return $products;
    }

    public function previewSplitProducts(array $data): array
    {
        $productIds = $this->normalizeSplitProductIds($data);
        $products = $this->loadSplitProducts($productIds);
        $items = [];
        $previewCount = 0;
        $skippedCount = 0;

        foreach ($productIds as $productId) {
            /** @var Product $source */
            $source = $products->get($productId);
            $variants = $this->buildSplitProductVariants($source);

            if ($variants === []) {
                $skippedCount++;
                $items[] = [
                    'source_product_id' => (int) $source->id,
                    'action' => 'skipped',
                    'reason' => '未找到可拆分的 CPU 或内存子选项',
                    'variants' => [],
                ];

                continue;
            }

            $sourceVariantKey = $this->resolveSourceSplitVariantKey($source, $variants);
            $previewVariants = [];
            foreach ($variants as $variant) {
                $previewProduct = new Product($this->buildSplitProductPayload($source, $variant));
                $previewSnapshot = array_merge((array) ($variant['defaults'] ?? []), [
                    'legacy_product_name' => (string) ($variant['name'] ?? ''),
                ]);
                $existing = $sourceVariantKey !== ''
                    && $sourceVariantKey === (string) ($variant['variant_key'] ?? '')
                    ? $source
                    : $this->findExistingSplitProduct($source, (string) $variant['variant_key']);
                $previewVariants[] = [
                    'product_id' => $existing instanceof Product ? (int) $existing->id : null,
                    'display_name' => (string) ($this->resolveProductDisplayNameResolver()->resolveForProduct(
                        $previewProduct,
                        $previewSnapshot
                    )['product_display_name'] ?? ''),
                    'source_display_name' => (string) ($this->resolveProductDisplayNameResolver()->resolveForProduct($source)['product_display_name'] ?? ''),
                    'variant_key' => (string) $variant['variant_key'],
                    'cpu' => (string) (($variant['defaults'] ?? [])['cpu'] ?? ''),
                    'memory' => (string) (($variant['defaults'] ?? [])['memory'] ?? ''),
                    'pricing' => (array) ($variant['pricing'] ?? []),
                    'action' => $existing instanceof Product ? 'update' : 'create',
                ];
                $previewCount++;
            }

            $items[] = [
                'source_product_id' => (int) $source->id,
                'source_display_name' => (string) ($this->resolveProductDisplayNameResolver()->resolveForProduct($source)['product_display_name'] ?? ''),
                'action' => 'preview',
                'variants' => $previewVariants,
            ];
        }

        return [
            'requested_count' => $productIds->count(),
            'preview_count' => $previewCount,
            'skipped_count' => $skippedCount,
            'items' => $items,
        ];
    }

    public function updateProductSortOrder(Product $product, int $sortOrder): Product
    {
        $product->update([
            'sort_order' => max($sortOrder, 0),
        ]);

        $this->forgetSiteCatalogCache();

        return $product->refresh()->load(['categoryMapping.parent', 'supplier']);
    }

    public function deleteProduct(Product $product): void
    {
        throw_if($product->services()->count() > 0, new BusinessException('该商品已有服务实例，无法直接删除'));

        $product->delete();
        $this->forgetSiteCatalogCache();
    }

    public function toggleProductStatus(Product $product): Product
    {
        $product->update([
            'status' => $product->status === 1 ? 0 : 1,
        ]);

        $this->forgetSiteCatalogCache();

        return $product->refresh()->load(['categoryMapping.parent', 'supplier']);
    }

    private function applyAdminProductFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(! empty($filters['keyword']), function (Builder $builder) use ($filters) {
                $keyword = trim((string) $filters['keyword']);
                $matchedProductIds = $this->resolveDisplayNameMatchedProductIds($keyword);

                $builder->where(function (Builder $keywordQuery) use ($keyword, $matchedProductIds) {
                    $keywordQuery
                        ->where('provision_module', 'like', "%{$keyword}%");

                    if ($matchedProductIds !== []) {
                        $keywordQuery->orWhereIn('products.id', $matchedProductIds);
                    }
                });
            })
            ->when(
                ! empty($filters['type']),
                fn (Builder $builder) => $builder->where('products.product_type', (string) $filters['type'])
            )
            ->when(
                array_key_exists('status', $filters) && $filters['status'] !== null && $filters['status'] !== '',
                fn (Builder $builder) => $builder->where('status', (int) $filters['status'])
            )
            ->when(! empty($filters['category_id']), function (Builder $builder) use ($filters) {
                $categoryId = (int) $filters['category_id'];

                $builder->where(function (Builder $categoryScopeQuery) use ($categoryId) {
                    $categoryScopeQuery
                        ->where('products.product_group_id', $categoryId)
                        ->orWhereHas('categoryMapping', fn (Builder $categoryQuery) => $categoryQuery->where('parent_group_id', $categoryId));
                });
            });
    }

    /**
     * @return array<int, int>
     */
    private function resolveDisplayNameMatchedProductIds(string $keyword): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        return Product::query()
            ->select(['id', 'product_type', 'purchase_requires', 'config_options'])
            ->get()
            ->filter(function (Product $product) use ($keyword): bool {
                $displayName = trim((string) ($this->resolveProductDisplayNameResolver()->resolveForProduct($product)['product_display_name'] ?? ''));

                return $displayName !== '' && mb_stripos($displayName, $keyword) !== false;
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function buildSplitProductVariants(Product $source): array
    {
        $splitOptions = [];

        foreach ((array) ($source->config_options ?? []) as $option) {
            $option = (array) $option;
            $field = $this->parseSplitOptionField($option);
            if (! in_array($field, ['cpu', 'memory'], true)) {
                continue;
            }

            $subItems = collect((array) ($option['sub'] ?? []))
                ->map(fn ($sub) => $this->normalizeSplitSubItem((array) $sub, $field))
                ->filter()
                ->values()
                ->all();

            if (count($subItems) < 2) {
                continue;
            }

            $splitOptions[] = [
                'field' => $field,
                'sub_items' => $subItems,
            ];
        }

        if ($splitOptions === []) {
            return [];
        }

        $variants = [[
            'defaults' => [],
            'labels' => [],
            'pricing_delta' => [],
            'variant_parts' => [],
        ]];

        foreach ($splitOptions as $splitOption) {
            $nextVariants = [];

            foreach ($variants as $variant) {
                foreach ($splitOption['sub_items'] as $subItem) {
                    $nextVariant = $variant;
                    $nextVariant['defaults'][$splitOption['field']] = (string) $subItem['value'];
                    $nextVariant['labels'][$splitOption['field']] = (string) $subItem['label'];
                    $nextVariant['variant_parts'][] = $splitOption['field'].'='.(string) $subItem['value'];
                    $nextVariant['pricing_delta'] = $this->mergePricingDelta(
                        (array) $nextVariant['pricing_delta'],
                        (array) $subItem['pricing_delta']
                    );
                    $nextVariants[] = $nextVariant;
                }
            }

            $variants = $nextVariants;
        }

        return collect($variants)
            ->map(function (array $variant) use ($source): array {
                $variant['variant_key'] = implode(';', (array) $variant['variant_parts']);
                $variant['name'] = $this->buildSplitVariantName(
                    (string) $source->name,
                    (array) $variant['labels']
                );
                $variant['pricing'] = $this->applyPricingDelta(
                    (array) ($source->pricing ?? []),
                    (array) $variant['pricing_delta']
                );
                $variant['config_options'] = $this->buildSplitVariantConfigOptions(
                    (array) ($source->config_options ?? []),
                    (array) ($variant['defaults'] ?? [])
                );

                return $variant;
            })
            ->values()
            ->all();
    }

    private function buildSplitProductPayload(Product $source, array $variant): array
    {
        $purchaseRequires = is_array($source->purchase_requires ?? null) ? $source->purchase_requires : [];
        $purchaseRequires['upstream_default_config'] = (array) ($variant['defaults'] ?? []);
        $purchaseRequires['upstream_split'] = [
            'source_product_id' => (int) $source->id,
            'source_product_name' => (string) $source->name,
            'variant_key' => (string) $variant['variant_key'],
        ];

        return [
            'category_id' => (int) ($source->category_id ?? 0),
            'name' => (string) $variant['name'],
            'product_type' => (string) ($source->product_type ?? ''),
            'remark' => $source->remark,
            'meta_title' => $source->meta_title,
            'meta_description' => $source->meta_description,
            'meta_keywords' => $source->meta_keywords,
            'pricing' => (array) ($variant['pricing'] ?? []),
            'setup_fee' => (float) ($source->setup_fee ?? 0),
            'config_options' => (array) ($variant['config_options'] ?? []),
            'purchase_requires' => $purchaseRequires,
            'stock' => (int) ($source->stock ?? -1),
            'status' => (int) ($source->status ?? 1),
            'sort_order' => (int) ($source->sort_order ?? 0),
            'provision_module' => $source->provision_module,
            'auto_setup' => (int) ($source->auto_setup ?? 0),
            'supplier_id' => $source->supplier_id,
            'supplier_product_id' => $source->supplier_product_id,
        ];
    }

    private function findExistingSplitProduct(Product $source, string $variantKey): ?Product
    {
        $candidates = Product::query()
            ->where('product_group_id', (int) ($source->category_id ?? 0))
            ->where('id', '!=', (int) $source->id)
            ->get();

        foreach ($candidates as $candidate) {
            $split = (array) (($candidate->purchase_requires ?? [])['upstream_split'] ?? []);
            if (
                (int) ($split['source_product_id'] ?? 0) === (int) $source->id
                && (string) ($split['variant_key'] ?? '') === $variantKey
            ) {
                return $candidate;
            }
        }

        return null;
    }

    private function isSplitSourceVariant(Product $source, array $variant): bool
    {
        $sourceName = $this->normalizeProductNameForSplitComparison((string) $source->name);
        $variantName = $this->normalizeProductNameForSplitComparison((string) ($variant['name'] ?? ''));

        return $sourceName !== '' && $sourceName === $variantName;
    }

    private function resolveSourceSplitVariantKey(Product $source, array $variants): string
    {
        foreach ($variants as $variant) {
            if ($this->isSplitSourceVariant($source, $variant)) {
                return (string) ($variant['variant_key'] ?? '');
            }
        }

        $preferredDefaults = $this->extractPreferredSplitDefaults($source);
        if ($preferredDefaults !== []) {
            foreach ($variants as $variant) {
                if ($this->matchesSplitVariantDefaults($preferredDefaults, (array) ($variant['defaults'] ?? []))) {
                    return (string) ($variant['variant_key'] ?? '');
                }
            }
        }

        // 商品名无法识别规格时，回退到首个可拆分子项作为基础规格，避免把所有规格都新建出来。
        return (string) (($variants[0]['variant_key'] ?? '') ?: '');
    }

    private function extractPreferredSplitDefaults(Product $source): array
    {
        $defaults = [];

        $purchaseDefaults = (array) (($source->purchase_requires ?? [])['upstream_default_config'] ?? []);
        foreach ($purchaseDefaults as $field => $value) {
            $field = $this->normalizeSplitConfigField((string) $field);
            $normalizedValue = $this->normalizeSplitComparableValue($field, $value);
            if ($field === '' || $normalizedValue === '') {
                continue;
            }

            $defaults[$field] = $normalizedValue;
        }

        foreach ((array) ($source->config_options ?? []) as $option) {
            $option = (array) $option;
            $field = $this->parseSplitOptionField($option);
            if ($field === '' || array_key_exists($field, $defaults)) {
                continue;
            }

            $configuredDefault = $this->resolveSplitOptionDefaultValue($option, $field);
            if ($configuredDefault !== '') {
                $defaults[$field] = $configuredDefault;
            }
        }

        return $defaults;
    }

    private function resolveSplitOptionDefaultValue(array $option, string $field): string
    {
        foreach ((array) ($option['sub'] ?? []) as $sub) {
            $sub = (array) $sub;
            if (! $this->isMarkedAsDefaultSplitSubItem($sub)) {
                continue;
            }

            $value = $sub['option_name_first'] ?? $sub['value'] ?? $sub['id'] ?? $sub['option_name'] ?? null;

            return $this->normalizeSplitComparableValue($field, $value);
        }

        foreach (['default_value', 'default'] as $key) {
            $normalized = $this->normalizeSplitComparableValue($field, $option[$key] ?? null);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function isMarkedAsDefaultSplitSubItem(array $sub): bool
    {
        foreach (['is_default', 'default', 'selected'] as $key) {
            $value = $sub[$key] ?? null;
            if (is_bool($value) ? $value : in_array($value, [1, '1', 'true', 'yes', 'on'], true)) {
                return true;
            }
        }

        return false;
    }

    private function matchesSplitVariantDefaults(array $preferredDefaults, array $variantDefaults): bool
    {
        if ($preferredDefaults === [] || $variantDefaults === []) {
            return false;
        }

        foreach ($preferredDefaults as $field => $value) {
            if (! array_key_exists($field, $variantDefaults)) {
                return false;
            }

            if ($this->normalizeSplitComparableValue((string) $field, $variantDefaults[$field]) !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    private function normalizeSplitComparableValue(string $field, mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $number = $this->parseSpecNumber($text);
        if ($number === '') {
            return mb_strtolower($text, 'UTF-8');
        }

        if ($field === 'memory') {
            return $this->normalizeMemoryDisplayNumber($number);
        }

        return ltrim($number, '0') !== '' ? ltrim($number, '0') : '0';
    }

    private function normalizeProductNameForSplitComparison(string $name): string
    {
        $normalized = preg_replace('/\s+/u', '', trim($name)) ?? trim($name);

        return mb_strtolower($normalized, 'UTF-8');
    }

    private function parseSplitOptionField(array $option): string
    {
        $field = $this->normalizeSplitConfigField((string) ($option['field'] ?? ''));
        if ($field !== '') {
            return $field;
        }

        $label = strtolower(trim(implode('|', array_filter([
            (string) ($option['name'] ?? ''),
            (string) ($option['option_name'] ?? ''),
            (string) ($option['spec_key'] ?? ''),
            (string) ($option['key'] ?? ''),
        ], fn (string $value) => trim($value) !== ''))));

        if ($label === '') {
            return '';
        }

        if (preg_match('/流量|带宽|系统盘|数据盘|磁盘|硬盘|traffic|flow|bandwidth|bw|disk|ip/u', $label)) {
            return '';
        }

        if (preg_match('/限制|智能|型号|模型|limit|advanced|model/u', $label)) {
            return '';
        }

        if (preg_match('/内存|memory|ram/u', $label)) {
            return 'memory';
        }

        return preg_match('/cpu|vcpu|核心|核数/u', $label) ? 'cpu' : '';
    }

    private function normalizeSplitConfigField(string $field): string
    {
        $normalized = strtolower(trim($field));
        $normalized = str_replace(['-', ' '], '_', $normalized);

        return match ($normalized) {
            'cpu', 'vcpu', 'core', 'cores', 'cpu_core', 'cpu_cores', 'cpu_num', 'cpu_number', 'core_num', 'cores_num' => 'cpu',
            'memory', 'ram', 'mem', 'memory_size', 'mem_size', 'ram_size', 'memory_num', 'ram_num' => 'memory',
            default => '',
        };
    }

    private function normalizeSplitSubItem(array $sub, string $field): ?array
    {
        if ((int) ($sub['hidden'] ?? 0) === 1) {
            return null;
        }

        $label = trim((string) ($sub['option_name'] ?? $sub['version'] ?? $sub['option_name_first'] ?? $sub['name'] ?? ''));
        $value = trim((string) ($sub['option_name_first'] ?? ''));

        if ($value === '') {
            $value = $this->parseSpecNumber($label);
        }

        if ($label === '') {
            $label = $value !== '' ? $this->formatSpecLabel($field, $value) : '';
        }

        if ($value === '' || $label === '') {
            return null;
        }

        return [
            'value' => $value,
            'label' => $this->formatSpecLabel($field, $label),
            'pricing_delta' => $this->normalizeSplitPricingDelta($sub['pricing'] ?? []),
        ];
    }

    private function normalizeSplitPricingDelta(mixed $pricing): array
    {
        if (! is_array($pricing)) {
            return [];
        }

        $result = [];
        foreach ($pricing as $cycle => $amount) {
            if ($amount === null || $amount === '' || ! is_numeric($amount)) {
                continue;
            }

            $result[(string) $cycle] = (float) $amount;
        }

        return $result;
    }

    private function mergePricingDelta(array $current, array $delta): array
    {
        foreach ($delta as $cycle => $amount) {
            $current[(string) $cycle] = (float) ($current[(string) $cycle] ?? 0) + (float) $amount;
        }

        return $current;
    }

    private function applyPricingDelta(array $basePricing, array $delta): array
    {
        $pricing = [];

        foreach ($basePricing as $cycle => $amount) {
            if (! is_numeric($amount)) {
                continue;
            }

            $pricing[(string) $cycle] = number_format(
                max(0, (float) $amount + (float) ($delta[(string) $cycle] ?? 0)),
                2,
                '.',
                ''
            );
        }

        return $pricing;
    }

    private function removeSplitConfigOptions(array $configOptions): array
    {
        return array_values(array_filter($configOptions, function ($option): bool {
            return $this->parseSplitOptionField((array) $option) === '';
        }));
    }

    private function buildSplitVariantConfigOptions(array $configOptions, array $variantDefaults): array
    {
        $normalizedDefaults = [];
        foreach ($variantDefaults as $field => $value) {
            $normalizedField = $this->normalizeSplitConfigField((string) $field);
            $normalizedValue = $this->normalizeSplitComparableValue($normalizedField, $value);
            if ($normalizedField === '' || $normalizedValue === '') {
                continue;
            }

            $normalizedDefaults[$normalizedField] = $normalizedValue;
        }

        return collect($configOptions)
            ->map(function ($option) use ($normalizedDefaults) {
                $option = (array) $option;
                $field = $this->parseSplitOptionField($option);

                if ($field === '') {
                    return $option;
                }

                $selectedValue = $normalizedDefaults[$field] ?? '';
                if ($selectedValue === '') {
                    return null;
                }

                return $this->buildFixedSplitConfigOption($option, $field, $selectedValue);
            })
            ->filter(fn ($option) => is_array($option))
            ->values()
            ->all();
    }

    private function buildFixedSplitConfigOption(array $option, string $field, string $selectedValue): ?array
    {
        $matchedSub = $this->findMatchingSplitSubItem($option, $field, $selectedValue);
        if ($matchedSub === null) {
            return null;
        }

        $fixedOption = $option;
        $fixedOption['hidden'] = 1;
        $fixedOption['required'] = 0;
        $fixedOption['default_value'] = (string) ($matchedSub['option_name_first'] ?? $matchedSub['value'] ?? $selectedValue);
        $fixedOption['parameter'] = $this->buildSingleSplitOptionParameter($matchedSub);
        $fixedOption['sub'] = [$this->normalizeFixedSplitSubItem($matchedSub)];

        if (array_key_exists('sub_items', $fixedOption)) {
            $fixedOption['sub_items'] = [$this->buildFixedSplitSubItemRecord($matchedSub)];
        }

        return $fixedOption;
    }

    private function findMatchingSplitSubItem(array $option, string $field, string $selectedValue): ?array
    {
        foreach ((array) ($option['sub'] ?? []) as $sub) {
            $sub = (array) $sub;
            $candidateValue = $this->normalizeSplitComparableValue(
                $field,
                $sub['option_name_first'] ?? $sub['value'] ?? $sub['id'] ?? $sub['option_name'] ?? null
            );

            if ($candidateValue === $selectedValue) {
                return $sub;
            }
        }

        foreach ((array) ($option['sub_items'] ?? []) as $sub) {
            $sub = (array) $sub;
            $candidateValue = $this->normalizeSplitComparableValue(
                $field,
                $sub['value'] ?? $sub['option_name_first'] ?? $sub['id'] ?? $sub['label'] ?? null
            );

            if ($candidateValue === $selectedValue) {
                return [
                    'id' => $sub['raw_id'] ?? $sub['id'] ?? $sub['value'] ?? $sub['label'] ?? $selectedValue,
                    'option_name' => $sub['label'] ?? $sub['option_name'] ?? $sub['value'] ?? '',
                    'option_name_first' => $sub['value'] ?? $sub['option_name_first'] ?? $selectedValue,
                    'version' => $sub['label'] ?? $sub['version'] ?? '',
                    'pricing' => (array) ($sub['pricing'] ?? []),
                    'sort_order' => $sub['sort_order'] ?? 0,
                    'hidden' => 0,
                ];
            }
        }

        return null;
    }

    private function normalizeFixedSplitSubItem(array $sub): array
    {
        $normalized = $sub;
        $normalized['hidden'] = 0;
        $normalized['pricing'] = $this->zeroConfigPricing((array) ($sub['pricing'] ?? []));

        return $normalized;
    }

    private function buildFixedSplitSubItemRecord(array $sub): array
    {
        return [
            'label' => (string) ($sub['option_name'] ?? $sub['version'] ?? $sub['label'] ?? $sub['option_name_first'] ?? ''),
            'value' => (string) ($sub['option_name_first'] ?? $sub['value'] ?? $sub['id'] ?? ''),
            'pricing' => $this->zeroConfigPricing((array) ($sub['pricing'] ?? [])),
            'sort_order' => (int) ($sub['sort_order'] ?? 0),
            'hidden' => false,
            'raw_id' => $sub['id'] ?? '',
        ];
    }

    private function buildSingleSplitOptionParameter(array $sub): string
    {
        $value = trim((string) ($sub['option_name_first'] ?? $sub['value'] ?? $sub['id'] ?? ''));
        $label = trim((string) ($sub['option_name'] ?? $sub['version'] ?? $sub['label'] ?? $value));

        if ($value === '' && $label === '') {
            return '';
        }

        return $value === '' ? $label : $value.'|'.($label !== '' ? $label : $value);
    }

    private function zeroConfigPricing(array $pricing): array
    {
        $normalized = [];

        foreach ($pricing as $cycle => $amount) {
            if (! is_string($cycle) || $cycle === '') {
                continue;
            }

            $normalized[$cycle] = is_numeric($amount) ? '0.00' : $amount;
        }

        return $normalized;
    }

    private function buildSplitVariantName(string $sourceName, array $labels): string
    {
        $name = trim($sourceName);
        $cpuLabel = isset($labels['cpu']) ? $this->formatSpecLabel('cpu', (string) $labels['cpu']) : null;
        $memoryLabel = isset($labels['memory']) ? $this->formatSpecLabel('memory', (string) $labels['memory']) : null;

        if ($cpuLabel !== null && $memoryLabel !== null) {
            $combined = $cpuLabel.$memoryLabel;
            $pattern = '/\d+\s*[HhCcv]\s*\d+\s*[Gg](?:[Bb])?/u';
            if (preg_match($pattern, $name)) {
                return preg_replace($pattern, $combined, $name, 1) ?? $name;
            }

            return trim($name.' '.$combined);
        }

        if ($memoryLabel !== null) {
            $pattern = '/(\d+)\s*[HhCcv]\s*\d+\s*[Gg](?:[Bb])?/u';
            if (preg_match($pattern, $name)) {
                return preg_replace_callback(
                    $pattern,
                    fn (array $matches) => (string) $matches[1].'H'.$memoryLabel,
                    $name,
                    1
                ) ?? $name;
            }

            return trim($name.' '.$memoryLabel);
        }

        if ($cpuLabel !== null) {
            $pattern = '/\d+\s*[HhCcv](\s*\d+\s*[Gg](?:[Bb])?)/u';
            if (preg_match($pattern, $name)) {
                return preg_replace($pattern, $cpuLabel.'${1}', $name, 1) ?? $name;
            }

            return trim($name.' '.$cpuLabel);
        }

        return $name;
    }

    private function formatSpecLabel(string $field, string $value): string
    {
        $number = $this->parseSpecNumber($value);
        if ($number === '') {
            return strtoupper(trim($value));
        }

        if ($field === 'cpu') {
            return $number.'H';
        }

        $displayNumber = $this->normalizeMemoryDisplayNumber($number);

        return $displayNumber.'G';
    }

    private function normalizeMemoryDisplayNumber(string $number): string
    {
        if (! is_numeric($number)) {
            return $number;
        }

        $value = (float) $number;
        if ($value >= 1024 && fmod($value, 1024.0) === 0.0) {
            $value = $value / 1024;
        }

        return fmod($value, 1.0) === 0.0
            ? (string) (int) $value
            : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function parseSpecNumber(string $value): string
    {
        if (preg_match('/\d+(?:\.\d+)?/', $value, $matches)) {
            return (string) $matches[0];
        }

        return '';
    }

    private function transformProductOwnerItem(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'display_name' => $user->display_name,
            'nickname' => $user->nickname,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => (int) $user->status,
            'status_label' => (int) $user->status === 1 ? '正常' : '已禁用',
            'product_services_count' => (int) ($user->product_services_count ?? 0),
            'active_product_services_count' => (int) ($user->active_product_services_count ?? 0),
            'latest_service_created_at' => $this->formatDateTimeValue($user->latest_product_service_created_at ?? null),
            'latest_service_expires_at' => $this->formatDateTimeValue($user->latest_product_service_expires_at ?? null),
            'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function resolveProductScopeIds(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        return Product::query()
            ->where('product_group_id', $categoryId)
            ->orderBy('sort_order')
            ->orderByDesc('status')
            ->orderByDesc('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function resequenceProductIds(array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        $bindings = [];
        $caseSql = collect(array_values($productIds))
            ->map(function (int $productId, int $index) use (&$bindings) {
                $bindings[] = $productId;
                $bindings[] = $index + 1;

                return 'WHEN ? THEN ?';
            })
            ->implode(' ');
        $productPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
        $bindings[] = now();
        array_push($bindings, ...array_values($productIds));

        DB::statement(
            "UPDATE products SET sort_order = CASE id {$caseSql} END, updated_at = ? WHERE id IN ({$productPlaceholders})",
            $bindings
        );
    }

    private function buildBatchCategoryResult(int $selectedCount, int $updatedCount, ProductCategory $targetCategory): array
    {
        $targetCategoryId = (int) $targetCategory->id;
        $targetGroupId = (int) (($targetCategory->legacy_group_id ?? 0) ?: $targetCategoryId);
        $targetCategoryName = (string) ($targetCategory->name ?? '');
        $targetCategoryFullName = $targetCategory->parent
            ? $targetCategory->parent->name.' / '.$targetCategoryName
            : $targetCategoryName;

        return [
            'selected_count' => $selectedCount,
            'updated_count' => $updatedCount,
            'target_category_id' => $targetCategoryId,
            'target_group_id' => $targetGroupId,
            'target_category_name' => $targetCategoryName,
            'target_category_full_name' => $targetCategoryFullName,
        ];
    }

    private function prepareProductPayload(array $data): array
    {
        $category = $this->resolveTargetCategory($data);
        $productTypeCode = trim((string) ($category->product_type ?? ($data['type'] ?? 'other')));
        if ($productTypeCode === '') {
            $productTypeCode = 'other';
        }

        $pricing = $this->normalizePricing($data['pricing'] ?? []);
        throw_if($pricing === [], new BusinessException('请至少配置一个计费周期价格'));

        $supplierId = $this->normalizeNullableInt($data['supplier_id'] ?? null);
        $supplierProductId = $this->normalizeNullableInt($data['supplier_product_id'] ?? null);
        $provisionModule = $this->normalizeNullableString($data['provision_module'] ?? null);
        $supplier = null;

        if ($supplierProductId !== null && $supplierId === null) {
            throw new BusinessException('选择供应商商品前请先选择供应商');
        }

        if ($supplierId !== null) {
            $supplier = Supplier::query()->enabled()->find($supplierId);
            throw_if(! $supplier, new BusinessException('供应商接口不存在或已停用'));
            $provisionModule = $this->normalizeNullableString($supplier->interface_type);
        }

        if ($supplierId === null) {
            $supplierProductId = null;
        }

        $derivedDisplayName = $this->deriveInternalProductName($data);

        return [
            'base' => [
                'category_id' => (int) $category->id,
                'name' => $derivedDisplayName,
                'product_type' => $productTypeCode,
                'remark' => $this->normalizeNullableString($data['remark'] ?? null),
                'meta_title' => $this->normalizeNullableString($data['meta_title'] ?? null),
                'meta_description' => $this->normalizeNullableString($data['meta_description'] ?? null),
                'meta_keywords' => $this->normalizeNullableString($data['meta_keywords'] ?? null),
                'pricing' => $pricing,
                'setup_fee' => max((float) ($data['setup_fee'] ?? 0), 0),
                'config_options' => $this->normalizeConfigOptions($data['config_options'] ?? []),
                'purchase_requires' => $this->normalizePurchaseRequires($data['purchase_requires'] ?? []),
                'stock' => (int) ($data['stock'] ?? -1),
                'status' => (int) (($data['status'] ?? 1) ? 1 : 0),
                'sort_order' => max((int) ($data['sort_order'] ?? 0), 0),
                'provision_module' => $provisionModule,
                'auto_setup' => (int) (($data['auto_setup'] ?? 0) ? 1 : 0),
                'supplier_id' => $supplierId,
                'supplier_product_id' => $supplierProductId,
            ],
            'category' => $category,
            'pricing' => $pricing,
            'config_options' => $this->normalizeConfigOptions($data['config_options'] ?? []),
            'purchase_requires' => $this->normalizePurchaseRequires($data['purchase_requires'] ?? []),
        ];
    }

    private function deriveInternalProductName(array $data): string
    {
        $purchaseRequires = $this->normalizePurchaseRequires($data['purchase_requires'] ?? []);
        $upstreamDefaults = is_array($purchaseRequires['upstream_default_config'] ?? null)
            ? $purchaseRequires['upstream_default_config']
            : [];
        $configOptions = $this->normalizeConfigOptions($data['config_options'] ?? []);
        $temporaryProduct = new Product([
            'id' => (int) ($data['id'] ?? 0),
            'purchase_requires' => $purchaseRequires,
            'config_options' => $configOptions,
        ]);
        $resolved = $this->resolveProductDisplayNameResolver()->resolveForProduct($temporaryProduct, $upstreamDefaults);

        return trim((string) ($resolved['product_spec_display'] ?? '')) ?: ('未配置规格 #'.(int) ($data['id'] ?? 0));
    }

    private function resolveTargetCategory(array $data): ProductCategory
    {
        $categoryId = $this->normalizeNullableInt($data['category_id'] ?? null);
        $category = $categoryId !== null
            ? ProductCategory::query()->find($categoryId)
            : null;

        throw_if(! $category, new BusinessException('商品分类不存在'));
        throw_if(
            $category->parent_id === null && $category->children()->visible()->exists(),
            new BusinessException('商品必须挂载到最终可售分类下，当前分类下仍存在子分类')
        );

        return $category;
    }

    private function loadProductSnapshot(Product $product): Product
    {
        return $product->refresh()->load([
            'categoryMapping.parent',
            'supplier',
        ]);
    }

    private function formatDateTimeValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizePurchaseRequires(mixed $requires): array
    {
        if (! is_array($requires)) {
            return [];
        }

        $normalized = array_filter([
            'require_verification' => isset($requires['require_verification']) ? (bool) $requires['require_verification'] : null,
            'require_phone' => isset($requires['require_phone']) ? (bool) $requires['require_phone'] : null,
        ], fn ($v) => $v !== null && $v !== false);

        $provisionHostnameRule = $this->normalizeProvisionHostnameRule($requires['provision_hostname'] ?? []);
        if ($provisionHostnameRule !== null) {
            $normalized['provision_hostname'] = $provisionHostnameRule;
        }

        return $normalized;
    }

    private function normalizeProvisionHostnameRule(mixed $rule): ?array
    {
        if (! is_array($rule)) {
            return null;
        }

        $mode = trim((string) ($rule['mode'] ?? ProductProvisionHostname::MODE_SYSTEM));
        if (! in_array($mode, ProductProvisionHostname::modes(), true)) {
            $mode = ProductProvisionHostname::MODE_SYSTEM;
        }

        if ($mode === ProductProvisionHostname::MODE_SYSTEM) {
            return null;
        }

        $rawValue = trim((string) ($rule['value'] ?? ''));
        $rawLength = isset($rule['length']) && is_numeric($rule['length'])
            ? (int) $rule['length']
            : 12;

        if ($mode === ProductProvisionHostname::MODE_FIXED) {
            $value = $this->settingService->normalizeHostname($rawValue, true);
            throw_if($value === '', new BusinessException('固定主机名不能为空'));

            return [
                'mode' => ProductProvisionHostname::MODE_FIXED,
                'value' => $value,
                'length' => max(4, min(63, $rawLength)),
            ];
        }

        $prefix = $this->settingService->sanitizeHostnamePrefix($rawValue);
        throw_if($prefix === '', new BusinessException('主机名前缀不能为空，且只能包含字母'));

        return [
            'mode' => ProductProvisionHostname::MODE_PREFIX,
            'value' => $prefix,
            'length' => max(max(4, min(63, $rawLength)), mb_strlen($prefix)),
        ];
    }

    private function resolveProductDisplayNameResolver(): ProductDisplayNameResolver
    {
        return $this->productDisplayNameResolver ?? new ProductDisplayNameResolver;
    }
}
