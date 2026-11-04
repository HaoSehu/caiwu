<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 补齐工单两个站内消息场景的模板（ticket_pending_reminder / ticket_reopened）。
 *
 * 这两个场景此前只有业务代码里的硬编码文案，没有模板载体；
 * 2026_11_04_000011 已为其余 9 个场景建模板，本迁移补齐剩余两个，
 * 并把两个场景的站内消息渠道补齐到已存配置。
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string, 2: string, 3: list<string>, 4: string}> */
    private const TEMPLATES = [
        'ticket_pending_reminder' => [
            '工单待处理提醒',
            '工单待您处理',
            '工单「{{ticket_subject}}」客服已回复，若无后续回复将自动关闭，请及时查看。',
            ['ticket_subject'],
            '100063',
        ],
        'ticket_reopened' => [
            '工单重新开启',
            '工单已重新开启',
            '工单「{{ticket_subject}}」已重新开启，请继续跟进。',
            ['ticket_subject'],
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

        foreach (self::TEMPLATES as $scene => [$name, $title, $body, $variables, $code]) {
            if (! DB::table('notification_templates')->where('channel', 'message')->where('code', $code)->exists()) {
                DB::table('notification_templates')->insert([
                    'channel' => 'message',
                    'code' => $code,
                    'name' => $name,
                    'description' => $name.'站内消息模板。',
                    'audience' => 'user',
                    'scene_code' => $scene,
                    'subject' => $title,
                    'content' => $body,
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

            DB::table('message_scene_templates')->insertOrIgnore([
                'audience' => 'customer',
                'scene_code' => $scene,
                'channel' => 'message',
                'template_code' => $code,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // 模板新增不回滚。
    }
};
