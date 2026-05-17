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
                if (Schema::hasColumn('products', 'supplier_mapping_id')) {
                    $table->dropForeign(['supplier_mapping_id']);
                    $table->dropColumn('supplier_mapping_id');
                }
            });
        }

        if (Schema::hasTable('service_supplier_bindings')) {
            Schema::table('service_supplier_bindings', function (Blueprint $table): void {
                if (Schema::hasColumn('service_supplier_bindings', 'supplier_mapping_id')) {
                    $table->dropForeign(['supplier_mapping_id']);
                }
            });
        }

        if (Schema::hasTable('supplier_products')) {
            Schema::drop('supplier_products');
        }
    }

    public function down(): void
    {
        // 当前重构为测试环境激进集中化，下行恢复不再提供 supplier_products 自动回建。
    }
};
