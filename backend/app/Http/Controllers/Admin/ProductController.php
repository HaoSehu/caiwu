<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Constants\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Product\ListProductOwnersRequest;
use App\Http\Resources\Admin\AdminProductListResource;
use App\Http\Resources\Product\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ClientServiceConsole\ServiceTrafficPackageService;
use App\Services\ProductCatalog\ProductCatalogService;
use App\Support\ProductProvisionHostname;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(private ProductCatalogService $productCatalogService) {}

    public function summary()
    {
        return $this->success($this->productCatalogService->adminSummary());
    }

    public function index(Request $request)
    {
        $filters = $request->only(['keyword', 'status', 'product_type', 'type', 'category_id', 'group_id', 'product_group_id']);
        $perPage = min(max((int) $request->input('page_size', 20), 1), 100);
        $paginator = $this->productCatalogService->adminProductList($this->normalizeFilterPayload($filters), $perPage);

        return $this->paginate($paginator, AdminProductListResource::class);
    }

    public function show(Product $product)
    {
        $product->load([
            'categoryMapping.parent',
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

    public function store(Request $request)
    {
        $payload = $this->validatedPayload($request);
        $product = $this->productCatalogService->createProduct($payload);
        $product->loadCount(['orders', 'services']);

        return $this->success(new ProductResource($product), '商品创建成功');
    }

    public function update(Request $request, Product $product)
    {
        $payload = $this->validatedPayload($request);
        $product = $this->productCatalogService->updateProduct($product, $payload);
        $product->loadCount(['orders', 'services']);

        return $this->success(new ProductResource($product), '商品更新成功');
    }

    public function updateSortOrder(Request $request, Product $product)
    {
        $validated = $request->validate([
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
        ]);

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

    public function batchSync(Request $request)
    {
        $payload = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'min:1'],
            'sync_pricing' => ['nullable', 'in:0,1'],
            'sync_config_options' => ['nullable', 'in:0,1'],
            'sync_config_pricing' => ['nullable', 'in:0,1'],
        ]);

        return $this->success(
            $this->productCatalogService->batchSyncProducts($payload),
            '商品批量同步完成'
        );
    }

    public function split(Request $request)
    {
        $payload = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:100'],
            'product_ids.*' => ['integer', 'min:1', 'distinct'],
        ]);

        return $this->success(
            $this->productCatalogService->splitProducts($payload),
            '商品拆分完成'
        );
    }

    public function splitPreview(Request $request)
    {
        $payload = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:100'],
            'product_ids.*' => ['integer', 'min:1', 'distinct'],
        ]);

        return $this->success(
            $this->productCatalogService->previewSplitProducts($payload),
            '商品拆分预览完成'
        );
    }

    public function batchUpdateProvisionHostname(Request $request)
    {
        $payload = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:200'],
            'product_ids.*' => ['integer', 'min:1', 'distinct'],
            'provision_hostname' => ['required', 'array'],
            'provision_hostname.mode' => ['required', 'string', Rule::in(ProductProvisionHostname::modes())],
            'provision_hostname.value' => ['nullable', 'string', 'max:200'],
            'provision_hostname.length' => ['nullable', 'integer', 'min:4', 'max:63'],
        ]);

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

    public function batchUpdateCategory(Request $request)
    {
        $categoryExistsRule = Rule::exists((new ProductCategory)->getTable(), 'id');

        $payload = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:200'],
            'product_ids.*' => ['integer', 'min:1', 'distinct'],
            'target_category_id' => ['required_without_all:target_group_id,target_product_group_id', 'integer', 'min:1', $categoryExistsRule],
            'target_group_id' => ['required_without_all:target_category_id,target_product_group_id', 'integer', 'min:1'],
            'target_product_group_id' => ['required_without_all:target_category_id,target_group_id', 'integer', 'min:1'],
        ]);

        $payload['target_category_id'] = $this->resolveCategoryIdFromPayload(
            $payload,
            ['target_category_id', 'target_group_id', 'target_product_group_id'],
            'target_category_id'
        );

        unset($payload['target_group_id'], $payload['target_product_group_id']);

        return $this->success(
            $this->productCatalogService->batchUpdateCategory($payload),
            '商品分类已批量更新'
        );
    }

    public function pullTrafficPackageCatalog(Request $request)
    {
        $payload = $request->validate([
            'category_id' => ['required', 'integer', 'min:1'],
            'product_type' => ['nullable', 'string', 'max:50'],
            'supplier_id' => ['nullable', 'integer', 'min:1'],
            'supplier_product_id' => ['nullable', 'integer', 'min:1'],
            'source_product_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->success(
            app(ServiceTrafficPackageService::class)
                ->pullCatalogTemplateForAdmin(
                    (int) $payload['category_id'],
                    trim((string) ($payload['product_type'] ?? '')),
                    (int) ($payload['supplier_id'] ?? 0),
                    (int) ($payload['supplier_product_id'] ?? 0),
                    (int) ($payload['source_product_id'] ?? 0),
                ),
            '流量包配置拉取成功'
        );
    }

    public function reorder(Request $request)
    {
        if ($request->filled('product_id')) {
            $categoryExistsRule = Rule::exists((new ProductCategory)->getTable(), 'id');

            $payload = $request->validate([
                'product_id' => ['required', 'integer', 'min:1', 'exists:products,id'],
                'target_category_id' => ['required_without_all:target_group_id,target_product_group_id', 'integer', 'min:1', $categoryExistsRule],
                'target_group_id' => ['required_without_all:target_category_id,target_product_group_id', 'integer', 'min:1'],
                'target_product_group_id' => ['required_without_all:target_category_id,target_group_id', 'integer', 'min:1'],
                'reference_product_id' => ['nullable', 'integer', 'min:1', 'exists:products,id'],
                'position' => ['required', 'string', 'in:before,after,append'],
            ]);

            $product = Product::query()->findOrFail((int) $payload['product_id']);

            return $this->success(
                $this->productCatalogService->moveAdminProduct(
                    $product,
                    $this->resolveCategoryIdFromPayload(
                        $payload,
                        ['target_category_id', 'target_group_id', 'target_product_group_id'],
                        'target_category_id'
                    ),
                    isset($payload['reference_product_id']) ? (int) $payload['reference_product_id'] : null,
                    (string) $payload['position']
                ),
                '商品位置已更新'
            );
        }

        $payload = $request->validate([
            'keyword' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'in:0,1'],
            'product_type' => ['nullable', Rule::in(ProductType::allowedValues())],
            'type' => ['nullable', Rule::in(ProductType::allowedValues())],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'group_id' => ['nullable', 'integer', 'min:1'],
            'product_group_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['required', 'integer', 'min:1'],
            'page_size' => ['required', 'integer', 'min:1', 'max:100'],
            'product_ids' => ['required', 'array', 'min:2'],
            'product_ids.*' => ['integer', 'min:1', 'distinct'],
        ]);

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

    private function validatedPayload(Request $request): array
    {
        $categoryExistsRule = Rule::exists((new ProductCategory)->getTable(), 'id');

        $payload = $request->validate([
            'category_id' => ['required_without_all:group_id,product_group_id', 'integer', 'min:1', $categoryExistsRule],
            'group_id' => ['required_without_all:category_id,product_group_id', 'integer', 'min:1'],
            'product_group_id' => ['required_without_all:category_id,group_id', 'integer', 'min:1'],
            'display_name' => ['nullable', 'string', 'max:190'],
            'name' => ['nullable', 'string', 'max:190'],
            'custom_display_name' => ['nullable', 'string', 'max:190'],
            'product_type' => ['nullable', Rule::in(ProductType::allowedValues())],
            'type' => ['nullable', Rule::in(ProductType::allowedValues())],
            'remark' => ['nullable', 'string', 'max:255'],
            'pricing' => ['required', 'array'],
            'setup_fee' => ['nullable', 'numeric', 'min:0'],
            'config_options' => ['nullable', 'array'],
            'purchase_requires' => ['nullable', 'array'],
            'purchase_requires.require_verification' => ['nullable', 'boolean'],
            'purchase_requires.require_phone' => ['nullable', 'boolean'],
            'purchase_requires.provision_hostname' => ['nullable', 'array'],
            'purchase_requires.provision_hostname.mode' => ['nullable', 'string', Rule::in(ProductProvisionHostname::modes())],
            'purchase_requires.provision_hostname.value' => ['nullable', 'string', 'max:200'],
            'purchase_requires.provision_hostname.length' => ['nullable', 'integer', 'min:4', 'max:63'],
            'stock' => ['nullable', 'integer', 'min:-1'],
            'status' => ['nullable', 'in:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'provision_module' => ['nullable', 'string', 'max:50'],
            'auto_setup' => ['nullable', 'in:0,1'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'supplier_product_id' => ['nullable', 'integer'],
        ]);

        $payload['category_id'] = $this->resolveCategoryIdFromPayload(
            $payload,
            ['category_id', 'group_id', 'product_group_id'],
            'category_id'
        );

        if (! isset($payload['type']) && isset($payload['product_type'])) {
            $payload['type'] = $payload['product_type'];
        }

        unset($payload['group_id'], $payload['product_group_id']);

        return $payload;
    }

    private function normalizeFilterPayload(array $filters): array
    {
        if (! array_key_exists('type', $filters) && array_key_exists('product_type', $filters)) {
            $filters['type'] = $filters['product_type'];
        }

        $categoryId = $this->resolveCategoryIdFromPayload(
            $filters,
            ['category_id', 'group_id', 'product_group_id'],
            'category_id',
            false
        );

        if ($categoryId !== null) {
            $filters['category_id'] = $categoryId;
        } else {
            unset($filters['category_id']);
        }

        unset($filters['group_id'], $filters['product_group_id']);

        return $filters;
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

            if (in_array($key, ['group_id', 'product_group_id', 'target_group_id', 'target_product_group_id'], true)) {
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
