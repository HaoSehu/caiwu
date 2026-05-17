<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 给 services 表加 invoice_id，并从 orders 回填到 services/payments/referral_rewards。
 * 保留 orders 表以兼容尚未迁移的流程（续费、流量包、管理员手动入账等）。
 * 后续完全迁移后会有另一个迁移彻底删除 orders 表与 order_id 字段。
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. services 表加 invoice_id
        if (Schema::hasTable('services') && ! Schema::hasColumn('services', 'invoice_id')) {
            Schema::table('services', function (Blueprint $table) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('order_id');
                $table->index('invoice_id', 'services_invoice_id_idx');
            });
        }

        // 2. 回填 services.invoice_id：通过 services.order_id -> orders.invoice_id
        if (Schema::hasTable('services') && Schema::hasTable('orders')
            && Schema::hasColumn('services', 'order_id')
            && Schema::hasColumn('services', 'invoice_id')
            && Schema::hasColumn('orders', 'invoice_id')
        ) {
            DB::statement(<<<'SQL'
                UPDATE services s
                INNER JOIN orders o ON o.id = s.order_id
                SET s.invoice_id = o.invoice_id
                WHERE s.invoice_id IS NULL AND o.invoice_id IS NOT NULL
            SQL);
        }

        // 3. 回填 payments.invoice_id：通过 payments.order_id -> orders.invoice_id
        if (Schema::hasTable('payments') && Schema::hasTable('orders')
            && Schema::hasColumn('payments', 'order_id')
            && Schema::hasColumn('payments', 'invoice_id')
            && Schema::hasColumn('orders', 'invoice_id')
        ) {
            DB::statement(<<<'SQL'
                UPDATE payments p
                INNER JOIN orders o ON o.id = p.order_id
                SET p.invoice_id = o.invoice_id
                WHERE p.invoice_id IS NULL AND o.invoice_id IS NOT NULL
            SQL);
        }

        // 4. 回填 referral_rewards.invoice_id：通过 order_id -> orders.invoice_id
        if (Schema::hasTable('referral_rewards') && Schema::hasTable('orders')
            && Schema::hasColumn('referral_rewards', 'order_id')
            && Schema::hasColumn('referral_rewards', 'invoice_id')
            && Schema::hasColumn('orders', 'invoice_id')
        ) {
            DB::statement(<<<'SQL'
                UPDATE referral_rewards r
                INNER JOIN orders o ON o.id = r.order_id
                SET r.invoice_id = o.invoice_id
                WHERE r.invoice_id IS NULL AND o.invoice_id IS NOT NULL
            SQL);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('services') && Schema::hasColumn('services', 'invoice_id')) {
            Schema::table('services', function (Blueprint $table) {
                try {
                    $table->dropIndex('services_invoice_id_idx');
                } catch (Throwable $e) {
                }
                $table->dropColumn('invoice_id');
            });
        }
    }
};
