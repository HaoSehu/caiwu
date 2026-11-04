<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 为工单两个站内消息场景补齐邮件与短信模板。
 *
 * ticket_pending_reminder / ticket_reopened 此前只有站内消息，
 * 用户要求邮件与短信也覆盖，本迁移补齐：
 * - 邮件编码 100063 / 100064（与 message 渠道同号，延续统一编号约定）
 * - 短信编码同号，正文用 {var} 单括号语法（短信厂商惯例）
 */
return new class extends Migration
{
    /**
     * 场景 => [名称, 邮件主题, 邮件正文, 短信正文, 变量, 编号]。
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: list<string>, 5: string}>
     */
    private const TEMPLATES = [
        'ticket_pending_reminder' => [
            '工单待处理提醒',
            '【{{site_name}}】工单待处理提醒 #{{ticket_id}}',
            '<p>{{display_name}}，您的工单「{{ticket_subject}}」客服已回复。</p><p>若在 {{hours_left}} 小时内无后续回复，工单将自动关闭，请及时查看。</p>',
            '您的工单「{ticket_subject}」客服已回复，请及时查看，逾期将自动关闭。',
            ['display_name', 'ticket_id', 'ticket_subject', 'hours_left'],
            '100063',
        ],
        'ticket_reopened' => [
            '工单重新开启',
            '【{{site_name}}】工单重新开启 #{{ticket_id}}',
            '<p>{{display_name}}，您的工单「{{ticket_subject}}」已重新开启，请继续跟进。</p>',
            '您的工单「{ticket_subject}」已重新开启，请继续跟进。',
            ['display_name', 'ticket_id', 'ticket_subject'],
            '100064',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')
            || ! Schema::hasTable('message_scene_templates')) {
            return;
        }

        $now = now();
        $sortBase = (int) DB::table('notification_templates')->max('sort_order');

        foreach (self::TEMPLATES as $scene => [$name, $subject, $emailBody, $smsBody, $variables, $code]) {
            // 邮件
            if (! DB::table('notification_templates')->where('channel', 'email')->where('code', $code)->exists()) {
                DB::table('notification_templates')->insert([
                    'channel' => 'email',
                    'code' => $code,
                    'name' => $name.'通知',
                    'description' => $name.'邮件模板。',
                    'audience' => 'user',
                    'scene_code' => $scene,
                    'subject' => $subject,
                    'content' => $emailBody,
                    'variables_json' => json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'provider_variables_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'provider_template_id' => null,
                    'is_enabled' => true,
                    'is_custom' => false,
                    'sort_order' => ++$sortBase,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // 短信
            $smsVariables = array_values(array_diff($variables, ['display_name', 'hours_left']));
            if (! DB::table('notification_templates')->where('channel', 'sms')->where('code', $code)->exists()) {
                DB::table('notification_templates')->insert([
                    'channel' => 'sms',
                    'code' => $code,
                    'name' => $name.'(用户)',
                    'description' => $name.'短信模板。',
                    'audience' => 'user',
                    'scene_code' => $scene,
                    'subject' => null,
                    'content' => $smsBody,
                    'variables_json' => json_encode($smsVariables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'provider_variables_json' => json_encode($smsVariables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
                    'scene_code' => $scene,
                    'channel' => $channel,
                    'template_code' => $code,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // 模板新增不回滚。
    }
};
