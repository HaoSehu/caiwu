<?php

declare(strict_types=1);

namespace App\Services\Provisioning;

use App\Constants\ServiceStatus;
use App\Models\Product;
use App\Models\Service;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Support\ServiceHostname;
use Illuminate\Database\Eloquent\Builder;

class AdminServiceListService
{
    public function __construct(
        private readonly ?ProductDisplayNameResolver $productDisplayNameResolver = null,
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
                'product:id,product_group_id,product_type,config_options,purchase_requires',
                'product.categoryMapping:id,parent_group_id,product_type,name',
                'order:id,order_no,status,paid_at',
                'invoice:id,invoice_no,service_id,order_id,product_spec_snapshot,status,paid_at',
                'invoices:id,invoice_no,service_id,order_id,product_spec_snapshot,status,paid_at',
            ]);

        // 关键词搜索：服务名、域名、主机ID、IP、产品名、订单号
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword) {
                $builder->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('domain', 'like', '%'.$keyword.'%')
                    ->orWhere('provision_data->requested_host', 'like', '%'.$keyword.'%')
                    ->orWhere('provision_data->upstream_host_id', $keyword)
                    ->orWhere('provision_data->dedicated_ip', 'like', '%'.$keyword.'%')
                    ->orWhereHas('product', function (Builder $productQuery) use ($keyword) {
                        $productQuery->where('name', 'like', '%'.$keyword.'%');
                    })
                    ->orWhereHas('order', function (Builder $orderQuery) use ($keyword) {
                        $orderQuery->where('order_no', 'like', '%'.$keyword.'%');
                    })
                    ->orWhereHas('invoice', function (Builder $invoiceQuery) use ($keyword) {
                        $invoiceQuery->where('invoice_no', 'like', '%'.$keyword.'%')
                            ->orWhere('product_spec_snapshot', 'like', '%'.$keyword.'%');
                    })
                    ->orWhereHas('invoices', function (Builder $invoiceQuery) use ($keyword) {
                        $invoiceQuery->where('invoice_no', 'like', '%'.$keyword.'%')
                            ->orWhere('product_spec_snapshot', 'like', '%'.$keyword.'%');
                    })
                    ->orWhereHas('user', function (Builder $userQuery) use ($keyword) {
                        $userQuery->where('nickname', 'like', '%'.$keyword.'%')
                            ->orWhere('email', 'like', '%'.$keyword.'%')
                            ->orWhere('phone', 'like', '%'.$keyword.'%');
                    });
            });
        }

        // 状态筛选
        $status = $filters['status'] ?? '';
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }

        $paginator = $query->orderByDesc('id')->paginate($pageSize, ['*'], 'page', $page);

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
        $provisionData = (array) ($service->provision_data ?? []);
        $statusLabels = ServiceStatus::$labels ?? [];
        $invoice = $this->resolvePrimaryInvoice($service);
        $order = $invoice ? null : $service->order;

        return [
            'id' => $service->id,
            'name' => (string) $service->name,
            'product_display_name' => $this->resolveProductDisplayName($service),
            'domain' => ServiceHostname::resolveDisplayDomain($service, $provisionData),
            'custom_hostname' => ServiceHostname::custom($provisionData),
            'has_custom_hostname' => ServiceHostname::hasCustom($provisionData),
            'status' => (int) $service->status,
            'status_label' => $statusLabels[$service->status] ?? (string) $service->status,
            'billing_cycle' => (string) $service->billing_cycle,
            'amount' => number_format((float) $service->amount, 2, '.', ''),
            'expires_at' => $service->expires_at?->format('Y-m-d H:i:s'),
            'created_at' => $service->created_at?->format('Y-m-d H:i:s'),
            'auto_renew' => (bool) $service->auto_renew,
            'upstream_host_id' => (int) (($provisionData['upstream_host_id'] ?? 0) ?: 0),
            'dedicated_ip' => (string) ($provisionData['dedicated_ip'] ?? ''),
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

    private function resolveProductDisplayName(Service $service): string
    {
        $invoiceDisplayName = trim((string) ($this->resolvePrimaryInvoice($service)?->product_spec_snapshot ?? ''));
        if ($invoiceDisplayName !== '') {
            return $invoiceDisplayName;
        }

        $orderDisplayName = trim((string) ($service->order?->display_product_name ?? ''));
        if ($orderDisplayName !== '') {
            return $orderDisplayName;
        }

        if ($service->product instanceof Product) {
            $resolved = $this->resolveProductDisplayNameResolver()->resolveForProduct(
                $service->product,
                (array) ($service->order?->config_snapshot ?? [])
            );

            return trim((string) ($resolved['product_display_name'] ?? ''));
        }

        return '';
    }

    private function resolveProductDisplayNameResolver(): ProductDisplayNameResolver
    {
        return $this->productDisplayNameResolver ?? new ProductDisplayNameResolver;
    }

    private function resolvePrimaryInvoice(Service $service): ?\App\Models\Invoice
    {
        if ($service->invoice) {
            return $service->invoice;
        }

        return $service->invoices
            ->sortByDesc('id')
            ->first();
    }
}
