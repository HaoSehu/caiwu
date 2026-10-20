<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 切换运行记录：每次 finance:rebuild 落一行，保存阶段、闸门结果与统计摘要。
 *
 * 用途：切换窗口内可回答「这次重建跑了多久、每个闸门是否通过、写了多少行」；
 * stats 只存摘要，不存逐行数据。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_migration_runs')) {
            return;
        }

        Schema::create('finance_migration_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('run_no', 40)->comment('运行号：MR+时间+随机');
            $table->string('phase', 32)->comment('阶段：snapshot/accounts/ledger/lots/calibration/refunds/verify');
            $table->string('status', 16)->default('running')->comment('状态：running/passed/failed/aborted');
            $table->json('stats')->nullable()->comment('阶段统计摘要与闸门结果');
            $table->string('message', 500)->nullable()->comment('失败原因或阶段说明');
            $table->dateTime('started_at')->comment('开始时间');
            $table->dateTime('finished_at')->nullable()->comment('结束时间');
            $table->timestamps();

            $table->unique(['run_no', 'phase'], 'finance_migration_runs_run_phase_unique');
            $table->index(['phase', 'started_at'], 'finance_migration_runs_phase_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_migration_runs');
    }
};
