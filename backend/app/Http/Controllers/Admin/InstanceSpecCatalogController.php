<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InstanceSpecCatalog\IndexRequest;
use App\Http\Requests\Admin\InstanceSpecCatalog\UpdateRequest;
use App\Services\ProductCatalog\InstanceSpecCatalogService;

class InstanceSpecCatalogController extends Controller
{
    public function __construct(
        private InstanceSpecCatalogService $instanceSpecCatalogService,
    ) {}

    public function index(IndexRequest $request)
    {
        $payload = $request->validated();

        return $this->success([
            'list' => $this->instanceSpecCatalogService->getCatalog(
                (string) ($payload['keyword'] ?? ''),
                (string) ($payload['binding_status'] ?? '')
            ),
        ]);
    }

    public function update(UpdateRequest $request)
    {
        $payload = $request->validated();

        return $this->success(
            [
                'list' => $this->instanceSpecCatalogService->saveCatalog($payload['list']),
            ],
            '实例规格目录已更新'
        );
    }
}
