<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'product_name_snapshot')) {
                $table->string('product_name_snapshot', 200)->nullable()->after('product_id');
            }

            if (! Schema::hasColumn('orders', 'product_type_snapshot')) {
                $table->string('product_type_snapshot', 50)->nullable()->after('product_name_snapshot');
            }
        });

        if (Schema::hasTable('order_items')) {
            DB::statement("
                UPDATE orders o
                LEFT JOIN products p ON p.id = o.product_id
                LEFT JOIN (
                    SELECT oi.order_id, oi.item_name
                    FROM order_items oi
                    INNER JOIN (
                        SELECT order_id, MIN(id) AS first_id
                        FROM order_items
                        GROUP BY order_id
                    ) first_item ON first_item.first_id = oi.id
                ) order_item_snapshot ON order_item_snapshot.order_id = o.id
                SET
                    o.product_name_snapshot = COALESCE(NULLIF(o.product_name_snapshot, ''), NULLIF(order_item_snapshot.item_name, ''), NULLIF(p.name, '')),
                    o.product_type_snapshot = COALESCE(NULLIF(o.product_type_snapshot, ''), NULLIF(p.product_type, ''))
            ");

            return;
        }

        DB::statement("
            UPDATE orders o
            LEFT JOIN products p ON p.id = o.product_id
            SET
                o.product_name_snapshot = COALESCE(NULLIF(o.product_name_snapshot, ''), NULLIF(p.name, '')),
                o.product_type_snapshot = COALESCE(NULLIF(o.product_type_snapshot, ''), NULLIF(p.product_type, ''))
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $dropColumns = [];

            if (Schema::hasColumn('orders', 'product_type_snapshot')) {
                $dropColumns[] = 'product_type_snapshot';
            }

            if (Schema::hasColumn('orders', 'product_name_snapshot')) {
                $dropColumns[] = 'product_name_snapshot';
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
