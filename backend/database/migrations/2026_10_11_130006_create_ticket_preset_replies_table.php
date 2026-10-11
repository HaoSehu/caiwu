<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M2 工单预设回复库：预设回复模板，支持占位符渲染；department_id 依赖 M3 部门表（NULL=通用）。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_preset_replies')) {
            return;
        }

        Schema::create('ticket_preset_replies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable()->comment('分类 ID，NULL=未分类（分类删除后置 NULL）');
            $table->unsignedBigInteger('department_id')->nullable()->comment('限定部门 ID，NULL=通用');
            $table->string('title', 100)->comment('模板名');
            $table->text('content')->comment('内容，支持 {{user_email}} 等占位符');
            $table->tinyInteger('is_enabled')->default(1)->comment('0=禁用 1=启用');
            $table->integer('sort_order')->default(0)->comment('排序值，越小越靠前');
            $table->unsignedInteger('usage_count')->default(0)->comment('使用次数（原子自增）');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人 admin_users.id');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'is_enabled'], 'idx_ticket_preset_replies_category_enabled');
            $table->index(['department_id', 'is_enabled'], 'idx_ticket_preset_replies_department_enabled');
            $table->foreign('category_id', 'fk_ticket_preset_replies_category')
                ->references('id')->on('ticket_preset_reply_categories')->nullOnDelete();
            $table->foreign('department_id', 'fk_ticket_preset_replies_department')
                ->references('id')->on('ticket_departments')->nullOnDelete();
            $table->foreign('created_by', 'fk_ticket_preset_replies_created_by')
                ->references('id')->on('admin_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_preset_replies');
    }
};
