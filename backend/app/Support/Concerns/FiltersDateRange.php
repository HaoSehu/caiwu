<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

trait FiltersDateRange
{
    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyDateFilter(Builder $query, array $filters, string $column = 'created_at'): void
    {
        $start = trim((string) ($filters['start_date'] ?? ''));
        $end = trim((string) ($filters['end_date'] ?? ''));

        if ($start === '' && $end === '') {
            return;
        }

        if ($start !== '' && $end !== '') {
            $query->whereBetween($column, [
                CarbonImmutable::parse($start)->startOfDay(),
                CarbonImmutable::parse($end)->endOfDay(),
            ]);

            return;
        }

        if ($start !== '') {
            $query->where($column, '>=', CarbonImmutable::parse($start)->startOfDay());

            return;
        }

        $query->where($column, '<=', CarbonImmutable::parse($end)->endOfDay());
    }
}
