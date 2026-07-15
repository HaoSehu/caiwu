<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductType\ReorderRequest;
use App\Http\Requests\Admin\ProductType\StoreRequest;
use App\Http\Requests\Admin\ProductType\UpdateRequest;
use App\Services\ProductCatalog\ProductTypeService;

class ProductTypeController extends Controller
{
    public function __construct(
        private ProductTypeService $productTypeService,
    ) {}

    public function index()
    {
        return $this->success([
            'list' => $this->productTypeService->list(),
        ]);
    }

    public function store(StoreRequest $request)
    {
        $payload = $request->validated();

        return $this->success(
            $this->productTypeService->create($payload['label'], $payload['icon'] ?? null),
            '商品种类已创建'
        );
    }

    public function update(UpdateRequest $request, string $productType)
    {
        $payload = $request->validated();

        return $this->success(
            $this->productTypeService->update(
                $productType,
                $payload['label'],
                array_key_exists('is_hidden', $payload) ? (bool) $payload['is_hidden'] : null,
                $payload['icon'] ?? null
            ),
            '商品种类已更新'
        );
    }

    public function destroy(string $productType)
    {
        $this->productTypeService->delete($productType);

        return $this->success(null, '商品种类已删除');
    }

    public function reorder(ReorderRequest $request)
    {
        $payload = $request->validated();

        return $this->success(
            ['list' => $this->productTypeService->reorder($payload['values'])],
            '商品种类排序已更新'
        );
    }
}
