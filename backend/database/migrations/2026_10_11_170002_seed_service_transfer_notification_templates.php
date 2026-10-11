<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M6 服务转移通知模板种子（可重入 upsert）。
 *
 * code：service_transfer_requested / confirmed / completed / rejected。
 * 频道：email（真实邮件发送走 NotificationService::sendTemplateEmail，其查询
 * channel='email'——与既有模板渠道命名对齐）与 inbox（站内信/前台展示口径）。
 * 模板变量：service_name、from_user_email、to_user_email、expires_at。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        foreach ($this->templates() as $template) {
            foreach (['email', 'inbox'] as $channel) {
                $this->upsertTemplate($channel, $template);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $template
     */
    private function upsertTemplate(string $channel, array $template): void
    {
        $now = now();
        $code = (string) $template['code'];
        $payload = [
            'name' => $template['name'],
            'description' => $template['description'],
            'audience' => 'user',
            'subject' => $template['subject'],
            'content' => $template['content'],
            'variables_json' => json_encode($template['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'provider_variables_json' => json_encode($template['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'provider_template_id' => null,
            'is_enabled' => true,
            'is_custom' => false,
            'updated_at' => $now,
        ];

        $existing = DB::table('notification_templates')
            ->where('channel', $channel)
            ->where('code', $code)
            ->first(['id']);

        if ($existing === null) {
            DB::table('notification_templates')->insert(
                $payload + ['channel' => $channel, 'code' => $code, 'sort_order' => $template['sort_order'], 'created_at' => $now]
            );

            return;
        }

        DB::table('notification_templates')->where('id', $existing->id)->update($payload);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            [
                'code' => 'service_transfer_requested',
                'name' => '服务转移待确认',
                'description' => '服务转移发起后的待确认通知。',
                'subject' => '服务转移确认请求：{{service_name}}',
                'content' => '服务「{{service_name}}」的转移请求已发起：{{from_user_email}} → {{to_user_email}}，请在 {{expires_at}} 前登录控制台确认或拒绝，逾期将自动过期。',
                'variables' => ['service_name', 'from_user_email', 'to_user_email', 'expires_at'],
                'sort_order' => 901,
            ],
            [
                'code' => 'service_transfer_confirmed',
                'name' => '服务转移双方已确认',
                'description' => '服务转移双方均已确认、进入待生效状态的通知。',
                'subject' => '服务转移已双方确认：{{service_name}}',
                'content' => '服务「{{service_name}}」的转移请求（{{from_user_email}} → {{to_user_email}}）已获双方确认，即将生效。',
                'variables' => ['service_name', 'from_user_email', 'to_user_email', 'expires_at'],
                'sort_order' => 902,
            ],
            [
                'code' => 'service_transfer_completed',
                'name' => '服务转移完成',
                'description' => '服务归属变更完成的通知。',
                'subject' => '服务转移完成：{{service_name}}',
                'content' => '服务「{{service_name}}」已从 {{from_user_email}} 转移至 {{to_user_email}}，转移于 {{expires_at}} 前发起并已生效。',
                'variables' => ['service_name', 'from_user_email', 'to_user_email', 'expires_at'],
                'sort_order' => 903,
            ],
            [
                'code' => 'service_transfer_rejected',
                'name' => '服务转移已拒绝/取消',
                'description' => '服务转移被拒绝或取消的通知。',
                'subject' => '服务转移未生效：{{service_name}}',
                'content' => '服务「{{service_name}}」的转移请求（{{from_user_email}} → {{to_user_email}}）未生效：{{reason}}。',
                'variables' => ['service_name', 'from_user_email', 'to_user_email', 'expires_at'],
                'sort_order' => 904,
            ],
        ];
    }

    public function down(): void
    {
        // 模板种子是数据更新，不做回滚（与历史模板迁移一致）。
    }
};
