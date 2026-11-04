<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 补齐「服务已到期」消息类型（对齐 howstack 的 service_expired）。
 *
 * 背景：caiwu 此前只有 service_due（到期前提醒）与 service_suspended（到期后暂停），
 * 缺少「刚刚到期、尚未暂停」这一档的独立通知。howstack 把三者拆成独立场景，
 * 用户要求消息类型完整，本迁移补上 service_expired 的邮件与短信模板。
 *
 * 同时修正 2026_11_04_000002 的模板码误用：sms:100031 属于 service_due（续费成功），
 * 被误挂到 account_bound；本迁移由 2026_11_04_000003 单独修正。
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
        $variables = ['service_name', 'product_name', 'expires_at'];
        $encoded = json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        DB::table('notification_templates')->insertOrIgnore([
            'channel' => 'email',
            'code' => '100046',
            'name' => '服务已到期通知',
            'description' => '服务到期后、暂停前发送通知。',
            'audience' => 'user',
            'scene_code' => 'service_expired',
            'subject' => '【{{site_name}}】服务已到期通知',
            'content' => '<p>{{display_name}}，您的服务 {{service_name}} 已于 {{expires_at}} 到期。</p><p>请尽快续费以恢复使用，逾期未续费将被暂停。</p>',
            'variables_json' => json_encode(['display_name', ...$variables], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'provider_variables_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'provider_template_id' => null,
            'is_enabled' => true,
            'is_custom' => false,
            'sort_order' => ++$sortBase,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('notification_templates')->insertOrIgnore([
            'channel' => 'sms',
            'code' => '100047',
            'name' => '服务已到期通知(用户)',
            'description' => '服务已到期通知短信模板。',
            'audience' => 'user',
            'scene_code' => 'service_expired',
            'subject' => null,
            'content' => '您的服务{service_name}已于{expires_at}到期，请尽快续费，逾期未续费将被暂停。',
            'variables_json' => $encoded,
            'provider_variables_json' => $encoded,
            'provider_template_id' => null,
            'is_enabled' => true,
            'is_custom' => false,
            'sort_order' => ++$sortBase,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            ['email', '100046'],
            ['sms', '100047'],
        ] as [$channel, $templateCode]) {
            DB::table('message_scene_templates')->insertOrIgnore([
                'audience' => 'customer',
                'scene_code' => 'service_expired',
                'channel' => $channel,
                'template_code' => $templateCode,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // 数据补齐不回滚。
    }
};
