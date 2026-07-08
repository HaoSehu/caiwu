<?php

declare(strict_types=1);

namespace App\Services\ProductCatalog;

use App\Constants\ProductType;
use App\Models\FirstProductGroup;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SecondProductGroup;
use App\Models\ThirdProductGroup;
use App\Support\DatabaseSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductGroupHierarchyService
{
    public function tablesReady(): bool
    {
        return DatabaseSchema::hasTableOrView('first_product_groups')
            && DatabaseSchema::hasTableOrView('second_product_groups')
            && DatabaseSchema::hasTableOrView('third_product_groups');
    }

    public function productColumnsReady(): bool
    {
        return DatabaseSchema::hasTableOrView('products') && Schema::hasColumn('products', 'product_group_id');
    }

    public function syncProductTypes(): array
    {
        if (! $this->tablesReady()) {
            return ['synced_count' => 0, 'hidden_count' => 0];
        }

        $items = ProductType::items();
        $codes = [];
        $synced = 0;

        foreach ($items as $index => $item) {
            $code = $this->normalizeProductTypeCode($item['value'] ?? '');
            if ($code === '') {
                continue;
            }

            $codes[] = $code;
            $this->ensureFirstProductGroup($code, $item, $index + 1);
            $synced++;
        }

        $hidden = 0;
        if ($codes !== []) {
            $hidden = FirstProductGroup::query()
                ->whereNotNull('legacy_product_type')
                ->whereNotIn('code', $codes)
                ->update(['is_visible' => 0]);
        }

        return [
            'synced_count' => $synced,
            'hidden_count' => $hidden,
        ];
    }

    public function syncAllFromLegacy(int $chunkSize = 500, bool $dryRun = false): array
    {
        $result = [
            'tables_ready' => $this->tablesReady(),
            'dry_run' => $dryRun,
            'first_product_groups' => 0,
            'second_product_groups' => 0,
            'third_product_groups' => 0,
            'products' => 0,
            'products_missing_legacy_group' => 0,
            'products_repaired_missing_legacy_group' => 0,
        ];

        if (! $this->tablesReady()) {
            return $result;
        }

        $result['first_product_groups'] = count(ProductType::items());
        $result['second_product_groups'] = ProductCategory::query()->whereNull('parent_group_id')->count();
        $result['third_product_groups'] = ProductCategory::query()->whereNotNull('parent_group_id')->count();
        $result['products'] = Product::withTrashed()->whereNotNull('product_group_id')->count();
        $result['products_missing_legacy_group'] = $this->missingLegacyGroupProductsQuery()->count();

        if ($dryRun) {
            return $result;
        }

        $this->syncProductTypes();

        ProductCategory::query()
            ->with(['parent'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($categories): void {
                foreach ($categories as $category) {
                    if ($category instanceof ProductCategory) {
                        $this->syncLegacyCategory($category);
                    }
                }
            });

        $this->repairProductHierarchy($chunkSize);
        $result['products_repaired_missing_legacy_group'] = $this->repairProductsWithMissingLegacyGroup();

        return $result;
    }

    public function syncLegacyCategoriesByIds(array $categoryIds): void
    {
        $ids = collect($categoryIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === [] || ! $this->tablesReady()) {
            return;
        }

        ProductCategory::query()
            ->whereIn('id', $ids)
            ->with(['parent'])
            ->get()
            ->each(fn (ProductCategory $category) => $this->syncLegacyCategory($category));
    }

    public function deleteLegacyCategoryMapping(int $legacyGroupId): void
    {
        if ($legacyGroupId <= 0 || ! $this->tablesReady()) {
            return;
        }

        ThirdProductGroup::query()
            ->where('legacy_product_group_id', $legacyGroupId)
            ->delete();

        SecondProductGroup::query()
            ->where('legacy_product_group_id', $legacyGroupId)
            ->delete();
    }

    public function syncLegacyCategoryTree(ProductCategory $category): array
    {
        $synced = $this->syncLegacyCategory($category);

        if ($category->parent_id === null) {
            $category->children()
                ->with(['parent'])
                ->get()
                ->each(fn (ProductCategory $child) => $this->syncLegacyCategory($child));
        }

        return $synced;
    }

    public function syncLegacyCategory(ProductCategory $category): array
    {
        if (! $this->tablesReady()) {
            return [];
        }

        $category->loadMissing(['parent']);

        if ($category->parent_id === null) {
            $synced = $this->syncSecondProductGroupFromLegacy($category);
        } else {
            $synced = $this->syncThirdProductGroupFromLegacy($category);
        }

        $this->syncProductsByLegacyCategory($category);

        return $synced;
    }

    public function buildProductHierarchyPayload(ProductCategory $category, ?string $serviceTypeCode = null): array
    {
        if (! $this->tablesReady() || ! DatabaseSchema::hasTableOrView('products')) {
            return [];
        }

        $hierarchy = $this->resolveHierarchyForLegacyCategory($category);
        if ($hierarchy === []) {
            return [];
        }

        $firstGroupId = (int) ($hierarchy['first_product_group_id'] ?? 0);
        $firstGroup = $firstGroupId > 0
            ? FirstProductGroup::query()->find($firstGroupId)
            : null;
        $productType = ProductType::businessValueForFirstGroup(
            $firstGroup,
            $serviceTypeCode ?? ($hierarchy['first_product_group_code'] ?? null)
        );

        $effectiveGroupId = (int) ($hierarchy['third_product_group_id'] ?? 0)
            ?: ((int) ($hierarchy['second_product_group_id'] ?? 0) ?: $firstGroupId);

        $payload = [
            'product_group_id' => $effectiveGroupId ?: null,
            'first_product_group_id' => $firstGroupId ?: null,
            'second_product_group_id' => $hierarchy['second_product_group_id'] ?? null,
            'third_product_group_id' => $hierarchy['third_product_group_id'] ?? null,
            'product_type' => $productType,
            'service_type_code' => $productType,
        ];

        return $payload;
    }

    public function repairProductHierarchy(int $chunkSize = 500): int
    {
        if (! $this->productColumnsReady()) {
            return 0;
        }

        $updated = 0;
        Product::withTrashed()
            ->whereNotNull('product_group_id')
            ->select([
                'id',
                'product_group_id',
                'product_type',
                ...Product::optionalSelectColumns([
                    'service_type_code',
                ]),
            ])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($products) use (&$updated): void {
                foreach ($products as $product) {
                    if (! $product instanceof Product) {
                        continue;
                    }

                    $legacyGroupId = (int) ($product->getRawOriginal('product_group_id') ?? 0);
                    if ($legacyGroupId <= 0) {
                        continue;
                    }

                    $category = ProductCategory::query()
                        ->with(['parent'])
                        ->find($legacyGroupId);
                    if (! $category instanceof ProductCategory) {
                        continue;
                    }

                    $payload = $this->buildProductHierarchyPayload($category, (string) $product->product_type);
                    if ($payload === []) {
                        continue;
                    }

                    Product::withoutEvents(function () use ($product, $payload): void {
                        $product->forceFill($this->productUpdatePayload($payload))->save();
                    });
                    $updated++;
                }
            });

        return $updated;
    }

    public function checkHierarchy(): array
    {
        if ($this->currentProductGroupsReady()) {
            return $this->checkCurrentProductGroupHierarchy();
        }

        $blockingErrors = [];
        $warnings = [];

        if (! $this->tablesReady()) {
            $blockingErrors[] = '三层商品分类表尚未全部创建';
        }

        $legacyRootCount = ProductCategory::query()->whereNull('parent_group_id')->count();
        $legacyChildCount = ProductCategory::query()->whereNotNull('parent_group_id')->count();
        $orphanLegacyChildCount = ProductCategory::query()
            ->whereNotNull('parent_group_id')
            ->whereDoesntHave('parent')
            ->count();

        if ($orphanLegacyChildCount > 0) {
            $blockingErrors[] = "存在 {$orphanLegacyChildCount} 个旧三级分类找不到父级";
        }

        $missingSecondCount = 0;
        $missingThirdCount = 0;
        if ($this->tablesReady()) {
            $secondLegacyIds = SecondProductGroup::query()
                ->whereNotNull('legacy_product_group_id')
                ->pluck('legacy_product_group_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $thirdLegacyIds = ThirdProductGroup::query()
                ->whereNotNull('legacy_product_group_id')
                ->pluck('legacy_product_group_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $missingSecondCount = ProductCategory::query()
                ->whereNull('parent_group_id')
                ->whereNotIn('id', $secondLegacyIds ?: [0])
                ->count();
            $missingThirdCount = ProductCategory::query()
                ->whereNotNull('parent_group_id')
                ->whereNotIn('id', $thirdLegacyIds ?: [0])
                ->count();

            if ($missingSecondCount > 0) {
                $blockingErrors[] = "存在 {$missingSecondCount} 个旧二级分类未映射到 second_product_groups";
            }

            if ($missingThirdCount > 0) {
                $blockingErrors[] = "存在 {$missingThirdCount} 个旧三级分类未映射到 third_product_groups";
            }
        }

        $missingProductHierarchyCount = 0;
        $missingLegacyProductGroupCount = 0;
        if ($this->productColumnsReady()) {
            $missingLegacyProductGroupCount = $this->missingLegacyGroupProductsQuery()->count();

            if ($missingLegacyProductGroupCount > 0) {
                $blockingErrors[] = "存在 {$missingLegacyProductGroupCount} 个商品引用了不存在的旧 product_groups";
            }

            $missingProductHierarchyCount = Product::withTrashed()
                ->whereNull('product_group_id')
                ->count();

            if ($missingProductHierarchyCount > 0) {
                $blockingErrors[] = "存在 {$missingProductHierarchyCount} 个商品缺少 product_group_id 挂载字段";
            }
        } else {
            $warnings[] = 'products 三层挂载字段尚未全部存在';
        }

        return [
            'blocking_errors' => $blockingErrors,
            'warnings' => $warnings,
            'counts' => [
                'legacy_second_product_groups' => $legacyRootCount,
                'legacy_third_product_groups' => $legacyChildCount,
                'missing_second_product_groups' => $missingSecondCount,
                'missing_third_product_groups' => $missingThirdCount,
                'missing_legacy_product_groups' => $missingLegacyProductGroupCount,
                'missing_product_hierarchy' => $missingProductHierarchyCount,
            ],
        ];
    }

    private function checkCurrentProductGroupHierarchy(): array
    {
        $blockingErrors = [];
        $warnings = [];

        if (! $this->tablesReady()) {
            $warnings[] = '商品分组兼容视图尚未全部创建';
        }

        $rootCount = DB::table('product_groups')->where('level', 1)->count();
        $secondCount = DB::table('product_groups')->where('level', 2)->count();
        $thirdCount = DB::table('product_groups')->where('level', 3)->count();
        $orphanGroupCount = DB::table('product_groups as child')
            ->whereNotNull('child.parent_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('product_groups as parent')
                    ->whereColumn('parent.id', 'child.parent_id');
            })
            ->count();

        if ($orphanGroupCount > 0) {
            $blockingErrors[] = "存在 {$orphanGroupCount} 个商品分组找不到父级";
        }

        $missingProductHierarchyCount = 0;
        $orphanProductGroupCount = 0;
        if ($this->productColumnsReady()) {
            $missingProductHierarchyCount = Product::withTrashed()
                ->whereNull('product_group_id')
                ->count();
            $orphanProductGroupCount = Product::withTrashed()
                ->whereNotNull('product_group_id')
                ->whereNotIn('product_group_id', DB::table('product_groups')->select('id'))
                ->count();

            if ($missingProductHierarchyCount > 0) {
                $blockingErrors[] = "存在 {$missingProductHierarchyCount} 个商品缺少 product_group_id 挂载字段";
            }

            if ($orphanProductGroupCount > 0) {
                $blockingErrors[] = "存在 {$orphanProductGroupCount} 个商品引用了不存在的 product_groups";
            }
        } else {
            $warnings[] = 'products.product_group_id 尚不存在';
        }

        return [
            'blocking_errors' => $blockingErrors,
            'warnings' => $warnings,
            'counts' => [
                'root_product_groups' => $rootCount,
                'second_product_groups' => $secondCount,
                'third_product_groups' => $thirdCount,
                'orphan_product_groups' => $orphanGroupCount,
                'missing_product_hierarchy' => $missingProductHierarchyCount,
                'orphan_product_group_products' => $orphanProductGroupCount,
            ],
        ];
    }

    private function syncSecondProductGroupFromLegacy(ProductCategory $category): array
    {
        $productTypeCode = $this->normalizeProductTypeCode($category->product_type ?? ProductType::OTHER);
        $firstGroup = $this->ensureFirstProductGroup($productTypeCode);
        if (! $firstGroup instanceof FirstProductGroup) {
            return [];
        }

        $existing = SecondProductGroup::query()
            ->where('legacy_product_group_id', (int) $category->id)
            ->first();
        $slug = $this->uniqueSlug(
            SecondProductGroup::class,
            $this->legacyCategorySlug($category),
            ['first_product_group_id' => (int) $firstGroup->id],
            $existing?->id
        );

        /** @var SecondProductGroup $secondGroup */
        $secondGroup = SecondProductGroup::query()->updateOrCreate(
            ['legacy_product_group_id' => (int) $category->id],
            [
                'first_product_group_id' => (int) $firstGroup->id,
                'name' => (string) $category->name,
                'slug' => $slug,
                'description' => $this->nullableString($category->slogan ?? null),
                'sort_order' => (int) ($category->sort_order ?? 0),
                'is_visible' => (int) ($category->is_visible ?? 1),
            ]
        );

        ThirdProductGroup::query()
            ->where('legacy_product_group_id', (int) $category->id)
            ->delete();

        return [
            'first_product_group_id' => (int) $firstGroup->id,
            'second_product_group_id' => (int) $secondGroup->id,
            'third_product_group_id' => null,
            'first_product_group_code' => (string) $firstGroup->code,
        ];
    }

    private function syncThirdProductGroupFromLegacy(ProductCategory $category): array
    {
        $parent = $category->parent;
        if (! $parent instanceof ProductCategory && $category->parent_id !== null) {
            $parent = ProductCategory::query()->find((int) $category->parent_id);
        }

        if (! $parent instanceof ProductCategory) {
            return [];
        }

        $parentHierarchy = $this->syncSecondProductGroupFromLegacy($parent);
        $secondGroupId = (int) ($parentHierarchy['second_product_group_id'] ?? 0);
        if ($secondGroupId <= 0) {
            return [];
        }

        $existing = ThirdProductGroup::query()
            ->where('legacy_product_group_id', (int) $category->id)
            ->first();
        $slug = $this->uniqueSlug(
            ThirdProductGroup::class,
            $this->legacyCategorySlug($category),
            ['second_product_group_id' => $secondGroupId],
            $existing?->id
        );

        /** @var ThirdProductGroup $thirdGroup */
        $thirdGroup = ThirdProductGroup::query()->updateOrCreate(
            ['legacy_product_group_id' => (int) $category->id],
            [
                'second_product_group_id' => $secondGroupId,
                'name' => (string) $category->name,
                'slug' => $slug,
                'description' => $this->nullableString($category->slogan ?? null),
                'sort_order' => (int) ($category->sort_order ?? 0),
                'is_visible' => (int) ($category->is_visible ?? 1),
            ]
        );

        SecondProductGroup::query()
            ->where('legacy_product_group_id', (int) $category->id)
            ->delete();

        return [
            'first_product_group_id' => (int) ($parentHierarchy['first_product_group_id'] ?? 0),
            'second_product_group_id' => $secondGroupId,
            'third_product_group_id' => (int) $thirdGroup->id,
            'first_product_group_code' => (string) ($parentHierarchy['first_product_group_code'] ?? ''),
        ];
    }

    private function resolveHierarchyForLegacyCategory(ProductCategory $category): array
    {
        $category->loadMissing(['parent']);

        if ($category->parent_id === null) {
            return $this->syncSecondProductGroupFromLegacy($category);
        }

        return $this->syncThirdProductGroupFromLegacy($category);
    }

    private function syncProductsByLegacyCategory(ProductCategory $category): int
    {
        if (! $this->productColumnsReady()) {
            return 0;
        }

        $payload = $this->buildProductHierarchyPayload($category, (string) $category->product_type);
        if ($payload === []) {
            return 0;
        }

        if (DatabaseSchema::hasTableOrView('product_groups')) {
            return 0;
        }

        return Product::withTrashed()
            ->where('product_group_id', (int) $category->id)
            ->update($this->productUpdatePayload($payload));
    }

    private function repairProductsWithMissingLegacyGroup(): int
    {
        if (! $this->productColumnsReady()) {
            return 0;
        }

        return 0;
    }

    private function currentProductGroupsReady(): bool
    {
        return DatabaseSchema::hasTableOrView('product_groups')
            && DatabaseSchema::hasColumn('product_groups', 'level')
            && DatabaseSchema::hasColumn('product_groups', 'parent_id');
    }

    private function ensureUnmappedSecondProductGroup(FirstProductGroup $firstGroup): SecondProductGroup
    {
        $slug = 'legacy-unmapped-'.$this->normalizeProductTypeCode($firstGroup->code);

        /** @var SecondProductGroup $group */
        $group = SecondProductGroup::query()->updateOrCreate(
            [
                'first_product_group_id' => (int) $firstGroup->id,
                'slug' => $slug,
            ],
            [
                'name' => '历史未归档分类',
                'description' => '旧 product_group_id 已缺失的历史商品归档分类',
                'sort_order' => 9999,
                'is_visible' => 0,
                'legacy_product_group_id' => null,
            ]
        );

        return $group;
    }

    private function missingLegacyGroupProductsQuery(): Builder
    {
        if (! DatabaseSchema::hasTableOrView('product_groups')) {
            return Product::withTrashed()->whereRaw('1 = 0');
        }

        return Product::withTrashed()
            ->whereNotNull('product_group_id')
            ->whereNotIn('product_group_id', DB::table('product_groups')->select('id'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function productUpdatePayload(array $payload): array
    {
        return [
            'product_group_id' => $payload['product_group_id'] ?? null,
            'product_type' => $payload['product_type'] ?? null,
            'service_type_code' => $payload['service_type_code'] ?? null,
        ];
    }

    public function ensureFirstProductGroupForType(string $code): ?FirstProductGroup
    {
        return $this->ensureFirstProductGroup($code);
    }

    private function ensureFirstProductGroup(string $code, ?array $item = null, int $sortOrder = 0): ?FirstProductGroup
    {
        if (! $this->tablesReady()) {
            return null;
        }

        $code = $this->normalizeProductTypeCode($code);
        if ($code === '') {
            $code = ProductType::OTHER;
        }

        $item ??= $this->productTypeItem($code);
        $existing = FirstProductGroup::query()->where('code', $code)->first();
        $label = trim((string) ($item['label'] ?? ProductType::labelOf($code)));
        $slug = $this->uniqueSlug(
            FirstProductGroup::class,
            Str::slug($code) ?: $code,
            [],
            $existing?->id
        );
        $payload = [
            'name' => $label !== '' ? $label : $code,
            'slug' => $slug,
            'icon' => $this->nullableString($item['icon'] ?? null),
            'sort_order' => $sortOrder > 0 ? $sortOrder : $this->productTypeSortOrder($code),
            'is_visible' => (bool) ($item['is_hidden'] ?? false) ? 0 : 1,
            'is_system' => (bool) ($item['is_builtin'] ?? false) ? 1 : 0,
            'legacy_product_type' => $code,
        ];

        if (DatabaseSchema::hasColumn('first_product_groups', 'product_type')) {
            $payload['product_type'] = $this->resolveFirstGroupProductType($code, $item);
        }

        /** @var FirstProductGroup $group */
        $group = FirstProductGroup::query()->updateOrCreate(
            ['code' => $code],
            $payload
        );

        return $group;
    }

    private function productTypeItem(string $code): array
    {
        foreach (ProductType::items() as $item) {
            if ((string) ($item['value'] ?? '') === $code) {
                return $item;
            }
        }

        return [
            'value' => $code,
            'label' => ProductType::labelOf($code),
            'icon' => '',
            'is_builtin' => false,
            'is_hidden' => false,
        ];
    }

    private function resolveFirstGroupProductType(string $code, ?array $item): string
    {
        if (is_array($item) && array_key_exists('product_type', $item)) {
            return ProductType::normalizeBusinessValue($item['product_type']);
        }

        return ProductType::normalizeBusinessValueFromMenuCode($item['value'] ?? $code);
    }

    private function productTypeSortOrder(string $code): int
    {
        foreach (array_values(ProductType::items()) as $index => $item) {
            if ((string) ($item['value'] ?? '') === $code) {
                return $index + 1;
            }
        }

        return 999;
    }

    private function uniqueSlug(string $modelClass, string $baseSlug, array $scope, ?int $ignoreId = null): string
    {
        $slug = Str::slug($baseSlug);
        if ($slug === '') {
            $slug = 'group';
        }

        $candidate = $slug;
        $suffix = 1;

        while ($this->slugExists($modelClass, $candidate, $scope, $ignoreId)) {
            $suffix++;
            $candidate = $slug.'-'.$suffix;
        }

        return $candidate;
    }

    private function slugExists(string $modelClass, string $slug, array $scope, ?int $ignoreId): bool
    {
        /** @var class-string<Model> $modelClass */
        $query = $modelClass::query()->where('slug', $slug);

        foreach ($scope as $column => $value) {
            $query->where($column, $value);
        }

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }

    private function legacyCategorySlug(ProductCategory $category): string
    {
        $slug = trim((string) ($category->slug ?? ''));
        if ($slug !== '') {
            return $slug;
        }

        $nameSlug = Str::slug((string) ($category->name ?? ''));
        if ($nameSlug !== '') {
            return $nameSlug;
        }

        return 'group-'.(int) $category->id;
    }

    private function normalizeProductTypeCode(mixed $value): string
    {
        return trim((string) $value);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
