<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                if (Schema::hasColumn('products', 'product_group_id')) {
                    $table->dropColumn('product_group_id');
                }
            });
        }

        if (Schema::hasTable('product_groups')) {
            Schema::drop('product_groups');
        }
    }

    public function down(): void
    {
        // 当前重构为测试环境激进集中化，下行恢复不再提供 product_groups 自动回建。
    }
};
