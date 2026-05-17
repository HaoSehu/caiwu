<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->string('filename', 255);
            $table->string('path', 500)->comment('相对路径，如 /uploads/content/20260419/cover_xxx.jpg');
            $table->string('url', 500)->comment('完整访问 URL');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0)->comment('文件大小(字节)');
            $table->unsignedInteger('width')->nullable()->comment('图片宽度');
            $table->unsignedInteger('height')->nullable()->comment('图片高度');
            $table->string('group', 50)->default('content')->comment('分组: content, avatar, brand 等');
            $table->unsignedBigInteger('uploaded_by')->default(0)->comment('上传管理员ID');
            $table->timestamps();

            $table->index('group');
            $table->index('uploaded_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
