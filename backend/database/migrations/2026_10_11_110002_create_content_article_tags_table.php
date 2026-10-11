<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M8 文章-标签 pivot：unique(article_id, tag_id) 防重复绑定；
// article/tag 均级联删除（物理删除场景）；软删文章的行保留，由查询侧 join deleted_at 排除。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('content_article_tags')) {
            return;
        }

        Schema::create('content_article_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('content_articles')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('content_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['article_id', 'tag_id'], 'uk_content_article_tags_article_tag');
            $table->index('tag_id', 'idx_content_article_tags_tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_article_tags');
    }
};
