<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * 删除财务切换脚手架（2026-10-25 评审裁决：直接删）。
 *
 * - finance_cutover_inbox：切换窗口回调收件箱；切换已于 2026-10-21 完成，
 *   回调控制器 capture 分支与 config(finance.cutover) 随本批次移除；
 * - finance_migration_runs：finance:run-cutover 执行台账，命令一并退役。
 *
 * 两表当前均无业务写入；down() 不重建，结构见全库备份。
 */
return new class extends Migration
{
    private const TABLES = ['finance_cutover_inbox', 'finance_migration_runs'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
            }
        }
    }

    public function down(): void
    {
        // 切换脚手架不回滚；结构留存于 pre-destructive-20261025.sql。
    }
};
