<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductCatalog\InstanceSpecCatalogService;
use Illuminate\Http\Request;

class InstanceSpecCatalogController extends Controller
{
    public function __construct(
        private InstanceSpecCatalogService $instanceSpecCatalogService,
    ) {}

    public function index(Request $request)
    {
        $payload = $request->validate([
            'keyword' => ['nullable', 'string', 'max:120'],
            'binding_status' => ['nullable', 'string', 'in:bound,unbound'],
        ]);

        return $this->success([
            'list' => $this->instanceSpecCatalogService->getCatalog(
                (string) ($payload['keyword'] ?? ''),
                (string) ($payload['binding_status'] ?? '')
            ),
        ]);
    }

    public function update(Request $request)
    {
        $payload = $request->validate([
            'list' => ['required', 'array'],
            'list.*.id' => ['nullable', 'string', 'max:80'],
            'list.*.value' => ['nullable', 'string', 'max:60'],
            'list.*.text' => ['required', 'string', 'max:80'],
            'list.*.alias' => ['nullable', 'string', 'max:80'],
            'list.*.note' => ['nullable', 'string', 'max:255'],
            'list.*.status' => ['nullable', 'string', 'max:30'],
            'list.*.bindings' => ['nullable', 'array'],
            'list.*.bindings.*.product_id' => ['required', 'integer', 'min:1'],
            'list.*.bindings.*.category_full_name' => ['nullable', 'string', 'max:160'],
            'list.*.bindings.*.primary_price' => ['nullable', 'array'],
            'list.*.bindings.*.primary_price.cycle' => ['nullable', 'string', 'max:40'],
            'list.*.bindings.*.primary_price.amount' => ['nullable', 'string', 'max:40'],
            'list.*.bindings.*.status' => ['nullable', 'integer', 'in:0,1'],
        ]);

        return $this->success(
            [
                'list' => $this->instanceSpecCatalogService->saveCatalog($payload['list']),
            ],
            '实例规格目录已更新'
        );
    }
}
