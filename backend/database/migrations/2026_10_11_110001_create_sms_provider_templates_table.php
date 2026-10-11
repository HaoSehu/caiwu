<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 短信运营商模板映射表（短信模板管理机制 2026-10-11）。
 *
 * 短信模板需经运营商审核，系统不内置模板。本表是「业务模板 → 运营商模板」的
 * 映射层：由短信插件经 sms.templates.pull/push action 从运营商 API 同步，
 * 平台侧统一 upsert（插件只做协议翻译，不落库）。
 *
 * 列语义：
 * - plugin_key：短信插件 key（字符串关联，不外键——插件卸载不硬删映射，
 *   历史审计与换插件回看由查询侧按 status 自然失效）。
 * - business_code：映射的 notification_templates.code（channel=sms，如续费提醒
 *   100003）；可空 = 运营商侧已审核但尚未挂接业务码（pull-only 模板）。
 * - provider_template_id：运营商原生模板 ID（阿里云 SMS_xxx / 号码认证数字码 /
 *   腾讯云 19 位数字码），格式因运营商而异。注意：与 SmsTemplateCatalog 业务码
 *   100001~100033 数值可能撞车但属不同命名空间，靠本列与 business_code 的
 *   格式差异区分。
 * - status：pending=运营商审核中 approved=审核通过（发送解析唯一有效态）
 *   rejected=审核驳回（reject_reason 回显管理端） deleted=运营商侧已删除
 *   （pull 删除探测置入，发送解析必须排除）。
 *
 * 唯一键 (plugin_key, provider_template_id)：同一运营商模板只留一行；
 * 同一业务码允许多条映射（多签名/新旧版本并存），发送解析取 approved 中
 * synced_at 最新（并列按 id）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sms_provider_templates')) {
            return;
        }

        Schema::create('sms_provider_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('plugin_key', 64)
                ->comment('短信插件 key（字符串关联，不外键；卸载插件不硬删本表）');
            $table->string('business_code', 32)->nullable()
                ->comment('映射的 notification_templates.code（channel=sms）；NULL=未挂接业务码');
            $table->string('provider_template_id', 120)
                ->comment('运营商原生模板 ID（SMS_xxx / 数字码，格式因运营商而异）');
            $table->string('provider_sign', 64)->default('')
                ->comment('短信签名（部分运营商模板与签名成对提交）');
            $table->longText('content')
                ->comment('运营商侧模板原文（${var} 占位语法，与本地 {{var}} 语法不同）');
            $table->json('variables_json')->nullable()
                ->comment('运营商模板变量名列表（发送解析时与渲染参数求交集校验）');
            $table->string('status', 20)->default('pending')
                ->comment('审核状态：pending=审核中 approved=通过（解析唯一有效态） rejected=驳回 deleted=运营商侧已删除');
            $table->string('reject_reason', 500)->default('')
                ->comment('审核驳回原因（管理端回显）');
            $table->json('raw_json')->nullable()
                ->comment('运营商原始响应摘要（脱敏后，仅审计用）');
            $table->timestamp('synced_at')->nullable()
                ->comment('最近一次同步时间（approved 多条时按此取最新，并列按 id）');
            $table->timestamps();

            $table->unique(['plugin_key', 'provider_template_id'], 'sms_provider_templates_plugin_template_unique');
            $table->index(['plugin_key', 'business_code', 'status'], 'sms_provider_templates_lookup_index');
            $table->comment('短信运营商模板映射（插件同步运营商 API 审核状态，发送解析唯一有效态为 approved）');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_provider_templates');
    }
};
