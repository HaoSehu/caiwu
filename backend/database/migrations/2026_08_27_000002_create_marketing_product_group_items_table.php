<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 营销产品组的商品映射：一个商品可归入多个营销组；
// 计价时命中多条规则取最终价最低的一档。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_product_group_items')) {
            return;
        }

        Schema::create('marketing_product_group_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('marketing_product_group_id');
            $table->unsignedBigInteger('product_id');
            $table->timestamps();

            $table->unique(
                ['marketing_product_group_id', 'product_id'],
                'marketing_product_group_items_group_product_unique'
            );
            $table->index('product_id', 'marketing_product_group_items_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_product_group_items');
    }
};
