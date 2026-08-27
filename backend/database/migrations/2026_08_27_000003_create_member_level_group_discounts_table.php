<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 会员等级 × 营销产品组 折扣矩阵：
// discount_type=1 百分比时 discount_value 为「折后保留比例」（bates 语义，90=九折），
// 与优惠券 percentage 的「减免比例」语义相反；
// discount_type=2 固定金额时 discount_value 为直接减免额，减完下限为 0。
// 未配置行的组合不打折；缺失即无折扣，不设默认档。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_level_group_discounts')) {
            return;
        }

        Schema::create('member_level_group_discounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('member_level_id')->comment('关联 member_levels.id');
            $table->unsignedBigInteger('marketing_product_group_id')->comment('关联 marketing_product_groups.id');
            $table->tinyInteger('discount_type')->comment('1=百分比(折后保留,bates语义) 2=固定金额减免');
            $table->decimal('discount_value', 12, 2)->default(100.00)->comment('百分比时0-100；固定金额时减免额');
            $table->timestamps();

            $table->unique(
                ['member_level_id', 'marketing_product_group_id'],
                'member_level_group_discounts_level_group_unique'
            );
            $table->index('marketing_product_group_id', 'member_level_group_discounts_group_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_level_group_discounts');
    }
};
