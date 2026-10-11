<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 运营商模板生命周期对齐 ZJMF 模式（照抄 ZJMF 重构 2026-10-11）。
 *
 * ZJMF 的 message_template 生命周期：本地创建（template_id 为空、status=0 未提交）
 * → 提交运营商审核（createCnTemplate 回填运营商 ID，status=1 审核中）
 * → 回查（status=2 通过 / 3 驳回）。本地草稿期没有运营商模板 ID。
 *
 * 对应调整：sms_provider_templates.provider_template_id 改为可空——
 * draft（未提交）模板无运营商 ID；MySQL 唯一键允许多个 NULL，
 * 多条 draft 不会互相冲突。status 值域新增 draft（未提交），
 * 与 ZJMF status=0 对齐；pending 语义不变（审核中）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sms_provider_templates')) {
            return;
        }

        Schema::table('sms_provider_templates', function (Blueprint $table): void {
            $table->string('provider_template_id', 120)->nullable()->comment(
                '运营商原生模板 ID（SMS_xxx / 数字码）；draft 未提交模板为 NULL'
            )->change();
            $table->string('title', 120)->default('')->after('plugin_key')
                ->comment('模板名称（提交运营商审核时作为 TemplateName，对齐 ZJMF message_template.title）');
            $table->string('remark', 500)->default('')->after('content')
                ->comment('申请备注（提交运营商审核时作为 Remark，对齐 ZJMF message_template.remark）');
        });

        DB::statement(
            "ALTER TABLE `sms_provider_templates` MODIFY `status` varchar(20) NOT NULL DEFAULT 'draft' "
            ."COMMENT '生命周期：draft=未提交（本地草稿） pending=审核中 approved=通过（解析唯一有效态） rejected=驳回 deleted=运营商侧已删除'"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('sms_provider_templates')) {
            return;
        }

        Schema::table('sms_provider_templates', function (Blueprint $table): void {
            $table->string('provider_template_id', 120)->comment(
                '运营商原生模板 ID（SMS_xxx / 数字码，格式因运营商而异）'
            )->change();
            $table->dropColumn(['title', 'remark']);
        });

        DB::statement(
            "ALTER TABLE `sms_provider_templates` MODIFY `status` varchar(20) NOT NULL DEFAULT 'pending' "
            ."COMMENT '审核状态：pending=审核中 approved=通过（解析唯一有效态） rejected=驳回 deleted=运营商侧已删除'"
        );
    }
};
