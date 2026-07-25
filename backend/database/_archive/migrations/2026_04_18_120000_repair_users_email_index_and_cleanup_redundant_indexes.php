<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->repairUsersEmailUniqueIndex();
        $this->ensureOperationLogIndexes();
        $this->dropRedundantIndexes();
    }

    public function down(): void
    {
        $this->dropIndexIfExists('operation_logs', 'operation_logs_user_created_at_idx');
        $this->dropIndexIfExists('operation_logs', 'operation_logs_created_at_idx');

        $this->ensureIndex('users', 'users_phone_idx', fn (Blueprint $table) => $table->index('phone', 'users_phone_idx'));
        $this->ensureIndex('users', 'users_referral_code_index', fn (Blueprint $table) => $table->index('referral_code', 'users_referral_code_index'));
        $this->ensureIndex('users', 'users_status_index', fn (Blueprint $table) => $table->index('status', 'users_status_index'));
        $this->ensureIndex('services', 'services_status_index', fn (Blueprint $table) => $table->index('status', 'services_status_index'));
        $this->ensureIndex('tickets', 'tickets_status_index', fn (Blueprint $table) => $table->index('status', 'tickets_status_index'));
        $this->ensureIndex('balance_logs', 'balance_logs_user_id_index', fn (Blueprint $table) => $table->index('user_id', 'balance_logs_user_id_index'));

        // Keep the repaired users_email_unique index in place. Rolling back to a broken or missing state is unsafe.
    }

    private function repairUsersEmailUniqueIndex(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'email')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('email')
            ->update([
                'email' => DB::raw("NULLIF(TRIM(email), '')"),
            ]);

        $duplicates = DB::table('users')
            ->select('email', DB::raw('COUNT(*) as aggregate_count'))
            ->whereNotNull('email')
            ->groupBy('email')
            ->having('aggregate_count', '>', 1)
            ->limit(5)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $emails = $duplicates->pluck('email')->map(fn ($email) => (string) $email)->implode(', ');

            throw new RuntimeException(
                'Cannot repair users.email unique index because duplicate emails exist: '.$emails
            );
        }

        $index = $this->getIndexDefinition('users', 'users_email_unique');
        $columns = (string) ($index->columns_list ?? '');
        $isValidUniqueEmailIndex = $index !== null
            && (int) ($index->non_unique ?? 1) === 0
            && $columns === 'email';

        if ($isValidUniqueEmailIndex) {
            return;
        }

        $this->dropIndexIfExists('users', 'users_email_unique');
        DB::statement('ALTER TABLE `users` ADD UNIQUE INDEX `users_email_unique` (`email`)');
    }

    private function ensureOperationLogIndexes(): void
    {
        $this->ensureIndex(
            'operation_logs',
            'operation_logs_created_at_idx',
            fn (Blueprint $table) => $table->index('created_at', 'operation_logs_created_at_idx')
        );

        $this->ensureIndex(
            'operation_logs',
            'operation_logs_user_created_at_idx',
            fn (Blueprint $table) => $table->index(['user_id', 'created_at'], 'operation_logs_user_created_at_idx')
        );
    }

    private function dropRedundantIndexes(): void
    {
        $this->dropIndexIfExists('users', 'users_phone_idx');
        $this->dropIndexIfExists('users', 'users_referral_code_index');
        $this->dropIndexIfExists('users', 'users_status_index');
        $this->dropIndexIfExists('services', 'services_status_index');
        $this->dropIndexIfExists('tickets', 'tickets_status_index');
        $this->dropIndexIfExists('balance_logs', 'balance_logs_user_id_index');
    }

    private function ensureIndex(string $tableName, string $indexName, callable $definition): void
    {
        if (! Schema::hasTable($tableName) || $this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($definition): void {
            $definition($table);
        });
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || ! $this->indexExists($tableName, $indexName)) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $tableName, $indexName));
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return $this->getIndexDefinition($tableName, $indexName) !== null;
    }

    private function getIndexDefinition(string $tableName, string $indexName): ?object
    {
        $definition = DB::table('information_schema.statistics')
            ->selectRaw('COUNT(*) as row_count')
            ->selectRaw('MIN(non_unique) as non_unique')
            ->selectRaw("GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') as columns_list")
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $tableName)
            ->where('index_name', $indexName)
            ->first();

        if (! $definition || (int) ($definition->row_count ?? 0) === 0) {
            return null;
        }

        return $definition;
    }
};
