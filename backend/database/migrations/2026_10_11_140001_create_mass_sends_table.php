<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M9 批量群发主表（功能模块扩展 2026-10-11）。
 *
 * 群发任务定义与进度台账：filter_json 客群条件、发送计数原子累加、
 * 状态机 0草稿→1待发送→2发送中→3已完成 / 1→4已取消。
 * template_id 与自定义 subject/content 二选一；营销类任务在目标生成与
 * 分片发送双侧剔除 users.marketing_alert=0 用户（双保险）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mass_sends')) {
            return;
        }

        Schema::create('mass_sends', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name', 100)->comment('任务名');
            $table->tinyInteger('channel')->comment('通道：1邮件 2站内信 3两者');
            $table->tinyInteger('is_marketing')->default(1)->comment('是否营销类：1是 0否（营销类剔除 marketing_alert=0 用户）');
            $table->unsignedBigInteger('template_id')->nullable()->comment('套用模板 notification_templates.id，与自定义内容二选一');
            $table->string('subject', 200)->nullable()->comment('自定义邮件主题/站内信标题（未套模板时必填）');
            $table->text('content')->nullable()->comment('自定义正文（未套模板时必填），支持 {{nickname}}/{{email}} 变量');
            $table->json('filter_json')->comment('客群条件（UserSegmentService 白名单键）');
            $table->unsignedInteger('total_count')->default(0)->comment('目标总数（start 时试算落库）');
            $table->unsignedInteger('sent_count')->default(0)->comment('成功数（分片原子累加）');
            $table->unsignedInteger('failed_count')->default(0)->comment('失败数（分片原子累加）');
            $table->tinyInteger('status')->default(0)->comment('0草稿 1待发送 2发送中 3已完成 4已取消');
            $table->dateTime('scheduled_at')->nullable()->comment('定时发送时间（NULL=立即）');
            $table->dateTime('started_at')->nullable()->comment('开始发送时间');
            $table->dateTime('finished_at')->nullable()->comment('完成/终止时间');
            $table->unsignedBigInteger('created_by')->comment('创建管理员 admin_users.id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('template_id')->references('id')->on('notification_templates')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('admin_users')->restrictOnDelete();
            $table->index(['status', 'scheduled_at'], 'idx_mass_sends_status_scheduled');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('mass_sends')) {
            Schema::dropIfExists('mass_sends');
        }
    }
};
