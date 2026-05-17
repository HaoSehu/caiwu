<?php

declare(strict_types=1);

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseEngineeringService
{
    /**
     * @return list<string>
     */
    public function baseTables(): array
    {
        return collect(DB::select("
            SELECT table_name AS table_name
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_type = 'BASE TABLE'
            ORDER BY table_name
        "))
            ->map(fn (object $row) => (string) $row->table_name)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function auditCore(): array
    {
        $tables = $this->baseTables();

        return [
            'database' => (string) DB::getDatabaseName(),
            'table_count' => count($tables),
            'tables' => $tables,
            'foreign_keys' => $this->foreignKeys(),
            'zero_reference_metrics' => $this->zeroReferenceMetrics(),
            'orphan_metrics' => $this->orphanMetrics(),
            'trace_id_metrics' => $this->traceIdMetrics(),
            'table_size_metrics' => $this->tableSizeMetrics(),
            'index_metrics' => $this->indexMetrics(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function normalizeCoreRelations(): array
    {
        $summary = [
            'services_order_id_zero_to_null' => 0,
            'services_invoice_id_zero_to_null' => 0,
            'payments_order_id_zero_to_null' => 0,
            'payments_invoice_id_zero_to_null' => 0,
            'invoices_order_id_zero_to_null' => 0,
            'invoice_items_deleted_orphans' => 0,
            'payment_callbacks_deleted_orphans' => 0,
            'user_accounts_deleted_orphans' => 0,
            'ticket_replies_deleted_orphans' => 0,
            'services_deleted_orphan_user_or_product' => 0,
            'services_cleared_orphan_invoice_id' => 0,
            'invoices_deleted_orphan_user_or_product' => 0,
            'payments_deleted_orphan_user_or_invoice' => 0,
            'trace_ids_backfilled' => 0,
        ];

        DB::transaction(function () use (&$summary): void {
            $this->ensureNullableUnsignedBigInt('services', 'order_id');
            $summary['services_order_id_zero_to_null'] = $this->normalizeZeroReference('services', 'order_id');
            $summary['services_invoice_id_zero_to_null'] = $this->normalizeZeroReference('services', 'invoice_id');
            $summary['payments_order_id_zero_to_null'] = $this->normalizeZeroReference('payments', 'order_id');
            $summary['payments_invoice_id_zero_to_null'] = $this->normalizeZeroReference('payments', 'invoice_id');
            $summary['invoices_order_id_zero_to_null'] = $this->normalizeZeroReference('invoices', 'order_id');

            $summary['invoice_items_deleted_orphans'] = $this->deleteOrphans(
                'invoice_items',
                'invoice_id',
                'invoices',
                'id'
            );
            $summary['payment_callbacks_deleted_orphans'] = $this->deleteOrphans(
                'payment_callbacks',
                'payment_id',
                'payments',
                'id'
            );
            $summary['user_accounts_deleted_orphans'] = $this->deleteOrphans(
                'user_accounts',
                'user_id',
                'users',
                'id',
                'user_id'
            );
            $summary['ticket_replies_deleted_orphans'] = $this->deleteOrphans(
                'ticket_replies',
                'ticket_id',
                'tickets',
                'id'
            );

            $summary['services_deleted_orphan_user_or_product'] =
                $this->deleteOrphans('services', 'user_id', 'users', 'id')
                + $this->deleteOrphans('services', 'product_id', 'products', 'id');
            $summary['services_cleared_orphan_invoice_id'] = $this->clearOrphansToNull(
                'services',
                'invoice_id',
                'invoices',
                'id'
            );
            $summary['invoices_deleted_orphan_user_or_product'] =
                $this->deleteOrphans('invoices', 'user_id', 'users', 'id')
                + $this->deleteOrphans('invoices', 'product_id', 'products', 'id');
            $summary['payments_deleted_orphan_user_or_invoice'] =
                $this->deleteOrphans('payments', 'user_id', 'users', 'id')
                + $this->deleteOrphans('payments', 'invoice_id', 'invoices', 'id');

            $summary['trace_ids_backfilled'] =
                $this->backfillTraceIds('invoices') +
                $this->backfillTraceIds('payments') +
                $this->backfillTraceIds('services') +
                $this->backfillTraceIds('account_transactions');
        });

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    public function archiveLogs(int $retainDays, int $chunkSize, bool $dryRun = false): array
    {
        $retainDays = max($retainDays, 1);
        $chunkSize = max($chunkSize, 1);
        $cutoff = now()->subDays($retainDays);

        $targets = [
            'operation_logs' => 'created_at',
            'notification_logs' => 'created_at',
            'email_logs' => 'created_at',
            'sms_logs' => 'created_at',
            'automation_logs' => 'created_at',
        ];

        $summary = [];

        foreach ($targets as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                $summary[$table] = 0;

                continue;
            }

            if ($dryRun) {
                $summary[$table] = DB::table($table)->where($column, '<', $cutoff)->count();

                continue;
            }

            $deleted = 0;

            do {
                $ids = DB::table($table)
                    ->where($column, '<', $cutoff)
                    ->orderBy('id')
                    ->limit($chunkSize)
                    ->pluck('id');

                $count = $ids->count();
                if ($count === 0) {
                    break;
                }

                $deleted += DB::table($table)->whereIn('id', $ids->all())->delete();
            } while ($count === $chunkSize);

            $summary[$table] = $deleted;
        }

        return $summary;
    }

    /**
     * @return list<array<string, string>>
     */
    public function foreignKeys(): array
    {
        return collect(DB::select('
            SELECT
                table_name AS table_name,
                constraint_name AS constraint_name,
                column_name AS column_name,
                referenced_table_name AS referenced_table_name,
                referenced_column_name AS referenced_column_name
            FROM information_schema.key_column_usage
            WHERE table_schema = DATABASE()
              AND referenced_table_name IS NOT NULL
            ORDER BY table_name, constraint_name, ordinal_position
        '))
            ->map(fn (object $row) => [
                'table_name' => (string) $row->table_name,
                'constraint_name' => (string) $row->constraint_name,
                'column_name' => (string) $row->column_name,
                'referenced_table_name' => (string) $row->referenced_table_name,
                'referenced_column_name' => (string) $row->referenced_column_name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function zeroReferenceMetrics(): array
    {
        return [
            'services.order_id' => $this->countEquals('services', 'order_id', 0),
            'services.invoice_id' => $this->countEquals('services', 'invoice_id', 0),
            'payments.order_id' => $this->countEquals('payments', 'order_id', 0),
            'payments.invoice_id' => $this->countEquals('payments', 'invoice_id', 0),
            'invoices.order_id' => $this->countEquals('invoices', 'order_id', 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function orphanMetrics(): array
    {
        return [
            'invoice_items.invoice_id->invoices.id' => $this->countOrphans('invoice_items', 'invoice_id', 'invoices', 'id'),
            'payment_callbacks.payment_id->payments.id' => $this->countOrphans('payment_callbacks', 'payment_id', 'payments', 'id'),
            'user_accounts.user_id->users.id' => $this->countOrphans('user_accounts', 'user_id', 'users', 'id'),
            'ticket_replies.ticket_id->tickets.id' => $this->countOrphans('ticket_replies', 'ticket_id', 'tickets', 'id'),
            'services.user_id->users.id' => $this->countOrphans('services', 'user_id', 'users', 'id'),
            'services.product_id->products.id' => $this->countOrphans('services', 'product_id', 'products', 'id'),
            'services.invoice_id->invoices.id' => $this->countOrphans('services', 'invoice_id', 'invoices', 'id'),
            'invoices.user_id->users.id' => $this->countOrphans('invoices', 'user_id', 'users', 'id'),
            'invoices.product_id->products.id' => $this->countOrphans('invoices', 'product_id', 'products', 'id'),
            'payments.user_id->users.id' => $this->countOrphans('payments', 'user_id', 'users', 'id'),
            'payments.invoice_id->invoices.id' => $this->countOrphans('payments', 'invoice_id', 'invoices', 'id'),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function traceIdMetrics(): array
    {
        return [
            'invoices.trace_id_missing' => $this->countMissingTraceId('invoices'),
            'payments.trace_id_missing' => $this->countMissingTraceId('payments'),
            'services.trace_id_missing' => $this->countMissingTraceId('services'),
            'account_transactions.trace_id_missing' => $this->countMissingTraceId('account_transactions'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tableSizeMetrics(): array
    {
        return collect(DB::select("
            SELECT
                table_name AS table_name,
                table_rows AS table_rows,
                ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                update_time AS update_time
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_type = 'BASE TABLE'
            ORDER BY (data_length + index_length) DESC, table_name
        "))
            ->map(fn (object $row) => [
                'table_name' => (string) $row->table_name,
                'table_rows' => (int) ($row->table_rows ?? 0),
                'size_mb' => (float) ($row->size_mb ?? 0),
                'update_time' => $row->update_time ? (string) $row->update_time : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, list<string>>
     */
    private function indexMetrics(): array
    {
        $targets = [
            'services',
            'invoices',
            'payments',
            'invoice_items',
            'payment_callbacks',
            'user_accounts',
            'ticket_replies',
            'operation_logs',
            'notification_logs',
        ];

        $rows = collect(DB::select("
            SELECT DISTINCT
                table_name AS table_name,
                index_name AS index_name
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name IN ('services','invoices','payments','invoice_items','payment_callbacks','user_accounts','ticket_replies','operation_logs','notification_logs')
            ORDER BY table_name, index_name
        "));

        $result = [];

        foreach ($targets as $table) {
            $result[$table] = $rows
                ->where('table_name', $table)
                ->map(fn (object $row) => (string) $row->index_name)
                ->values()
                ->all();
        }

        return $result;
    }

    private function normalizeZeroReference(string $table, string $column): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)->where($column, 0)->update([$column => null]);
    }

    private function deleteOrphans(string $table, string $column, string $parentTable, string $parentColumn, string $primaryColumn = 'id'): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        $ids = DB::table($table)
            ->leftJoin($parentTable.' as parent', "{$table}.{$column}", '=', "parent.{$parentColumn}")
            ->whereNotNull("{$table}.{$column}")
            ->whereNull("parent.{$parentColumn}")
            ->pluck("{$table}.{$primaryColumn}");

        if ($ids->isEmpty()) {
            return 0;
        }

        return DB::table($table)->whereIn($primaryColumn, $ids->all())->delete();
    }

    private function clearOrphansToNull(string $table, string $column, string $parentTable, string $parentColumn): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        $ids = DB::table($table)
            ->leftJoin($parentTable.' as parent', "{$table}.{$column}", '=', "parent.{$parentColumn}")
            ->whereNotNull("{$table}.{$column}")
            ->whereNull("parent.{$parentColumn}")
            ->pluck("{$table}.id");

        if ($ids->isEmpty()) {
            return 0;
        }

        return DB::table($table)->whereIn('id', $ids->all())->update([$column => null]);
    }

    private function countEquals(string $table, string $column, int $value): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)->where($column, $value)->count();
    }

    private function ensureNullableUnsignedBigInt(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $columnInfo = DB::table('information_schema.columns')
            ->select('is_nullable', 'column_type')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->first();

        if (! $columnInfo) {
            return;
        }

        if ((string) ($columnInfo->is_nullable ?? 'YES') === 'YES') {
            return;
        }

        $columnType = (string) ($columnInfo->column_type ?? 'bigint unsigned');
        DB::statement(sprintf(
            'ALTER TABLE `%s` MODIFY `%s` %s NULL',
            $table,
            $column,
            $columnType
        ));
    }

    private function countOrphans(string $table, string $column, string $parentTable, string $parentColumn): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)
            ->leftJoin($parentTable.' as parent', "{$table}.{$column}", '=', "parent.{$parentColumn}")
            ->whereNotNull("{$table}.{$column}")
            ->whereNull("parent.{$parentColumn}")
            ->count();
    }

    private function countMissingTraceId(string $table): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'trace_id')) {
            return 0;
        }

        return DB::table($table)
            ->where(function ($query) {
                $query->whereNull('trace_id')
                    ->orWhere('trace_id', '');
            })
            ->count();
    }

    private function backfillTraceIds(string $table): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'trace_id')) {
            return 0;
        }

        $rows = DB::table($table)
            ->select('id')
            ->where(function ($query) {
                $query->whereNull('trace_id')
                    ->orWhere('trace_id', '');
            })
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            DB::table($table)
                ->where('id', $row->id)
                ->update(['trace_id' => "legacy-{$table}-{$row->id}"]);
        }

        return $rows->count();
    }
}
