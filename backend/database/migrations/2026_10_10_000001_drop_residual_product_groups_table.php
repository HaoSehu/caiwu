<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * M1 清除 product_groups 兼容表残留（结构整改批 2026-10-10）。
 *
 * 该表曾由 2026_08_14_000001 声明删除，但本库存在整批回灌残留（43 行，
 * updated_at 全部为 2026-07-15 14:15:44，此后零变更）。app/ 内无任何模型或
 * 查询绑定裸 product_groups 表（rg 实证零命中），商品分类真源为
 * first/second/third_product_groups 三层实体表，products.product_group_id
 * 外键指向 third_product_groups。
 * 数据归档：storage/app/archives/product_groups_20260813_235959.json。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_groups')) {
            return;
        }

        Schema::dropIfExists('product_groups');
    }

    public function down(): void
    {
        // 不自动恢复；如需重建请从归档 JSON 导入并重建结构与外键。
    }
};
