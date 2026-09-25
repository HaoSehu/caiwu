<?php

namespace App\Services\Finance;

use App\Http\Resources\Finance\FinanceLedgerResource;
use App\Models\User;

class ClientFinanceQueryService
{
    public function __construct(
        private readonly FinanceLedgerQueryService $financeLedgerQueryService,
    ) {}

    public function paginateBalanceLogs(User $user, array $filters, int $perPage): array
    {
        $ledgerPaginator = $this->financeLedgerQueryService->paginatorForUser($user, $this->normalizeLedgerFilters($filters), $perPage);
        $items = $ledgerPaginator->items();
        $resolved = FinanceLedgerResource::collection($items)->resolve();

        // 余额变动列表保留账户流水原始 event_type，仅标签做归一化展示。
        foreach ($resolved as $index => &$row) {
            $rawEventType = trim((string) ($items[$index]->event_type ?? ''));
            if ($rawEventType !== '') {
                $row['event_type'] = $rawEventType;
            }
        }
        unset($row);

        return [
            'list' => $resolved,
            'total' => $ledgerPaginator->total(),
            'page' => $ledgerPaginator->currentPage(),
            'page_size' => $ledgerPaginator->perPage(),
        ];
    }

    public function balanceLogSummary(User $user, array $filters): array
    {
        // 直接透传内层 summary：其 30s 缓存命中时会重读用户余额（cash_balance 不入缓存载荷），
        // 与 /finance/ledger/summary 保持同一口径。此前的 120s 外层整包缓存会把余额冻住，
        // 造成充值后两分钟内两个汇总端点余额不一致。
        return $this->financeLedgerQueryService->summaryForClient($user, $this->normalizeLedgerFilters($filters));
    }

    private function normalizeLedgerFilters(array $filters): array
    {
        $eventType = trim((string) ($filters['event_type'] ?? ''));
        $tab = trim((string) ($filters['tab'] ?? ''));
        $normalizedTab = match ($tab) {
            'all' => null,
            'invoices', 'balance', 'recharge', 'adjustment' => $tab,
            default => null,
        };

        return array_filter([
            'tab' => $normalizedTab ?? match ($eventType) {
                'recharge' => 'recharge',
                'consume', 'refund' => 'invoices',
                'adjust', 'admin_deduct' => 'adjustment',
                default => null,
            },
            'event_type' => match ($eventType) {
                'consume' => 'invoice_payment',
                'refund' => 'invoice_refund',
                'adjust' => 'system_adjustment',
                'admin_deduct' => 'manual_deduction',
                default => $eventType !== '' ? $eventType : null,
            },
            'start_date' => $filters['start_date'] ?? null,
            'end_date' => $filters['end_date'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }
}
