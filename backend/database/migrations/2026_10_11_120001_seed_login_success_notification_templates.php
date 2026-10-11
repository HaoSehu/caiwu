<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M10 登录成功提醒（补全）：种子 notification_templates 两行 code=login_success。
 *
 * - channel=mail：邮件通道模板（正文变量与既有邮件提醒链路对齐，含 login_ip/login_time/login_location）；
 * - channel=inbox：站内信通道模板（登录成功后写 user_notifications 的标题/正文来源）。
 *
 * 可重入：按 (channel, code) 幂等 upsert；已存在且 is_custom=1（管理员自定义）的记录不覆盖；
 * is_custom=0 的记录刷新为最新默认内容。channel 列为 varchar(20) 自由字符串，
 * 新增 mail/inbox 两个通道值不影响既有 email/sms 模板。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        foreach ($this->templates() as $template) {
            $now = now();
            $payload = [
                'name' => $template['name'],
                'description' => $template['description'],
                'audience' => 'user',
                'subject' => $template['subject'],
                'content' => $template['content'],
                'variables_json' => json_encode($template['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'provider_variables_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'provider_template_id' => null,
                'is_enabled' => true,
                'is_custom' => false,
                'updated_at' => $now,
            ];

            $existing = DB::table('notification_templates')
                ->where('channel', $template['channel'])
                ->where('code', 'login_success')
                ->first(['id', 'is_custom']);

            if ($existing === null) {
                DB::table('notification_templates')->insert(
                    $payload + [
                        'channel' => $template['channel'],
                        'code' => 'login_success',
                        'sort_order' => 0,
                        'created_at' => $now,
                    ]
                );

                continue;
            }

            if ((int) $existing->is_custom === 1) {
                continue;
            }

            DB::table('notification_templates')
                ->where('id', $existing->id)
                ->update($payload);
        }
    }

    /**
     * @return array<int, array{channel: string, name: string, description: string, subject: ?string, content: string, variables: array<int, string>}>
     */
    private function templates(): array
    {
        return [
            [
                'channel' => 'mail',
                'name' => '登录成功提醒（邮件）',
                'description' => '用户登录成功后的邮件提醒模板，变量含登录 IP、时间与归属地。',
                'subject' => '您的账号于 {{login_time}} 成功登录',
                'content' => <<<'HTML'
<div style="padding:24px;font-family:Arial,'Microsoft YaHei',sans-serif;color:#333;">
  <h2 style="margin:0 0 16px;font-size:18px;">登录成功提醒</h2>
  <p style="margin:0 0 12px;">您好，{{nickname}}：</p>
  <p style="margin:0 0 12px;">您的账号刚刚成功登录，本次登录信息如下：</p>
  <ul style="margin:0 0 12px;padding-left:20px;">
    <li>登录时间：{{login_time}}</li>
    <li>登录 IP：{{login_ip}}</li>
    <li>登录归属地：{{login_location}}</li>
  </ul>
  <p style="margin:0;color:#999;font-size:12px;">如非本人操作，请立即修改登录密码以保障账号安全。</p>
</div>
HTML,
                'variables' => ['nickname', 'login_ip', 'login_time', 'login_location'],
            ],
            [
                'channel' => 'inbox',
                'name' => '登录成功提醒（站内信）',
                'description' => '用户登录成功后的站内信提醒模板，变量含登录 IP、时间与归属地。',
                'subject' => null,
                'content' => '您的账号于 {{login_time}} 成功登录（IP：{{login_ip}}，归属地：{{login_location}}）。如非本人操作，请立即修改登录密码。',
                'variables' => ['login_ip', 'login_time', 'login_location'],
            ],
        ];
    }

    public function down(): void
    {
        // 模板种子是数据更新，不做回滚（与历史模板迁移一致）。
    }
};
