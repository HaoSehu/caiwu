<?php

declare(strict_types=1);

namespace App\Services\ProductCatalog;

use App\Constants\ProductType;
use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductCatalog\Concerns\HandlesProductCatalogHelpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductCategoryService
{
    use HandlesProductCatalogHelpers;

    private const ADMIN_SUMMARY_CACHE_TTL_SECONDS = 60;

    public function adminSummary(): array
    {
        return Cache::remember(
            self::ADMIN_SUMMARY_CACHE_KEY,
            now()->addSeconds(self::ADMIN_SUMMARY_CACHE_TTL_SECONDS),
            fn () => [
                'groups_total' => ProductCategory::count(),
                'root_groups_total' => ProductCategory::query()->whereNull('parent_group_id')->count(),
                'sub_groups_total' => ProductCategory::query()->whereNotNull('parent_group_id')->count(),
                'products_total' => Product::query()->count(),
                'products_active' => Product::query()->where('status', 1)->count(),
                'products_low_stock' => Product::query()->where('stock', '>=', 0)->where('stock', '<=', 5)->count(),
            ]
        );
    }

    public function adminCategoryTree(?string $productType = null): Collection
    {
        return ProductCategory::query()
            ->whereNull('parent_group_id')
            ->when(
                $productType,
                fn (Builder $query) => $query->where('product_type', $productType)
            )
            ->withCount(['products', 'children'])
            ->with([
                'parent',
                'children' => fn ($query) => $query
                    ->withCount(['products', 'children'])
                    ->with(['parent'])
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function categoryOptions(?string $productType = null): array
    {
        return ProductCategory::query()
            ->when(
                $productType,
                fn (Builder $query) => $query->where('product_type', $productType)
            )
            ->with(['parent:id,name'])
            ->orderByRaw('CASE WHEN parent_group_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (ProductCategory $category): array {
                $label = $category->parent ? $category->parent->name.' / '.$category->name : $category->name;
                $productTypeCode = trim((string) ($category->product_type ?? ''));

                return [
                    'id' => (int) $category->id,
                    'category_id' => (int) $category->id,
                    'group_id' => (int) (($category->legacy_group_id ?? 0) ?: $category->id),
                    'legacy_group_id' => $category->legacy_group_id !== null ? (int) $category->legacy_group_id : null,
                    'parent_id' => $category->parent_id !== null ? (int) $category->parent_id : null,
                    'parent_category_id' => $category->parent_id !== null ? (int) $category->parent_id : null,
                    'parent_group_id' => $category->parent
                        ? (int) (($category->parent->legacy_group_id ?? 0) ?: $category->parent->id)
                        : null,
                    'product_type' => $productTypeCode,
                    'product_type_label' => ProductType::labelOf($productTypeCode),
                    'level' => $category->parent_id ? 2 : 1,
                    'label' => $label,
                    'name' => $category->name,
                    'is_visible' => (int) $category->is_visible,
                ];
            })
            ->values()
            ->all();
    }

    public function createCategory(array $data): ProductCategory
    {
        $category = DB::transaction(function () use ($data): ProductCategory {
            $payload = $this->prepareCategoryPayload($data);

            /** @var ProductCategory $category */
            $category = ProductCategory::query()->create($payload);
            $category->load(['parent']);

            return $this->loadCategorySnapshot($category);
        });

        $this->forgetSiteCatalogCache();

        return $category;
    }

    public function updateCategory(ProductCategory $category, array $data): ProductCategory
    {
        $updatedCategory = DB::transaction(function () use ($category, $data): ProductCategory {
            $originalType = trim((string) ($category->product_type ?? ''));
            $originalParentId = $category->parent_id === null ? null : (int) $category->parent_id;
            $payload = $this->prepareCategoryPayload($data, $category);
            $category->update($payload);
            $category->refresh()->load(['parent']);

            if (
                $originalType !== trim((string) ($category->product_type ?? ''))
                || $originalParentId !== ($category->parent_id === null ? null : (int) $category->parent_id)
            ) {
                $this->syncCategoryProductType($category);

                return $this->loadCategorySnapshot($category);
            }

            return $this->loadCategorySnapshot($category);
        });

        $this->forgetSiteCatalogCache();

        return $updatedCategory;
    }

    public function deleteCategory(ProductCategory $category): void
    {
        throw_if($category->children()->count() > 0, new BusinessException('请先删除下级分类'));
        throw_if($category->products()->count() > 0, new BusinessException('请先迁移或删除该分类下的商品'));

        DB::transaction(function () use ($category): void {
            $category->delete();
        });

        $this->forgetSiteCatalogCache();
    }

    public function reorderAdminCategories(?string $productType, ?int $parentId, array $categoryIds): array
    {
        $orderedCategoryIds = collect($categoryIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        throw_if($orderedCategoryIds->count() < 2, new BusinessException('至少需要两个分类才能拖动排序'));

        $scopeQuery = ProductCategory::query();

        if ($parentId !== null && $parentId > 0) {
            $scopeQuery->where('parent_group_id', $parentId);
        } else {
            $resolvedProductType = trim((string) ($productType ?? ''));
            throw_if($resolvedProductType === '', new BusinessException('请选择所属一级菜单'));
            throw_if(! in_array($resolvedProductType, ProductType::allowedValues(), true), new BusinessException('所属一级菜单不存在'));
            $scopeQuery
                ->whereNull('parent_group_id')
                ->where('product_type', $resolvedProductType);
        }

        $currentIds = $scopeQuery
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if (
            $currentIds->count() !== $orderedCategoryIds->count()
            || $currentIds->sort()->values()->all() !== $orderedCategoryIds->sort()->values()->all()
        ) {
            throw new BusinessException('分类列表已发生变化，请刷新后重新拖动排序');
        }

        $sortMap = [];
        foreach ($orderedCategoryIds as $index => $categoryId) {
            $sortMap[(int) $categoryId] = $index + 1;
        }

        DB::transaction(function () use ($sortMap): void {
            $bindings = [];
            $caseSql = collect($sortMap)
                ->map(function (int $sortOrder, int $categoryId) use (&$bindings): string {
                    $bindings[] = $categoryId;
                    $bindings[] = $sortOrder;

                    return 'WHEN ? THEN ?';
                })
                ->implode(' ');
            $placeholders = implode(',', array_fill(0, count($sortMap), '?'));
            $bindings[] = now();
            array_push($bindings, ...array_keys($sortMap));

            DB::statement(
                "UPDATE product_groups SET sort_order = CASE id {$caseSql} END, updated_at = ? WHERE id IN ({$placeholders})",
                $bindings
            );

            $this->syncLegacyMappingsByIds(array_keys($sortMap));
        });

        $this->forgetSiteCatalogCache();

        return [
            'updated_count' => count($sortMap),
            'parent_id' => $parentId,
            'product_type' => $productType,
        ];
    }

    public function moveAdminCategory(
        ProductCategory $category,
        ?string $targetProductType,
        ?int $targetParentId,
        ?int $referenceCategoryId,
        string $position = 'append',
    ): array {
        $resolvedTargetType = trim((string) ($targetProductType ?? ''));
        $resolvedTargetParentId = $targetParentId !== null ? (int) $targetParentId : null;
        $sourceParentId = $category->parent_id === null ? null : (int) $category->parent_id;
        $sourceType = trim((string) ($category->product_type ?? ''));

        if ($resolvedTargetParentId !== null) {
            $targetParent = ProductCategory::query()->find($resolvedTargetParentId);
            throw_if(! $targetParent, new BusinessException('目标分类不存在'));
            throw_if((int) $category->id === $resolvedTargetParentId, new BusinessException('不能拖到当前分类自身'));
            throw_if($targetParent->parent_id !== null, new BusinessException('子分类只能放在一级分类下'));
            throw_if($category->parent_id === null && $category->children()->exists(), new BusinessException('包含子分类的一级分类不能拖到其他分类下'));

            $resolvedTargetType = trim((string) ($targetParent->product_type ?? ''));
            throw_if($resolvedTargetType === '', new BusinessException('目标一级分类缺少商品种类'));
        } else {
            throw_if($resolvedTargetType === '', new BusinessException('请选择所属一级菜单'));
            throw_if(! in_array($resolvedTargetType, ProductType::allowedValues(), true), new BusinessException('所属一级菜单不存在'));
        }

        throw_if(! in_array($position, ['before', 'after', 'append'], true), new BusinessException('拖动位置参数不正确'));

        $sameScope = $sourceParentId === $resolvedTargetParentId && $sourceType === $resolvedTargetType;
        $sourceIds = $this->resolveCategoryScopeIds($sourceType, $sourceParentId);
        $targetIds = $sameScope ? $sourceIds : $this->resolveCategoryScopeIds($resolvedTargetType, $resolvedTargetParentId);

        $reorderedTargetIds = $this->buildReorderedIds(
            $targetIds,
            (int) $category->id,
            $referenceCategoryId,
            $position,
            '分类'
        );
        $remainingSourceIds = $sameScope
            ? []
            : array_values(array_filter($sourceIds, fn (int $id) => $id !== (int) $category->id));

        DB::transaction(function () use (
            $category,
            $resolvedTargetParentId,
            $resolvedTargetType,
            $sourceParentId,
            $sourceType,
            $sameScope,
            $reorderedTargetIds,
            $remainingSourceIds,
        ): void {
            if ($sourceParentId !== $resolvedTargetParentId || $sourceType !== $resolvedTargetType) {
                $category->update([
                    'parent_id' => $resolvedTargetParentId,
                    'product_type' => $resolvedTargetType,
                ]);
                $category->refresh()->load(['parent']);
                $this->syncCategoryProductType($category);
            }

            $this->resequenceCategoryIds($reorderedTargetIds);

            if (! $sameScope) {
                $this->resequenceCategoryIds($remainingSourceIds);
            }
        });

        $this->forgetSiteCatalogCache();

        return [
            'category_id' => (int) $category->id,
            'target_parent_id' => $resolvedTargetParentId,
            'target_product_type' => $resolvedTargetType,
            'position' => $position,
        ];
    }

    private function prepareCategoryPayload(array $data, ?ProductCategory $category = null): array
    {
        $parentId = $this->normalizeNullableInt($data['parent_id'] ?? ($data['parent_category_id'] ?? null));
        $productTypeCode = trim((string) ($data['product_type'] ?? ''));

        if ($parentId) {
            $parentCategory = ProductCategory::query()->find($parentId);
            throw_if(! $parentCategory, new BusinessException('上级分类不存在'));
            throw_if($parentCategory->parent_id !== null, new BusinessException('仅支持两级商品分类'));
            throw_if($category && (int) $category->id === $parentId, new BusinessException('不能将当前分类设置为自己的上级'));
            throw_if($category && $category->children()->exists(), new BusinessException('当前分类存在下级分类，不能再设置上级'));
            $resolvedProductType = trim((string) ($parentCategory->product_type ?? ProductType::OTHER));
        } else {
            throw_if($category && $category->parent_id !== null && $category->products()->exists(), new BusinessException('该子分类下已有商品，不能直接调整为一级分类'));
            $resolvedProductType = trim((string) $productTypeCode);
            throw_if($resolvedProductType === '', new BusinessException('请选择所属一级菜单'));
            throw_if(! in_array($resolvedProductType, ProductType::allowedValues(), true), new BusinessException('所属一级菜单不存在'));
        }

        $name = trim((string) ($data['name'] ?? ''));
        throw_if($name === '', new BusinessException('分类名称不能为空'));

        return [
            'parent_id' => $parentId,
            'product_type' => $resolvedProductType,
            'name' => $name,
            'slug' => $this->resolveCategorySlug($name, $category),
            'slogan' => $this->normalizeNullableString($data['slogan'] ?? null),
            'is_visible' => (int) (($data['is_visible'] ?? 1) ? 1 : 0),
            'status' => (int) (($data['status'] ?? ($category?->status ?? 1)) ? 1 : 0),
            'sort_order' => max((int) ($data['sort_order'] ?? 0), 0),
        ];
    }

    private function resolveCategorySlug(string $name, ?ProductCategory $category = null): string
    {
        $currentSlug = trim((string) ($category?->slug ?? ''));

        if ($currentSlug !== '') {
            return $currentSlug;
        }

        return $this->generateUniqueCategorySlug($name, $category?->id);
    }

    private function resolveCategoryScopeIds(?string $productType, ?int $parentId): array
    {
        return ProductCategory::query()
            ->when(
                $parentId !== null,
                fn (Builder $query) => $query->where('parent_group_id', $parentId),
                fn (Builder $query) => $query
                    ->whereNull('parent_group_id')
                    ->where('product_type', (string) $productType)
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function resequenceCategoryIds(array $categoryIds): void
    {
        if ($categoryIds === []) {
            return;
        }

        $bindings = [];
        $caseSql = collect(array_values($categoryIds))
            ->map(function (int $categoryId, int $index) use (&$bindings): string {
                $bindings[] = $categoryId;
                $bindings[] = $index + 1;

                return 'WHEN ? THEN ?';
            })
            ->implode(' ');
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $bindings[] = now();
        array_push($bindings, ...array_values($categoryIds));

        DB::statement(
            "UPDATE product_groups SET sort_order = CASE id {$caseSql} END, updated_at = ? WHERE id IN ({$placeholders})",
            $bindings
        );

        $this->syncLegacyMappingsByIds($categoryIds);
    }

    private function syncLegacyMappingsByIds(array $categoryIds): void
    {
        if ($categoryIds === []) {
            return;
        }

        ProductCategory::query()
            ->whereIn('id', $categoryIds)
            ->get();
    }

    private function syncCategoryProductType(ProductCategory $category): void
    {
        $resolvedTypeCode = trim((string) ($category->product_type ?? ProductType::OTHER));
        $categoriesToSync = collect([$category]);

        if ($category->parent_id === null) {
            $children = $category->children()->get();
            $categoriesToSync = $categoriesToSync->merge($children);

            if ($children->isNotEmpty()) {
                Product::query()
                    ->whereIn('product_group_id', $children->pluck('id')->all())
                    ->update(['product_type' => $resolvedTypeCode]);
            }
        }

        Product::query()
            ->where('product_group_id', (int) $category->id)
            ->update(['product_type' => $resolvedTypeCode]);

        $categoriesToSync
            ->filter(fn ($item) => $item instanceof ProductCategory)
            ->each(function (ProductCategory $targetCategory) use ($resolvedTypeCode): void {
                if (trim((string) ($targetCategory->product_type ?? '')) !== $resolvedTypeCode) {
                    $targetCategory->update(['product_type' => $resolvedTypeCode]);
                }

                $targetCategory->refresh()->load(['parent']);
            });
    }

    private function loadCategorySnapshot(ProductCategory $category): ProductCategory
    {
        return $category->refresh()->load([
            'parent',
            'children',
        ]);
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
}
