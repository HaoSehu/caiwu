<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array<string, list<string>>>
     */
    private array $indexes = [
        'payments' => [
            'payments_user_id_index' => ['user_id'],
        ],
        'ticket_replies' => [
            'ticket_replies_ticket_id_index' => ['ticket_id'],
        ],
        'sms_logs' => [
            'sms_logs_phone_index' => ['phone'],
        ],
        'orders' => [
            'orders_status_index' => ['status'],
        ],
        'products' => [
            'products_group_id_index' => ['product_group_id'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexes): void {
                foreach ($indexes as $indexName => $columns) {
                    if ($this->hasIndex($tableName, $indexName)) {
                        $table->dropIndex($indexName);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexes): void {
                foreach ($indexes as $indexName => $columns) {
                    if (! $this->hasIndex($tableName, $indexName) && $this->hasColumns($tableName, $columns)) {
                        $table->index($columns, $indexName);
                    }
                }
            });
        }
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', Schema::getConnection()->getDatabaseName())
            ->where('table_name', $tableName)
            ->where('index_name', $indexName)
            ->exists();
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasColumns(string $tableName, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                return false;
            }
        }

        return true;
    }
};
