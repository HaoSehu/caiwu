<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_run_logs', function (Blueprint $table) {
            $table->id();
            $table->string('task_name', 100)->comment('任务名称');
            $table->string('status', 20)->default('success')->comment('执行状态: success, failed, skipped');
            $table->unsignedInteger('duration_ms')->default(0)->comment('执行耗时(毫秒)');
            $table->json('summary')->nullable()->comment('执行摘要数据');
            $table->text('error_msg')->nullable()->comment('错误信息');
            $table->timestamp('started_at')->nullable()->comment('开始时间');
            $table->timestamp('finished_at')->nullable()->comment('结束时间');
            $table->timestamps();

            $table->index('task_name');
            $table->index('status');
            $table->index('created_at');
            $table->index(['task_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_run_logs');
    }
};
