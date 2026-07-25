<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignKeysByColumn('invoice_items', 'order_item_id');
        $this->dropForeignKeysByColumn('order_item_options', 'order_item_id');

        if (Schema::hasTable('invoice_items') && Schema::hasColumn('invoice_items', 'order_item_id')) {
            Schema::table('invoice_items', function (Blueprint $table): void {
                $table->dropColumn('order_item_id');
            });
        }

        foreach ([
            'order_item_options',
            'order_items',
        ] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }
    }

    public function down(): void
    {
        // 当前重构为测试环境激进集中化，下行恢复不再提供订单投影表自动回建。
    }

    private function dropForeignKeysByColumn(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $database = DB::getDatabaseName();
        $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->select('CONSTRAINT_NAME')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME')
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->values()
            ->all();

        foreach ($constraints as $constraintName) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($constraintName): void {
                $tableBlueprint->dropForeign($constraintName);
            });
        }
    }
};
