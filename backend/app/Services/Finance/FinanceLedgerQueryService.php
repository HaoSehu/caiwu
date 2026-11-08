<?php

namespace App\Services\Finance;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceType;
use App\Models\FinanceLedgerEntry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\Concerns\FiltersDateRange;
use App\Support\LikeEscape;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FinanceLedgerQueryService
{
    use FiltersDateRange;

    private const SUMMARY_CACHE_TTL_SECONDS = 30;

    public function paginatorForUser(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->buildQuery(array_merge($filters, ['user_id' => (int) $user->id]))->paginate($perPage);
    }

    public function summaryForClient(User $user, array $filters): array
    {
        return $this->summary(array_merge($filters, ['user_id' => (int) $user->id]), $user);
    }

    private function summary(array $filters, ?User $boundUser = null): array
    {
        $cacheKey = $this->buildSummaryCacheKey($filters);
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && $cached !== []) {
            $balance = $boundUser?->balance;
            if ($balance === null && ! empty($filters['user_id'])) {
                $balance = optional(User::query()->find((int) $filters['user_id']))->balance;
            }
            $cached['cash_balance'] = number_format((float) ($balance ?? 0), 2, '.', '');
            unset($cached['balance']);

            return $cached;
        }

        $baseQuery = FinanceLedgerEntry::query()
            ->where('account_type', 'cash');

        $this->applyFilters($baseQuery, $filters);

        // 与列表口径保持一致：0 元结清台账是「账单渠道证据」、不是资金流水，
        // 不参与 total_count / total_in 等聚合，避免汇总里出现无意义的 0 元行。
        $this->excludeZeroAmountInvoiceEvidence($baseQuery);

        $normalizedEventSql = $this->normalizedEventTypeSql('finance_ledger_entries.event_type');
        $summaryRow = (clone $baseQuery)
            ->selectRaw('COALESCE(SUM(CASE WHEN change_amount > 0 THEN change_amount ELSE 0 END), 0) as total_in')
            ->selectRaw('COALESCE(SUM(CASE WHEN change_amount < 0 THEN ABS(change_amount) ELSE 0 END), 0) as total_out')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN {$normalizedEventSql} = ? THEN change_amount ELSE 0 END), 0) as recharge_in",
                array_merge($this->normalizedEventTypeBindings(), [FinanceLedgerEventType::RECHARGE])
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN {$normalizedEventSql} = ? THEN ABS(change_amount) ELSE 0 END), 0) as invoice_payment_out",
                array_merge($this->normalizedEventTypeBindings(), [FinanceLedgerEventType::INVOICE_PAYMENT])
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN {$normalizedEventSql} = ? THEN change_amount ELSE 0 END), 0) as refund_in",
                array_merge($this->normalizedEventTypeBindings(), [FinanceLedgerEventType::INVOICE_REFUND])
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN {$normalizedEventSql} IN (?, ?) THEN ABS(change_amount) ELSE 0 END), 0) as manual_adjust_out",
                array_merge(
                    $this->normalizedEventTypeBindings(),
                    [
                        FinanceLedgerEventType::MANUAL_DEDUCTION,
                        FinanceLedgerEventType::SYSTEM_ADJUSTMENT,
                    ]
                )
            )
            ->first();

        $invoiceQuery = Invoice::query();
        if (! empty($filters['user_id'])) {
            $invoiceQuery->where('user_id', (int) $filters['user_id']);
        }
        $this->applyDateFilter($invoiceQuery, $filters);
        // 未付口径统一走 InvoiceUnpaidAggregate（此前此处用 IN (0, 3)，3 为已收敛死值）
        $invoiceSummary = InvoiceUnpaidAggregate::applyUnpaidSelects($invoiceQuery)
            ->selectRaw('COUNT(*) as total_invoices')
            ->first();

        $recharge30Days = (clone $baseQuery)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereRaw(
                $this->normalizedEventTypeSql('finance_ledger_entries.event_type').' = ?',
                array_merge($this->normalizedEventTypeBindings(), [FinanceLedgerEventType::RECHARGE])
            )
            ->sum('change_amount');

        $refund30Days = (clone $baseQuery)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereRaw(
                $this->normalizedEventTypeSql('finance_ledger_entries.event_type').' = ?',
                array_merge($this->normalizedEventTypeBindings(), [FinanceLedgerEventType::INVOICE_REFUND])
            )
            ->sum('change_amount');

        $balance = $boundUser?->balance;
        if ($balance === null && ! empty($filters['user_id'])) {
            $balance = optional(User::query()->find((int) $filters['user_id']))->balance;
        }

        $result = [
            'cash_balance' => number_format((float) ($balance ?? 0), 2, '.', ''),
            'total_in' => number_format((float) ($summaryRow?->total_in ?? 0), 2, '.', ''),
            'total_out' => number_format((float) ($summaryRow?->total_out ?? 0), 2, '.', ''),
            'total_count' => (int) ($summaryRow?->total_count ?? 0),
            'recharge_in' => number_format((float) ($summaryRow?->recharge_in ?? 0), 2, '.', ''),
            'invoice_payment_out' => number_format((float) ($summaryRow?->invoice_payment_out ?? 0), 2, '.', ''),
            'refund_in' => number_format((float) ($summaryRow?->refund_in ?? 0), 2, '.', ''),
            'manual_adjust_out' => number_format((float) ($summaryRow?->manual_adjust_out ?? 0), 2, '.', ''),
            'unpaid_amount' => number_format((float) ($invoiceSummary?->unpaid_amount ?? 0), 2, '.', ''),
            'unpaid_count' => (int) ($invoiceSummary?->unpaid_count ?? 0),
            'total_invoices' => (int) ($invoiceSummary?->total_invoices ?? 0),
            'recent_30d_recharge' => number_format((float) $recharge30Days, 2, '.', ''),
            'recent_30d_refund' => number_format((float) $refund30Days, 2, '.', ''),
        ];

        Cache::put($cacheKey, $result, now()->addSeconds(self::SUMMARY_CACHE_TTL_SECONDS));

        return $result;
    }

    private function buildSummaryCacheKey(array $filters): string
    {
        $userId = (int) ($filters['user_id'] ?? 0);
        // 指纹必须覆盖 summary 聚合消费的全部过滤字段（user_id 在键前缀），
        // 否则 30s TTL 内不同筛选会共享同一份汇总金额。
        $fingerprint = md5((string) json_encode([
            'tab' => $filters['tab'] ?? null,
            'event_type' => $filters['event_type'] ?? null,
            'status' => $filters['status'] ?? null,
            'direction' => $filters['direction'] ?? null,
            'start_date' => $filters['start_date'] ?? '',
            'end_date' => $filters['end_date'] ?? '',
            'service_id' => $filters['service_id'] ?? null,
            'invoice_no' => $filters['invoice_no'] ?? '',
            'payment_no' => $filters['payment_no'] ?? '',
            'keyword' => $filters['keyword'] ?? '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return 'finance:ledger:summary:'.$userId.':'.$fingerprint;
    }

    /**
     * @param  bool  $forList  列表/汇总为 true（排除 0 元渠道证据行）；按 id 取详情为 false
     */
    private function buildQuery(array $filters, bool $withRelations = true, bool $forList = true): Builder
    {
        $query = FinanceLedgerEntry::query()
            ->where('account_type', 'cash')
            ->select('finance_ledger_entries.*')
            ->selectRaw(
                $this->normalizedEventTypeSql('finance_ledger_entries.event_type').' as normalized_event_type',
                $this->normalizedEventTypeBindings()
            );

        if ($withRelations) {
            $query->with(['user']);
        }

        $this->applyFilters($query, $filters);

        // 0 元结清台账是「账单渠道证据」、不是资金流水：它只用于说明该账单由哪种
        // 渠道结清，渲染到余额流水列表会变成无意义的「扣款 ¥0.00」，故排除。
        //
        // 只在列表/汇总路径排除：详情接口（detailForClient/detailForAdmin）也走本方法，
        // 若在此一并过滤，按 id 直查 0 元证据行会变成 404，运维与用户从旧链接进来就查不到记录。
        // 详情是「按 id 精确取一条」，不会出现列表里堆满 ¥0.00 行的问题，无需排除。
        if ($forList) {
            $this->excludeZeroAmountInvoiceEvidence($query);
        }

        $rows = $query->orderByDesc('created_at')->orderByDesc('id');

        if ($withRelations) {
            $this->attachContextResolvers($rows);
        }

        return $rows;
    }

    /**
     * 排除 0 元结清台账行：它们是「账单渠道证据」（记录账单由哪个渠道结清），
     * 不是真正的资金变动流水，列表与汇总都不应包含。
     *
     * 语义为「不是（event_type = invoice_payment 且 change_amount = 0）」：
     * 其余事件类型即使金额为 0（理论上不应出现）也不受影响。
     * 0 元行只会以规范值 invoice_payment 写入，历史别名（consume 等）无需覆盖。
     */
    private function excludeZeroAmountInvoiceEvidence(Builder $query): void
    {
        $query->where(function (Builder $inner) {
            $inner->whereRaw(
                'NOT (finance_ledger_entries.event_type = ? AND finance_ledger_entries.change_amount = 0)',
                [FinanceLedgerEventType::INVOICE_PAYMENT]
            );
        });
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['event_type'])) {
            $query->whereRaw(
                $this->normalizedEventTypeSql('finance_ledger_entries.event_type').' = ?',
                array_merge($this->normalizedEventTypeBindings(), [$filters['event_type']])
            );
        }

        if (! empty($filters['direction'])) {
            if ($filters['direction'] === FinanceLedgerEventType::DIRECTION_IN) {
                $query->where('change_amount', '>', 0);
            } elseif ($filters['direction'] === FinanceLedgerEventType::DIRECTION_OUT) {
                $query->where('change_amount', '<', 0);
            }
        }

        $this->applyDateFilter($query, $filters);

        if (! empty($filters['service_id'])) {
            $this->applyServiceFilter($query, (int) $filters['service_id']);
        }

        if (! empty($filters['tab'])) {
            match ($filters['tab']) {
                'invoices' => $query->whereRaw(
                    $this->normalizedEventTypeSql('finance_ledger_entries.event_type').' IN (?, ?)',
                    array_merge(
                        $this->normalizedEventTypeBindings(),
                        [
                            FinanceLedgerEventType::INVOICE_PAYMENT,
                            FinanceLedgerEventType::INVOICE_REFUND,
                        ]
                    )
                ),
                'balance' => $query->whereRaw(
                    $this->normalizedEventTypeSql('finance_ledger_entries.event_type').' IN (?, ?, ?, ?, ?)',
                    array_merge(
                        $this->normalizedEventTypeBindings(),
                        [
                            FinanceLedgerEventType::RECHARGE,
                            FinanceLedgerEventType::MANUAL_RECHARGE,
                            FinanceLedgerEventType::MANUAL_DEDUCTION,
                            FinanceLedgerEventType::SYSTEM_ADJUSTMENT,
                            FinanceLedgerEventType::REFERRAL_CREDIT_CASH,
                        ]
                    )
                ),
                'recharge' => $query->whereRaw(
                    $this->normalizedEventTypeSql('finance_ledger_entries.event_type').' IN (?, ?)',
                    array_merge(
                        $this->normalizedEventTypeBindings(),
                        [
                            FinanceLedgerEventType::RECHARGE,
                            FinanceLedgerEventType::MANUAL_RECHARGE,
                        ]
                    )
                ),
                'adjustment' => $query->whereRaw(
                    $this->normalizedEventTypeSql('finance_ledger_entries.event_type').' IN (?, ?)',
                    array_merge(
                        $this->normalizedEventTypeBindings(),
                        [
                            FinanceLedgerEventType::MANUAL_DEDUCTION,
                            FinanceLedgerEventType::SYSTEM_ADJUSTMENT,
                        ]
                    )
                ),
                default => null,
            };
        }

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $status = (int) $filters['status'];
            // 关联子查询代替 pluck 全表拉取：台账过滤此前把整张 invoices/payments 的
            // 匹配 id 拉入 PHP 再 whereIn，行数增长后内存与查询包体积同步放大，且
            // whereIn 大列表会让 MySQL range 优化失效。子查询命中
            // finance_ledger_entries(source_type, source_id) 关联索引与源表主键。
            $query->where(function (Builder $builder) use ($status) {
                $builder->where(function (Builder $inner) use ($status) {
                    $inner->where('source_type', 'invoice')
                        ->whereExists(fn ($q) => $q->selectRaw(1)
                            ->from('invoices')
                            ->whereColumn('invoices.id', 'finance_ledger_entries.source_id')
                            ->where('invoices.status', $status));
                })->orWhere(function (Builder $inner) use ($status) {
                    $inner->where('source_type', 'payment')
                        ->whereExists(fn ($q) => $q->selectRaw(1)
                            ->from('payments')
                            ->whereColumn('payments.id', 'finance_ledger_entries.source_id')
                            ->where('payments.status', $status));
                });
            });
        }

        if (! empty($filters['invoice_no'])) {
            $keyword = trim((string) $filters['invoice_no']);
            $query->where(function (Builder $builder) use ($keyword) {
                $builder->where(function (Builder $inner) use ($keyword) {
                    $inner->where('source_type', 'invoice')
                        ->whereExists(fn ($q) => $q->selectRaw(1)
                            ->from('invoices')
                            ->whereColumn('invoices.id', 'finance_ledger_entries.source_id')
                            ->where('invoices.invoice_no', 'like', LikeEscape::contains($keyword)));
                })->orWhere(function (Builder $inner) use ($keyword) {
                    // 支付单归属匹配账单：payments.invoice_id ∈ invoices(invoice_no like)
                    $inner->where('source_type', 'payment')
                        ->whereExists(fn ($q) => $q->selectRaw(1)
                            ->from('payments')
                            ->whereColumn('payments.id', 'finance_ledger_entries.source_id')
                            ->whereExists(fn ($pi) => $pi->selectRaw(1)
                                ->from('invoices')
                                ->whereColumn('invoices.id', 'payments.invoice_id')
                                ->where('invoices.invoice_no', 'like', LikeEscape::contains($keyword))));
                });
            });
        }

        if (! empty($filters['payment_no'])) {
            $keyword = trim((string) $filters['payment_no']);
            $query->where(function (Builder $builder) use ($keyword) {
                $builder->where(function (Builder $inner) use ($keyword) {
                    $inner->where('source_type', 'payment')
                        ->whereExists(fn ($q) => $q->selectRaw(1)
                            ->from('payments')
                            ->whereColumn('payments.id', 'finance_ledger_entries.source_id')
                            ->where('payments.payment_no', 'like', LikeEscape::contains($keyword)));
                })->orWhere(function (Builder $inner) use ($keyword) {
                    // 支付单号命中时，其关联账单也纳入结果：invoices.id ∈ payments(payment_no like).invoice_id
                    $inner->where('source_type', 'invoice')
                        ->whereExists(fn ($q) => $q->selectRaw(1)
                            ->from('invoices')
                            ->whereColumn('invoices.id', 'finance_ledger_entries.source_id')
                            ->whereExists(fn ($pi) => $pi->selectRaw(1)
                                ->from('payments')
                                ->whereColumn('payments.invoice_id', 'invoices.id')
                                ->where('payments.payment_no', 'like', LikeEscape::contains($keyword))));
                });
            });
        }

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);

            // digital exact match → user_id
            if (ctype_digit($keyword)) {
                $query->where('user_id', (int) $keyword);
            } else {
                // 单号关键词双向匹配：账单号命中其账单与关联支付单；
                // 支付单号命中其支付单与关联账单。
                $query->where(function (Builder $builder) use ($keyword) {
                    $builder->where(function (Builder $inner) use ($keyword) {
                        $inner->where('source_type', 'invoice')
                            ->whereExists(fn ($q) => $q->selectRaw(1)
                                ->from('invoices')
                                ->whereColumn('invoices.id', 'finance_ledger_entries.source_id')
                                ->where(function (QueryBuilder $invoiceMatch) use ($keyword) {
                                    $invoiceMatch->where('invoices.invoice_no', 'like', LikeEscape::contains($keyword))
                                        ->orWhereExists(fn ($pi) => $pi->selectRaw(1)
                                            ->from('payments')
                                            ->whereColumn('payments.invoice_id', 'invoices.id')
                                            ->where('payments.payment_no', 'like', LikeEscape::contains($keyword)));
                                }));
                    })->orWhere(function (Builder $inner) use ($keyword) {
                        $inner->where('source_type', 'payment')
                            ->whereExists(fn ($q) => $q->selectRaw(1)
                                ->from('payments')
                                ->whereColumn('payments.id', 'finance_ledger_entries.source_id')
                                ->where(function (QueryBuilder $paymentMatch) use ($keyword) {
                                    $paymentMatch->where('payments.payment_no', 'like', LikeEscape::contains($keyword))
                                        ->orWhereExists(fn ($iv) => $iv->selectRaw(1)
                                            ->from('invoices')
                                            ->whereColumn('invoices.id', 'payments.invoice_id')
                                            ->where('invoices.invoice_no', 'like', LikeEscape::contains($keyword)));
                                }));
                    });
                });
            }
        }
    }

    private function applyServiceFilter(Builder $query, int $serviceId): void
    {
        if ($serviceId <= 0) {
            return;
        }

        // 关联子查询版：账单命中 = 自身 service_id 命中或关联订单 service_id 命中；
        // 支付单命中 = 关联账单命中（同样两层判断）。
        $query->where(function (Builder $builder) use ($serviceId) {
            $builder->where(function (Builder $inner) use ($serviceId) {
                $inner->where('source_type', 'invoice')
                    ->whereExists(fn ($q) => $q->selectRaw(1)
                        ->from('invoices')
                        ->whereColumn('invoices.id', 'finance_ledger_entries.source_id')
                        ->where(function (QueryBuilder $invoiceMatch) use ($serviceId) {
                            $invoiceMatch->where('invoices.service_id', $serviceId)
                                ->orWhereExists(fn ($oi) => $oi->selectRaw(1)
                                    ->from('orders')
                                    ->whereColumn('orders.id', 'invoices.order_id')
                                    ->where('orders.service_id', $serviceId));
                        }));
            })->orWhere(function (Builder $inner) use ($serviceId) {
                $inner->where('source_type', 'payment')
                    ->whereExists(fn ($q) => $q->selectRaw(1)
                        ->from('payments')
                        ->whereColumn('payments.id', 'finance_ledger_entries.source_id')
                        ->whereExists(fn ($iv) => $iv->selectRaw(1)
                            ->from('invoices')
                            ->whereColumn('invoices.id', 'payments.invoice_id')
                            ->where(function (QueryBuilder $invoiceMatch) use ($serviceId) {
                                $invoiceMatch->where('invoices.service_id', $serviceId)
                                    ->orWhereExists(fn ($oi) => $oi->selectRaw(1)
                                        ->from('orders')
                                        ->whereColumn('orders.id', 'invoices.order_id')
                                        ->where('orders.service_id', $serviceId));
                            })));
            });
        });
    }

    private function attachContextResolvers(Builder $query): void
    {
        $query->withCasts(['normalized_event_type' => 'string']);
        $query->afterQuery(function ($records) {
            $isCollection = $records instanceof Collection;
            $items = $isCollection ? $records : collect($records);
            if ($items->isEmpty()) {
                return $records;
            }

            $invoiceIds = $items
                ->filter(fn ($item) => (string) ($item->source_type ?? '') === 'invoice')
                ->pluck('source_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $paymentIds = $items
                ->filter(fn ($item) => (string) ($item->source_type ?? '') === 'payment')
                ->pluck('source_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $paymentMap = Payment::query()
                ->whereIn('id', array_unique($paymentIds))
                ->get()
                ->keyBy('id');

            // 支付来源流水（充值流水全部属于此类）的关联账单一并并入预取集合，
            // 一次 whereIn 建好 map；否则循环内逐条 find() 会退化成每条流水 4 次查询的 N+1。
            $invoiceIds = array_merge(
                $invoiceIds,
                $paymentMap->pluck('invoice_id')->filter()->map(fn ($id) => (int) $id)->all()
            );

            $invoiceMap = Invoice::query()
                ->with([
                    // 渠道名要走回调证据（历史支付单的 gateway_key 为空），预取避免逐行回查。
                    'payments.callbacks:payment_id,callback_type,payload_json',
                    'service:id,name,domain,status,expires_at,provision_data',
                    'order:id,order_no,type,service_id,billing_cycle,quantity,remark,operator,trace_id,product_spec_snapshot',
                ])
                ->whereIn('id', array_unique($invoiceIds))
                ->get()
                ->map(function (Invoice $invoice) {
                    $invoice->type_label = InvoiceType::label((string) $invoice->type);

                    return $invoice;
                })
                ->keyBy('id');

            foreach ($items as $item) {
                $invoice = null;
                $payment = null;

                if ((string) ($item->source_type ?? '') === 'invoice') {
                    $invoice = $invoiceMap->get((int) ($item->source_id ?? 0));
                    $payment = $invoice?->payments?->first();
                } elseif ((string) ($item->source_type ?? '') === 'payment') {
                    $payment = $paymentMap->get((int) ($item->source_id ?? 0));
                    $invoice = $payment?->invoice_id ? $invoiceMap->get((int) $payment->invoice_id) : null;
                }

                $item->setRelation('invoice', $invoice);
                $item->setRelation('payment', $payment);
            }

            return $isCollection ? $items : $items->all();
        });
    }

    private function normalizedEventTypeSql(string $column): string
    {
        return "CASE
            WHEN {$column} = 'consume' THEN ?
            WHEN {$column} = 'refund' THEN ?
            WHEN {$column} IN ('admin_deduct', 'deduct') THEN ?
            WHEN {$column} = 'adjust' THEN ?
            WHEN {$column} = 'referral_withdraw_approved' THEN ?
            ELSE {$column}
        END";
    }

    private function normalizedEventTypeBindings(): array
    {
        return [
            FinanceLedgerEventType::INVOICE_PAYMENT,
            FinanceLedgerEventType::INVOICE_REFUND,
            FinanceLedgerEventType::MANUAL_DEDUCTION,
            FinanceLedgerEventType::SYSTEM_ADJUSTMENT,
            FinanceLedgerEventType::REFERRAL_CREDIT_CASH,
        ];
    }
}
