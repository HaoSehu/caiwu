<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * 删除旧版操作日志表 operation_logs（2026-10-25 表结构评审裁决：直接删）。
 *
 * 日志中心已由 activity_logs / admin_login_logs / gateway_logs / message_logs /
 * schedule_run_logs / integration_plugin_runtime_logs 承载，业务代码对本表零读写；
 * 删除前全库已备份（pre-destructive-20261025.sql，含 1848 行历史）。
 * 表不可重建：历史日志经该备份文件留存，down() 不恢复。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operation_logs')) {
            Schema::dropIfExists('operation_logs');
        }
    }

    public function down(): void
    {
        // 历史数据仅存在于 pre-destructive-20261025.sql 备份，不做结构回滚。
    }
};
