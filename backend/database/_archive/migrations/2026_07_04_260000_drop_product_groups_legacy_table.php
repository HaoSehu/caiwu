<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * product_groups 遗留表最终清理：两步合并迁移
 *
 * 前置条件（已验证）：
 *   - BackfillProductGroupHierarchyCommand 已执行完毕
 *   - 126 个非删除产品全部有 first/second_product_group_id 映射
 *   - second_product_groups.legacy_product_group_id 保留了 product_groups 的历史 ID 映射
 *
 * 执行内容：
 *   1. DROP products.product_group_id 列及其外键约束
 *   2. DROP product_groups 表（37 行遗留数据，已完全映射到新三级体系）
 */
return new class extends Migration
{
    public function up(): void
    {
        // 步骤1：清理 products.product_group_id
        if (Schema::hasColumn('products', 'product_group_id')) {
            Schema::table('products', function (Blueprint $table) {
                // 先删外键
                $fks = $this->getForeignKeys('products');
                if (in_array('fk_products_product_group_id', $fks, true)) {
                    $table->dropForeign('fk_products_product_group_id');
                }

                // 删除遗留索引（如存在）
                if ($this->indexExists('products', 'products_group_status_sort_id_idx')) {
                    $table->dropIndex('products_group_status_sort_id_idx');
                }

                // 删除列
                $table->dropColumn('product_group_id');
            });
        }

        // 步骤2：DROP product_groups 表
        Schema::dropIfExists('product_groups');
    }

    public function down(): void
    {
        // 恢复 product_groups 表（结构）
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_group_id')->nullable()->index();
            $table->string('product_type', 50)->default('other');
            $table->string('name', 100);
            $table->string('slogan', 255)->nullable();
            $table->string('slug', 100)->unique();
            $table->integer('sort_order')->default(0);
            $table->tinyInteger('is_visible')->default(1);
            $table->timestamps();
        });

        // 恢复 products.product_group_id 列（无原始数据）
        if (! Schema::hasColumn('products', 'product_group_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedBigInteger('product_group_id')
                    ->nullable()
                    ->after('id')
                    ->comment('旧商品分组ID，已废弃，恢复后无数据');
                $table->foreign('product_group_id', 'fk_products_product_group_id')
                    ->references('id')
                    ->on('product_groups')
                    ->onDelete('restrict');
                $table->index(['product_group_id', 'status', 'sort_order', 'id'],
                    'products_group_status_sort_id_idx');
            });
        }
    }

    /** @return string[] */
    private function getForeignKeys(string $table): array
    {
        $rows = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$table]);

        return array_column($rows, 'CONSTRAINT_NAME');
    }

    private function indexExists(string $table, string $index): bool
    {
        return ! empty(DB::select('
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND index_name = ?
            LIMIT 1
        ', [$table, $index]));
    }
};
