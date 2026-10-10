<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3 为营销分组域补齐数据库级外键（结构整改批 2026-10-10）。
 *
 * 实库孤儿数已核为 0；ON DELETE RESTRICT 与 MarketingProductGroupService::delete()
 * 的业务守卫（组内仍有商品/折扣即拒删）保持一致，CASCADE 反而会绕过业务保护。
 * member_level_group_discounts / marketing_product_group_items 增删改为整包替换裸写，
 * 数据库级约束作为最后一道防线。
 * 所需索引已存在：两表唯一键首列覆盖 group/level 外键，product_id 有独立索引。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_product_group_items')
            && Schema::hasTable('marketing_product_groups')
            && Schema::hasTable('products')
            && ! $this->hasForeignKey('marketing_product_group_items', 'marketing_product_group_id')) {
            Schema::table('marketing_product_group_items', function (Blueprint $table): void {
                $table->foreign('marketing_product_group_id', 'fk_mpgi_group')
                    ->references('id')->on('marketing_product_groups')->onDelete('restrict');
                $table->foreign('product_id', 'fk_mpgi_product')
                    ->references('id')->on('products')->onDelete('restrict');
            });
        }

        if (Schema::hasTable('member_level_group_discounts')
            && Schema::hasTable('member_levels')
            && Schema::hasTable('marketing_product_groups')
            && ! $this->hasForeignKey('member_level_group_discounts', 'member_level_id')) {
            Schema::table('member_level_group_discounts', function (Blueprint $table): void {
                $table->foreign('member_level_id', 'fk_mlgd_level')
                    ->references('id')->on('member_levels')->onDelete('restrict');
                $table->foreign('marketing_product_group_id', 'fk_mlgd_group')
                    ->references('id')->on('marketing_product_groups')->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketing_product_group_items')) {
            Schema::table('marketing_product_group_items', function (Blueprint $table): void {
                $table->dropForeign('fk_mpgi_group');
                $table->dropForeign('fk_mpgi_product');
            });
        }

        if (Schema::hasTable('member_level_group_discounts')) {
            Schema::table('member_level_group_discounts', function (Blueprint $table): void {
                $table->dropForeign('fk_mlgd_level');
                $table->dropForeign('fk_mlgd_group');
            });
        }
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            $columns = is_array($foreignKey['columns'] ?? null) ? $foreignKey['columns'] : [];
            if (in_array($column, $columns, true)) {
                return true;
            }
        }

        return false;
    }
};
