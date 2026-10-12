<?php

declare(strict_types=1);

use App\Support\EmailNotificationTemplateDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 修复邮件模板 100004「绑定通知」变更时间重复显示。
 *
 * 默认模板「变更时间」行原为 {{bound_at}}{{changed_at}} 双通道占位（设计意图是
 * 两个场景二选一），但密码/手机/邮箱变更三个发送场景均同时传入两个变量且同值，
 * 渲染后时间字符串拼接显示两遍。真源 EmailNotificationTemplateDefaults 已收敛为
 * 单变量 {{changed_at}}（variables 同步移除 bound_at，发送侧不再传 bound_at），
 * 本迁移将库内 is_custom=0 的同码模板同步为该默认值。
 *
 * 管理员自定义模板（is_custom=1）不动；若自定义正文仍含 {{bound_at}}，
 * 发送侧已不提供该变量，占位符将渲染为空。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        $template = null;

        foreach (EmailNotificationTemplateDefaults::templates() as $candidate) {
            if ((string) ($candidate['code'] ?? '') === '100004') {
                $template = $candidate;

                break;
            }
        }

        if ($template === null) {
            return;
        }

        DB::table('notification_templates')
            ->where('channel', 'email')
            ->where('code', '100004')
            ->where('is_custom', 0)
            ->update([
                'content' => (string) $template['content'],
                'variables_json' => json_encode($template['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // 模板修复为单向同步，不做回滚（与 2026_08_23 默认值同步迁移口径一致）。
    }
};
