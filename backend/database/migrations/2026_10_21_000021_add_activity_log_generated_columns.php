<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * activity_logs 高频 JSON 键物化为生成列并建索引（数据库结构分析报告 P1-9）。
 *
 * 现状：AdminLogService 有 10+ 处 JSON_EXTRACT(context, '$.status'/'$.request_id')
 * 过滤与聚合，activity_logs 是百万行级表，这些条件完全走不了索引，只能全表扫。
 *
 * 方案：用 MySQL 8 的 STORED 生成列把两个高频键从 JSON 中解析出来，并为
 * status 建索引。生成列由数据库自动维护，写入侧无需改动、不会出现漏写。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_logs') || ! $this->supportsGeneratedColumns()) {
            return;
        }

        // context.status 混有非 HTTP 状态码的字符串（如 'approved'），直接 CAST 会让
        // 建列失败（严格模式下 1292），因此只在「纯数字」时才落值，其余为 NULL。
        if (! Schema::hasColumn('activity_logs', 'context_status')) {
            DB::statement("
                ALTER TABLE activity_logs
                ADD COLUMN context_status SMALLINT UNSIGNED
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN JSON_UNQUOTE(JSON_EXTRACT(context, '$.status')) REGEXP '^[0-9]{1,5}$'
                            THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(context, '$.status')) AS UNSIGNED)
                            ELSE NULL
                        END
                    ) STORED
                    COMMENT '从 context.status 解析的 HTTP 状态码（生成列，供索引过滤）'
            ");
        }

        if (! Schema::hasColumn('activity_logs', 'context_request_id')) {
            DB::statement("
                ALTER TABLE activity_logs
                ADD COLUMN context_request_id VARCHAR(64)
                    GENERATED ALWAYS AS (LEFT(JSON_UNQUOTE(JSON_EXTRACT(context, '$.request_id')), 64)) STORED
                    COMMENT '从 context.request_id 解析的链路标识（生成列，截断 64）'
            ");
        }

        $this->addIndexIfMissing('activity_logs', 'activity_logs_context_status_idx', 'context_status');
        $this->addIndexIfMissing('activity_logs', 'activity_logs_context_request_id_idx', 'context_request_id');
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        foreach (['activity_logs_context_status_idx', 'activity_logs_context_request_id_idx'] as $index) {
            if ($this->indexExists('activity_logs', $index)) {
                Schema::table('activity_logs', fn (Blueprint $table) => $table->dropIndex($index));
            }
        }

        $columns = array_values(array_filter(
            ['context_status', 'context_request_id'],
            fn (string $column): bool => Schema::hasColumn('activity_logs', $column),
        ));

        if ($columns !== []) {
            Schema::table('activity_logs', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }

    private function supportsGeneratedColumns(): bool
    {
        try {
            $version = (string) DB::selectOne('select version() as v')->v;
        } catch (Throwable) {
            return false;
        }

        return version_compare($version, '5.7.0', '>=');
    }

    private function addIndexIfMissing(string $table, string $index, string $column): void
    {
        if ($this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($column, $index));
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'select count(*) as c from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ?',
            [$table, $index],
        );

        return (int) ($rows[0]->c ?? 0) > 0;
    }
};
