<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Product\BatchSyncRequest;
use App\Http\Requests\Admin\Product\BatchUpdateCategoryRequest;
use App\Http\Requests\Admin\Product\BatchUpdateProvisionHostnameRequest;
use App\Http\Requests\Admin\Product\ListProductOwnersRequest;
use App\Http\Requests\Admin\Product\PullTrafficPackageCatalogRequest;
use App\Http\Requests\Admin\Product\ReorderRequest;
use App\Http\Requests\Admin\Product\SplitPreviewRequest;
use App\Http\Requests\Admin\Product\SplitRequest;
use App\Http\Requests\Admin\Product\StoreRequest;
use App\Http\Requests\Admin\Product\UpdateRequest;
use App\Http\Requests\Admin\Product\UpdateSortOrderRequest;
use App\Http\Resources\Admin\AdminProductListResource;
use App\Http\Resources\Product\ProductResource;
use App\Models\Product;
use App\Services\ClientServiceConsole\ServiceTrafficPackageService;
use App\Services\ProductCatalog\ProductCatalogService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductCatalogService $productCatalogService) {}

    public function summary()
    {
        return $this->success($this->productCatalogService->adminSummary());
    }

    public function index(Request $request)
    {
        $filters = $request->only([
            'keyword',
            'status',
            'product_type',
            'type',
            'first_product_group_id',
            'second_product_group_id',
            'third_product_group_id',
        ]);
        $perPage = min(max((int) $request->input('page_size', 20), 1), 100);
        $paginator = $this->productCatalogService->adminProductList($this->normalizeFilterPayload($filters), $perPage);

        return $this->paginate($paginator, AdminProductListResource::class);
    }

    public function show(Product $product)
    {
        $product->load([
            'productGroup.secondProductGroup.firstProductGroup',
            'supplier',
        ])->loadCount(['orders', 'services']);

        return $this->success(new ProductResource($product));
    }

    public function owners(ListProductOwnersRequest $request, Product $product)
    {
        return $this->success(
            $this->productCatalogService->adminProductOwners($product, $request->filters(), $request->perPage())
        );
    }

    public function store(StoreRequest $request)
    {
        $payload = $this->validatedPayload($request->validated());
        $product = $this->productCatalogService->createProduct($payload);
        $product->loadCount(['orders', 'services']);

        return $this->success(new ProductResource($product), '商品创建成功');
    }

    public function update(UpdateRequest $request, Product $product)
    {
        $payload = $this->validatedPayload($request->validated());
        $product = $this->productCatalogService->updateProduct($product, $payload);
        $product->loadCount(['orders', 'services']);

        return $this->success(new ProductResource($product), '商品更新成功');
    }

    public function updateSortOrder(UpdateSortOrderRequest $request, Product $product)
    {
        $validated = $request->validated();

        $product = $this->productCatalogService->updateProductSortOrder(
            $product,
            (int) $validated['sort_order']
        );
        $product->loadCount(['orders', 'services']);

        return $this->success(new ProductResource($product), '商品排序已更新');
    }

    public function destroy(Product $product)
    {
        $this->productCatalogService->deleteProduct($product);

        return $this->success(null, '商品删除成功');
    }

    public function toggleStatus(Product $product)
    {
        $product = $this->productCatalogService->toggleProductStatus($product);
        $product->loadCount(['orders', 'services']);

        return $this->success(new ProductResource($product), '商品状态已更新');
    }

    public function batchSync(BatchSyncRequest $request)
    {
        $payload = $request->validated();

        return $this->success(
            $this->productCatalogService->batchSyncProducts($payload),
            '商品批量同步完成'
        );
    }

    public function split(SplitRequest $request)
    {
        $payload = $request->validated();

        return $this->success(
            $this->productCatalogService->splitProducts($payload),
            '商品拆分完成'
        );
    }

    public function splitPreview(SplitPreviewRequest $request)
    {
        $payload = $request->validated();

        return $this->success(
            $this->productCatalogService->previewSplitProducts($payload),
            '商品拆分预览完成'
        );
    }

    public function batchUpdateProvisionHostname(BatchUpdateProvisionHostnameRequest $request)
    {
        $payload = $request->validated();

        return $this->success(
            $this->productCatalogService->batchUpdateProvisionHostname($payload, [
                'operator_id' => (int) ($request->user()?->id ?? 0),
                'operator_name' => (string) ($request->user()?->username ?? $request->user()?->name ?? $request->user()?->email ?? 'admin'),
                'trace_id' => (string) $request->header('X-Request-Id', ''),
                'ip_address' => (string) $request->ip(),
            ]),
            '商品开通主机名规则已更新'
        );
    }

    public function batchUpdateCategory(BatchUpdateCategoryRequest $request)
    {
        $payload = $request->validated();

        return $this->success(
            $this->productCatalogService->batchUpdateCategory($payload),
            '商品分类已批量更新'
        );
    }

    public function pullTrafficPackageCatalog(PullTrafficPackageCatalogRequest $request)
    {
        $payload = $request->validated();

        return $this->success(
            app(ServiceTrafficPackageService::class)
                ->pullCatalogTemplateForAdmin(
                    (int) ($payload['second_product_group_id'] ?? 0),
                    isset($payload['third_product_group_id']) ? (int) $payload['third_product_group_id'] : null,
                    trim((string) ($payload['product_type'] ?? '')),
                    (int) ($payload['supplier_id'] ?? 0),
                    (int) ($payload['supplier_product_id'] ?? 0),
                    (int) ($payload['source_product_id'] ?? 0),
                ),
            '流量包配置拉取成功'
        );
    }

    public function reorder(ReorderRequest $request)
    {
        if ($request->filled('product_id')) {
            $payload = $request->validated();

            $product = Product::query()->findOrFail((int) $payload['product_id']);

            return $this->success(
                $this->productCatalogService->moveAdminProduct(
                    $product,
                    [
                        'first_product_group_id' => $payload['target_first_product_group_id'] ?? null,
                        'second_product_group_id' => $payload['target_second_product_group_id'] ?? null,
                        'third_product_group_id' => $payload['target_third_product_group_id'] ?? null,
                    ],
                    isset($payload['reference_product_id']) ? (int) $payload['reference_product_id'] : null,
                    (string) $payload['position']
                ),
                '商品位置已更新'
            );
        }

        $payload = $request->validated();

        return $this->success(
            $this->productCatalogService->reorderAdminProducts(
                $this->normalizeFilterPayload($payload),
                (int) $payload['page'],
                (int) $payload['page_size'],
                $payload['product_ids']
            ),
            '商品拖动排序已更新'
        );
    }

    private function validatedPayload(array $payload): array
    {
        if (! isset($payload['type']) && isset($payload['product_type'])) {
            $payload['type'] = $payload['product_type'];
        }

        return $payload;
    }

    private function normalizeFilterPayload(array $filters): array
    {
        if (! array_key_exists('type', $filters) && array_key_exists('product_type', $filters)) {
            $filters['type'] = $filters['product_type'];
        }

        return $filters;
    }
}
