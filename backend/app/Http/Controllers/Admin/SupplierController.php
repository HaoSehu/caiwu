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
use App\Services\Integrations\Plugins\PluginBindingResolver;
use App\Services\Integrations\Plugins\UpstreamBindingWriter;
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
        if (Schema::hasTable('supplier_plugin_bindings')) {
            $query->with('pluginBindings');
        }
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

    public function store(UpsertRequest $request, UpstreamBindingWriter $bindingWriter)
    {
        $bindingPayload = $request->upstreamBindingPayload();
        $supplierPayload = $request->supplierPayload();
        $supplierPayload['code'] = $this->generateInternalCode($bindingPayload['provider_key']);
        $supplier = DB::transaction(function () use ($supplierPayload, $bindingPayload, $bindingWriter): Supplier {
            $supplier = Supplier::create($supplierPayload);
            $bindingWriter->syncSupplierBinding($supplier, $bindingPayload);

            return $supplier;
        });

        return $this->success(new SupplierResource($supplier->refresh()), '创建成功');
    }

    public function show(Supplier $supplier)
    {
        if (! $supplier->exists) {
            return $this->error(40400, '接口不存在');
        }
        $binding = $this->supplierBindingProjection($supplier, includeSecrets: true);
        $providerKey = trim((string) ($binding['provider_key'] ?? ''));

        return $this->success([
            'id' => $supplier->id,
            'name' => $supplier->name,
            'code' => $supplier->code,
            'provider_key' => $providerKey,
            'provider_label' => $this->providerLabel($providerKey),
            'api_url' => '',
            'has_api_url' => (bool) ($binding['has_base_url'] ?? false),
            'api_username' => (string) ($binding['account_name'] ?? ''),
            'has_api_key' => (bool) ($binding['has_api_key'] ?? false),
            'provider_config' => $this->visibleProviderConfig($providerKey, (array) ($binding['provider_config'] ?? [])),
            'has_provider_secret_values' => $this->providerSecretValues($providerKey, (array) ($binding['provider_config'] ?? [])),
            'upstream_binding' => $this->upstreamBindingPayload($supplier),
            'status' => (int) $supplier->status,
            'sort_order' => (int) $supplier->sort_order,
        ]);
    }

    public function revealSecret(Supplier $supplier, string $key)
    {
        if (! $supplier->exists) {
            return $this->error(40400, '接口不存在');
        }

        $secretKey = trim($key);
        $binding = $this->supplierBindingProjection($supplier, includeSecrets: true);
        if ($secretKey === 'api_key') {
            $value = trim((string) ($binding['api_key'] ?? ''));
            if ($value === '') {
                throw new BusinessException('接口密钥尚未配置', 42200);
            }

            return $this->success(['key' => $secretKey, 'value' => $value]);
        }

        $providerKey = trim((string) ($binding['provider_key'] ?? ''));
        $descriptor = app(ProviderRegistry::class)->descriptor($providerKey);
        $fields = (array) ($descriptor?->supplierForm['fields'] ?? []);
        $field = collect($fields)->first(function (mixed $item) use ($secretKey): bool {
            return is_array($item)
                && trim((string) ($item['key'] ?? '')) === $secretKey
                && (bool) ($item['secret'] ?? false);
        });

        if (! is_array($field)) {
            throw new BusinessException('敏感字段不存在', 42200);
        }

        $config = (array) ($binding['provider_config'] ?? []);
        $value = trim((string) ($config[$secretKey] ?? ''));
        if ($value === '') {
            throw new BusinessException('敏感字段尚未配置', 42200);
        }

        return $this->success(['key' => $secretKey, 'value' => $value]);
    }

    public function update(UpsertRequest $request, Supplier $supplier, UpstreamBindingWriter $bindingWriter)
    {
        if (! $supplier->exists) {
            return $this->error(40400, '接口不存在');
        }

        $bindingPayload = $request->upstreamBindingPayload();
        $supplierPayload = $request->supplierPayload();
        $supplierPayload['code'] = $supplier->code ?: $this->generateInternalCode($bindingPayload['provider_key'], $supplier->id);
        $updated = DB::transaction(function () use ($supplier, $supplierPayload, $bindingPayload, $bindingWriter): bool {
            $updated = $supplier->update($supplierPayload);
            $bindingWriter->syncSupplierBinding($supplier->refresh(), $bindingPayload);

            return $updated;
        });

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
            $runtimeSupplier = $this->supplierWithRuntimeCredentials($supplier);
            $provider = $providerResolver->resolveForSupplier($runtimeSupplier);
            $renewal = $provider->require(ProvidesRenewal::class, '当前供应商暂不支持余额查询');
            $result = $renewal->getBalance($runtimeSupplier);
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

        $runtimeSupplier = $this->supplierWithRuntimeCredentials($supplier);
        $provider = $providerResolver->resolveForSupplier($runtimeSupplier);
        $catalogCapability = $provider->require(ProvidesConsoleCatalog::class, '当前供应商暂不支持商品同步');
        $catalog = $catalogCapability->getProductCatalog($runtimeSupplier);
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

        $result = $productCatalogService->bulkConnectSupplierProducts($this->supplierWithRuntimeCredentials($supplier), $payload);

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

        $runtimeSupplier = $this->supplierWithRuntimeCredentials($supplier);
        $provider = $providerResolver->resolveForSupplier($runtimeSupplier);
        $catalogCapability = $provider->require(ProvidesConsoleCatalog::class, '当前供应商暂不支持拉取商品配置');
        $template = $catalogCapability->getProductConfigTemplate($runtimeSupplier, $productId);

        return $this->success([
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'upstream_product_id' => $productId,
            'product' => $template['product'],
            'config_options' => $template['config_options'],
            'auto_filled_fields' => $template['auto_filled_fields'],
        ], '商品配置拉取成功');
    }

    private function canQueryProvider(Supplier $supplier): bool
    {
        $binding = $this->supplierBindingProjection($supplier, includeSecrets: true);
        $providerKey = trim((string) ($binding['provider_key'] ?? ''));
        $descriptor = app(ProviderRegistry::class)->descriptor($providerKey);
        $fields = (array) ($descriptor?->supplierForm['fields'] ?? []);
        $providerConfig = (array) ($binding['provider_config'] ?? []);

        foreach ($fields as $field) {
            if (! is_array($field) || ! (bool) ($field['required'] ?? false)) {
                continue;
            }

            $key = trim((string) ($field['key'] ?? ''));
            $value = match ($key) {
                'api_url' => $binding['base_url'] ?? null,
                'api_username' => $binding['account_name'] ?? null,
                'api_key' => $binding['api_key'] ?? null,
                default => $providerConfig[$key] ?? null,
            };

            if (trim((string) ($value ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    private function visibleProviderConfig(string $providerKey, array $providerConfig): array
    {
        $descriptor = app(ProviderRegistry::class)->descriptor($providerKey);
        $fields = (array) ($descriptor?->supplierForm['fields'] ?? []);
        $visible = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = trim((string) ($field['key'] ?? ''));
            if ($key === '' || in_array($key, ['api_url', 'api_username', 'api_key'], true)) {
                continue;
            }

            $value = $providerConfig[$key] ?? null;
            $visible[$key] = (bool) ($field['secret'] ?? false) ? '' : $value;
        }

        return $visible;
    }

    private function providerSecretValues(string $providerKey, array $providerConfig): array
    {
        $descriptor = app(ProviderRegistry::class)->descriptor($providerKey);
        $fields = (array) ($descriptor?->supplierForm['fields'] ?? []);
        $values = [];

        foreach ($fields as $field) {
            if (! is_array($field) || ! (bool) ($field['secret'] ?? false)) {
                continue;
            }

            $key = trim((string) ($field['key'] ?? ''));
            if ($key === '' || in_array($key, ['api_url', 'api_username', 'api_key'], true)) {
                continue;
            }

            $values[$key] = trim((string) ($providerConfig[$key] ?? '')) !== '';
        }

        return $values;
    }

    private function upstreamBindingPayload(Supplier $supplier): ?array
    {
        if (! Schema::hasTable('supplier_plugin_bindings')) {
            return null;
        }

        $binding = $this->supplierBindingProjection($supplier);
        if ($binding === []) {
            return null;
        }

        return [
            'id' => (int) $binding['id'],
            'plugin_id' => (int) $binding['plugin_id'],
            'provider_key' => (string) $binding['provider_key'],
            'environment' => (string) $binding['environment'],
            'status' => (int) $binding['status'],
            'priority' => (int) $binding['priority'],
            'base_url' => '',
            'has_base_url' => (bool) ($binding['has_base_url'] ?? false),
            'account_name' => (string) ($binding['account_name'] ?? ''),
            'has_secret_values' => is_array($binding['has_secret_values'] ?? null) ? $binding['has_secret_values'] : [],
            'last_checked_at' => $binding['last_checked_at'] ?? null,
            'last_check_status' => $binding['last_check_status'] ?? null,
            'last_check_error' => $binding['last_check_error'] ?? null,
        ];
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

        $localProducts = $this->localProductsByUpstreamProductIds($supplier, $productIds);

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

    private function providerKeyForSupplier(Supplier $supplier): string
    {
        $binding = $this->supplierBindingProjection($supplier);

        return trim((string) ($binding['provider_key'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierBindingProjection(Supplier $supplier, bool $includeSecrets = false): array
    {
        return app(PluginBindingResolver::class)->supplierBindingProjection($supplier, $includeSecrets);
    }

    private function supplierWithRuntimeCredentials(Supplier $supplier): Supplier
    {
        return app(PluginBindingResolver::class)->supplierWithRuntimeCredentials($supplier);
    }

    private function providerLabel(string $providerKey): string
    {
        if ($providerKey === '') {
            return '';
        }

        return app(ProviderRegistry::class)->descriptor($providerKey)?->label ?? $providerKey;
    }

    private function localProductsByUpstreamProductIds(Supplier $supplier, array $productIds)
    {
        $normalizedIds = collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $bindingProducts = collect();
        $bindingIds = $this->supplierPluginBindingIds((int) $supplier->id);
        if (Schema::hasTable('product_upstream_bindings') && Schema::hasTable('supplier_plugin_bindings')) {
            if ($normalizedIds !== [] && $bindingIds !== []) {
                $bindingProducts = Product::withTrashed()
                    ->with(['firstProductGroup', 'secondProductGroup', 'thirdProductGroup'])
                    ->select('products.*', 'pub.upstream_product_id as binding_upstream_product_id')
                    ->join('product_upstream_bindings as pub', 'pub.product_id', '=', 'products.id')
                    ->whereIn('pub.supplier_plugin_binding_id', $bindingIds)
                    ->whereIn('pub.upstream_product_id', array_map(static fn (int $id): string => (string) $id, $normalizedIds))
                    ->orderByDesc('pub.status')
                    ->orderByDesc('pub.id')
                    ->get()
                    ->keyBy(fn (Product $product) => (int) $product->getAttribute('binding_upstream_product_id'));
            }

            return $bindingProducts;
        }

        return $bindingProducts;
    }

    private function supplierUsageSummary(Supplier $supplier): array
    {
        $supplierId = (int) $supplier->id;
        $bindingIds = $this->supplierPluginBindingIds($supplierId);
        $productCount = $this->countBoundProducts($supplierId, $bindingIds);
        $serviceCount = $this->countBoundServices($supplierId);
        $serviceInstanceCount = $this->countBoundServiceInstances($supplierId);

        return [
            'products' => $productCount,
            'services' => $serviceCount,
            'service_instances' => $serviceInstanceCount,
            'total' => $productCount + $serviceCount + $serviceInstanceCount,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function supplierPluginBindingIds(int $supplierId): array
    {
        if ($supplierId <= 0 || ! Schema::hasTable('supplier_plugin_bindings')) {
            return [];
        }

        return DB::table('supplier_plugin_bindings')
            ->where('supplier_id', $supplierId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $bindingIds
     */
    private function countBoundProducts(int $supplierId, array $bindingIds): int
    {
        $productIds = collect();

        if ($bindingIds !== [] && Schema::hasTable('product_upstream_bindings')) {
            $productIds = $productIds->merge(
                DB::table('product_upstream_bindings')
                    ->whereIn('supplier_plugin_binding_id', $bindingIds)
                    ->pluck('product_id')
                    ->map(fn ($id) => (int) $id)
            );
        }

        return $productIds
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->count();
    }

    private function countBoundServices(int $supplierId): int
    {
        $bindingIds = $this->supplierPluginBindingIds($supplierId);
        if ($bindingIds === [] || ! Schema::hasTable('service_upstream_bindings')) {
            return 0;
        }

        return (int) DB::table('service_upstream_bindings')
            ->whereIn('supplier_plugin_binding_id', $bindingIds)
            ->distinct()
            ->count('service_id');
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
