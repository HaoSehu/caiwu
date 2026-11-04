<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 收尾补齐：让每个场景都同时拥有邮件与短信模板。
 *
 * 上一支迁移（2026_11_04_000001）后仍有两处缺口：
 * - customer/auto_renew_notice 只有邮件 100015，补短信 100043；
 * - customer/real_name_approved 只有短信 100032，补邮件 100044，并把 100032 的 scene_code 从 __unmapped 修正。
 *
 * 同时把 100031 的邮件模板（账号绑定）挂回 account_bound 场景。
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

        // 1) auto_renew_notice 的短信模板
        DB::table('notification_templates')->insertOrIgnore([
            'channel' => 'sms',
            'code' => '100043',
            'name' => '自动续费通知',
            'description' => '自动续费通知短信模板。',
            'audience' => 'user',
            'scene_code' => 'auto_renew_notice',
            'subject' => null,
            'content' => '您的产品{product_name}已完成自动续费，账单号{bill_no}，金额{amount}元。',
            'variables_json' => json_encode(['product_name', 'bill_no', 'amount'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'provider_variables_json' => json_encode(['product_name', 'bill_no', 'amount'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'provider_template_id' => null,
            'is_enabled' => true,
            'is_custom' => false,
            'sort_order' => ++$sortBase,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2) real_name_approved 的邮件模板
        DB::table('notification_templates')->insertOrIgnore([
            'channel' => 'email',
            'code' => '100044',
            'name' => '实名认证通过',
            'description' => '实名认证审核通过后通知客户。',
            'audience' => 'user',
            'scene_code' => 'real_name_approved',
            'subject' => '【{{site_name}}】实名认证通过',
            'content' => '<p>{{display_name}}，您提交的实名认证审核已通过，可以正常使用全部服务。</p>',
            'variables_json' => json_encode(['display_name'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'provider_variables_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'provider_template_id' => null,
            'is_enabled' => true,
            'is_custom' => false,
            'sort_order' => ++$sortBase,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3) 修正已在库但未挂场景的模板（历史遗留）
        DB::table('notification_templates')
            ->where('channel', 'sms')
            ->where('code', '100032')
            ->where('scene_code', '__unmapped')
            ->update(['scene_code' => 'real_name_approved']);

        // 4) 关联表补齐
        foreach ([
            ['sms', '100043', 'customer', 'auto_renew_notice'],
            ['email', '100044', 'customer', 'real_name_approved'],
            ['sms', '100032', 'customer', 'real_name_approved'],
            ['email', '100031', 'customer', 'account_bound'],
            ['sms', '100031', 'customer', 'account_bound'],
        ] as [$channel, $templateCode, $audience, $scene]) {
            DB::table('message_scene_templates')->insertOrIgnore([
                'audience' => $audience,
                'scene_code' => $scene,
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
