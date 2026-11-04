<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 修复三个场景的渠道覆盖缺口。
 *
 * 现象（2026-11-04 实测）：
 * - login_verification / password_reset 在 message_scene_templates 里的关联为空，
 *   导致管理端「可发送渠道」返回空数组、开关全部禁用；
 * - real_name_rejected 只有站内消息，缺邮件与短信模板。
 *
 * 本迁移：
 * 1. 恢复 login_verification / password_reset 的 email + sms 关联（模板 100001 已存在）；
 * 2. 为 real_name_rejected 补邮件与短信模板（100045，与站内消息同号），并建立关联。
 *
 * 幂等：模板不存在才插入，关联用 insertOrIgnore。
 */
return new class extends Migration
{
    /**
     * 需要恢复关联的场景 => 模板编号。
     *
     * @var array<string, string>
     */
    private const RESTORE_LINKS = [
        'login_verification' => '100001',
        'password_reset' => '100001',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')
            || ! Schema::hasTable('message_scene_templates')) {
            return;
        }

        $now = now();
        $sortBase = (int) DB::table('notification_templates')->max('sort_order');

        // 1. 恢复两个验证码场景的 email + sms 关联
        foreach (self::RESTORE_LINKS as $scene => $code) {
            foreach (['email', 'sms'] as $channel) {
                $exists = DB::table('notification_templates')
                    ->where('channel', $channel)
                    ->where('code', $code)
                    ->exists();

                if (! $exists) {
                    continue;
                }

                DB::table('message_scene_templates')->insertOrIgnore([
                    'audience' => 'customer',
                    'scene_code' => $scene,
                    'channel' => $channel,
                    'template_code' => $code,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 2. real_name_rejected 补邮件与短信
        $reason = ['reason'];
        $encodedVars = json_encode($reason, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! DB::table('notification_templates')->where('channel', 'email')->where('code', '100045')->exists()) {
            DB::table('notification_templates')->insert([
                'channel' => 'email',
                'code' => '100045',
                'name' => '实名认证未通过',
                'description' => '实名认证审核未通过后通知客户补充资料。',
                'audience' => 'user',
                'scene_code' => 'real_name_rejected',
                'subject' => '【{{site_name}}】实名认证未通过',
                'content' => '<p>{{display_name}}，您提交的实名认证审核未通过。</p><p>原因：{{reason}}。请补充资料后重新提交。</p>',
                'variables_json' => json_encode(['display_name', 'reason'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'provider_variables_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'provider_template_id' => null,
                'is_enabled' => true,
                'is_custom' => false,
                'sort_order' => ++$sortBase,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('notification_templates')->where('channel', 'sms')->where('code', '100045')->exists()) {
            DB::table('notification_templates')->insert([
                'channel' => 'sms',
                'code' => '100045',
                'name' => '实名认证未通过(用户)',
                'description' => '实名认证未通过短信模板。',
                'audience' => 'user',
                'scene_code' => 'real_name_rejected',
                'subject' => null,
                'content' => '您的实名认证审核未通过，原因：{reason}。请补充资料后重新提交。',
                'variables_json' => $encodedVars,
                'provider_variables_json' => $encodedVars,
                'provider_template_id' => null,
                'is_enabled' => true,
                'is_custom' => false,
                'sort_order' => ++$sortBase,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (['email', 'sms'] as $channel) {
            DB::table('message_scene_templates')->insertOrIgnore([
                'audience' => 'customer',
                'scene_code' => 'real_name_rejected',
                'channel' => $channel,
                'template_code' => '100045',
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // 数据修复不回滚。
    }
};
