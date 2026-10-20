<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 登录日志回填幂等键（对齐 log:backfill-admin-login-logs）。
 *
 * 历史管理员登录只存在于 activity_logs(module=auth, action=admin.login)。
 * 回填命令把历史行复制到 admin_login_logs，并以 legacy_activity_log_id
 * 作为唯一幂等键：重复执行/中断重跑由 insertOrIgnore 自动跳过。
 * 运行时新产生的登录日志该列为 NULL（MySQL 唯一索引允许多个 NULL）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_login_logs')
            || Schema::hasColumn('admin_login_logs', 'legacy_activity_log_id')) {
            return;
        }

        Schema::table('admin_login_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('legacy_activity_log_id')
                ->nullable()
                ->after('id')
                ->comment('回填来源 activity_logs.id；运行时写入为 NULL');
            $table->unique('legacy_activity_log_id', 'admin_login_logs_legacy_activity_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_login_logs')
            || ! Schema::hasColumn('admin_login_logs', 'legacy_activity_log_id')) {
            return;
        }

        Schema::table('admin_login_logs', function (Blueprint $table): void {
            $table->dropUnique('admin_login_logs_legacy_activity_unique');
            $table->dropColumn('legacy_activity_log_id');
        });
    }
};
