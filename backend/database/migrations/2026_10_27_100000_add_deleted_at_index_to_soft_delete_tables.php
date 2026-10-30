<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 软删表补 deleted_at 索引（D-12）。
 *
 * 背景：content_articles / products / users 都启用了 SoftDeletes，Eloquent 全局
 * 作用域会给几乎每次查询拼上 `deleted_at IS NULL`；而这三张表唯独 deleted_at 没有
 * 索引，数据量上来后只能全表扫。coupons 在 2026_10_21_000022 已建
 * `coupons_deleted_status_idx`，此处不重复处理。
 *
 * 索引单独建在 deleted_at 上而不是复合索引：软删过滤几乎出现在所有查询，放在
 * 复合索引首列会打乱既有索引选择；MySQL 8 对 IS NULL 走索引，单列即可命中。
 *
 * 幂等：DDL 不随事务回滚，逐表判断索引存在性后再建，部分生效后可安全重跑。
 */
return new class extends Migration
{
    /** @var array<string, string> 表名 => 索引名 */
    private array $tables = [
        'content_articles' => 'content_articles_deleted_at_idx',
        'products' => 'products_deleted_at_idx',
        'users' => 'users_deleted_at_idx',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $index) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            if (Schema::hasIndex($table, $index)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                $blueprint->index('deleted_at', $index);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $index) {
            if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $index)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                $blueprint->dropIndex($index);
            });
        }
    }
};
