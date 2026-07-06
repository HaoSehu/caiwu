<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M3/M4 — 删除完全重复的索引，减少写放大
 *
 * 以下索引与已有索引完全覆盖，属于冗余：
 *   - user_coupons.user_coupons_user_status_uidx    → 与 user_coupons_user_status_idx 重复
 *   - second_product_groups.idx_second_product_groups_first_visible_sort
 *                                                   → 与 idx_second_group_first_visible_sort 重复
 *   - third_product_groups.idx_third_product_groups_second_visible_sort
 *                                                   → 与 idx_third_group_second_visible_sort 重复
 */
return new class extends Migration
{
    /**
     * 删除冗余索引。
     * 每次 DROP 均先查询 information_schema 确认索引存在，防止不存在时报错。
     */
    public function up(): void
    {
        // 1. user_coupons — 删除冗余索引 user_coupons_user_status_uidx
        $this->dropIndexIfExists('user_coupons', 'user_coupons_user_status_uidx');

        // 2. second_product_groups — 删除冗余索引 idx_second_product_groups_first_visible_sort
        $this->dropIndexIfExists(
            'second_product_groups',
            'idx_second_product_groups_first_visible_sort'
        );

        // 3. third_product_groups — 删除冗余索引 idx_third_product_groups_second_visible_sort
        $this->dropIndexIfExists(
            'third_product_groups',
            'idx_third_product_groups_second_visible_sort'
        );
    }

    /**
     * 回滚：重建三个被删除的冗余索引。
     */
    public function down(): void
    {
        // 1. 重建 user_coupons_user_status_uidx
        if (Schema::hasTable('user_coupons') && ! $this->indexExists('user_coupons', 'user_coupons_user_status_uidx')) {
            Schema::table('user_coupons', function (Blueprint $table) {
                $table->index(['user_id', 'status'], 'user_coupons_user_status_uidx');
            });
        }

        // 2. 重建 idx_second_product_groups_first_visible_sort
        if (
            Schema::hasTable('second_product_groups') &&
            ! $this->indexExists('second_product_groups', 'idx_second_product_groups_first_visible_sort')
        ) {
            Schema::table('second_product_groups', function (Blueprint $table) {
                $table->index(
                    ['first_product_group_id', 'is_visible', 'sort_order'],
                    'idx_second_product_groups_first_visible_sort'
                );
            });
        }

        // 3. 重建 idx_third_product_groups_second_visible_sort
        if (
            Schema::hasTable('third_product_groups') &&
            ! $this->indexExists('third_product_groups', 'idx_third_product_groups_second_visible_sort')
        ) {
            Schema::table('third_product_groups', function (Blueprint $table) {
                $table->index(
                    ['second_product_group_id', 'is_visible', 'sort_order'],
                    'idx_third_product_groups_second_visible_sort'
                );
            });
        }
    }

    /**
     * 若索引存在则执行 DROP INDEX，不存在则跳过。
     */
    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            try {
                DB::statement(
                    "ALTER TABLE `{$tableName}` DROP INDEX `{$indexName}`"
                );
            } catch (Throwable $e) {
                // 并发场景下可能已被删除，忽略异常
            }
        }
    }

    /**
     * 查询 information_schema 判断索引是否存在。
     */
    private function indexExists(string $tableName, string $indexName): bool
    {
        $count = DB::selectOne('
            SELECT COUNT(*) AS cnt
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = ?
              AND INDEX_NAME   = ?
        ', [$tableName, $indexName]);

        return $count && (int) $count->cnt > 0;
    }
};
