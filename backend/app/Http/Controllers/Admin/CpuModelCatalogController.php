<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CpuModelCatalog\UpdateRequest;
use App\Services\ProductCatalog\CpuModelCatalogService;

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

    public function update(UpdateRequest $request)
    {
        $payload = $request->validated();

        return $this->success(
            [
                'list' => $this->cpuModelCatalogService->saveCatalog($payload['list']),
            ],
            'CPU 型号目录已更新'
        );
    }
}
