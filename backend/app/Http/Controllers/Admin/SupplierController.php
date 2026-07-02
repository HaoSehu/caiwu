<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Supplier\BulkConnectProductsRequest;
use App\Http\Requests\Admin\Supplier\IndexRequest;
use App\Http\Requests\Admin\Supplier\UpsertRequest;
use App\Http\Resources\Product\SupplierResource;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ProductCatalog\ProductCatalogService;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\Contracts\ProvidesRenewal;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupplierController extends Controller
{
    public function index(IndexRequest $request)
    {
        $filters = $request->validated();
        $query = Supplier::query();
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $status = $filters['status'] ?? null;

        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder
                    ->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $paginator = $query
            ->orderByDesc('status')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate($request->perPage());

        return $this->paginate($paginator, SupplierResource::class);
    }

    public function summary()
    {
        return $this->success([
            'total' => Supplier::count(),
            'active' => Supplier::where('status', 1)->count(),
            'inactive' => Supplier::where('status', 0)->count(),
        ]);
    }

    public function providerTypes(ProviderRegistry $providerRegistry)
    {
        return $this->success([
            'list' => $providerRegistry->options(),
        ]);
    }

    public function store(UpsertRequest $request)
    {
        $payload = $request->payload();
        $payload['code'] = $this->generateInternalCode($payload['interface_type']);
        $supplier = Supplier::create($payload);

        return $this->success(new SupplierResource($supplier), '创建成功');
    }

    public function show(Supplier $supplier)
    {
        if (! $supplier->exists) {
            return $this->error(40400, '接口不存在');
        }

        return $this->success([
            'id' => $supplier->id,
            'name' => $supplier->name,
            'code' => $supplier->code,
            'interface_type' => $supplier->interface_type,
            'api_url' => (string) $supplier->api_url,
            'has_api_url' => trim((string) $supplier->api_url) !== '',
            'api_username' => (string) $supplier->api_username,
            'has_api_key' => trim((string) $supplier->api_key) !== '',
            'status' => (int) $supplier->status,
            'sort_order' => (int) $supplier->sort_order,
        ]);
    }

    public function update(UpsertRequest $request, Supplier $supplier)
    {
        if (! $supplier->exists) {
            return $this->error(40400, '接口不存在');
        }

        $payload = $request->payload();
        $payload['code'] = $supplier->code ?: $this->generateInternalCode($payload['interface_type'], $supplier->id);
        $updated = $supplier->update($payload);

        if (! $updated) {
            return $this->error(50000, '接口更新失败');
        }

        return $this->success(new SupplierResource($supplier->refresh()), '更新成功');
    }

    public function destroy(Supplier $supplier)
    {
        if (! $supplier->exists) {
            return $this->error(40400, '接口不存在');
        }

        $usage = $this->supplierUsageSummary($supplier);
        if (($usage['total'] ?? 0) > 0) {
            return $this->error(40900, '供应商已被商品或服务引用，请先解绑或停用', [
                'usage' => $usage,
            ]);
        }

        $supplier->delete();

        return $this->success(null, '删除成功');
    }

    public function toggleStatus(Supplier $supplier)
    {
        if (! $supplier->exists) {
            return $this->error(40400, '接口不存在');
        }

        $updated = $supplier->update([
            'status' => $supplier->status === 1 ? 0 : 1,
        ]);

        if (! $updated) {
            return $this->error(50000, '状态更新失败');
        }

        return $this->success(new SupplierResource($supplier->refresh()), '状态已更新');
    }

    public function balance(Supplier $supplier, ProviderResolver $providerResolver)
    {
        if (! $supplier->exists) {
            return $this->error(40400, '接口不存在');
        }

        if (! $this->canQueryProvider($supplier)) {
            return $this->error(42200, '接口配置不完整，暂时无法查询余额');
        }

        try {
            $provider = $providerResolver->resolveForSupplier($supplier);
            $renewal = $provider->require(ProvidesRenewal::class, '当前供应商暂不支持余额查询');
            $result = $renewal->getBalance($supplier);
        } catch (BusinessException $exception) {
            return $exception->render();
        }

        return $this->success([
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'balance' => $result['balance'],
        ], '余额获取成功');
    }

    public function products(Supplier $supplier, ProviderResolver $providerResolver)
    {
        if (! $supplier->exists) {
            return $this->error(40400, '接口不存在');
        }

        if (! $this->canQueryProvider($supplier)) {
            return $this->error(42200, '接口配置不完整，暂时无法同步供应商商品');
        }

        $provider = $providerResolver->resolveForSupplier($supplier);
        $catalogCapability = $provider->require(ProvidesConsoleCatalog::class, '当前供应商暂不支持商品同步');
        $catalog = $catalogCapability->getProductCatalog($supplier);
        $catalog = $this->appendLocalProductMappings($supplier, $catalog);

        return $this->success([
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'groups' => $catalog['groups'],
            'products' => $catalog['products'],
        ], '供应商商品同步成功');
    }

    public function bulkConnectProducts(BulkConnectProductsRequest $request, Supplier $supplier, ProductCatalogService $productCatalogService)
    {
        if (! $supplier->exists) {
            return $this->error(40400, '接口不存在');
        }

        if (! $this->canQueryProvider($supplier)) {
            return $this->error(42200, '接口配置不完整，暂时无法批量对接商品');
        }

        $payload = $request->validated();

        $result = $productCatalogService->bulkConnectSupplierProducts($supplier, $payload);

        return $this->success($result, '批量对接完成');
    }

    public function productConfigTemplate(Supplier $supplier, int $productId, ProviderResolver $providerResolver)
    {
        if (! $supplier->exists) {
            return $this->error(40400, '接口不存在');
        }

        if (! $this->canQueryProvider($supplier)) {
            return $this->error(42200, '接口配置不完整，暂时无法拉取商品配置');
        }

        $provider = $providerResolver->resolveForSupplier($supplier);
        $catalogCapability = $provider->require(ProvidesConsoleCatalog::class, '当前供应商暂不支持拉取商品配置');
        $template = $catalogCapability->getProductConfigTemplate($supplier, $productId);

        return $this->success([
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'supplier_product_id' => $productId,
            'product' => $template['product'],
            'config_options' => $template['config_options'],
            'auto_filled_fields' => $template['auto_filled_fields'],
        ], '商品配置拉取成功');
    }

    private function canQueryProvider(Supplier $supplier): bool
    {
        return trim((string) $supplier->api_url) !== ''
            && trim((string) $supplier->api_username) !== ''
            && trim((string) $supplier->api_key) !== '';
    }

    private function generateInternalCode(string $interfaceType, ?int $ignoreId = null): string
    {
        $base = trim($interfaceType) !== '' ? $interfaceType : 'interface';
        $code = $base;
        $suffix = 1;

        while (
            Supplier::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('code', $code)
                ->exists()
        ) {
            $suffix++;
            $code = "{$base}_{$suffix}";
        }

        return $code;
    }

    private function appendLocalProductMappings(Supplier $supplier, array $catalog): array
    {
        $products = is_array($catalog['products'] ?? null) ? $catalog['products'] : [];
        $productIds = collect($products)
            ->map(fn (array $item) => (int) ($item['id'] ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($productIds === []) {
            return $catalog;
        }

        $localProducts = Product::withTrashed()
            ->with(['firstProductGroup', 'secondProductGroup', 'thirdProductGroup'])
            ->where('supplier_id', $supplier->id)
            ->whereIn('supplier_product_id', $productIds)
            ->get()
            ->keyBy(fn (Product $product) => (int) ($product->supplier_product_id ?? 0));

        $mapProduct = function (array $item) use ($localProducts): array {
            $localProduct = $localProducts->get((int) ($item['id'] ?? 0));
            $displayName = $localProduct instanceof Product
                ? trim((string) ((new ProductDisplayNameResolver)->resolveForProduct($localProduct)['product_display_name'] ?? ''))
                : '';
            $firstGroup = $localProduct?->firstProductGroup;
            $secondGroup = $localProduct?->secondProductGroup;
            $thirdGroup = $localProduct?->thirdProductGroup;
            $groupNameSegments = array_values(array_filter([
                trim((string) ($firstGroup?->name ?? '')),
                trim((string) ($secondGroup?->name ?? '')),
                trim((string) ($thirdGroup?->name ?? '')),
            ], static fn (string $name): bool => $name !== ''));

            return array_merge($item, [
                'is_connected' => $localProduct !== null,
                'connected_product_id' => $localProduct?->id ? (int) $localProduct->id : null,
                'connected_display_name' => $displayName,
                'connected_deleted' => $localProduct?->trashed() ?? false,
                'connected_first_product_group_id' => $firstGroup?->id ? (int) $firstGroup->id : null,
                'connected_first_product_group_name' => $firstGroup?->name,
                'connected_second_product_group_id' => $secondGroup?->id ? (int) $secondGroup->id : null,
                'connected_second_product_group_name' => $secondGroup?->name,
                'connected_third_product_group_id' => $thirdGroup?->id ? (int) $thirdGroup->id : null,
                'connected_third_product_group_name' => $thirdGroup?->name,
                'connected_effective_product_group_id' => $thirdGroup?->id
                    ? (int) $thirdGroup->id
                    : ($secondGroup?->id ? (int) $secondGroup->id : null),
                'connected_effective_product_group_level' => $thirdGroup?->id ? 3 : ($secondGroup?->id ? 2 : null),
                'connected_effective_product_group_full_name' => implode(' / ', $groupNameSegments),
                'connected_updated_at' => $localProduct?->updated_at?->format('Y-m-d H:i:s'),
            ]);
        };

        $catalog['products'] = array_map($mapProduct, $products);
        $catalog['groups'] = collect($catalog['groups'] ?? [])
            ->map(function (array $group) use ($mapProduct) {
                $group['items'] = array_map($mapProduct, is_array($group['items'] ?? null) ? $group['items'] : []);

                return $group;
            })
            ->values()
            ->all();

        return $catalog;
    }

    private function supplierUsageSummary(Supplier $supplier): array
    {
        $supplierId = (int) $supplier->id;
        $productCount = Product::withTrashed()
            ->where('supplier_id', $supplierId)
            ->count();
        $serviceCount = $this->countBoundServices($supplierId);
        $serviceInstanceCount = $this->countBoundServiceInstances($supplierId);

        return [
            'products' => $productCount,
            'services' => $serviceCount,
            'service_instances' => $serviceInstanceCount,
            'total' => $productCount + $serviceCount + $serviceInstanceCount,
        ];
    }

    private function countBoundServices(int $supplierId): int
    {
        if (! Schema::hasTable('services') || ! Schema::hasColumn('services', 'provision_data')) {
            return 0;
        }

        return (int) DB::table('services')
            ->where(function ($query) use ($supplierId) {
                $query
                    ->where('provision_data->supplier_id', $supplierId)
                    ->orWhere('provision_data->supplier_id', (string) $supplierId);
            })
            ->count();
    }

    private function countBoundServiceInstances(int $supplierId): int
    {
        if (! Schema::hasTable('service_instances') || ! Schema::hasColumn('service_instances', 'supplier_id')) {
            return 0;
        }

        return (int) DB::table('service_instances')
            ->where('supplier_id', $supplierId)
            ->count();
    }
}
