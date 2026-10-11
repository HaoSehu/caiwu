<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M8 知识库标签：文章打标签的字典表；
// usage_count 为 pivot 写入路径维护的排序缓存（标签云展示以查询侧实时统计未删文章为准）。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('content_tags')) {
            return;
        }

        Schema::create('content_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50)->unique()->comment('标签名（trim+全半角归一后唯一）');
            $table->integer('usage_count')->default(0)->comment('使用计数缓存：绑定文章数（含软删口径，展示排序用）');
            $table->integer('sort_order')->default(0)->comment('排序值，越小越靠前');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_tags');
    }
};
