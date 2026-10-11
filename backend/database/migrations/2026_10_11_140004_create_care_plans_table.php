<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M12 客户关怀计划定义表。
 *
 * type 决定目标口径：1生日（birth_date 月日 + offset）、2到期提醒（服务
 * expires_at 提前 offset 天）、3流失召回（last_login_at 早于 date-offset 且
 * 名下无 active 服务）、4自定义（仅 filter_json）。目标叠加 filter_json
 * （UserSegmentService）。到期提醒与 BillingAutomationService 的既有续费提醒
 * 并存会双发，上线须二选一（见 INTEGRATION.md 去重口径）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('care_plans')) {
            return;
        }

        Schema::create('care_plans', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name', 100)->comment('计划名');
            $table->tinyInteger('type')->comment('1生日祝福 2到期提醒 3流失召回 4自定义');
            $table->integer('offset_days')->default(0)->comment('生日：提前N天(负=延后)；到期：提前N天；召回：沉默N天');
            $table->tinyInteger('channel')->comment('通道：1邮件 2站内信 3两者（同 M9）');
            $table->unsignedBigInteger('template_id')->nullable()->comment('套用模板 notification_templates.id');
            $table->string('subject', 200)->nullable()->comment('自定义邮件主题/站内信标题（未套模板时必填）');
            $table->text('content')->nullable()->comment('自定义正文（未套模板时必填），支持 {{nickname}}/{{email}} 变量');
            $table->json('filter_json')->nullable()->comment('叠加客群过滤（UserSegmentService 白名单键，NULL=不限）');
            $table->tinyInteger('is_enabled')->default(0)->comment('是否启用：1启用 0停用');
            $table->date('last_planned_date')->nullable()->comment('最近一次计划执行的目标日期');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('template_id')->references('id')->on('notification_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('care_plans')) {
            Schema::dropIfExists('care_plans');
        }
    }
};
