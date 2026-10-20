<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\PaymentStatus;
use App\Models\FinanceRefund;
use App\Models\Invoice;
use App\Models\Product;
use App\Support\Concerns\FiltersDateRange;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class InvoiceV2QueryService
{
    use FiltersDateRange;

    public function __construct(
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateAdminInvoices(array $filters): LengthAwarePaginator
    {
        return $this->invoices->adminList($filters, $this->pageSize($filters));
    }

    /**
     * @return array<string, mixed>
     */
    public function adminInvoiceDetail(int $id): array
    {
        return $this->invoices->adminDetail($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateClientInvoices(int $userId, array $filters): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->with($this->invoiceListRelations())
            ->withSum(
                // 退款投影（列表轻量口径）与 adminList 同源，预加载避免逐行 N+1。
                ['refunds' => fn ($refundQuery) => $refundQuery->where('status', FinanceRefund::STATUS_COMPLETED)],
                'amount'
            )
            ->where('user_id', $userId)
            ->orderByDesc('id');

        $this->applyClientFilters($query, $filters);

        $paginator = $query->paginate($this->pageSize($filters));
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Invoice $invoice): array => $this->invoices->adminListItem($invoice))
        );

        return $paginator;
    }

    public function findClientInvoice(int $userId, int $id): Invoice
    {
        return Invoice::query()
            ->with($this->invoiceDetailRelations())
            ->where('user_id', $userId)
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function clientInvoiceDetail(Invoice $invoice): array
    {
        return $this->invoices->clientDetail($invoice);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyClientFilters(Builder $query, array $filters): void
    {
        // status 可能是 HTTP 查询串原样透传的字符串（直调 service 或未走
        // Request 转型时），必须转 int 再与常量严格比较，否则「已退款」筛选
        // 的支付退款分支永远走不到（D1A-02）。
        $statusFilter = $filters['status'] ?? null;
        if ($statusFilter !== null && $statusFilter !== '' && (int) $statusFilter === InvoiceStatus::REFUNDED) {
            // 「有过退款」语义（退款标记批 2026-10-11）：已收口 REFUNDED、第三方原路
            // 退款、或名下存在已完成退款记录（含纯标记部分退款，账单仍 PAID）均命中。
            $query->where(function (Builder $builder): void {
                $builder->whereHas('payments', fn (Builder $paymentQuery): Builder => $paymentQuery->where('status', PaymentStatus::REFUNDED))
                    ->orWhere('status', InvoiceStatus::REFUNDED)
                    ->orWhereHas('refunds', fn (Builder $refundQuery): Builder => $refundQuery->where('status', FinanceRefund::STATUS_COMPLETED));
            });
        } elseif ($statusFilter !== null && $statusFilter !== '' && (int) $statusFilter === InvoiceStatus::PARTIAL_PAID) {
            // 派生展示态「部分已付」：库内仍为待支付，按已付金额介于 (0, 应付) 命中。
            $query->where('status', InvoiceStatus::UNPAID)
                ->where('paid_amount', '>', 0)
                ->whereColumn('paid_amount', '<', 'amount');
        } elseif ($statusFilter !== null && $statusFilter !== '') {
            $query->where('status', (int) $statusFilter);
        }

        $types = $this->normalizeInvoiceTypeFilters((string) ($filters['type'] ?? ''));
        if ($types !== []) {
            $query->whereIn('type', $types);
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder->where('invoice_no', 'like', "%{$keyword}%")
                    ->orWhereHas('order', function (Builder $orderQuery) use ($keyword): void {
                        $orderQuery->where('order_no', 'like', "%{$keyword}%");
                    })
                    ->orWhereHas('payments', function (Builder $paymentQuery) use ($keyword): void {
                        $paymentQuery->where('payment_no', 'like', "%{$keyword}%")
                            ->orWhere('trade_no', 'like', "%{$keyword}%");
                    });
            });
        }

        $this->applyDateFilter($query, $filters);
    }

    /**
     * @return list<string>
     */
    private function normalizeInvoiceTypeFilters(string $typeFilter): array
    {
        $types = [];

        foreach (explode(',', $typeFilter) as $type) {
            $normalized = trim($type);
            if ($normalized === '') {
                continue;
            }

            if (in_array($normalized, ['new', 'normal'], true)) {
                $types[] = InvoiceType::NEW_PURCHASE;
                $types[] = 'normal';

                continue;
            }

            $types[] = $normalized;
        }

        return array_values(array_unique($types));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function pageSize(array $filters): int
    {
        return max(1, min((int) ($filters['page_size'] ?? 20), 100));
    }

    /**
     * 列表关系集：不加载仅详情使用的 items（buildInvoiceItems 只在详情路径调用）。
     *
     * @return array<int, string>
     */
    private function invoiceListRelations(): array
    {
        return array_values(array_filter(
            $this->invoiceDetailRelations(),
            static fn (string $relation): bool => $relation !== 'items'
        ));
    }

    /**
     * 详情关系集。
     *
     * @return array<int, string>
     */
    private function invoiceDetailRelations(): array
    {
        $productColumns = implode(',', $this->productProjectionColumns());

        return [
            'user:id,email,nickname,phone',
            'order:id,order_no,status,type,service_id,paid_at,product_id,billing_cycle,amount,discount,paid_amount,quantity,product_spec_snapshot,product_type_snapshot,config_snapshot,config_pricing_snapshot',
            'order.product:'.$productColumns,
            'order.product.productGroup:id,second_product_group_id,name',
            'order.product.productGroup.secondProductGroup:id,first_product_group_id,name',
            'order.product.productGroup.secondProductGroup.firstProductGroup:id,code,name',
            'product:'.$productColumns,
            'product.productGroup:id,second_product_group_id,name',
            'product.productGroup.secondProductGroup:id,first_product_group_id,name',
            'product.productGroup.secondProductGroup.firstProductGroup:id,code,name',
            'service:id,name,status,expires_at',
            'payments',
            'items',
        ];
    }

    /**
     * @return list<string>
     */
    private function productProjectionColumns(): array
    {
        return array_values(array_unique(array_merge(
            ['id'],
            Product::optionalSelectColumns([
                'product_type',
                'service_type_code',
                'product_group_id',
                'custom_display_name',
                'remark',
                'config_options',
                'purchase_requires',
            ])
        )));
    }
}
