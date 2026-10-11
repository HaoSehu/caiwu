<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M5 提成方案阶梯：计提基数 min_amount ≤ base < max_amount（max_amount NULL=∞）；
// unique(plan_id, min_amount)，区间重叠由服务层保存时校验。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_commission_tiers')) {
            return;
        }

        Schema::create('sales_commission_tiers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('plan_id')->comment('所属方案');
            $table->decimal('min_amount', 12, 2)->default(0.00)->comment('阶梯下限（含）');
            $table->decimal('max_amount', 12, 2)->nullable()->comment('阶梯上限（不含），空=∞');
            $table->decimal('rate', 5, 2)->default(0.00)->comment('提成比例（%）');
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('sales_commission_plans')->cascadeOnDelete();
            $table->unique(['plan_id', 'min_amount'], 'sales_commission_tiers_plan_min_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_commission_tiers');
    }
};
