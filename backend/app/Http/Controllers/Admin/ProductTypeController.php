<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductCatalog\ProductTypeService;
use Illuminate\Http\Request;

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

    public function store(Request $request)
    {
        $payload = $request->validate([
            'label' => ['required', 'string', 'max:30'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

        return $this->success(
            $this->productTypeService->create($payload['label'], $payload['icon'] ?? null),
            '商品种类已创建'
        );
    }

    public function update(Request $request, string $productType)
    {
        $payload = $request->validate([
            'label' => ['required', 'string', 'max:30'],
            'is_hidden' => ['nullable', 'boolean'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

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

    public function reorder(Request $request)
    {
        $payload = $request->validate([
            'values' => ['required', 'array', 'min:2'],
            'values.*' => ['required', 'string', 'max:50', 'distinct'],
        ]);

        return $this->success(
            ['list' => $this->productTypeService->reorder($payload['values'])],
            '商品种类排序已更新'
        );
    }
}
