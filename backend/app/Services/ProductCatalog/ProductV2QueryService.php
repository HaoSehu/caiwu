<?php

declare(strict_types=1);

namespace App\Services\ProductCatalog;

use App\Constants\ProductType;
use App\Exceptions\BusinessException;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductV2QueryService
{
    public function __construct(
        private readonly ProductCatalogService $catalog,
        private readonly ProductGroupV2QueryService $productGroups,
        private readonly ProductSiteService $siteProducts,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateAdminProducts(array $filters): LengthAwarePaginator
    {
        return $this->catalog->adminProductList($filters, $this->perPage($filters, 20, 100));
    }

    /**
     * @return array<string, mixed>
     */
    public function adminSummary(): array
    {
        return $this->catalog->adminSummary();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function adminProductOwners(Product $product, array $filters, int $pageSize): array
    {
        return $this->catalog->adminProductOwners($product, $filters, $pageSize);
    }

    public function findAdminProduct(int $productId): Product
    {
        $product = Product::query()
            ->withTrashed()
            ->select($this->adminProductDetailColumns())
            ->with([
                'firstProductGroup',
                'secondProductGroup',
                'thirdProductGroup',
                'upstreamBindings.supplierPluginBinding.supplier',
            ])
            ->withCount([
                'orders',
                'services as total_services_count',
                'services as active_services_count' => fn (Builder $query) => $query->where('status', 1),
            ])
            ->find($productId);

        if (! $product instanceof Product) {
            throw new BusinessException('商品不存在', 40400, 404);
        }

        return $product;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateSiteProducts(array $filters): LengthAwarePaginator
    {
        $paginator = $this->siteProductQuery($filters)
            ->select($this->siteProductListColumns())
            ->with([
                'firstProductGroup',
                'secondProductGroup',
                'thirdProductGroup',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPage($filters, 20, 50), ['*'], 'page', $this->page($filters));

        return $this->attachCpuModelPayloads($paginator);
    }

    public function findSiteProduct(int $productId): Product
    {
        $product = $this->siteProductQuery([])
            ->select($this->siteProductDetailColumns())
            ->with([
                'firstProductGroup',
                'secondProductGroup',
                'thirdProductGroup',
            ])
            ->whereKey($productId)
            ->first();

        if (! $product instanceof Product) {
            throw new BusinessException('商品不存在', 40400, 404);
        }

        return $product;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function purchaseContext(array $filters): array
    {
        $types = $this->catalog->siteProductTypes();
        $productType = trim((string) ($filters['product_type'] ?? ''));

        if ($productType === '' && $types !== []) {
            $productType = (string) ($types[0]['value'] ?? '');
        }

        $rootGroups = $this->productGroups->paginateSiteRootGroups([
            'product_type' => $productType !== '' ? $productType : null,
            'page' => 1,
            'page_size' => $filters['root_page_size'] ?? 50,
        ]);

        return [
            'types' => $types,
            'root_groups' => $rootGroups,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function siteProductQuery(array $filters): Builder
    {
        $visibleProductTypes = ProductType::visibleValues();

        if ($visibleProductTypes === []) {
            return Product::query()->whereRaw('1 = 0');
        }

        $productType = trim((string) ($filters['product_type'] ?? $filters['type'] ?? ''));
        if ($productType !== '' && ! in_array($productType, $visibleProductTypes, true)) {
            return Product::query()->whereRaw('1 = 0');
        }

        return Product::query()
            ->onSale()
            ->whereNotNull('first_product_group_id')
            ->whereNotNull('second_product_group_id')
            ->whereHas('firstProductGroup', fn (Builder $query) => $query
                ->where('is_visible', 1)
                ->whereIn('code', $visibleProductTypes)
                ->when($productType !== '', fn (Builder $typeQuery) => $typeQuery->where('code', $productType)))
            ->whereHas('secondProductGroup', fn (Builder $query) => $query->where('is_visible', 1))
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('third_product_group_id')
                    ->orWhereHas('thirdProductGroup', fn (Builder $thirdQuery) => $thirdQuery->where('is_visible', 1));
            })
            ->when(isset($filters['first_product_group_id']), fn (Builder $query) => $query->where('first_product_group_id', (int) $filters['first_product_group_id']))
            ->when(isset($filters['second_product_group_id']), fn (Builder $query) => $query->where('second_product_group_id', (int) $filters['second_product_group_id']))
            ->when(isset($filters['third_product_group_id']), fn (Builder $query) => $query->where('third_product_group_id', (int) $filters['third_product_group_id']))
            ->when(isset($filters['effective_product_group_id']), function (Builder $query) use ($filters): void {
                $groupId = (int) $filters['effective_product_group_id'];
                $query->where(function (Builder $groupQuery) use ($groupId): void {
                    $groupQuery
                        ->where('third_product_group_id', $groupId)
                        ->orWhere(function (Builder $secondQuery) use ($groupId): void {
                            $secondQuery
                                ->where('second_product_group_id', $groupId)
                                ->whereNull('third_product_group_id');
                        });
                });
            });
    }

    /**
     * @return list<string>
     */
    private function adminProductDetailColumns(): array
    {
        return [
            'id',
            'product_type',
            'remark',
            'pricing',
            'setup_fee',
            'config_options',
            'purchase_requires',
            'stock',
            'status',
            'sort_order',
            'auto_setup',
            'deleted_at',
            ...Product::optionalSelectColumns([
                'custom_display_name',
                'first_product_group_id',
                'second_product_group_id',
                'third_product_group_id',
                'service_type_code',
            ]),
            'created_at',
            'updated_at',
        ];
    }

    /**
     * @return list<string>
     */
    private function siteProductListColumns(): array
    {
        return [
            'id',
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
            'sort_order',
        ];
    }

    /**
     * @return list<string>
     */
    private function siteProductDetailColumns(): array
    {
        return [
            ...$this->siteProductListColumns(),
            'status',
        ];
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
    private function perPage(array $filters, int $default, int $max): int
    {
        $pageSize = (int) ($filters['page_size'] ?? $default);

        return min(max($pageSize, 1), $max);
    }

    private function attachCpuModelPayloads(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        foreach ($paginator->items() as $item) {
            if (! $item instanceof Product) {
                continue;
            }

            foreach ($this->siteProducts->cpuModelPayloadForProduct($item) as $key => $value) {
                $item->setAttribute($key, $value);
            }
        }

        return $paginator;
    }
}
