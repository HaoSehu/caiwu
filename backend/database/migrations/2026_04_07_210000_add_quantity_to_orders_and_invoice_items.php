<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'quantity')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unsignedInteger('quantity')->default(1)->after('billing_cycle');
            });
        }

        if (Schema::hasTable('orders')) {
            DB::table('orders')
                ->whereNull('quantity')
                ->update(['quantity' => 1]);
        }

        if (Schema::hasTable('invoice_items') && Schema::hasTable('invoices') && Schema::hasTable('orders')) {
            DB::statement("
                UPDATE invoice_items ii
                INNER JOIN invoices i ON i.id = ii.invoice_id
                INNER JOIN orders o ON o.id = i.order_id
                SET
                    ii.quantity = GREATEST(COALESCE(o.quantity, 1), 1),
                    ii.unit_price = CASE
                        WHEN GREATEST(COALESCE(o.quantity, 1), 1) > 0
                            THEN ROUND(COALESCE(o.amount, i.amount, 0) / GREATEST(COALESCE(o.quantity, 1), 1), 2)
                        ELSE COALESCE(o.amount, i.amount, 0)
                    END,
                    ii.discount_amount = ROUND(COALESCE(o.discount, 0), 2),
                    ii.line_amount = ROUND(COALESCE(i.amount, 0), 2),
                    ii.updated_at = CURRENT_TIMESTAMP
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'quantity')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('quantity');
            });
        }
    }
};
