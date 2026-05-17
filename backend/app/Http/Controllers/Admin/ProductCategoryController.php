<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Constants\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Product\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Services\ProductCatalog\ProductCatalogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductCategoryController extends Controller
{
    public function __construct(private ProductCatalogService $productCatalogService) {}

    public function index()
    {
        $productType = trim((string) request()->input('product_type', request()->input('type', '')));
        $categories = $this->productCatalogService->adminCategoryTree($productType !== '' ? $productType : null);

        return $this->success([
            'tree' => ProductCategoryResource::collection($categories),
            'options' => $this->productCatalogService->categoryOptions($productType !== '' ? $productType : null),
        ]);
    }

    public function store(Request $request)
    {
        $categoryExistsRule = Rule::exists((new ProductCategory)->getTable(), 'id');

        $payload = $request->validate([
            'parent_id' => ['nullable', 'integer', 'min:1', $categoryExistsRule],
            'parent_group_id' => ['nullable', 'integer', 'min:1'],
            'parent_category_id' => ['nullable', 'integer', 'min:1', $categoryExistsRule],
            'product_type' => ['nullable', 'string', 'in:'.implode(',', ProductType::allowedValues())],
            'name' => ['required', 'string', 'max:100'],
            'slogan' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_visible' => ['nullable', 'in:0,1'],
        ]);

        $category = $this->productCatalogService->createCategory($this->normalizeCategoryPayload($payload));

        return $this->success(new ProductCategoryResource($category), '分类创建成功');
    }

    public function update(Request $request, ProductCategory $productCategory)
    {
        $categoryExistsRule = Rule::exists((new ProductCategory)->getTable(), 'id');

        $payload = $request->validate([
            'parent_id' => ['nullable', 'integer', 'min:1', $categoryExistsRule],
            'parent_group_id' => ['nullable', 'integer', 'min:1'],
            'parent_category_id' => ['nullable', 'integer', 'min:1', $categoryExistsRule],
            'product_type' => ['nullable', 'string', 'in:'.implode(',', ProductType::allowedValues())],
            'name' => ['required', 'string', 'max:100'],
            'slogan' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_visible' => ['nullable', 'in:0,1'],
        ]);

        $category = $this->productCatalogService->updateCategory($productCategory, $this->normalizeCategoryPayload($payload));

        return $this->success(new ProductCategoryResource($category), '分类更新成功');
    }

    public function destroy(ProductCategory $productCategory)
    {
        $this->productCatalogService->deleteCategory($productCategory);

        return $this->success(null, '分类删除成功');
    }

    public function reorder(Request $request)
    {
        $categoryExistsRule = Rule::exists((new ProductCategory)->getTable(), 'id');

        if ($request->filled('category_id') || $request->filled('group_id') || $request->filled('product_group_id')) {
            $payload = $request->validate([
                'category_id' => ['required_without_all:group_id,product_group_id', 'integer', 'min:1', $categoryExistsRule],
                'group_id' => ['required_without_all:category_id,product_group_id', 'integer', 'min:1'],
                'product_group_id' => ['required_without_all:category_id,group_id', 'integer', 'min:1'],
                'target_parent_id' => ['nullable', 'integer', 'min:1', $categoryExistsRule],
                'target_parent_group_id' => ['nullable', 'integer', 'min:1'],
                'target_parent_category_id' => ['nullable', 'integer', 'min:1', $categoryExistsRule],
                'target_product_type' => ['nullable', 'string', 'in:'.implode(',', ProductType::allowedValues())],
                'reference_category_id' => ['nullable', 'integer', 'min:1', $categoryExistsRule],
                'reference_group_id' => ['nullable', 'integer', 'min:1'],
                'reference_product_group_id' => ['nullable', 'integer', 'min:1'],
                'position' => ['required', 'string', 'in:before,after,append'],
            ]);

            $category = ProductCategory::query()->findOrFail(
                $this->resolveCategoryIdFromPayload(
                    $payload,
                    ['category_id', 'group_id', 'product_group_id'],
                    'category_id'
                )
            );

            return $this->success(
                $this->productCatalogService->moveAdminCategory(
                    $category,
                    $payload['target_product_type'] ?? null,
                    $this->resolveCategoryIdFromPayload(
                        $payload,
                        ['target_parent_id', 'target_parent_category_id', 'target_parent_group_id'],
                        'target_parent_id'
                    ),
                    $this->resolveCategoryIdFromPayload(
                        $payload,
                        ['reference_category_id', 'reference_group_id', 'reference_product_group_id'],
                        'reference_category_id'
                    ),
                    (string) $payload['position']
                ),
                '分类位置已更新'
            );
        }

        $payload = $request->validate([
            'product_type' => ['nullable', 'string', 'in:'.implode(',', ProductType::allowedValues())],
            'parent_id' => ['nullable', 'integer', 'min:1', $categoryExistsRule],
            'parent_group_id' => ['nullable', 'integer', 'min:1'],
            'parent_category_id' => ['nullable', 'integer', 'min:1', $categoryExistsRule],
            'category_ids' => ['required_without_all:group_ids,product_group_ids', 'array', 'min:2'],
            'category_ids.*' => ['required', 'integer', 'min:1', 'distinct', $categoryExistsRule],
            'group_ids' => ['required_without_all:category_ids,product_group_ids', 'array', 'min:2'],
            'group_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'product_group_ids' => ['required_without_all:category_ids,group_ids', 'array', 'min:2'],
            'product_group_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        return $this->success(
            $this->productCatalogService->reorderAdminCategories(
                $payload['product_type'] ?? null,
                $this->resolveCategoryIdFromPayload(
                    $payload,
                    ['parent_id', 'parent_category_id', 'parent_group_id'],
                    'parent_id'
                ),
                $this->resolveCategoryIdsFromPayload($payload)
            ),
            '分类排序已更新'
        );
    }

    private function normalizeCategoryPayload(array $payload): array
    {
        $payload['parent_id'] = $this->resolveCategoryIdFromPayload(
            $payload,
            ['parent_id', 'parent_category_id', 'parent_group_id'],
            'parent_id'
        );

        unset($payload['parent_group_id'], $payload['parent_category_id']);

        return $payload;
    }

    private function resolveCategoryIdsFromPayload(array $payload): array
    {
        $rawIds = [];
        $usePublicIds = false;

        if (array_key_exists('category_ids', $payload)) {
            $rawIds = (array) $payload['category_ids'];
        } elseif (array_key_exists('group_ids', $payload)) {
            $rawIds = (array) $payload['group_ids'];
            $usePublicIds = true;
        } elseif (array_key_exists('product_group_ids', $payload)) {
            $rawIds = (array) $payload['product_group_ids'];
            $usePublicIds = true;
        }

        $rawIds = array_values(array_unique(array_map('intval', $rawIds)));
        $resolvedIds = collect($rawIds)
            ->map(function (int $rawId) use ($usePublicIds): ?int {
                if ($rawId <= 0) {
                    return null;
                }

                return $usePublicIds
                    ? $this->resolveCategoryIdFromPublicId($rawId)
                    : $rawId;
            })
            ->filter(fn ($id) => $id !== null)
            ->values()
            ->all();

        if (count($resolvedIds) !== count($rawIds)) {
            throw ValidationException::withMessages([
                'category_ids' => '存在无效分类，请刷新后重试',
            ]);
        }

        return $resolvedIds;
    }

    private function resolveCategoryIdFromPayload(
        array $payload,
        array $keys,
        string $errorField,
        bool $strict = true,
    ): ?int {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '') {
                continue;
            }

            $rawId = (int) $payload[$key];
            if ($rawId <= 0) {
                continue;
            }

            if (in_array($key, [
                'group_id',
                'product_group_id',
                'parent_group_id',
                'target_parent_group_id',
                'reference_group_id',
                'reference_product_group_id',
            ], true)) {
                $resolvedId = $this->resolveCategoryIdFromPublicId($rawId);
                if ($resolvedId !== null) {
                    return $resolvedId;
                }

                if ($strict) {
                    throw ValidationException::withMessages([
                        $errorField => '商品分类不存在',
                    ]);
                }

                return null;
            }

            return $rawId;
        }

        return null;
    }

    private function resolveCategoryIdFromPublicId(int $publicId): ?int
    {
        if ($publicId <= 0) {
            return null;
        }

        $categoryId = ProductCategory::query()
            ->whereKey($publicId)
            ->value('id');

        return $categoryId !== null ? (int) $categoryId : null;
    }
}
