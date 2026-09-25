<?php

declare(strict_types=1);

namespace App\Services\Integrations\Plugins;

use App\Models\Product;
use App\Models\ProductUpstreamBinding;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\SupplierPluginBinding;
use App\Services\Upstream\ProviderRegistry;
use App\Support\SchemaMetadataCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class PluginBindingResolver
{
    /**
     * @var array<int, object|null>
     */
    private array $productBindingCache = [];

    /**
     * 服务绑定/快照的行级缓存：只由 preloadServiceProjections() 显式批量填充，
     * 单行读取只读不写——避免状态同步等"写快照后再读投影"的路径命中旧数据。
     *
     * @var array<int, object|null>
     */
    private array $serviceBindingCache = [];

    /**
     * @var array<int, object|null>
     */
    private array $runtimeSnapshotRowCache = [];

    /**
     * @var array<int, object|null>
     */
    private array $connectionSnapshotRowCache = [];

    public function providerKeyForSupplier(Supplier $supplier): ?string
    {
        $binding = $this->supplierBindingForSupplier($supplier);

        return $this->nullableString($binding->provider_key ?? null);
    }

    public function providerKeyForProduct(Product $product): ?string
    {
        $binding = $this->productBindingForProduct($product);

        return $this->nullableString($binding->provider_key ?? null);
    }

    public function providerKeyForService(Service $service): ?string
    {
        $binding = $this->serviceBinding((int) $service->id);
        if ($binding !== null) {
            return $this->nullableString($binding->provider_key ?? null);
        }

        $service->loadMissing('product');
        if ($service->product instanceof Product) {
            return $this->providerKeyForProduct($service->product);
        }

        return null;
    }

    public function supplierIdForService(Service $service): ?int
    {
        $binding = $this->serviceBinding((int) $service->id);
        $supplierId = (int) (($binding->supplier_id ?? 0) ?: 0);

        return $supplierId > 0 ? $supplierId : null;
    }

    public function supplierForService(Service $service): ?Supplier
    {
        $supplierId = $this->supplierIdForService($service);

        return $supplierId === null ? null : Supplier::query()->find($supplierId);
    }

    public function upstreamServiceIdForService(Service $service): ?string
    {
        $binding = $this->serviceBinding((int) $service->id);

        return $this->nullableString($binding->upstream_service_id ?? null);
    }

    public function productIdForService(Service $service): ?int
    {
        $binding = $this->serviceBinding((int) $service->id);
        $productId = (int) (($binding->product_id ?? 0) ?: 0);

        return $productId > 0 ? $productId : null;
    }

    public function upstreamProductIdForService(Service $service): ?string
    {
        $binding = $this->serviceBinding((int) $service->id);

        return $this->nullableString($binding->upstream_product_id ?? null);
    }

    public function upstreamProductIdForProduct(Product $product): ?string
    {
        $binding = $this->productBindingForProduct($product);

        return $this->nullableString($binding->upstream_product_id ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function supplierBindingProjection(Supplier $supplier, bool $includeSecrets = false): array
    {
        $supplierId = (int) $supplier->id;
        if ($supplierId <= 0) {
            return [];
        }

        $binding = $this->supplierBindingForSupplier($supplier);
        if ($binding === null) {
            return [];
        }

        $config = $this->decodePayload($binding->config_json ?? null);
        $secrets = $this->decryptPayload($binding->secret_json ?? null);
        $providerKey = $this->nullableString($binding->provider_key ?? null);
        $providerConfig = $this->providerConfigFromBinding($config, $secrets);
        $apiKey = $this->nullableString($secrets['api_key'] ?? null);
        $hasSecretValues = $this->decodePayload($binding->has_secret_json ?? null);
        $hasSecretValues = array_replace(
            $this->providerSecretPresence($providerKey, $providerConfig),
            array_filter($hasSecretValues, static fn (mixed $value): bool => (bool) $value)
        );

        if ($apiKey !== null) {
            $hasSecretValues['api_key'] = true;
        }

        $projection = [
            'id' => (int) $binding->id,
            'supplier_id' => (int) $binding->supplier_id,
            'plugin_id' => (int) $binding->plugin_id,
            'provider_key' => $providerKey,
            'environment' => $this->nullableString($binding->environment ?? null) ?? 'production',
            'status' => (int) ($binding->status ?? 0),
            'priority' => (int) ($binding->priority ?? 0),
            'base_url' => $this->nullableString($binding->base_url ?? null),
            'account_name' => $this->nullableString($binding->account_name ?? null),
            'has_base_url' => $this->nullableString($binding->base_url ?? null) !== null,
            'has_api_key' => ($hasSecretValues['api_key'] ?? false) === true,
            'web_session_cookie' => $this->nullableString($secrets['web_session_cookie'] ?? null, 4000),
            'provider_config' => $providerConfig,
            'has_secret_values' => $hasSecretValues,
            'last_checked_at' => $this->formatDateTime($binding->last_checked_at ?? null),
            'last_check_status' => $this->nullableString($binding->last_check_status ?? null),
            'last_check_error' => $this->nullableString($binding->last_check_error ?? null),
        ];

        if ($includeSecrets) {
            $projection['api_key'] = $apiKey;
        }

        return array_filter($projection, static fn (mixed $value): bool => $value !== null);
    }

    public function supplierWithRuntimeCredentials(Supplier $supplier, bool $includeSecrets = true): Supplier
    {
        $projection = $this->supplierBindingProjection($supplier, $includeSecrets);
        if ($projection === []) {
            return $supplier;
        }

        $supplier->setAttribute('provider_key', $projection['provider_key'] ?? null);
        $supplier->setAttribute('api_url', $projection['base_url'] ?? null);
        $supplier->setAttribute('api_username', $projection['account_name'] ?? null);
        $supplier->setAttribute('provider_config', (array) ($projection['provider_config'] ?? []));

        if ($includeSecrets) {
            $supplier->setAttribute('api_key', $projection['api_key'] ?? null);
            $supplier->setAttribute('web_session_cookie', $projection['web_session_cookie'] ?? null);
        }

        return $supplier;
    }

    public function supplierIdForProduct(Product $product): ?int
    {
        return $this->supplierIdFromProductBinding($this->productBindingForProduct($product));
    }

    public function supplierForProduct(Product $product): ?Supplier
    {
        $binding = $this->productBindingForProduct($product);
        if ($binding instanceof ProductUpstreamBinding) {
            $supplierBinding = $binding->relationLoaded('supplierPluginBinding')
                ? $binding->supplierPluginBinding
                : null;

            if (
                $supplierBinding instanceof SupplierPluginBinding
                && $supplierBinding->relationLoaded('supplier')
                && $supplierBinding->supplier instanceof Supplier
            ) {
                return $supplierBinding->supplier;
            }
        }

        $supplierId = $this->supplierIdFromProductBinding($binding);

        return $supplierId === null ? null : Supplier::query()->find($supplierId);
    }

    public function productIdForSupplierAndUpstreamProduct(int $supplierId, mixed $upstreamProductId): ?int
    {
        $upstreamProductKey = $this->nullableString($upstreamProductId);
        if ($supplierId <= 0 || $upstreamProductKey === null || ! $this->hasTable('supplier_plugin_bindings') || ! $this->hasTable('product_upstream_bindings')) {
            return null;
        }

        $row = DB::table('product_upstream_bindings as pub')
            ->join('supplier_plugin_bindings as spb', 'spb.id', '=', 'pub.supplier_plugin_binding_id')
            ->where('spb.supplier_id', $supplierId)
            ->where('pub.upstream_product_id', $upstreamProductKey)
            ->orderByDesc('pub.status')
            ->orderByDesc('pub.id')
            ->first(['pub.product_id']);

        $productId = (int) (($row->product_id ?? 0) ?: 0);

        return $productId > 0 ? $productId : null;
    }

    /**
     * legacy services.provision_data 与插件投影的合并单一入口：
     * 投影为空时原样回退 legacy，否则投影键覆盖 legacy 同名键。
     * （原 ServiceStatusSyncService / AdminServiceListService / HandlesAdminUserServices
     * 三处逐字重复的私有实现收敛于此，E1-21。）
     *
     * @return array<string, mixed>
     */
    public function serviceProvisionData(Service $service, bool $includeSecrets = false): array
    {
        $legacy = is_array($service->provision_data ?? null) ? $service->provision_data : [];
        $projection = $this->serviceProvisionProjection($service, $includeSecrets);

        return $projection === [] ? $legacy : array_replace($legacy, $projection);
    }

    /**
     * Build a provision-data-like projection from the normalized service binding
     * tables. This lets runtime code prefer the new schema while older call sites
     * are migrated away from services.provision_data one by one.
     *
     * @return array<string, mixed>
     */
    public function serviceProvisionProjection(Service $service, bool $includeSecrets = false): array
    {
        $serviceId = (int) $service->id;
        if ($serviceId <= 0) {
            return [];
        }

        $binding = $this->serviceBinding($serviceId);
        $runtime = $this->serviceRuntimeSnapshot($serviceId);
        $connection = $this->serviceConnectionSnapshot($serviceId, includeSecrets: $includeSecrets);

        $projection = [];

        if ($binding !== null) {
            $projection = array_replace($projection, [
                'provider_key' => $this->nullableString($binding->provider_key ?? null),
                'supplier_id' => $this->positiveInt($binding->supplier_id ?? null),
                'upstream_product_id' => $this->nullableString($binding->upstream_product_id ?? null),
                'upstream_host_id' => $this->nullableString($binding->upstream_service_id ?? null),
                'upstream_account_id' => $this->nullableString($binding->upstream_account_id ?? null),
                'upstream_status' => $this->nullableString($binding->status_snapshot ?? null),
                'last_synced_at' => $this->formatDateTime($binding->last_synced_at ?? null),
                'status_sync_error' => $this->nullableString($binding->last_sync_error ?? null),
            ]);

            $runtimeSnapshot = $this->decodePayload($binding->runtime_snapshot_json ?? null);
            $connectionSnapshot = $this->decodePayload($binding->connection_snapshot_json ?? null);
            $projection = array_replace($projection, $runtimeSnapshot, $connectionSnapshot);
        }

        if ($runtime !== []) {
            $projection = array_replace($projection, $runtime);
        }

        if ($connection !== []) {
            $projection = array_replace($projection, $connection);
        }

        return array_filter($projection, static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * 列表路径批量预载：对整页 serviceId 一次往返取绑定/运行快照/连接快照三张表，
     * 写入行级缓存；后续逐行投影组装全部命中缓存（查询数从每行 3 条降为每页 3 条）。
     * 只填充缓存、不改变单行投影的取数优先级与组装逻辑，输出与逐行查询完全一致。
     */
    public function preloadServiceProjections(iterable $serviceIds): void
    {
        $ids = collect($serviceIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        if ($this->hasTable('service_upstream_bindings')) {
            $rows = DB::table('service_upstream_bindings as sub')
                ->leftJoin('supplier_plugin_bindings as spb', 'spb.id', '=', 'sub.supplier_plugin_binding_id')
                ->leftJoin('product_upstream_bindings as pub', 'pub.id', '=', 'sub.product_upstream_binding_id')
                ->leftJoin('supplier_plugin_bindings as pub_spb', 'pub_spb.id', '=', 'pub.supplier_plugin_binding_id')
                ->whereIn('sub.service_id', $ids)
                ->orderByDesc('sub.id')
                ->get([
                    'sub.service_id',
                    'sub.id',
                    'sub.provider_key',
                    'sub.upstream_service_id',
                    'sub.product_upstream_binding_id',
                    'sub.supplier_plugin_binding_id',
                    'sub.plugin_id',
                    'sub.upstream_account_id',
                    'sub.runtime_snapshot_json',
                    'sub.connection_snapshot_json',
                    'sub.status_snapshot',
                    'sub.last_synced_at',
                    'sub.last_sync_error',
                    DB::raw('COALESCE(spb.supplier_id, pub_spb.supplier_id) as supplier_id'),
                    'pub.product_id',
                    'pub.upstream_product_id',
                ]);

            foreach ($this->firstRowPerService($rows) as $serviceId => $row) {
                $this->serviceBindingCache[$serviceId] = $row;
            }
            // 无绑定行的服务也要缓存空结果，避免逐行 transform 时的重复空查询
            foreach ($ids as $serviceId) {
                $this->serviceBindingCache[$serviceId] ??= null;
            }
        }

        if ($this->hasTable('service_runtime_snapshots')) {
            $rows = DB::table('service_runtime_snapshots')
                ->whereIn('service_id', $ids)
                ->orderByDesc('id')
                ->get([
                    'service_id',
                    'provider_key',
                    'status_key',
                    'status_text',
                    'resource_json',
                    'metrics_json',
                    'snapshot_json',
                    'synced_at',
                ]);

            foreach ($this->firstRowPerService($rows) as $serviceId => $row) {
                $this->runtimeSnapshotRowCache[$serviceId] = $row;
            }
            // 无快照行的服务同样缓存空结果
            foreach ($ids as $serviceId) {
                $this->runtimeSnapshotRowCache[$serviceId] ??= null;
            }
        }

        if ($this->hasTable('service_connection_snapshots')) {
            $rows = DB::table('service_connection_snapshots')
                ->whereIn('service_id', $ids)
                ->where('connection_type', 'default')
                ->orderByDesc('id')
                ->get([
                    'service_id',
                    'provider_key',
                    'hostname',
                    'ip_address',
                    'port',
                    'connection_json',
                    'secret_json',
                    'checked_at',
                ]);

            foreach ($this->firstRowPerService($rows) as $serviceId => $row) {
                $this->connectionSnapshotRowCache[$serviceId] = $row;
            }
            // 无快照行的服务同样缓存空结果
            foreach ($ids as $serviceId) {
                $this->connectionSnapshotRowCache[$serviceId] ??= null;
            }
        }
    }

    /**
     * 全局倒序排列的行集合按 service_id 分组取首行——与单行查询
     * where(service_id)->orderByDesc(id)->first() 取到的行完全一致。
     *
     * @param  iterable<int, object>  $rows
     * @return array<int, object>
     */
    private function firstRowPerService(iterable $rows): array
    {
        $first = [];

        foreach ($rows as $row) {
            $serviceId = (int) ($row->service_id ?? 0);
            if ($serviceId > 0 && ! array_key_exists($serviceId, $first)) {
                $first[$serviceId] = $row;
            }
        }

        return $first;
    }

    private function supplierBinding(int $supplierId): ?object
    {
        if ($supplierId <= 0 || ! $this->hasTable('supplier_plugin_bindings')) {
            return null;
        }

        return DB::table('supplier_plugin_bindings')
            ->where('supplier_id', $supplierId)
            ->orderByDesc('status')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->first();
    }

    private function supplierBindingForSupplier(Supplier $supplier): ?object
    {
        if ($supplier->relationLoaded('pluginBindings')) {
            $binding = $this->supplierBindingFromLoadedRelation($supplier);
            if ($binding !== null) {
                return $binding;
            }
        }

        return $this->supplierBinding((int) $supplier->id);
    }

    private function supplierBindingFromLoadedRelation(Supplier $supplier): ?object
    {
        $bindings = $supplier->getRelation('pluginBindings');
        if (! $bindings instanceof Collection || $bindings->isEmpty()) {
            return null;
        }

        return $bindings
            ->sort(function (object $left, object $right): int {
                return [
                    (int) ($right->status ?? 0),
                    (int) ($right->priority ?? 0),
                    (int) ($right->id ?? 0),
                ] <=> [
                    (int) ($left->status ?? 0),
                    (int) ($left->priority ?? 0),
                    (int) ($left->id ?? 0),
                ];
            })
            ->first();
    }

    private function productBindingForProduct(Product $product): ?object
    {
        if ($product->relationLoaded('upstreamBindings')) {
            return $this->productBindingFromLoadedRelation($product);
        }

        return $this->productBinding((int) $product->id);
    }

    private function productBindingFromLoadedRelation(Product $product): ?object
    {
        $bindings = $product->getRelation('upstreamBindings');
        if (! $bindings instanceof Collection || $bindings->isEmpty()) {
            return null;
        }

        $binding = $bindings
            ->sort(function (object $left, object $right): int {
                return [
                    (int) ($right->status ?? 0),
                    (int) ($right->id ?? 0),
                ] <=> [
                    (int) ($left->status ?? 0),
                    (int) ($left->id ?? 0),
                ];
            })
            ->first();

        if (
            $binding instanceof ProductUpstreamBinding
            && $binding->relationLoaded('supplierPluginBinding')
            && $binding->supplierPluginBinding instanceof SupplierPluginBinding
        ) {
            $binding->setAttribute('supplier_id', (int) $binding->supplierPluginBinding->supplier_id);
        }

        return $binding;
    }

    private function productBinding(int $productId): ?object
    {
        if ($productId <= 0 || ! $this->hasTable('product_upstream_bindings')) {
            return null;
        }

        if (array_key_exists($productId, $this->productBindingCache)) {
            return $this->productBindingCache[$productId];
        }

        $query = DB::table('product_upstream_bindings as pub')
            ->where('pub.product_id', $productId)
            ->orderByDesc('pub.status')
            ->orderByDesc('pub.id');

        $columns = ['pub.*'];
        if ($this->hasTable('supplier_plugin_bindings')) {
            $query->leftJoin('supplier_plugin_bindings as spb', 'spb.id', '=', 'pub.supplier_plugin_binding_id');
            $columns[] = 'spb.supplier_id';
        }

        return $this->productBindingCache[$productId] = $query->first($columns);
    }

    private function supplierIdFromProductBinding(?object $binding): ?int
    {
        $supplierId = (int) (($binding->supplier_id ?? 0) ?: 0);

        return $supplierId > 0 ? $supplierId : null;
    }

    private function serviceBinding(int $serviceId): ?object
    {
        if ($serviceId <= 0 || ! $this->hasTable('service_upstream_bindings')) {
            return null;
        }

        // 批量预载后命中缓存；单行路径不写缓存，保证"写快照后读投影"不受污染
        if (array_key_exists($serviceId, $this->serviceBindingCache)) {
            return $this->serviceBindingCache[$serviceId];
        }

        return DB::table('service_upstream_bindings as sub')
            ->leftJoin('supplier_plugin_bindings as spb', 'spb.id', '=', 'sub.supplier_plugin_binding_id')
            ->leftJoin('product_upstream_bindings as pub', 'pub.id', '=', 'sub.product_upstream_binding_id')
            ->leftJoin('supplier_plugin_bindings as pub_spb', 'pub_spb.id', '=', 'pub.supplier_plugin_binding_id')
            ->where('sub.service_id', $serviceId)
            ->orderByDesc('sub.id')
            ->first([
                'sub.id',
                'sub.provider_key',
                'sub.upstream_service_id',
                'sub.product_upstream_binding_id',
                'sub.supplier_plugin_binding_id',
                'sub.plugin_id',
                'sub.upstream_account_id',
                'sub.runtime_snapshot_json',
                'sub.connection_snapshot_json',
                'sub.status_snapshot',
                'sub.last_synced_at',
                'sub.last_sync_error',
                DB::raw('COALESCE(spb.supplier_id, pub_spb.supplier_id) as supplier_id'),
                'pub.product_id',
                'pub.upstream_product_id',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceRuntimeSnapshot(int $serviceId): array
    {
        if ($serviceId <= 0 || ! $this->hasTable('service_runtime_snapshots')) {
            return [];
        }

        $snapshot = $this->serviceRuntimeSnapshotRow($serviceId);

        if ($snapshot === null) {
            return [];
        }

        $resource = $this->decodePayload($snapshot->resource_json ?? null);
        $metrics = $this->decodePayload($snapshot->metrics_json ?? null);
        $rawSnapshot = $this->decodePayload($snapshot->snapshot_json ?? null);

        return array_filter(array_replace($rawSnapshot, $resource, $metrics, [
            'provider_key' => $this->nullableString($snapshot->provider_key ?? null),
            'upstream_status' => $this->nullableString($snapshot->status_key ?? null),
            'runtime_status' => $this->nullableString($snapshot->status_key ?? null),
            'runtime_description' => $this->nullableString($snapshot->status_text ?? null),
            'last_synced_at' => $this->formatDateTime($snapshot->synced_at ?? null),
            'last_status_sync_at' => $this->formatDateTime($snapshot->synced_at ?? null),
        ]), static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * 运行快照原始行：优先命中批量预载写入的行级缓存，未命中时单行查询（不写缓存）。
     */
    private function serviceRuntimeSnapshotRow(int $serviceId): ?object
    {
        if (array_key_exists($serviceId, $this->runtimeSnapshotRowCache)) {
            return $this->runtimeSnapshotRowCache[$serviceId];
        }

        return DB::table('service_runtime_snapshots')
            ->where('service_id', $serviceId)
            ->orderByDesc('id')
            ->first([
                'provider_key',
                'status_key',
                'status_text',
                'resource_json',
                'metrics_json',
                'snapshot_json',
                'synced_at',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceConnectionSnapshot(int $serviceId, bool $includeSecrets = false): array
    {
        if ($serviceId <= 0 || ! $this->hasTable('service_connection_snapshots')) {
            return [];
        }

        $snapshot = $this->serviceConnectionSnapshotRow($serviceId);

        if ($snapshot === null) {
            return [];
        }

        $connection = $this->decodePayload($snapshot->connection_json ?? null);
        $connection = array_replace($connection, [
            'provider_key' => $this->nullableString($snapshot->provider_key ?? null),
            'connection_cached_hostname' => $this->nullableString($snapshot->hostname ?? null),
            'dedicated_ip' => $this->nullableString($snapshot->ip_address ?? null),
            'nat_remote_port' => $this->positiveInt($snapshot->port ?? null),
            'connection_cached_at' => $this->formatDateTime($snapshot->checked_at ?? null),
        ]);

        if ($includeSecrets) {
            $secrets = $this->decryptPayload($snapshot->secret_json ?? null);
            $connection = array_replace($connection, [
                'connection_secret' => $this->nullableString($secrets['connection_secret'] ?? null),
                'password' => $this->nullableString($secrets['password'] ?? null),
            ]);
        }

        return array_filter($connection, static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * 连接快照原始行：优先命中批量预载写入的行级缓存，未命中时单行查询（不写缓存）。
     * 秘钥解密仍延迟到组装阶段，按调用方 includeSecrets 决定，缓存行可复用于两种口径。
     */
    private function serviceConnectionSnapshotRow(int $serviceId): ?object
    {
        if (array_key_exists($serviceId, $this->connectionSnapshotRowCache)) {
            return $this->connectionSnapshotRowCache[$serviceId];
        }

        return DB::table('service_connection_snapshots')
            ->where('service_id', $serviceId)
            ->where('connection_type', 'default')
            ->orderByDesc('id')
            ->first([
                'provider_key',
                'hostname',
                'ip_address',
                'port',
                'connection_json',
                'secret_json',
                'checked_at',
            ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $secrets
     * @return array<string, mixed>
     */
    private function providerConfigFromBinding(array $config, array $secrets): array
    {
        $providerConfig = [];

        if (is_array($config['provider_config'] ?? null)) {
            $providerConfig = array_replace($providerConfig, $config['provider_config']);
        }

        if (is_array($secrets['provider_config'] ?? null)) {
            $providerConfig = array_replace($providerConfig, $secrets['provider_config']);
        }

        return array_filter($providerConfig, static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     * @return array<string, bool>
     */
    private function providerSecretPresence(?string $providerKey, array $providerConfig): array
    {
        $presence = [];

        $descriptor = app(ProviderRegistry::class)->descriptor($providerKey);
        foreach ((array) ($descriptor?->supplierForm['fields'] ?? []) as $field) {
            if (! is_array($field) || ! (bool) ($field['secret'] ?? false)) {
                continue;
            }

            $key = trim((string) ($field['key'] ?? ''));
            if ($key === '' || $key === 'api_key') {
                continue;
            }

            if ($this->nullableString($providerConfig[$key] ?? null) !== null) {
                $presence[$key] = true;
            }
        }

        return $presence;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function decryptPayload(mixed $payload): array
    {
        $encrypted = trim((string) ($payload ?? ''));
        if ($encrypted === '') {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($encrypted), true);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function hasTable(string $table): bool
    {
        try {
            return SchemaMetadataCache::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function positiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }
}
