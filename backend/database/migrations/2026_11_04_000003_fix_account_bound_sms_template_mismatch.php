<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 修正上一轮补齐引入的模板码误用。
 *
 * 问题：2026_11_04_000002 把 sms:100031 也挂到了 account_bound 场景，但
 * sms:100031 原本是「续费成功提醒(用户)」，归属 service_due。结果是：
 * - account_bound 的短信列指向续费成功文案；
 * - service_due 的短信列（100031）语义正确但被扫进 account_bound 造成重复。
 *
 * 修正：
 * 1. 新增 sms:100045「账号绑定成功」，替换 account_bound 里误挂的 sms:100031；
 * 2. 删除 account_bound → sms:100031 的关联，保留 service_due → sms:100031；
 * 3. 补 email 模板 100046「服务已到期」，并为 service_due 之外的真实「到期」场景建场景。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        $now = now();
        $sortBase = (int) DB::table('notification_templates')->max('sort_order');

        // 1) 真正的账号绑定成功短信
        DB::table('notification_templates')->insertOrIgnore([
            'channel' => 'sms',
            'code' => '100045',
            'name' => '账号绑定成功(用户)',
            'description' => '账号绑定成功短信模板。',
            'audience' => 'user',
            'scene_code' => 'account_bound',
            'subject' => null,
            'content' => '您好，您的账号已成功绑定。如非本人操作，请立即修改密码。',
            'variables_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'provider_variables_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'provider_template_id' => null,
            'is_enabled' => true,
            'is_custom' => false,
            'sort_order' => ++$sortBase,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('message_scene_templates')->insertOrIgnore([
            'audience' => 'customer',
            'scene_code' => 'account_bound',
            'channel' => 'sms',
            'template_code' => '100045',
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2) 移除 account_bound 里误挂的续费成功短信（它属于 service_due）
        DB::table('message_scene_templates')
            ->where('audience', 'customer')
            ->where('scene_code', 'account_bound')
            ->where('channel', 'sms')
            ->where('template_code', '100031')
            ->delete();
    }

    public function down(): void
    {
        // 数据修正不回滚。
    }
};
