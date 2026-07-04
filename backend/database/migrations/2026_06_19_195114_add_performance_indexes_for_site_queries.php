<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 添加 products 表补充索引（其他索引已存在）
        Schema::table('products', function (Blueprint $table) {
            // 检查索引是否存在，不存在才添加
            $indexes = collect(DB::select('SHOW INDEX FROM products'))->pluck('Key_name');

            if (! $indexes->contains('idx_product_second_status')) {
                $table->index(['second_product_group_id', 'status'], 'idx_product_second_status');
            }

            if (! $indexes->contains('idx_product_third_status')) {
                $table->index(['third_product_group_id', 'status'], 'idx_product_third_status');
            }
        });

        // 添加 content_articles 补充索引（其他索引已存在）
        Schema::table('content_articles', function (Blueprint $table) {
            $indexes = collect(DB::select('SHOW INDEX FROM content_articles'))->pluck('Key_name');

            if (! $indexes->contains('idx_article_published')) {
                $table->index(['status', 'publish_at', 'is_pinned'], 'idx_article_published');
            }

            if (! $indexes->contains('idx_article_category_published')) {
                $table->index(['category_id', 'status', 'publish_at'], 'idx_article_category_published');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $indexes = collect(DB::select('SHOW INDEX FROM products'))->pluck('Key_name');

            if ($indexes->contains('idx_product_second_status')) {
                $table->dropIndex('idx_product_second_status');
            }

            if ($indexes->contains('idx_product_third_status')) {
                $table->dropIndex('idx_product_third_status');
            }
        });

        Schema::table('content_articles', function (Blueprint $table) {
            $indexes = collect(DB::select('SHOW INDEX FROM content_articles'))->pluck('Key_name');

            if ($indexes->contains('idx_article_published')) {
                $table->dropIndex('idx_article_published');
            }

            if ($indexes->contains('idx_article_category_published')) {
                $table->dropIndex('idx_article_category_published');
            }
        });
    }
};
