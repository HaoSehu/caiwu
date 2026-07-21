<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'second_product_groups',
        'third_product_groups',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'legacy_product_group_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('legacy_product_group_id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'legacy_product_group_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('legacy_product_group_id')->nullable()->unique();
            });
        }
    }
};
