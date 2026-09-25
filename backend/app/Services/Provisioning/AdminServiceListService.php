<?php

declare(strict_types=1);

namespace App\Services\Provisioning;

use App\Constants\ServiceStatus;
use App\Models\Invoice;
use App\Models\Service;
use App\Services\Integrations\Plugins\PluginBindingResolver;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Support\SchemaMetadataCache;
use App\Support\ServiceHostname;
use App\Support\ServiceListPresentation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class AdminServiceListService
{
    public function __construct(
        private readonly ?ProductDisplayNameResolver $productDisplayNameResolver = null,
        private ?PluginBindingResolver $bindingResolver = null,
    ) {}

    /**
     * 管理端全量服务分页列表
     */
    public function paginate(array $filters = []): array
    {
        $pageSize = min(max((int) ($filters['page_size'] ?? 20), 1), 100);
        $page = max((int) ($filters['page'] ?? 1), 1);

        $query = Service::query()
            ->select([
                'id',
                'user_id',
                'product_id',
                'order_id',
                'invoice_id',
                'name',
                'domain',
                'billing_cycle',
                'amount',
                'status',
                'provision_data',
                'expires_at',
                'created_at',
                'auto_renew',
            ])
            ->with([
                'user:id,nickname,email,phone,status',
                'product:id,product_type,service_type_code,product_group_id,config_options,purchase_requires',
                'product.productGroup:id,second_product_group_id,name,description,slug',
                'product.productGroup.secondProductGroup:id,first_product_group_id,name,description,slug',
                'product.productGroup.secondProductGroup.firstProductGroup:id,code,name,description,slug',
                'order:id,order_no,status,paid_at',
                'invoice:id,invoice_no,service_id,order_id,product_spec_snapshot,status,paid_at',
                'invoices:id,invoice_no,service_id,order_id,product_spec_snapshot,status,paid_at',
            ]);

        // 关键词搜索：服务名、主机名、主机 ID、IP、用户、账单等服务相关信息
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $this->applyKeywordSearch($query, $keyword);
        }

        // 状态筛选
        $status = $filters['status'] ?? '';
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }

        $paginator = $query->orderByDesc('id')->paginate($pageSize, ['*'], 'page', $page);

        // 列表路径批量预载绑定与快照：每页 3 次往返替代每行 3 次（D3B-02），逐行投影输出不变
        $this->bindingResolver()->preloadServiceProjections(
            collect($paginator->items())->map(static fn (Service $service): int => (int) $service->id)
        );

        return [
            'list' => collect($paginator->items())
                ->map(fn (Service $service) => $this->transform($service))
                ->values()
                ->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    private function transform(Service $service): array
    {
        $provisionData = $this->bindingResolver()->serviceProvisionData($service, includeSecrets: true);
        $connection = $this->resolveConnection($provisionData);
        $hostIps = $this->resolveHostIps($provisionData, $connection);
        $statusLabels = ServiceStatus::$labels ?? [];
        $invoice = $this->resolvePrimaryInvoice($service);
        $order = $invoice ? null : $service->order;
        // D3B-01：产品名/路径与客户端控制台共用单一口径（ServiceListPresentation）
        $productDisplayName = ServiceListPresentation::productDisplayName($service, $this->productDisplayNameResolver);

        return [
            'id' => $service->id,
            'service_id' => (int) $service->id,
            'instance_id' => (int) $service->id,
            'name' => (string) $service->name,
            'product_display_name' => $productDisplayName,
            'product_full_path' => ServiceListPresentation::serviceProductPath($service, $productDisplayName),
            'domain' => ServiceHostname::resolveDisplayDomain($service, $provisionData),
            'requested_hostname' => (string) ($provisionData['requested_host'] ?? ''),
            'custom_hostname' => ServiceHostname::custom($provisionData),
            'has_custom_hostname' => ServiceHostname::hasCustom($provisionData),
            'status' => (int) $service->status,
            'status_label' => $statusLabels[$service->status] ?? (string) $service->status,
            'billing_cycle' => (string) $service->billing_cycle,
            'amount' => number_format((float) $service->amount, 2, '.', ''),
            'expires_at' => $service->expires_at?->format('Y-m-d H:i:s'),
            'created_at' => $service->created_at?->format('Y-m-d H:i:s'),
            // D3B-01：与客户端契约统一为 int（原管理端为 bool，已分叉）
            'auto_renew' => (int) $service->auto_renew,
            'upstream_host_id' => (int) (($provisionData['upstream_host_id'] ?? 0) ?: 0),
            'upstream_host_id_text' => (string) ($provisionData['upstream_host_id'] ?? ''),
            'upstream_host_ids' => $this->normalizeStringList($provisionData['upstream_host_ids'] ?? []),
            'dedicated_ip' => (string) ($provisionData['dedicated_ip'] ?? ''),
            'host_ips' => $hostIps,
            'internal_ip' => (string) ($provisionData['internal_ip'] ?? ''),
            'host_username' => (string) ($provisionData['username'] ?? ($connection['username'] ?? '')),
            'connection' => [
                'hostname' => (string) ($connection['hostname'] ?? ''),
                'username' => (string) ($connection['username'] ?? ''),
                'internal_ip' => (string) ($connection['internal_ip'] ?? ''),
                'port' => (int) (($connection['port'] ?? 0) ?: 0),
            ],
            'os' => (string) ($provisionData['os'] ?? ''),
            'user' => [
                'id' => (int) ($service->user?->id ?? 0),
                'username' => (string) ($service->user?->nickname ?? ''),
                'email' => (string) ($service->user?->email ?? ''),
                'phone' => (string) ($service->user?->phone ?? ''),
                'status' => (int) ($service->user?->status ?? 0),
            ],
            'product' => [
                'id' => (int) ($service->product?->id ?? 0),
                'name' => (string) ($service->product?->name ?? ''),
                'type' => (string) ($service->product?->product_type ?? ''),
            ],
            'order' => [
                'id' => (int) ($order?->id ?? 0),
                'order_no' => (string) ($order?->order_no ?? ''),
            ],
            'invoice' => [
                'id' => (int) ($invoice?->id ?? 0),
                'invoice_no' => (string) ($invoice?->invoice_no ?? ''),
                'status' => (int) ($invoice?->status ?? 0),
                'paid_at' => $invoice?->paid_at?->format('Y-m-d H:i:s'),
            ],
        ];
    }

    private function resolveConnection(array $provisionData): array
    {
        $plainConnection = is_array($provisionData['connection'] ?? null)
            ? (array) $provisionData['connection']
            : [];
        $secretConnection = $this->readConnectionSecret($provisionData);
        $snapshotConnection = [
            'hostname' => $provisionData['hostname'] ?? ($provisionData['connection_cached_hostname'] ?? ''),
            'username' => $provisionData['username'] ?? '',
            'internal_ip' => $provisionData['internal_ip'] ?? '',
            'port' => $provisionData['port'] ?? ($provisionData['nat_remote_port'] ?? 0),
        ];
        $connection = array_merge($snapshotConnection, $secretConnection, $plainConnection);

        return [
            'hostname' => trim((string) ($connection['hostname'] ?? '')),
            'username' => trim((string) ($connection['username'] ?? '')),
            'internal_ip' => trim((string) ($connection['internal_ip'] ?? '')),
            'port' => (int) (($connection['port'] ?? 0) ?: 0),
        ];
    }

    private function resolveHostIps(array $provisionData, array $connection): array
    {
        return collect([
            $provisionData['dedicated_ip'] ?? '',
            $provisionData['internal_ip'] ?? '',
            $connection['internal_ip'] ?? '',
            ...(array) ($provisionData['assigned_ips'] ?? []),
        ])
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            $value = $value === null || $value === '' ? [] : [$value];
        }

        return collect($value)
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn (string $item) => $item !== '')
            ->values()
            ->all();
    }

    private function applyKeywordSearch(Builder $query, string $keyword): void
    {
        $likeKeyword = '%'.$keyword.'%';
        $numericKeyword = $this->extractNumericKeyword($keyword);

        $query->where(function (Builder $builder) use ($keyword, $likeKeyword, $numericKeyword) {
            $builder->where('name', 'like', $likeKeyword)
                ->orWhere('domain', 'like', $likeKeyword);

            $snapshotMatchedServiceIds = $this->resolveSnapshotMatchedServiceIds($keyword);
            if ($snapshotMatchedServiceIds !== []) {
                $builder->orWhereIn('id', $snapshotMatchedServiceIds);
            }

            if ($numericKeyword !== null) {
                $builder->orWhere('id', $numericKeyword)
                    ->orWhere('user_id', $numericKeyword)
                    ->orWhere('order_id', $numericKeyword)
                    ->orWhere('invoice_id', $numericKeyword);
            }

            $builder
                ->orWhereHas('product', function (Builder $productQuery) use ($likeKeyword) {
                    $productQuery->where('name', 'like', $likeKeyword);
                })
                ->orWhereHas('order', function (Builder $orderQuery) use ($likeKeyword, $numericKeyword) {
                    $orderQuery->where(function (Builder $innerQuery) use ($likeKeyword, $numericKeyword) {
                        if ($numericKeyword !== null) {
                            $innerQuery->where('id', $numericKeyword)
                                ->orWhere('service_id', $numericKeyword);
                        }

                        $this->orWhereKeyword($innerQuery, 'order_no', $likeKeyword, $numericKeyword !== null);
                    });
                })
                ->orWhereHas('invoice', function (Builder $invoiceQuery) use ($likeKeyword, $numericKeyword) {
                    $this->applyInvoiceKeywordSearch($invoiceQuery, $likeKeyword, $numericKeyword);
                })
                ->orWhereHas('invoices', function (Builder $invoiceQuery) use ($likeKeyword, $numericKeyword) {
                    $this->applyInvoiceKeywordSearch($invoiceQuery, $likeKeyword, $numericKeyword);
                })
                ->orWhereHas('user', function (Builder $userQuery) use ($likeKeyword, $numericKeyword) {
                    $userQuery->where(function (Builder $innerQuery) use ($likeKeyword, $numericKeyword) {
                        if ($numericKeyword !== null) {
                            $innerQuery->where('id', $numericKeyword);
                        }

                        $this->orWhereKeyword($innerQuery, 'nickname', $likeKeyword, $numericKeyword !== null);
                        $innerQuery->orWhere('email', 'like', $likeKeyword)
                            ->orWhere('phone', 'like', $likeKeyword)
                            ->orWhere('real_name', 'like', $likeKeyword);
                    });
                });
        });
    }

    private function applyInvoiceKeywordSearch(Builder $query, string $likeKeyword, ?int $numericKeyword): void
    {
        $query->where(function (Builder $innerQuery) use ($likeKeyword, $numericKeyword) {
            if ($numericKeyword !== null) {
                $innerQuery->where('id', $numericKeyword)
                    ->orWhere('service_id', $numericKeyword)
                    ->orWhere('order_id', $numericKeyword);
            }

            $this->orWhereKeyword($innerQuery, 'invoice_no', $likeKeyword, $numericKeyword !== null);
            $innerQuery->orWhere('product_spec_snapshot', 'like', $likeKeyword);
        });
    }

    private function orWhereKeyword(Builder $query, string $column, string $likeKeyword, bool $hasPreviousCondition): void
    {
        if ($hasPreviousCondition) {
            $query->orWhere($column, 'like', $likeKeyword);

            return;
        }

        $query->where($column, 'like', $likeKeyword);
    }

    private function extractNumericKeyword(string $keyword): ?int
    {
        $normalized = trim($keyword);
        if (preg_match('/^(?:id[:：#]?\s*)?(\d+)$/i', $normalized, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function resolveSnapshotMatchedServiceIds(string $keyword): array
    {
        $likeKeyword = '%'.$keyword.'%';
        $ids = collect();

        $ids = $ids->merge(DB::table('service_upstream_bindings')
            ->where(function ($query) use ($likeKeyword): void {
                $query->where('upstream_service_id', 'like', $likeKeyword)
                    ->orWhere('upstream_account_id', 'like', $likeKeyword)
                    ->orWhere('status_snapshot', 'like', $likeKeyword)
                    ->orWhere('runtime_snapshot_json', 'like', $likeKeyword)
                    ->orWhere('connection_snapshot_json', 'like', $likeKeyword);
            })
            ->limit(500)
            ->pluck('service_id'));

        if (SchemaMetadataCache::hasTable('service_runtime_snapshots')) {
            $ids = $ids->merge(DB::table('service_runtime_snapshots')
                ->where(function ($query) use ($likeKeyword): void {
                    $query->where('status_key', 'like', $likeKeyword)
                        ->orWhere('status_text', 'like', $likeKeyword)
                        ->orWhere('resource_json', 'like', $likeKeyword)
                        ->orWhere('metrics_json', 'like', $likeKeyword)
                        ->orWhere('snapshot_json', 'like', $likeKeyword);
                })
                ->limit(500)
                ->pluck('service_id'));
        }

        if (SchemaMetadataCache::hasTable('service_connection_snapshots')) {
            $ids = $ids->merge(DB::table('service_connection_snapshots')
                ->where(function ($query) use ($likeKeyword): void {
                    $query->where('hostname', 'like', $likeKeyword)
                        ->orWhere('ip_address', 'like', $likeKeyword)
                        ->orWhere('connection_json', 'like', $likeKeyword);
                })
                ->limit(500)
                ->pluck('service_id'));
        }

        return $ids
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function readConnectionSecret(array $provisionData): array
    {
        $payload = trim((string) ($provisionData['connection_secret'] ?? ''));
        if ($payload === '') {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($payload), true);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function bindingResolver(): PluginBindingResolver
    {
        return $this->bindingResolver ??= app(PluginBindingResolver::class);
    }

    private function resolvePrimaryInvoice(Service $service): ?Invoice
    {
        if ($service->invoice) {
            return $service->invoice;
        }

        return $service->invoices
            ->sortByDesc('id')
            ->first();
    }
}
