<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        if (! Schema::hasColumn('payments', 'order_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('order_id')->nullable()->after('user_id');
                $table->index(['order_id', 'status'], 'payments_order_status_idx');
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('payments', 'invoice_id')) {
            DB::statement(<<<'SQL'
                UPDATE payments p
                INNER JOIN invoices i ON i.id = p.invoice_id
                SET p.order_id = i.order_id
                WHERE p.invoice_id IS NOT NULL
                  AND i.order_id IS NOT NULL
                  AND (p.order_id IS NULL OR p.order_id <> i.order_id)
            SQL);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'order_id')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_order_status_idx');
            $table->dropColumn('order_id');
        });
    }
};
