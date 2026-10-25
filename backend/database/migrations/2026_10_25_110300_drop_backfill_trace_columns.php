<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 删除数据回填/迁移期追踪列（2026-10-25 评审裁决：直接删）。
 *
 * - backfill_batch_id ×7（绑定/快照表）：PluginDataBackfillService 已随本批次退役，
 *   列与配套索引失去写入方；
 * - admin_login_logs.legacy_activity_log_id：旧 activity_logs→admin_login_logs
 *   映射指针，专属回填命令一并退役。
 *
 * down() 恢复列与索引。
 */
return new class extends Migration
{
    /** @var array<string, string> 表名 => backfill 索引名 */
    private const BACKFILL_COLUMNS = [
        'integration_plugin_bindings' => 'plugin_bindings_backfill_batch_idx',
        'product_upstream_bindings' => 'product_upstream_backfill_batch_idx',
        'service_connection_snapshots' => 'service_connection_backfill_batch_idx',
        'service_provision_attempts' => 'service_attempt_backfill_batch_idx',
        'service_runtime_snapshots' => 'service_runtime_backfill_batch_idx',
        'service_upstream_bindings' => 'service_upstream_backfill_batch_idx',
        'supplier_plugin_bindings' => 'supplier_plugin_backfill_batch_idx',
    ];

    public function up(): void
    {
        foreach (self::BACKFILL_COLUMNS as $table => $index) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'backfill_batch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $index): void {
                if (Schema::hasIndex($table, $index)) {
                    $blueprint->dropIndex($index);
                }

                $blueprint->dropColumn('backfill_batch_id');
            });
        }

        if (Schema::hasTable('admin_login_logs') && Schema::hasColumn('admin_login_logs', 'legacy_activity_log_id')) {
            Schema::table('admin_login_logs', function (Blueprint $blueprint): void {
                if (Schema::hasIndex('admin_login_logs', 'admin_login_logs_legacy_activity_unique')) {
                    $blueprint->dropUnique('admin_login_logs_legacy_activity_unique');
                }

                $blueprint->dropColumn('legacy_activity_log_id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::BACKFILL_COLUMNS as $table => $index) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'backfill_batch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) use ($index): void {
                $table->string('backfill_batch_id', 64)->nullable()->comment('数据回填批次号（迁移期追踪标识）');
                $table->index('backfill_batch_id', $index);
            });
        }

        if (Schema::hasTable('admin_login_logs') && ! Schema::hasColumn('admin_login_logs', 'legacy_activity_log_id')) {
            Schema::table('admin_login_logs', function (Blueprint $table): void {
                $table->unsignedBigInteger('legacy_activity_log_id')->nullable()->comment('回填来源 activity_logs.id；运行时写入为 NULL');
                $table->unique('legacy_activity_log_id', 'admin_login_logs_legacy_activity_unique');
            });
        }
    }
};
