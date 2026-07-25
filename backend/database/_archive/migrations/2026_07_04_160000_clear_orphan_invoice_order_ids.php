<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('invoices')
            || ! Schema::hasTable('orders')
            || ! Schema::hasColumn('invoices', 'order_id')
        ) {
            return;
        }

        DB::table('invoices')
            ->leftJoin('orders', 'orders.id', '=', 'invoices.order_id')
            ->whereNotNull('invoices.order_id')
            ->whereNull('orders.id')
            ->update(['invoices.order_id' => null]);
    }

    public function down(): void
    {
        // 历史孤儿 order_id 无法可靠恢复；回滚不重新写入无效引用。
    }
};
