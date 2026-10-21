<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 履约状态从 config_snapshot JSON 迁到独立列（P1-8 / P1-9）。
 *
 * 背景：fulfillment_pending / fulfillment_pending_at / requires_refund 三个键
 * 与业务快照共用 config_snapshot，saveQuietly 无行锁，并发写会 lost update；
 * 唯一的大表扫描（RetryFailedProvisionCommand）也只能 JSON_EXTRACT 无法走索引。
 *
 * 策略：新增列并回填存量，旧键保留一个版本作为只读兼容（写入侧切换到新列），
 * 读侧优先新列、缺失时回退旧键，避免切换瞬间新旧数据不一致。
 *
 * 幂等：MySQL DDL 可能部分生效后中断（Phinx/Laravel 均无法随事务回滚 DDL），
 * 因此每一列、每一个索引都单独判断存在性后再执行，保证重试不会因「已存在」失败。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        if (! Schema::hasColumn('invoices', 'fulfillment_pending')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->boolean('fulfillment_pending')->default(false)->after('config_pricing_snapshot');
            });
        }

        if (! Schema::hasColumn('invoices', 'fulfillment_pending_at')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->timestamp('fulfillment_pending_at')->nullable()->after('fulfillment_pending');
            });
        }

        if (! Schema::hasColumn('invoices', 'fulfillment_cleared_at')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->timestamp('fulfillment_cleared_at')->nullable()->after('fulfillment_pending_at');
            });
        }

        if (! Schema::hasColumn('invoices', 'requires_refund')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->boolean('requires_refund')->default(false)->after('fulfillment_cleared_at');
            });
        }

        if (! Schema::hasIndex('invoices', 'invoices_fulfillment_pending_idx')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->index(['fulfillment_pending', 'fulfillment_pending_at'], 'invoices_fulfillment_pending_idx');
            });
        }

        if (! Schema::hasIndex('invoices', 'invoices_requires_refund_idx')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->index(['requires_refund', 'id'], 'invoices_requires_refund_idx');
            });
        }

        // 存量回填：按旧 JSON 键还原状态，回填后历史行与新增行口径一致。
        // 回填条件本身幂等，重复执行不会改变已回填的值。
        DB::table('invoices')
            ->whereRaw("JSON_EXTRACT(config_snapshot, '$.fulfillment_pending') = true")
            ->update(['fulfillment_pending' => true]);

        DB::table('invoices')
            ->whereRaw("JSON_EXTRACT(config_snapshot, '$.requires_refund') = true")
            ->update(['requires_refund' => true]);

        DB::statement(<<<'SQL'
            UPDATE invoices
            SET fulfillment_pending_at = COALESCE(
                STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(config_snapshot, '$.fulfillment_pending_at')), '%Y-%m-%d %H:%i:%s'),
                updated_at
            )
            WHERE fulfillment_pending = 1
        SQL);

        DB::statement(<<<'SQL'
            UPDATE invoices
            SET fulfillment_cleared_at = STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(config_snapshot, '$.fulfillment_cleared_at')), '%Y-%m-%d %H:%i:%s')
            WHERE JSON_EXTRACT(config_snapshot, '$.fulfillment_cleared_at') IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        if (Schema::hasIndex('invoices', 'invoices_fulfillment_pending_idx')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropIndex('invoices_fulfillment_pending_idx');
            });
        }

        if (Schema::hasIndex('invoices', 'invoices_requires_refund_idx')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropIndex('invoices_requires_refund_idx');
            });
        }

        foreach (['fulfillment_pending', 'fulfillment_pending_at', 'fulfillment_cleared_at', 'requires_refund'] as $column) {
            if (! Schema::hasColumn('invoices', $column)) {
                continue;
            }
            Schema::table('invoices', function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }
};
