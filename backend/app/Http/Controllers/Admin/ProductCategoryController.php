<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductCategory\ReorderRequest;
use App\Http\Requests\Admin\ProductCategory\StoreRequest;
use App\Services\ProductCatalog\ProductCatalogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    public function __construct(private ProductCatalogService $productCatalogService) {}

    public function index(Request $request)
    {
        $serviceTypeCode = trim((string) $request->input('service_type_code', $request->input('product_type', '')));

        return $this->success([
            'tree' => $this->productCatalogService->adminCategoryTree($serviceTypeCode !== '' ? $serviceTypeCode : null),
            'options' => $this->productCatalogService->categoryOptions($serviceTypeCode !== '' ? $serviceTypeCode : null),
        ]);
    }

    public function store(StoreRequest $request)
    {
        $payload = $request->validated();

        return $this->success(
            $this->productCatalogService->createAdminCategory($payload),
            '分类已创建'
        );
    }

    public function reorder(ReorderRequest $request)
    {
        $payload = $request->validated();

        $level = (int) $payload['effective_product_group_level'];
        $parentId = match ($level) {
            2 => (int) ($payload['first_product_group_id'] ?? 0),
            3 => (int) ($payload['second_product_group_id'] ?? 0),
            default => null,
        };
        $groupIds = match ($level) {
            1 => (array) ($payload['first_product_group_ids'] ?? []),
            2 => (array) ($payload['second_product_group_ids'] ?? []),
            3 => (array) ($payload['third_product_group_ids'] ?? []),
        };

        return $this->success(
            $this->productCatalogService->reorderAdminCategories($level, $parentId, $groupIds),
            '分类排序已更新'
        );
    }

    private function writeRules(bool $requireName = true): array
    {
        return [
            'effective_product_group_level' => ['required', 'integer', Rule::in([1, 2, 3])],
            'service_type_code' => ['nullable', 'string', 'max:50'],
            'first_product_group_code' => ['nullable', 'string', 'max:50'],
            'first_product_group_id' => ['nullable', 'integer', 'min:1', 'required_if:effective_product_group_level,2'],
            'second_product_group_id' => ['nullable', 'integer', 'min:1', 'required_if:effective_product_group_level,3'],
            'name' => [$requireName ? 'required' : 'sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'slogan' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120'],
            'banner_image' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_visible' => ['nullable', 'in:0,1'],
            'is_system' => ['nullable', 'in:0,1'],
        ];
    }
}
