<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 系统升级账本：数据库最后成功升级到的代码版本，以及每一次升级的执行记录。
 *
 * 结构说明：
 * - `system_installation` 为单行表（主键固定 1），保存数据库实际落地的代码版本；
 *   版本清单本体在项目根的 version.json，两者分离是为了让「代码包版本」与
 *   「数据库已升级到的版本」可以独立比较，从而识别漏升级与误降级。
 * - `system_upgrade_runs` 每次 db:upgrade 一条，记录来源/目标版本、待执行与实执行
 *   迁移数、备份路径和失败摘要；迁移失败时 app_version 不推进，靠此表追溯。
 *
 * 不建立外键：账本独立于任何业务表，避免业务表重建时影响升级历史。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_installation')) {
            Schema::create('system_installation', function (Blueprint $table): void {
                $table->tinyInteger('id')->unsigned()->comment('安装记录主键，固定为 1');
                $table->string('app_version', 32)->comment('数据库最后成功升级到的代码版本号');
                $table->timestamp('adopted_at')->nullable()->comment('首次采纳既有数据库的时间，空表示未采纳');
                $table->timestamp('upgraded_at')->nullable()->comment('最近一次成功升级完成时间');
                $table->timestamps();

                $table->primary('id');
            });
        }

        if (! Schema::hasTable('system_upgrade_runs')) {
            Schema::create('system_upgrade_runs', function (Blueprint $table): void {
                $table->id()->comment('升级记录自增主键');
                $table->string('from_version', 32)->nullable()->comment('来源代码版本；首次采纳时为 NULL');
                $table->string('to_version', 32)->comment('目标代码版本');
                $table->string('status', 20)->comment('执行状态：running 执行中 / completed 成功 / failed 未产生结构变更的失败 / recovery_required 已产生结构变更需人工恢复 / adopted 首次采纳');
                $table->unsignedInteger('pending_count')->default(0)->comment('本次开始时的待执行迁移数量');
                $table->unsignedInteger('executed_count')->default(0)->comment('本次实际执行的迁移数量');
                $table->json('executed_migrations')->nullable()->comment('本次执行的迁移文件名列表（JSON 数组）');
                $table->string('backup_path', 500)->nullable()->comment('升级前数据库备份文件的绝对路径；跳过备份时为 NULL');
                $table->text('error_message')->nullable()->comment('失败摘要，最多保留 1000 字符');
                $table->string('operator', 50)->nullable()->comment('执行升级的命令行操作者，系统任务记为 SYSTEM');
                $table->string('client_ip', 45)->nullable()->comment('操作者 IP；命令行执行时为 NULL');
                $table->timestamp('started_at')->comment('升级开始时间');
                $table->timestamp('finished_at')->nullable()->comment('升级结束时间，未结束时为 NULL');
                $table->timestamps();

                $table->index(['status', 'started_at'], 'system_upgrade_runs_status_started_idx');
                $table->index('started_at', 'system_upgrade_runs_started_idx');
            });
        }
    }

    public function down(): void
    {
        // 升级账本是审计链路的一部分，不提供自动回滚删除，避免丢失升级历史。
        // 如需清理，请先人工导出 system_upgrade_runs 全量记录。
    }
};
