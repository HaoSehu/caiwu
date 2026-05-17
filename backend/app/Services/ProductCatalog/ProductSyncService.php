<?php

declare(strict_types=1);

namespace App\Services\ProductCatalog;

use App\Constants\OrderStatus;
use App\Constants\ProductType;
use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Services\ProductCatalog\Concerns\HandlesProductCatalogHelpers;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\ProviderResolver;
use App\Support\TextSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductSyncService
{
    use HandlesProductCatalogHelpers;

    private const IMPORT_PRICING_MONTHS = [
        'monthly' => 1,
        'quarterly' => 3,
        'semiannually' => 6,
        'annually' => 12,
    ];

    private const REMOTE_STOCK_CACHE_TTL_SECONDS = 15;

    public function __construct(
        private readonly ProviderResolver $providerResolver,
        private readonly ?ProductDisplayNameResolver $productDisplayNameResolver = null,
    ) {}

    public function batchSyncProducts(array $data): array
    {
        $productIds = collect($data['product_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        throw_if($productIds->isEmpty(), new BusinessException('请至少选择一个商品'));

        $syncPricing = (int) ($data['sync_pricing'] ?? 0) === 1;
        $syncConfigOptions = (int) ($data['sync_config_options'] ?? 0) === 1;
        $syncConfigPricing = (int) ($data['sync_config_pricing'] ?? 0) === 1;

        throw_if(
            ! $syncPricing && ! $syncConfigOptions && ! $syncConfigPricing,
            new BusinessException('请至少选择一个同步项')
        );

        $products = Product::query()
            ->with(['categoryMapping.parent', 'supplier'])
            ->whereIn('id', $productIds->all())
            ->get()
            ->keyBy(fn (Product $product) => (int) $product->id);

        $items = [];
        $validProductsBySupplier = [];

        foreach ($productIds as $productId) {
            $product = $products->get($productId);

            if (! $product instanceof Product) {
                $items[] = $this->buildBatchSyncSkippedItem($productId, null, '商品不存在或已删除');

                continue;
            }

            $supplier = $product->supplier;

            if (! $supplier instanceof Supplier) {
                $items[] = $this->buildBatchSyncSkippedItem($productId, $product, '商品未绑定供应商');

                continue;
            }

            if ((int) ($product->supplier_product_id ?? 0) <= 0) {
                $items[] = $this->buildBatchSyncSkippedItem($productId, $product, '商品未绑定上游商品');

                continue;
            }

            if (! $this->providerResolver->resolveForSupplier($supplier)->supports(ProvidesConsoleCatalog::class)) {
                $items[] = $this->buildBatchSyncSkippedItem($productId, $product, '当前供应商暂不支持批量同步');

                continue;
            }

            $validProductsBySupplier[(int) $supplier->id][] = $product;
        }

        foreach ($validProductsBySupplier as $supplierProducts) {
            $supplier = $supplierProducts[0]->supplier;

            if (! $supplier instanceof Supplier) {
                continue;
            }

            try {
                $catalogProducts = [];
                $remoteConfigOptions = [];
                $catalogCapability = $this->resolveCatalogCapability($supplier);

                if ($syncPricing) {
                    $catalog = $catalogCapability->getProductCatalog($supplier);
                    $catalogProducts = collect($catalog['products'] ?? [])
                        ->filter(fn ($item) => is_array($item) && (int) ($item['id'] ?? 0) > 0)
                        ->keyBy(fn (array $item) => (int) ($item['id'] ?? 0))
                        ->all();
                }

                if ($syncConfigOptions || $syncConfigPricing) {
                    $remoteConfigOptions = $this->prefetchImportedConfigOptions(
                        $supplier,
                        collect($supplierProducts)
                            ->map(fn (Product $product) => (int) ($product->supplier_product_id ?? 0))
                            ->filter(fn (int $supplierProductId) => $supplierProductId > 0)
                            ->values()
                            ->all()
                    );
                }

                foreach ($supplierProducts as $product) {
                    $syncResult = $this->syncSingleSupplierProduct(
                        $product,
                        $catalogProducts,
                        $remoteConfigOptions,
                        $syncPricing,
                        $syncConfigOptions,
                        $syncConfigPricing
                    );

                    $items[] = array_merge($syncResult, [
                        'product_id' => (int) $product->id,
                        'product_name' => (string) $product->name,
                        'supplier_id' => (int) ($product->supplier_id ?? 0),
                        'supplier_name' => (string) ($supplier->name ?? ''),
                    ]);
                }
            } catch (\Throwable $exception) {
                Log::warning('[商品批量同步] 供应商同步失败', [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);

                foreach ($supplierProducts as $product) {
                    $items[] = $this->buildBatchSyncSkippedItem(
                        (int) $product->id,
                        $product,
                        '供应商同步失败：'.$exception->getMessage()
                    );
                }
            }
        }

        $syncedCount = collect($items)->where('status', 'synced')->count();
        $skippedCount = collect($items)->where('status', 'skipped')->count();

        if ($syncedCount > 0) {
            $this->forgetSiteCatalogCache();
        }

        return [
            'requested_count' => $productIds->count(),
            'synced_count' => $syncedCount,
            'skipped_count' => $skippedCount,
            'sync_pricing' => $syncPricing,
            'sync_config_options' => $syncConfigOptions,
            'sync_config_pricing' => $syncConfigPricing,
            'items' => $items,
        ];
    }

    public function bulkConnectSupplierProducts(Supplier $supplier, array $data): array
    {
        $productType = trim((string) ($data['product_type'] ?? ''));
        throw_if($productType === '', new BusinessException('请选择所属一级菜单'));

        $supplierProductIds = collect($data['product_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        throw_if($supplierProductIds->isEmpty(), new BusinessException('请选择至少一个上游商品'));

        $catalogCapability = $this->resolveCatalogCapability($supplier);
        $catalog = $catalogCapability->getProductCatalog($supplier);
        $catalogProducts = collect($catalog['products'] ?? [])
            ->filter(fn ($item) => is_array($item) && (int) ($item['id'] ?? 0) > 0)
            ->keyBy(fn (array $item) => (int) ($item['id'] ?? 0));

        $existingProducts = Product::withTrashed()
            ->with(['categoryMapping.parent', 'supplier'])
            ->where('supplier_id', (int) $supplier->id)
            ->whereIn('supplier_product_id', $supplierProductIds->all())
            ->get()
            ->keyBy(fn (Product $product) => (int) ($product->supplier_product_id ?? 0));

        $defaultStatus = (int) ($data['default_status'] ?? 1) === 1 ? 1 : 0;
        $defaultAutoSetup = (int) ($data['default_auto_setup'] ?? 1) === 1 ? 1 : 0;
        $syncConfigOptions = (int) ($data['sync_config_options'] ?? 0) === 1;

        $rootCategory = $this->resolveImportedRootCategory(
            $productType,
            (int) ($data['root_category_id'] ?? $data['root_group_id'] ?? 0),
            TextSanitizer::nullable((string) ($data['root_group_name'] ?? ''))
        );
        $childCategory = $this->resolveImportedChildCategory(
            $productType,
            (int) ($data['child_category_id'] ?? $data['child_group_id'] ?? 0),
            $rootCategory
        );

        $importedProducts = [];
        $skippedItems = [];
        $createdCount = 0;
        $updatedCount = 0;

        foreach ($supplierProductIds as $supplierProductId) {
            $supplierProduct = $catalogProducts->get($supplierProductId);
            if (! is_array($supplierProduct)) {
                $skippedItems[] = $this->buildBulkConnectSkippedItem(
                    $supplierProductId,
                    null,
                    '未找到对应的上游商品'
                );

                continue;
            }

            $pricing = $this->buildImportedPricing($supplierProduct);
            if ($pricing === []) {
                $skippedItems[] = $this->buildBulkConnectSkippedItem(
                    $supplierProductId,
                    $supplierProduct,
                    '上游商品缺少可导入价格'
                );

                continue;
            }

            $targetCategory = $childCategory instanceof ProductCategory
                ? $childCategory
                : $this->resolveImportedTargetCategory($productType, $rootCategory, $supplierProduct);

            $configOptions = $this->resolveImportedBatchConfigOptions(
                $supplier,
                $supplierProduct,
                $syncConfigOptions,
                $existingProducts->get($supplierProductId)?->config_options ?? []
            );

            $payload = $this->buildBulkConnectProductPayload(
                $supplier,
                $targetCategory,
                $supplierProduct,
                $productType,
                $pricing,
                $defaultStatus,
                $defaultAutoSetup,
                $configOptions
            );

            $localProduct = $existingProducts->get($supplierProductId);
            if ($localProduct instanceof Product) {
                $payload['sort_order'] = (int) ($localProduct->sort_order ?? 0);
                $localProduct = DB::transaction(
                    fn () => $this->persistProductWithStructuredSync($localProduct, $payload)
                );
                $updatedCount++;
                $action = 'updated';
            } else {
                $localProduct = DB::transaction(
                    fn () => $this->createProductWithStructuredSync($payload)
                );
                $createdCount++;
                $action = 'created';
            }

            $importedProducts[] = $this->buildBulkConnectImportedItem(
                $localProduct,
                $supplierProductId,
                $supplierProduct,
                $action
            );
        }

        if (($createdCount + $updatedCount) > 0) {
            $this->forgetSiteCatalogCache();
        }

        return [
            'selected_count' => $supplierProductIds->count(),
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'skipped_count' => count($skippedItems),
            'imported_products' => $importedProducts,
            'skipped_items' => $skippedItems,
        ];
    }

    public function finalizeUpstreamBindings(array $data = []): array
    {
        $productIds = collect(explode(',', (string) ($data['product_ids'] ?? '')))
            ->map(fn ($id) => (int) trim((string) $id))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $forceAll = (bool) ($data['force_all'] ?? false);
        $syncConfigOptions = ! (bool) ($data['skip_config'] ?? false);
        $dryRun = (bool) ($data['dry_run'] ?? false);

        $products = Product::query()
            ->with('supplier')
            ->when(
                $productIds->isNotEmpty(),
                fn ($query) => $query->whereIn('id', $productIds->all())
            )
            ->whereNotNull('supplier_id')
            ->where('supplier_id', '>', 0)
            ->whereNotNull('supplier_product_id')
            ->where('supplier_product_id', '>', 0)
            ->orderBy('supplier_id')
            ->orderBy('id')
            ->get();

        $items = [];
        $eligibleProducts = $products->filter(
            fn (Product $product) => $forceAll || $this->productNeedsUpstreamFinalize($product, $syncConfigOptions)
        );

        foreach ($eligibleProducts->groupBy(fn (Product $product) => (int) $product->supplier_id) as $supplierProducts) {
            $supplier = $supplierProducts->first()?->supplier;

            if (! $supplier instanceof Supplier) {
                foreach ($supplierProducts as $product) {
                    $items[] = $this->buildFinalizeUpstreamSkippedItem($product, '商品未找到有效供应商');
                }

                continue;
            }

            if (! $this->providerResolver->resolveForSupplier($supplier)->supports(ProvidesConsoleCatalog::class)) {
                foreach ($supplierProducts as $product) {
                    $items[] = $this->buildFinalizeUpstreamSkippedItem($product, '当前供应商接口不支持批量固化');
                }

                continue;
            }

            $remoteConfigOptions = [];

            if ($syncConfigOptions) {
                $remoteConfigOptions = $this->prefetchImportedConfigOptions(
                    $supplier,
                    $supplierProducts
                        ->map(fn (Product $product) => (int) ($product->supplier_product_id ?? 0))
                        ->filter(fn (int $supplierProductId) => $supplierProductId > 0)
                        ->values()
                        ->all()
                );
            }

            foreach ($supplierProducts as $product) {
                $payload = [];
                $updatedFields = [];
                $targetModule = trim((string) $supplier->interface_type);

                if ($targetModule !== '' && trim((string) ($product->provision_module ?? '')) !== $targetModule) {
                    $payload['provision_module'] = $targetModule;
                    $updatedFields[] = 'provision_module';
                }

                if ((int) ($product->auto_setup ?? 0) !== 1) {
                    $payload['auto_setup'] = 1;
                    $updatedFields[] = 'auto_setup';
                }

                if ($syncConfigOptions) {
                    $supplierProductId = (int) ($product->supplier_product_id ?? 0);
                    $normalizedRemoteConfigOptions = $this->normalizeImportedConfigOptions(
                        $remoteConfigOptions[$supplierProductId] ?? []
                    );
                    $localConfigOptions = $this->normalizeConfigOptions($product->config_options);

                    if ($normalizedRemoteConfigOptions !== []) {
                        $mergedConfigOptions = $this->mergeConfigOptionsPreservingPricing(
                            $localConfigOptions,
                            $normalizedRemoteConfigOptions
                        );

                        if ($mergedConfigOptions !== $localConfigOptions) {
                            $payload['config_options'] = $mergedConfigOptions;
                            $updatedFields[] = 'config_options';
                        }
                    }
                }

                if ($payload === []) {
                    $items[] = array_merge(
                        $this->buildFinalizeUpstreamSkippedItem($product, '当前商品无需更新'),
                        ['updated_fields' => []]
                    );

                    continue;
                }

                if (! $dryRun) {
                    $product = $this->persistProductWithStructuredSync($product, $payload);
                }

                $items[] = [
                    'status' => 'updated',
                    'product_id' => (int) $product->id,
                    'product_name' => (string) $product->name,
                    'supplier_id' => (int) ($product->supplier_id ?? 0),
                    'supplier_name' => (string) ($supplier->name ?? ''),
                    'supplier_product_id' => (int) ($product->supplier_product_id ?? 0),
                    'updated_fields' => $updatedFields,
                ];
            }
        }

        $updatedCount = collect($items)->where('status', 'updated')->count();
        $skippedCount = collect($items)->where('status', 'skipped')->count();

        return [
            'requested_count' => $productIds->isNotEmpty() ? $productIds->count() : $products->count(),
            'matched_count' => $products->count(),
            'eligible_count' => $eligibleProducts->count(),
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
            'sync_config_options' => $syncConfigOptions,
            'force_all' => $forceAll,
            'dry_run' => $dryRun,
            'items' => $items,
        ];
    }

    public function syncUpstreamProductConfigOptions(): array
    {
        $summary = [
            'matched_products' => 0,
            'matched_suppliers' => 0,
            'synced_products' => 0,
            'skipped_products' => 0,
            'failed_products' => 0,
        ];

        $products = Product::query()
            ->with('supplier')
            ->whereNotNull('supplier_id')
            ->where('supplier_id', '>', 0)
            ->whereNotNull('supplier_product_id')
            ->where('supplier_product_id', '>', 0)
            ->orderBy('supplier_id')
            ->orderBy('id')
            ->get();

        $summary['matched_products'] = $products->count();

        if ($products->isEmpty()) {
            return $summary;
        }

        $hasChanges = false;

        foreach ($products->groupBy(fn (Product $product) => (int) ($product->supplier_id ?? 0)) as $supplierProducts) {
            $supplier = $supplierProducts->first()?->supplier;

            if (! $supplier instanceof Supplier) {
                $summary['skipped_products'] += $supplierProducts->count();

                continue;
            }

            if ((int) ($supplier->status ?? 0) !== 1) {
                $summary['skipped_products'] += $supplierProducts->count();

                Log::info('[定时任务] 上游产品配置同步跳过：供应商未启用', [
                    'supplier_id' => $supplier->id,
                    'product_ids' => $supplierProducts->pluck('id')->values()->all(),
                ]);

                continue;
            }

            if (! $this->providerResolver->resolveForSupplier($supplier)->supports(ProvidesConsoleCatalog::class)) {
                $summary['skipped_products'] += $supplierProducts->count();

                continue;
            }

            $summary['matched_suppliers']++;

            try {
                $catalogCapability = $this->resolveCatalogCapability($supplier);
                $remoteConfigOptions = $catalogCapability->fetchBatchProductConfigOptions(
                    $supplier,
                    $supplierProducts
                        ->map(fn (Product $product) => (int) ($product->supplier_product_id ?? 0))
                        ->filter(fn (int $supplierProductId) => $supplierProductId > 0)
                        ->unique()
                        ->values()
                        ->all()
                );
            } catch (\Throwable $exception) {
                $summary['failed_products'] += $supplierProducts->count();

                Log::error('[定时任务] 上游产品配置同步失败：供应商拉取异常', [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'product_ids' => $supplierProducts->pluck('id')->values()->all(),
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);

                continue;
            }

            foreach ($supplierProducts as $product) {
                $supplierProductId = (int) ($product->supplier_product_id ?? 0);
                $normalizedRemoteConfigOptions = $this->normalizeImportedConfigOptions(
                    $remoteConfigOptions[$supplierProductId] ?? []
                );

                if ($normalizedRemoteConfigOptions === []) {
                    $summary['skipped_products']++;

                    continue;
                }

                $localConfigOptions = is_array($product->config_options) ? $product->config_options : [];
                $mergedConfigOptions = $this->mergeConfigOptionsPreservingPricing(
                    $localConfigOptions,
                    $normalizedRemoteConfigOptions
                );

                if ($mergedConfigOptions === $localConfigOptions) {
                    $summary['skipped_products']++;

                    continue;
                }

                $this->persistProductWithStructuredSync($product, [
                    'config_options' => $mergedConfigOptions,
                ]);

                $summary['synced_products']++;
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $this->forgetSiteCatalogCache();
        }

        return $summary;
    }

    public function siteProductStock(int $productId): ?array
    {
        $cacheKey = 'site_product_stock:'.$productId;
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }

        $product = $this->findSaleProductForStock($productId);

        if (! $product instanceof Product) {
            Cache::put($cacheKey, false, now()->addSeconds(10));

            return null;
        }

        $product = $this->applyLiveStockToProduct($product->loadMissing('supplier'));

        $result = [
            'product_id' => (int) $product->id,
            'stock' => (int) ($product->live_stock ?? $product->stock),
        ];

        Cache::put($cacheKey, $result, now()->addSeconds(15));

        return $result;
    }

    public function assertProductCanBeProvisioned(Product $product, int $requiredQuantity = 1): void
    {
        $product = $this->applyLiveStockToProduct($product->loadMissing('supplier'), true);
        $availableStock = (int) ($product->getAttribute('live_stock') ?? $product->stock ?? 0);

        throw_if(
            $availableStock >= 0 && $availableStock < max($requiredQuantity, 1),
            new BusinessException('该商品库存不足，无法继续下单')
        );
    }

    public function applyLiveStockToProduct(Product $product, bool $strict = false): Product
    {
        $products = $this->applyLiveStockToProducts(new Collection([$product]), $strict);

        return $products->first() ?? $product;
    }

    public function applyLiveStockToProducts(Collection $products, bool $strict = false): Collection
    {
        if ($products->isEmpty()) {
            return $products;
        }

        $products->loadMissing('supplier');

        $reservedCounts = $this->queryOpenStockReservations(
            $products->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all()
        );

        $liveStockMap = [];
        $upstreamProducts = $products->filter(function (Product $product) {
            return (int) ($product->supplier_product_id ?? 0) > 0
                && $product->supplier instanceof Supplier
                && $this->providerResolver->resolveForSupplier($product->supplier)->supports(ProvidesConsoleCatalog::class);
        });

        foreach ($upstreamProducts->groupBy(fn (Product $product) => (int) ($product->supplier_id ?? 0)) as $supplierProducts) {
            $supplier = $supplierProducts->first()?->supplier;

            if (! $supplier instanceof Supplier) {
                continue;
            }

            $supplierProductIds = $supplierProducts
                ->map(fn (Product $product) => (int) ($product->supplier_product_id ?? 0))
                ->filter(fn (int $supplierProductId) => $supplierProductId > 0)
                ->values()
                ->all();

            try {
                $remoteStocks = $this->resolveSupplierRemoteStocks($supplier, $supplierProductIds);
            } catch (\Throwable $exception) {
                if ($strict) {
                    throw new BusinessException('暂时无法获取上游库存，请稍后重试');
                }

                $throttleKey = 'stock_log:detail_fail:'.$supplier->id;
                if (! Cache::has($throttleKey)) {
                    Cache::put($throttleKey, true, now()->addSeconds(60));
                    Log::warning('[商品库存] 拉取上游明细库存失败', [
                        'supplier_id' => $supplier->id,
                        'supplier_name' => $supplier->name,
                        'message' => $exception->getMessage(),
                        'exception' => $exception::class,
                    ]);
                }

                $remoteStocks = [];
            }

            foreach ($supplierProducts as $product) {
                $supplierProductId = (int) ($product->supplier_product_id ?? 0);
                $remoteStock = array_key_exists($supplierProductId, $remoteStocks)
                    ? $remoteStocks[$supplierProductId]
                    : null;

                if ($remoteStock === null) {
                    if ($strict) {
                        throw new BusinessException('未找到上游库存信息，请稍后重试');
                    }

                    $notFoundThrottleKey = 'stock_log:not_found:'.$product->id;
                    if (! Cache::has($notFoundThrottleKey)) {
                        Cache::put($notFoundThrottleKey, true, now()->addSeconds(60));
                        Log::warning('[商品库存] 未找到对应上游商品库存', [
                            'product_id' => (int) $product->id,
                            'supplier_id' => (int) ($product->supplier_id ?? 0),
                            'supplier_product_id' => $supplierProductId,
                        ]);
                    }

                    continue;
                }

                $reservedCount = (int) ($reservedCounts[(int) $product->id] ?? 0);
                $liveStockMap[(int) $product->id] = $this->resolveLiveStockValue(
                    (int) ($product->stock ?? -1),
                    $remoteStock,
                    $reservedCount,
                    true
                );
            }
        }

        foreach ($products as $product) {
            $productId = (int) $product->id;
            $product->setAttribute(
                'live_stock',
                $liveStockMap[$productId] ?? $this->resolveLiveStockValue(
                    (int) ($product->stock ?? -1),
                    null,
                    (int) ($reservedCounts[$productId] ?? 0),
                    false
                )
            );
        }

        return $products;
    }

    private function resolveSupplierRemoteStocks(Supplier $supplier, array $supplierProductIds): array
    {
        $normalizedSupplierProductIds = collect($supplierProductIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $supplierProductId) => $supplierProductId > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($normalizedSupplierProductIds === []) {
            return [];
        }

        $cacheKey = $this->supplierRemoteStockCacheKey($supplier, $normalizedSupplierProductIds);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $remoteStocks = $this->fetchSupplierRemoteStocks($supplier, $normalizedSupplierProductIds);

        Cache::put($cacheKey, $remoteStocks, now()->addSeconds(self::REMOTE_STOCK_CACHE_TTL_SECONDS));

        return $remoteStocks;
    }

    private function fetchSupplierRemoteStocks(Supplier $supplier, array $supplierProductIds): array
    {
        $configStocks = $this->resolveCatalogCapability($supplier)->fetchBatchProductStocks($supplier, $supplierProductIds);
        $catalogProducts = [];
        $missingCatalogIds = collect($supplierProductIds)
            ->filter(fn (int $supplierProductId) => ! is_array($configStocks[$supplierProductId] ?? null))
            ->values()
            ->all();

        if ($missingCatalogIds !== []) {
            $missingCatalogLookup = array_fill_keys($missingCatalogIds, true);
            $catalog = $this->resolveCatalogCapability($supplier)->getProductCatalog($supplier);
            $catalogProducts = collect($catalog['products'] ?? [])
                ->filter(fn ($item) => is_array($item) && isset($missingCatalogLookup[(int) ($item['id'] ?? 0)]))
                ->keyBy(fn (array $item) => (int) ($item['id'] ?? 0))
                ->all();
        }

        $remoteStocks = [];

        foreach ($supplierProductIds as $supplierProductId) {
            $remoteStocks[$supplierProductId] = $this->resolvePreferredRemoteStock(
                $configStocks[$supplierProductId] ?? null,
                $catalogProducts[$supplierProductId] ?? null
            );
        }

        return $remoteStocks;
    }

    private function supplierRemoteStockCacheKey(Supplier $supplier, array $supplierProductIds): string
    {
        return 'product_remote_stock:'.$supplier->id.':'.sha1(implode(',', $supplierProductIds));
    }

    private function findSaleProductForStock(int $productId): ?Product
    {
        return $this->saleProductQuery()
            ->select([
                'id',
                'product_group_id',
                'supplier_id',
                'supplier_product_id',
                'stock',
            ])
            ->whereKey($productId)
            ->first();
    }

    private function resolveRemoteCatalogStock(array $remoteProduct): int
    {
        if (array_key_exists('stock', $remoteProduct)) {
            return (int) $remoteProduct['stock'];
        }

        if ((int) ($remoteProduct['stock_control'] ?? 0) !== 1) {
            return -1;
        }

        $qty = $remoteProduct['qty'] ?? null;
        if ($qty === null || $qty === '' || ! is_numeric($qty)) {
            return 0;
        }

        return max((int) $qty, 0);
    }

    private function resolvePreferredRemoteStock(?array $configStock, mixed $catalogProduct): ?int
    {
        if (is_array($configStock) && array_key_exists('stock', $configStock)) {
            return (int) $configStock['stock'];
        }

        if (is_array($catalogProduct)) {
            return $this->resolveRemoteCatalogStock($catalogProduct);
        }

        return null;
    }

    private function resolveLiveStockValue(int $localStock, ?int $remoteStock, int $reservedCount, bool $preferUpstream): int
    {
        if (! $preferUpstream || $remoteStock === null) {
            return $localStock;
        }

        if ($remoteStock < 0) {
            return $localStock >= 0 ? $localStock : -1;
        }

        return max($remoteStock - max($reservedCount, 0), 0);
    }

    private function buildBatchSyncSkippedItem(int $productId, ?Product $product, string $reason): array
    {
        return [
            'status' => 'skipped',
            'product_id' => $productId,
            'product_name' => $product instanceof Product ? (string) $product->name : '',
            'supplier_id' => $product instanceof Product ? (int) ($product->supplier_id ?? 0) : 0,
            'supplier_name' => $product?->supplier instanceof Supplier ? (string) ($product->supplier->name ?? '') : '',
            'updated_fields' => [],
            'reason' => $reason,
        ];
    }

    private function prefetchImportedConfigOptions(Supplier $supplier, array $supplierProductIds): array
    {
        $normalizedSupplierProductIds = collect($supplierProductIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $supplierProductId) => $supplierProductId > 0)
            ->unique()
            ->values()
            ->all();

        if ($normalizedSupplierProductIds === []) {
            return [];
        }

        try {
            $templates = $this->resolveCatalogCapability($supplier)->fetchBatchProductConfigOptions($supplier, $normalizedSupplierProductIds);
        } catch (\Throwable $exception) {
            Log::warning('[商品同步] 批量拉取上游配置项失败', [
                'supplier_id' => (int) $supplier->id,
                'supplier_product_ids' => $normalizedSupplierProductIds,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return [];
        }

        $result = [];

        foreach ($normalizedSupplierProductIds as $supplierProductId) {
            $result[$supplierProductId] = $this->normalizeImportedConfigOptions($templates[$supplierProductId] ?? []);
        }

        return $result;
    }

    private function syncSingleSupplierProduct(
        Product $product,
        array $catalogProducts,
        array $remoteConfigOptions,
        bool $syncPricing,
        bool $syncConfigOptions,
        bool $syncConfigPricing,
    ): array {
        $supplierProductId = (int) ($product->supplier_product_id ?? 0);
        $supplier = $product->supplier;

        if (! $supplier instanceof Supplier) {
            return $this->buildBatchSyncSkippedItem((int) $product->id, $product, '商品未绑定供应商');
        }

        $payload = [];
        $updatedFields = [];

        if ($syncPricing) {
            $remoteProduct = $catalogProducts[$supplierProductId] ?? null;
            if (! is_array($remoteProduct)) {
                return $this->buildBatchSyncSkippedItem((int) $product->id, $product, '未找到对应的上游商品');
            }

            $pricing = $this->buildImportedPricing($remoteProduct);
            if ($pricing === []) {
                return $this->buildBatchSyncSkippedItem((int) $product->id, $product, '上游商品缺少可导入价格');
            }

            if ($pricing !== $this->normalizePricing($product->pricing)) {
                $payload['pricing'] = $pricing;
                $updatedFields[] = 'pricing';
            }

            $remoteStock = $this->resolveRemoteCatalogStock($remoteProduct);
            if ($remoteStock !== (int) ($product->stock ?? 0)) {
                $payload['stock'] = $remoteStock;
                $updatedFields[] = 'stock';
            }
        }

        if ($syncConfigOptions || $syncConfigPricing) {
            $normalizedRemoteConfigOptions = $this->normalizeImportedConfigOptions(
                $remoteConfigOptions[$supplierProductId] ?? []
            );
            $localConfigOptions = $this->normalizeConfigOptions($product->config_options);

            if ($normalizedRemoteConfigOptions !== []) {
                $mergedConfigOptions = $syncConfigPricing
                    ? $normalizedRemoteConfigOptions
                    : $this->mergeConfigOptionsPreservingPricing($localConfigOptions, $normalizedRemoteConfigOptions);

                if ($mergedConfigOptions !== $localConfigOptions) {
                    $payload['config_options'] = $mergedConfigOptions;
                    $updatedFields[] = 'config_options';
                }
            }
        }

        if ($payload === []) {
            return [
                'status' => 'skipped',
                'updated_fields' => [],
                'reason' => '当前商品无需更新',
            ];
        }

        $this->persistProductWithStructuredSync($product, $payload);

        return [
            'status' => 'synced',
            'updated_fields' => $updatedFields,
            'reason' => '',
        ];
    }

    private function productNeedsUpstreamFinalize(Product $product, bool $syncConfigOptions): bool
    {
        $supplier = $product->supplier;
        if (! $supplier instanceof Supplier) {
            return false;
        }

        $targetModule = trim((string) ($supplier->interface_type ?? ''));
        if ($targetModule !== '' && trim((string) ($product->provision_module ?? '')) !== $targetModule) {
            return true;
        }

        if ((int) ($product->auto_setup ?? 0) !== 1) {
            return true;
        }

        if (! $syncConfigOptions) {
            return false;
        }

        return true;
    }

    private function buildFinalizeUpstreamSkippedItem(Product $product, string $reason): array
    {
        return [
            'status' => 'skipped',
            'product_id' => (int) $product->id,
            'product_name' => (string) $product->name,
            'supplier_id' => (int) ($product->supplier_id ?? 0),
            'supplier_name' => $product->supplier instanceof Supplier ? (string) ($product->supplier->name ?? '') : '',
            'supplier_product_id' => (int) ($product->supplier_product_id ?? 0),
            'reason' => $reason,
        ];
    }

    private function mergeConfigOptionsPreservingPricing(array $localConfigOptions, array $remoteConfigOptions): array
    {
        $localMap = collect($localConfigOptions)
            ->filter(fn ($item) => is_array($item))
            ->keyBy(fn (array $item, int $index) => $this->resolveConfigOptionKey($item, $index));

        return collect($remoteConfigOptions)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $remoteOption, int $index) use ($localMap) {
                $key = $this->resolveConfigOptionKey($remoteOption, $index);
                $localOption = $localMap->get($key);

                if (! is_array($localOption)) {
                    return $remoteOption;
                }

                $mergedOption = $remoteOption;

                if (array_key_exists('pricing', $localOption)) {
                    $mergedOption['pricing'] = $localOption['pricing'];
                }

                if (array_key_exists('default_value', $localOption)) {
                    $mergedOption['default_value'] = $localOption['default_value'];
                }

                $localSubMap = collect($localOption['sub'] ?? [])
                    ->filter(fn ($sub) => is_array($sub))
                    ->keyBy(fn (array $sub, int $subIndex) => $this->resolveConfigSubOptionKey($sub, $subIndex));

                $mergedOption['sub'] = collect($remoteOption['sub'] ?? [])
                    ->filter(fn ($sub) => is_array($sub))
                    ->map(function (array $remoteSub, int $subIndex) use ($localSubMap) {
                        $localSub = $localSubMap->get($this->resolveConfigSubOptionKey($remoteSub, $subIndex));
                        if (! is_array($localSub)) {
                            return $remoteSub;
                        }

                        if (array_key_exists('pricing', $localSub)) {
                            $remoteSub['pricing'] = $localSub['pricing'];
                        }

                        return $remoteSub;
                    })
                    ->values()
                    ->all();

                return $mergedOption;
            })
            ->values()
            ->all();
    }

    private function buildImportedPricing(array $supplierProduct): array
    {
        $monthlyBasePrice = $this->resolveMonthlyBaseAmount(
            $supplierProduct['monthly_price'] ?? null,
            $supplierProduct['product_price'] ?? null,
            $supplierProduct['billingcycle'] ?? ''
        );

        if ($monthlyBasePrice === null) {
            return [];
        }

        $pricing = [];

        foreach (self::IMPORT_PRICING_MONTHS as $cycle => $months) {
            $pricing[$cycle] = number_format($monthlyBasePrice * $months, 2, '.', '');
        }

        return $pricing;
    }

    private function resolveConfigOptionKey(array $option, int $index): string
    {
        $field = trim((string) ($option['field'] ?? ''));
        if ($field !== '') {
            return 'field:'.$field;
        }

        $name = trim((string) ($option['name'] ?? ''));
        if ($name !== '') {
            return 'name:'.$name;
        }

        return 'index:'.$index;
    }

    private function resolveConfigSubOptionKey(array $subOption, int $index): string
    {
        $id = (int) ($subOption['id'] ?? 0);
        if ($id > 0) {
            return 'id:'.$id;
        }

        $value = trim((string) ($subOption['option_name_first'] ?? $subOption['option_name'] ?? ''));
        if ($value !== '') {
            return 'value:'.$value;
        }

        return 'index:'.$index;
    }

    private function normalizeImportedAmount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $amount = (float) $value;

        return $amount > 0 ? number_format($amount, 2, '.', '') : null;
    }

    private function resolveMonthlyBaseAmount(mixed $monthlyCandidate, mixed $productPriceCandidate, mixed $billingCycle): ?float
    {
        $monthlyAmount = $this->normalizeImportedAmount($monthlyCandidate);
        if ($monthlyAmount !== null) {
            return (float) $monthlyAmount;
        }

        $productPrice = $this->normalizeImportedAmount($productPriceCandidate);
        if ($productPrice === null) {
            return null;
        }

        $months = $this->resolveBillingCycleMonths($billingCycle);
        if ($months <= 0) {
            return null;
        }

        return round(((float) $productPrice) / $months, 2);
    }

    private function mapSupplierBillingCycle(mixed $billingCycle): string
    {
        return match (strtolower(trim((string) $billingCycle))) {
            'free', 'monthly' => 'monthly',
            'quarterly' => 'quarterly',
            'semiannually', 'semi-annually', 'semi' => 'semiannually',
            'annually', 'yearly' => 'annually',
            'biennially', 'biennial' => 'biennially',
            'triennially', 'triennial' => 'triennially',
            'onetime', 'one_time', 'once' => 'onetime',
            default => 'monthly',
        };
    }

    private function resolveBillingCycleMonths(mixed $billingCycle): int
    {
        $cycle = $this->mapSupplierBillingCycle($billingCycle);

        return self::IMPORT_PRICING_MONTHS[$cycle] ?? 1;
    }

    private function resolveImportedChildGroupName(array $supplierProduct): string
    {
        foreach (['group_label', 'group_name', 'first_group_name'] as $key) {
            $value = trim((string) ($supplierProduct[$key] ?? ''));
            if ($value !== '') {
                return mb_substr($value, 0, 100);
            }
        }

        return '默认子菜单';
    }

    private function resolveImportedConfigOptions(Supplier $supplier, array $supplierProduct, array $fallback = []): array
    {
        $supplierProductId = (int) ($supplierProduct['id'] ?? 0);
        if ($supplierProductId <= 0) {
            return $fallback;
        }

        try {
            $template = $this->resolveCatalogCapability($supplier)->getProductConfigTemplate($supplier, $supplierProductId);
            $configOptions = $this->normalizeImportedConfigOptions($template['config_options'] ?? []);

            return $configOptions !== [] ? $configOptions : $fallback;
        } catch (\Throwable $exception) {
            Log::warning('[商品批量对接] 自动拉取配置项失败', [
                'supplier_id' => $supplier->id,
                'supplier_product_id' => $supplierProductId,
                'message' => $exception->getMessage(),
            ]);

            return $fallback;
        }
    }

    private function resolveCatalogCapability(Supplier $supplier): object
    {
        return $this->providerResolver
            ->resolveForSupplier($supplier)
            ->require(ProvidesConsoleCatalog::class, '当前供应商不支持商品目录同步');
    }

    private function normalizeImportedConfigOptions(mixed $configOptions): array
    {
        $items = $this->normalizeConfigOptions($configOptions);

        return collect($items)
            ->map(function (array $item, int $index) {
                $normalized = $item;
                $normalized['sort_order'] = (int) ($item['sort_order'] ?? $item['order'] ?? ($index + 1));
                $normalized['required'] = (int) ($item['required'] ?? 0);
                $normalized['hidden'] = (int) ($item['hidden'] ?? 0);
                $normalized['allow_upgrade'] = (int) ($item['allow_upgrade'] ?? 0);
                $normalized['allow_promo_code'] = array_key_exists('allow_promo_code', $item)
                    ? (int) $item['allow_promo_code']
                    : 1;
                $normalized['parameter'] = trim((string) ($item['parameter'] ?? $this->buildImportedConfigParameter($item)));
                $normalized['sub'] = $this->normalizeImportedConfigSubOptions($item['sub'] ?? []);

                return $normalized;
            })
            ->values()
            ->all();
    }

    private function normalizeImportedConfigSubOptions(mixed $subOptions): array
    {
        if (! is_array($subOptions)) {
            return [];
        }

        return collect($subOptions)
            ->filter(fn ($sub) => is_array($sub))
            ->map(function (array $sub, int $index) {
                $pricing = $this->normalizeImportedSubPricing($sub['pricing'] ?? $sub['pricings'] ?? []);
                $optionName = trim((string) ($sub['option_name'] ?? $sub['version'] ?? ''));
                $optionNameFirst = trim((string) ($sub['option_name_first'] ?? ''));

                if ($optionNameFirst === '') {
                    $optionNameFirst = $optionName !== '' ? $optionName : (string) ($sub['id'] ?? '');
                }

                return array_merge($sub, [
                    'option_name' => $optionName,
                    'option_name_first' => $optionNameFirst,
                    'hidden' => (int) ($sub['hidden'] ?? 0),
                    'sort_order' => (int) ($sub['sort_order'] ?? $sub['order'] ?? $index),
                    'qty_minimum' => (int) ($sub['qty_minimum'] ?? 0),
                    'qty_maximum' => (int) ($sub['qty_maximum'] ?? 0),
                    'pricing' => $pricing,
                ]);
            })
            ->values()
            ->all();
    }

    private function normalizeImportedSubPricing(mixed $pricing): array
    {
        $pricingData = [];

        if (is_array($pricing)) {
            $pricingData = isset($pricing[0]) && is_array($pricing[0])
                ? (array) $pricing[0]
                : $pricing;
        }

        $directPricing = $this->normalizePricing($this->normalizeImportedPricingKeys($pricingData));
        if ($directPricing !== []) {
            return $directPricing;
        }

        $monthlyBasePrice = $this->resolveMonthlyBaseAmount(
            $pricingData['monthly'] ?? null,
            $this->resolveFirstAvailablePricingValue($pricingData),
            $this->resolveFirstAvailablePricingCycle($pricingData)
        );

        if ($monthlyBasePrice === null) {
            return [];
        }

        $normalized = [];
        foreach (self::IMPORT_PRICING_MONTHS as $cycle => $months) {
            $normalized[$cycle] = number_format($monthlyBasePrice * $months, 2, '.', '');
        }

        return $normalized;
    }

    private function normalizeImportedPricingKeys(array $pricingData): array
    {
        $normalized = [];
        $cycleMap = [
            'hour' => 'hour',
            'day' => 'day',
            'ontrial' => 'ontrial',
            'monthly' => 'monthly',
            'quarterly' => 'quarterly',
            'semiannually' => 'semiannually',
            'annually' => 'annually',
            'biennially' => 'biennially',
            'triennially' => 'triennially',
            'fourly' => 'fourly',
            'fively' => 'fively',
            'sixly' => 'sixly',
            'sevenly' => 'sevenly',
            'eightly' => 'eightly',
            'ninely' => 'ninely',
            'tenly' => 'tenly',
            'onetime' => 'one_time',
            'one_time' => 'one_time',
        ];

        foreach ($cycleMap as $source => $target) {
            if (! array_key_exists($source, $pricingData)) {
                continue;
            }

            $normalized[$target] = $pricingData[$source];
        }

        return $normalized;
    }

    private function resolveFirstAvailablePricingValue(array $pricingData): mixed
    {
        foreach (array_keys(self::IMPORT_PRICING_MONTHS) as $cycle) {
            if (($pricingData[$cycle] ?? null) !== null && $pricingData[$cycle] !== '') {
                return $pricingData[$cycle];
            }
        }

        foreach (['biennially', 'triennially', 'onetime', 'onetime_fee', 'yearly'] as $cycle) {
            if (($pricingData[$cycle] ?? null) !== null && $pricingData[$cycle] !== '') {
                return $pricingData[$cycle];
            }
        }

        return null;
    }

    private function resolveFirstAvailablePricingCycle(array $pricingData): string
    {
        foreach (array_keys(self::IMPORT_PRICING_MONTHS) as $cycle) {
            if (($pricingData[$cycle] ?? null) !== null && $pricingData[$cycle] !== '') {
                return $cycle;
            }
        }

        foreach (['biennially', 'triennially', 'onetime', 'onetime_fee', 'yearly'] as $cycle) {
            if (($pricingData[$cycle] ?? null) !== null && $pricingData[$cycle] !== '') {
                return $cycle;
            }
        }

        return 'monthly';
    }

    private function buildImportedConfigParameter(array $item): string
    {
        if (! is_array($item['sub'] ?? null)) {
            return '';
        }

        return collect($item['sub'])
            ->filter(fn ($sub) => is_array($sub) && (int) ($sub['hidden'] ?? 0) !== 1)
            ->map(function (array $sub) {
                $value = trim((string) ($sub['option_name_first'] ?? $sub['option_name'] ?? $sub['id'] ?? ''));
                $label = trim((string) ($sub['version'] ?? $sub['option_name'] ?? $sub['option_name_first'] ?? $sub['id'] ?? ''));

                return $value !== '' ? "{$value}|{$label}" : '';
            })
            ->filter()
            ->implode(',');
    }

    private function resolveImportedRootCategory(string $productType, int $rootCategoryId, ?string $rootGroupName): ?ProductCategory
    {
        if ($rootCategoryId > 0) {
            /** @var ProductCategory|null $category */
            $category = ProductCategory::query()->with('parent')->find($rootCategoryId);
            throw_if(! $category, new BusinessException('目标一级分类不存在'));
            throw_if(
                trim((string) ($category->product_type ?? '')) !== $productType,
                new BusinessException('目标一级分类与所属一级菜单不匹配')
            );

            return $category->parent instanceof ProductCategory ? $category->parent : $category;
        }

        if ($rootGroupName !== null && $rootGroupName !== '') {
            return $this->resolveOrCreateImportedRootGroup($productType, $rootGroupName);
        }

        return null;
    }

    private function resolveImportedChildCategory(
        string $productType,
        int $childCategoryId,
        ?ProductCategory $rootCategory
    ): ?ProductCategory {
        if ($childCategoryId <= 0) {
            return null;
        }

        /** @var ProductCategory|null $category */
        $category = ProductCategory::query()->with('parent')->find($childCategoryId);
        throw_if(! $category, new BusinessException('目标子分类不存在'));
        throw_if($category->parent_id === null, new BusinessException('请选择最终子分类'));
        throw_if(
            trim((string) ($category->product_type ?? '')) !== $productType,
            new BusinessException('目标子分类与所属一级菜单不匹配')
        );

        if ($rootCategory instanceof ProductCategory) {
            throw_if(
                (int) ($category->parent_id ?? 0) !== (int) $rootCategory->id,
                new BusinessException('目标子分类不属于所选一级分类')
            );
        }

        return $category;
    }

    private function resolveImportedTargetCategory(
        string $productType,
        ?ProductCategory $rootCategory,
        array $supplierProduct
    ): ProductCategory {
        $resolvedRoot = $rootCategory;
        if (! $resolvedRoot instanceof ProductCategory) {
            $fallbackRootName = trim((string) ($supplierProduct['first_group_name'] ?? ''));
            if ($fallbackRootName === '') {
                $fallbackRootName = '默认分类';
            }

            $resolvedRoot = $this->resolveOrCreateImportedRootGroup($productType, mb_substr($fallbackRootName, 0, 100));
        }

        return $this->resolveOrCreateImportedChildGroup(
            $resolvedRoot,
            $this->resolveImportedChildGroupName($supplierProduct),
            $productType
        );
    }

    private function resolveImportedBatchConfigOptions(
        Supplier $supplier,
        array $supplierProduct,
        bool $syncConfigOptions,
        mixed $fallbackConfigOptions
    ): array {
        $fallback = $this->normalizeConfigOptions($fallbackConfigOptions);
        if (! $syncConfigOptions) {
            return $fallback;
        }

        return $this->resolveImportedConfigOptions($supplier, $supplierProduct, $fallback);
    }

    private function buildBulkConnectProductPayload(
        Supplier $supplier,
        ProductCategory $targetCategory,
        array $supplierProduct,
        string $productType,
        array $pricing,
        int $defaultStatus,
        int $defaultAutoSetup,
        array $configOptions
    ): array {
        $name = TextSanitizer::nullable((string) ($supplierProduct['name'] ?? ''));
        throw_if($name === null, new BusinessException('上游商品名称不能为空'));
        $purchaseRequires = $this->buildImportedPurchaseRequires($name);

        return [
            'category_id' => (int) $targetCategory->id,
            'name' => $name,
            'product_type' => $productType,
            'pricing' => $pricing,
            'setup_fee' => $this->normalizeImportedAmount($supplierProduct['setup_fee'] ?? null) ?? '0.00',
            'config_options' => $configOptions,
            'purchase_requires' => $purchaseRequires,
            'stock' => $this->resolveRemoteCatalogStock($supplierProduct),
            'status' => $defaultStatus,
            'sort_order' => 0,
            'provision_module' => trim((string) ($supplier->interface_type ?? '')) ?: null,
            'auto_setup' => $defaultAutoSetup,
            'supplier_id' => (int) $supplier->id,
            'supplier_product_id' => (int) ($supplierProduct['id'] ?? 0),
        ];
    }

    private function buildImportedPurchaseRequires(string $name): array
    {
        [$cpu, $memory] = $this->extractCpuMemoryDefaultsFromName($name);
        if ($cpu === null && $memory === null) {
            return [];
        }

        $defaultConfig = [];
        if ($cpu !== null) {
            $defaultConfig['cpu'] = $cpu;
        }
        if ($memory !== null) {
            $defaultConfig['memory'] = $memory;
        }

        return [
            'upstream_default_config' => $defaultConfig,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function extractCpuMemoryDefaultsFromName(string $name): array
    {
        $normalizedName = trim($name);
        if ($normalizedName === '') {
            return [null, null];
        }

        $patterns = [
            '/(\d+(?:\.\d+)?)\s*(?:v?cpu|核|c|h)\s*[-_\/ ]*(\d+(?:\.\d+)?)\s*(g|gb|m|mb)\b/iu',
            '/(\d+(?:\.\d+)?)\s*(?:c|h|核)\s*(\d+(?:\.\d+)?)\s*(g|gb|m|mb)\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalizedName, $matches) !== 1) {
                continue;
            }

            $cpu = $this->normalizeImportedConfigNumeric($matches[1]);
            $memory = $this->normalizeImportedMemoryConfigValue($matches[2], $matches[3] ?? '');

            return [$cpu, $memory];
        }

        return [null, null];
    }

    private function normalizeImportedConfigNumeric(string $value): ?string
    {
        $number = (float) $value;
        if ($number <= 0) {
            return null;
        }

        if (floor($number) === $number) {
            return (string) ((int) $number);
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function normalizeImportedMemoryConfigValue(string $number, string $unit): ?string
    {
        $numeric = (float) $number;
        if ($numeric <= 0) {
            return null;
        }

        $normalizedUnit = strtolower(trim($unit));
        if (in_array($normalizedUnit, ['g', 'gb'], true)) {
            return $this->normalizeImportedConfigNumeric($number);
        }

        if (in_array($normalizedUnit, ['m', 'mb'], true)) {
            return (string) ((int) round($numeric));
        }

        return $this->normalizeImportedConfigNumeric($number);
    }

    private function buildBulkConnectImportedItem(
        Product $product,
        int $supplierProductId,
        array $supplierProduct,
        string $action
    ): array {
        $group = $product->categoryMapping;
        $parentGroup = $group?->parent;

        return [
            'action' => $action,
            'product_id' => (int) $product->id,
            'supplier_product_id' => $supplierProductId,
            'supplier_display_name' => (string) ($supplierProduct['name'] ?? ''),
            'local_display_name' => $this->resolveProductDisplayName($product),
            'group_full_name' => $parentGroup instanceof ProductCategory
                ? $parentGroup->name.' / '.($group?->name ?? '')
                : (string) ($group?->name ?? ''),
        ];
    }

    private function buildBulkConnectSkippedItem(int $supplierProductId, ?array $supplierProduct, string $reason): array
    {
        return [
            'supplier_product_id' => $supplierProductId,
            'supplier_display_name' => (string) ($supplierProduct['name'] ?? ''),
            'reason' => $reason,
        ];
    }

    private function resolveProductDisplayName(Product $product): string
    {
        $resolver = $this->productDisplayNameResolver ?? new ProductDisplayNameResolver;
        $displayName = trim((string) ($resolver->resolveForProduct($product)['product_display_name'] ?? ''));

        return $displayName !== '' ? $displayName : ('未配置规格 #'.(int) $product->id);
    }

    private function generateUniqueCategorySlug(string $source, ?int $ignoreId = null): string
    {
        $slug = Str::slug(trim($source));
        if ($slug === '') {
            $slug = 'category';
        }

        $candidate = $slug;
        $suffix = 1;

        while (
            ProductCategory::query()
                ->when($ignoreId, fn (Builder $query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $candidate)
                ->exists()
        ) {
            $suffix++;
            $candidate = $slug.'-'.$suffix;
        }

        return $candidate;
    }

    private function resolveOrCreateImportedRootGroup(string $productType, string $rootGroupName): ProductCategory
    {
        $existing = ProductCategory::query()
            ->whereNull('parent_group_id')
            ->where('product_type', $productType)
            ->where('name', $rootGroupName)
            ->first();

        if ($existing) {
            return $existing;
        }

        /** @var ProductCategory $category */
        $category = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => $productType,
            'name' => $rootGroupName,
            'slogan' => null,
            'slug' => $this->generateUniqueCategorySlug($rootGroupName),
            'sort_order' => 0,
            'is_visible' => 1,
        ]);

        return $category->fresh();
    }

    private function resolveOrCreateImportedChildGroup(ProductCategory $rootGroup, string $childGroupName, string $productType): ProductCategory
    {
        $existing = ProductCategory::query()
            ->where('parent_group_id', $rootGroup->id)
            ->where('name', $childGroupName)
            ->first();

        if ($existing) {
            return $existing;
        }

        /** @var ProductCategory $category */
        $category = ProductCategory::query()->create([
            'parent_id' => $rootGroup->id,
            'product_type' => $productType,
            'name' => $childGroupName,
            'slogan' => null,
            'slug' => $this->generateUniqueCategorySlug($rootGroup->name.'-'.$childGroupName),
            'sort_order' => 0,
            'is_visible' => 1,
        ]);

        return $category->fresh();
    }

    private function createProductWithStructuredSync(array $payload): Product
    {
        /** @var Product $product */
        $product = Product::withoutEvents(fn () => Product::create($payload));

        return $product->fresh([
            'categoryMapping.parent',
            'supplier',
        ]);
    }

    private function persistProductWithStructuredSync(Product $product, array $payload): Product
    {
        Product::withoutEvents(function () use ($product, $payload): void {
            $product->fill($payload)->save();
        });

        if ($product->trashed()) {
            $product->restore();
        }

        $product->refresh();

        return $product->fresh([
            'categoryMapping.parent',
            'supplier',
        ]);
    }

    private function queryOpenStockReservations(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return Order::query()
            ->selectRaw('product_id, SUM(CASE WHEN quantity IS NULL OR quantity < 1 THEN 1 ELSE quantity END) as aggregate')
            ->whereIn('product_id', $productIds)
            ->where('type', 'new')
            ->whereIn('status', [
                OrderStatus::PENDING,
                OrderStatus::PAID,
                OrderStatus::PROCESSING,
            ])
            ->where(function ($query) {
                $query->whereNull('service_id')
                    // 服务已挂单但仍在开通中时，库存仍然需要继续占用。
                    ->orWhereHas('service', function ($serviceQuery) {
                        $serviceQuery->where('status', ServiceStatus::PENDING);
                    });
            })
            ->groupBy('product_id')
            ->pluck('aggregate', 'product_id')
            ->map(fn ($count) => (int) $count)
            ->all();
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
}
