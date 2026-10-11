<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M12 关怀逐用户发送日志（防重发核心约束）。
 *
 * unique(care_plan_id, user_id, cycle_key)：cycle_key 口径——
 * 生日 YYYY-MM-DD（10）、到期 svc:{service_id}:{YYYY-MM}（最长 31）、
 * 召回/自定义 YYYY-MM(-DD)；varchar(64) 留裕量，insert orIgnore +
 * 状态回写实现"只发一次"。
 */
return new class extends Migration
{
    private const CYCLE_KEY_COMMENT = '防重周期键：生日 YYYY-MM-DD；到期 svc:{id}:{YYYY-MM}（最长 31）；召回 YYYY-MM(-DD)';

    public function up(): void
    {
        if (! Schema::hasTable('care_send_logs')) {
            Schema::create('care_send_logs', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('care_plan_id')->comment('所属关怀计划');
                $table->unsignedBigInteger('run_id')->comment('所属执行批次 care_task_runs.id');
                $table->unsignedBigInteger('user_id')->comment('目标用户 users.id');
                $table->string('cycle_key', 64)->comment(self::CYCLE_KEY_COMMENT);
                $table->unsignedInteger('service_id')->nullable()->comment('到期提醒关联服务 services.id（其余类型 NULL）');
                $table->tinyInteger('status')->default(0)->comment('0待发 1成功 2失败');
                $table->string('error', 255)->nullable()->comment('失败原因');
                $table->dateTime('sent_at')->nullable()->comment('发送完成时间');
                $table->timestamps();

                $table->foreign('care_plan_id')->references('id')->on('care_plans')->cascadeOnDelete();
                $table->index('run_id', 'idx_care_send_logs_run');
                $table->unique(['care_plan_id', 'user_id', 'cycle_key'], 'uk_care_send_logs_plan_user_cycle');
                $table->index(['care_plan_id', 'status'], 'idx_care_send_logs_plan_status');
            });

            return;
        }

        // 可重入：表已存在（旧口径 cycle_key varchar(30)，svc:{id}:{YYYY-MM} 最长 31 会被截断）时扩容到 64。
        $column = DB::selectOne(
            "SELECT CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'care_send_logs' AND COLUMN_NAME = 'cycle_key'"
        );

        if ($column !== null && (int) $column->len < 64) {
            DB::statement("ALTER TABLE `care_send_logs` MODIFY `cycle_key` VARCHAR(64) NOT NULL COMMENT '".self::CYCLE_KEY_COMMENT."'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('care_send_logs')) {
            Schema::dropIfExists('care_send_logs');
        }
    }
};
