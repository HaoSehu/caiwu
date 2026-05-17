<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_item_options')) {
            Schema::table('order_item_options', function (Blueprint $table): void {
                if (Schema::hasColumn('order_item_options', 'product_option_value_id')) {
                    $table->dropForeign(['product_option_value_id']);
                }

                if (Schema::hasColumn('order_item_options', 'product_option_id')) {
                    $table->dropForeign(['product_option_id']);
                }
            });
        }

        foreach ([
            'product_option_value_prices',
            'product_option_values',
            'product_options',
            'product_option_groups',
            'product_purchase_constraints',
            'product_prices',
        ] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }
    }

    public function down(): void
    {
        // 当前重构为测试环境激进集中化，下行恢复不再提供旧商品投影表自动回建。
    }
};
