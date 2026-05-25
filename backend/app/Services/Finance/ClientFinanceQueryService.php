<?php

namespace App\Services\Finance;

use App\Http\Resources\Finance\BalanceLogResource;
use App\Models\AccountTransaction;
use App\Models\BalanceLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ClientFinanceQueryService
{
    private const SUMMARY_CACHE_TTL_SECONDS = 30;

    public function __construct(
        private readonly FinanceLedgerQueryService $financeLedgerQueryService,
    ) {}

    public function paginateBalanceLogs(User $user, array $filters, int $perPage): array
    {
        if (Schema::hasTable('account_transactions')) {
            $ledgerPaginator = $this->financeLedgerQueryService->paginatorForUser($user, $this->normalizeLedgerFilters($filters), $perPage);

            return [
                'list' => BalanceLogResource::collection($ledgerPaginator->items())->resolve(),
                'total' => $ledgerPaginator->total(),
                'page' => $ledgerPaginator->currentPage(),
                'page_size' => $ledgerPaginator->perPage(),
            ];
        }

        $paginator = $this->buildBalanceLogQuery($user, $filters)->paginate($perPage);

        return [
            'list' => BalanceLogResource::collection($paginator->items())->resolve(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    public function balanceLogSummary(User $user, array $filters): array
    {
        $cacheKey = 'client_finance:balance_summary:'.$user->id.':'.$this->cacheFingerprint($filters);

        return Cache::remember($cacheKey, now()->addSeconds(self::SUMMARY_CACHE_TTL_SECONDS), function () use ($user, $filters) {
            if (Schema::hasTable('account_transactions')) {
                return $this->financeLedgerQueryService->summaryForClient($user, $this->normalizeLedgerFilters($filters));
            }

            if (User::accountTableAvailable()) {
                $user->loadMissing('account');
            }

            $summary = $this->buildBalanceLogQuery($user, $filters)
                ->selectRaw('COALESCE(SUM(CASE WHEN change_amount > 0 THEN change_amount ELSE 0 END), 0) as total_in')
                ->selectRaw('COALESCE(SUM(CASE WHEN change_amount < 0 THEN ABS(change_amount) ELSE 0 END), 0) as total_out')
                ->first();

            return [
                'balance' => (float) $user->balance,
                'total_in' => (float) ($summary?->total_in ?? 0),
                'total_out' => (float) ($summary?->total_out ?? 0),
            ];
        });
    }

    private function buildBalanceLogQuery(User $user, array $filters): Builder
    {
        $dateRange = ! empty($filters['date_range'])
            ? [
                $filters['date_range'][0],
                $filters['date_range'][1].' 23:59:59',
            ]
            : null;

        if (Schema::hasTable('account_transactions')) {
            return AccountTransaction::query()
                ->where('user_id', $user->id)
                ->where('account_type', 'cash')
                ->when(
                    array_key_exists('event_type', $filters),
                    fn (Builder $query) => $query->where('event_type', $filters['event_type'])
                )
                ->when(
                    $dateRange !== null,
                    fn (Builder $query) => $query->whereBetween('created_at', $dateRange)
                )
                ->orderByDesc('id');
        }

        return BalanceLog::query()
            ->where('user_id', $user->id)
            ->when(
                array_key_exists('event_type', $filters),
                fn (Builder $query) => $query->where('event_type', $filters['event_type'])
            )
            ->when(
                $dateRange !== null,
                fn (Builder $query) => $query->whereBetween('created_at', $dateRange)
            )
            ->orderByDesc('id');
    }

    private function cacheFingerprint(array $filters): string
    {
        return md5((string) json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function normalizeLedgerFilters(array $filters): array
    {
        $eventType = trim((string) ($filters['event_type'] ?? ''));

        return array_filter([
            'tab' => match ($eventType) {
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
            'date_range' => $filters['date_range'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }
}
