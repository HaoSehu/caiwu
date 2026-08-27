<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 会员折扣矩阵的营销产品组：把商品圈进营销维度分组，
// 供 member_level_group_discounts 按 (等级, 营销组) 配置价格折扣。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_product_groups')) {
            return;
        }

        Schema::create('marketing_product_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50)->comment('营销组名称');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序值，越小越靠前');
            $table->timestamps();

            $table->unique('name', 'marketing_product_groups_name_unique');
            $table->index(['sort_order', 'id'], 'marketing_product_groups_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_product_groups');
    }
};
