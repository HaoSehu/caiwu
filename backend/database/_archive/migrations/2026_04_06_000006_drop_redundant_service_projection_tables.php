<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'service_renew_prices',
            'service_supplier_bindings',
        ] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }
    }

    public function down(): void
    {
        // 当前重构为测试环境激进集中化，下行恢复不再提供旧服务投影表自动回建。
    }
};
