<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REDUNDANT_INDEXES = [
        'idx_product_group_status',
        'idx_product_second_status',
        'idx_product_third_status',
    ];

    public function up(): void
    {
        foreach (self::REDUNDANT_INDEXES as $indexName) {
            $this->dropIndexIfExists('products', $indexName);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        foreach (self::REDUNDANT_INDEXES as $indexName) {
            if ($this->indexExists('products', $indexName)) {
                continue;
            }

            Schema::table('products', function (Blueprint $table) use ($indexName): void {
                $table->index('status', $indexName);
            });
        }
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || ! $this->indexExists($tableName, $indexName)) {
            return;
        }

        DB::statement("ALTER TABLE `{$tableName}` DROP INDEX `{$indexName}`");
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $index = DB::selectOne(
            'SELECT 1
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?
             LIMIT 1',
            [$tableName, $indexName],
        );

        return $index !== null;
    }
};
