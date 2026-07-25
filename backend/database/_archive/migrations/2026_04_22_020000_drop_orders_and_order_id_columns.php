<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice-first 过渡：确保 invoice_id 列在 services / payments / referral_rewards 上存在，
 * 并从 orders.invoice_id 回填历史数据。
 *
 * 注意：本迁移**不再**删除 orders 表或 order_id 列，而是保留 orders 作为
 * 内部基础设施（不对外暴露 UI/API），供上游 ZJMF 开通链路继续使用。
 * 用户侧的业务流水全部以 Invoice 为唯一展示口径，符合"抛弃订单概念"的目标。
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. 确保 invoice_id 列存在 ──
        if (Schema::hasTable('services') && ! Schema::hasColumn('services', 'invoice_id')) {
            Schema::table('services', function (Blueprint $table): void {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('order_id');
                $table->index('invoice_id', 'services_invoice_id_idx');
            });
        }

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'invoice_id')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('order_id');
                $table->index('invoice_id', 'payments_invoice_id_idx');
            });
        }

        if (Schema::hasTable('referral_rewards') && ! Schema::hasColumn('referral_rewards', 'invoice_id')) {
            Schema::table('referral_rewards', function (Blueprint $table): void {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('order_id');
                $table->index('invoice_id', 'referral_rewards_invoice_id_idx');
            });
        }

        // ── 2. 回填 invoice_id（通过 orders.invoice_id 映射） ──
        if ($this->canBackfill('services')) {
            DB::statement(<<<'SQL'
                UPDATE services s
                INNER JOIN orders o ON o.id = s.order_id
                SET s.invoice_id = o.invoice_id
                WHERE s.invoice_id IS NULL AND o.invoice_id IS NOT NULL
            SQL);
        }

        if ($this->canBackfill('payments')) {
            DB::statement(<<<'SQL'
                UPDATE payments p
                INNER JOIN orders o ON o.id = p.order_id
                SET p.invoice_id = o.invoice_id
                WHERE p.invoice_id IS NULL AND o.invoice_id IS NOT NULL
            SQL);
        }

        if ($this->canBackfill('referral_rewards')) {
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
        // 仅回滚列创建；不尝试撤销回填（回填是幂等的，重复执行安全）。
        foreach (['services', 'payments', 'referral_rewards'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'invoice_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                try {
                    $table->dropIndex($tableName.'_invoice_id_idx');
                } catch (Throwable $e) {
                }
                $table->dropColumn('invoice_id');
            });
        }
    }

    private function canBackfill(string $tableName): bool
    {
        return Schema::hasTable($tableName)
            && Schema::hasTable('orders')
            && Schema::hasColumn($tableName, 'order_id')
            && Schema::hasColumn($tableName, 'invoice_id')
            && Schema::hasColumn('orders', 'invoice_id');
    }
};
