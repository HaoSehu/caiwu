<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_categories')) {
            Schema::table('product_categories', function (Blueprint $table): void {
                if (Schema::hasColumn('product_categories', 'product_type_id')) {
                    $table->dropForeign(['product_type_id']);
                    $table->dropColumn('product_type_id');
                }
            });
        }

        if (Schema::hasTable('product_types')) {
            Schema::drop('product_types');
        }
    }

    public function down(): void
    {
        // 当前重构为测试环境激进集中化，下行恢复不再提供 product_types 自动回建。
    }
};
