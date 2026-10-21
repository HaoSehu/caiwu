<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 补系统升级账本的表级中文注释。
 *
 * 背景：2026_10_21_000024 创建 system_installation / system_upgrade_runs 时只写了列注释，
 * 漏了表注释。本迁移只做 `ALTER TABLE ... COMMENT`，是元数据级操作，不重建表、不改数据。
 *
 * 幂等：先读取 information_schema 判断当前注释是否已一致，一致则跳过，避免重复执行。
 */
return new class extends Migration
{
    private const COMMENTS = [
        'system_installation' => '系统安装版本账本：单行记录数据库最后成功升级到的代码版本',
        'system_upgrade_runs' => '系统升级执行记录：每次升级的来源/目标版本、状态、备份与失败摘要',
    ];

    public function up(): void
    {
        foreach (self::COMMENTS as $table => $comment) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $current = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('TABLE_COMMENT');

            if ((string) $current === $comment) {
                continue;
            }

            DB::statement(sprintf('ALTER TABLE `%s` COMMENT = %s', $table, DB::getPdo()->quote($comment)));
        }
    }

    public function down(): void
    {
        // 注释属于说明性元数据，回滚时保留，避免丢失业务说明。
    }
};
