<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M5 按产品差异化费率：rate（比例）与 fixed_amount（固定金额）二选一，
// fixed_amount 优先于 rate；产品费率命中优先于阶梯。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_commission_product_rates')) {
            return;
        }

        Schema::create('sales_commission_product_rates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('plan_id')->comment('所属方案');
            $table->unsignedBigInteger('product_id')->comment('商品 products.id');
            $table->decimal('rate', 5, 2)->nullable()->comment('提成比例（%），与 fixed_amount 二选一');
            $table->decimal('fixed_amount', 12, 2)->nullable()->comment('固定提成金额，优先于 rate');
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('sales_commission_plans')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['plan_id', 'product_id'], 'sales_commission_product_rates_plan_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_commission_product_rates');
    }
};
