<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductCatalog\CpuModelCatalogService;
use Illuminate\Http\Request;

class CpuModelCatalogController extends Controller
{
    public function __construct(
        private CpuModelCatalogService $cpuModelCatalogService,
    ) {}

    public function index()
    {
        return $this->success([
            'list' => $this->cpuModelCatalogService->getCatalog(),
        ]);
    }

    public function update(Request $request)
    {
        $payload = $request->validate([
            'list' => ['required', 'array'],
            'list.*.id' => ['nullable', 'string', 'max:80'],
            'list.*.value' => ['nullable', 'string', 'max:60'],
            'list.*.name' => ['required', 'string', 'max:80'],
            'list.*.models' => ['nullable', 'array'],
            'list.*.models.*.id' => ['nullable', 'string', 'max:80'],
            'list.*.models.*.value' => ['nullable', 'string', 'max:60'],
            'list.*.models.*.name' => ['required', 'string', 'max:80'],
            'list.*.models.*.base_frequency' => ['nullable', 'string', 'max:40'],
            'list.*.models.*.turbo_frequency' => ['nullable', 'string', 'max:40'],
            'list.*.models.*.bindings' => ['nullable', 'array'],
            'list.*.models.*.bindings.*.product_id' => ['required', 'integer', 'min:1'],
            'list.*.models.*.bindings.*.category_full_name' => ['nullable', 'string', 'max:160'],
            'list.*.models.*.bindings.*.primary_price' => ['nullable', 'array'],
            'list.*.models.*.bindings.*.primary_price.cycle' => ['nullable', 'string', 'max:40'],
            'list.*.models.*.bindings.*.primary_price.amount' => ['nullable', 'string', 'max:40'],
            'list.*.models.*.bindings.*.status' => ['nullable', 'integer', 'in:0,1'],
        ]);

        return $this->success(
            [
                'list' => $this->cpuModelCatalogService->saveCatalog($payload['list']),
            ],
            'CPU 型号目录已更新'
        );
    }
}
