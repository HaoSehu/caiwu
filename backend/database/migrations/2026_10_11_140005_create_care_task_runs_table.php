<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M12 关怀计划执行台账。
 *
 * unique(care_plan_id, planned_date)：同一计划同一目标日期只建一次 run，
 * 是每日调度的幂等锚点；重触发时复用已有 run，逐目标由 care_send_logs 防重。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('care_task_runs')) {
            return;
        }

        Schema::create('care_task_runs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('care_plan_id')->comment('所属关怀计划');
            $table->date('planned_date')->comment('计划目标日期');
            $table->tinyInteger('status')->default(0)->comment('0待执行 1成功 2部分失败 3失败 4跳过');
            $table->unsignedInteger('total_count')->default(0)->comment('目标总数');
            $table->unsignedInteger('sent_count')->default(0)->comment('成功数');
            $table->unsignedInteger('failed_count')->default(0)->comment('失败数');
            $table->unsignedInteger('skipped_count')->default(0)->comment('跳过数（如未填生日）');
            $table->dateTime('started_at')->nullable()->comment('开始时间');
            $table->dateTime('finished_at')->nullable()->comment('完成时间');
            $table->timestamps();

            $table->foreign('care_plan_id')->references('id')->on('care_plans')->cascadeOnDelete();
            $table->unique(['care_plan_id', 'planned_date'], 'uk_care_task_runs_plan_date');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('care_task_runs')) {
            Schema::dropIfExists('care_task_runs');
        }
    }
};
