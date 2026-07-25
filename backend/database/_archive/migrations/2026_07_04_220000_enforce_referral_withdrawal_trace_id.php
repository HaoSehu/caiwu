<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M7 — 强制 referral_withdrawals.trace_id 非空
 *
 * trace_id 已有唯一约束，但允许 NULL，多个 NULL 值不受唯一保护。
 * 先回填所有 NULL 值，再将列改为 NOT NULL，彻底封堵空值漏洞。
 */
return new class extends Migration
{
    /**
     * 回填 NULL 后将 trace_id 改为 NOT NULL。
     */
    public function up(): void
    {
        // 步骤1：回填所有 trace_id 为 NULL 的行
        // 使用 legacy-{id}-{unix_timestamp} 格式，保证全局唯一且可追溯
        DB::statement("
            UPDATE referral_withdrawals
            SET trace_id = CONCAT('legacy-', id, '-', UNIX_TIMESTAMP())
            WHERE trace_id IS NULL
        ");

        // 步骤2：将列改为 NOT NULL
        if (Schema::hasTable('referral_withdrawals') && Schema::hasColumn('referral_withdrawals', 'trace_id')) {
            Schema::table('referral_withdrawals', function (Blueprint $table) {
                $table->string('trace_id', 64)->nullable(false)->change();
            });
        }
    }

    /**
     * 回滚：将列改回可空（不清除已回填的 legacy- 值，数据无害保留）。
     */
    public function down(): void
    {
        if (Schema::hasTable('referral_withdrawals') && Schema::hasColumn('referral_withdrawals', 'trace_id')) {
            Schema::table('referral_withdrawals', function (Blueprint $table) {
                $table->string('trace_id', 64)->nullable()->change();
            });
        }
    }
};
