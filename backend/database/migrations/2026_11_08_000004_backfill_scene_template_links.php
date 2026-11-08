<?php

declare(strict_types=1);

use App\Models\NotificationTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 按模板的 `scene_code` 补齐 `message_scene_templates` 关联。
 *
 * 背景：`2026_11_04_000011_create_message_channel_templates` 与
 * `2026_11_04_000016_fix_scene_channel_gaps` 只给当时存在的模板建关联。
 * 之后补种进来的模板（例如 `2026_11_08_000003` 补的邮件模板）虽然在
 * `notification_templates.scene_code` 上标了场景，却没有对应的关联行。
 *
 * 后果是 `MessageDispatcher::loadSceneTemplate()` 先查关联表，查不到就
 * `return null` —— 即便模板就在库里，指定 `templateCode` 也不会生效，
 * 发送会沿用配置里的默认文案（例如「第 3 次支付提醒」被当成首次提醒发出）。
 *
 * `notification_templates.scene_code` 本身就是权威映射，本迁移据此补齐。
 * 用 `insertOrIgnore` 保证幂等，不动已有行。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('message_scene_templates')
            || ! Schema::hasTable('notification_templates')) {
            return;
        }

        $now = now();

        $templates = NotificationTemplate::query()
            ->whereNotNull('scene_code')
            ->where('scene_code', '!=', '')
            ->where('scene_code', '!=', '__unmapped')
            ->whereIn('channel', ['email', 'sms', 'message'])
            ->get(['channel', 'code', 'audience', 'scene_code']);

        foreach ($templates as $template) {
            $channel = (string) $template->channel;
            $code = (string) $template->code;
            $audience = $template->audience === 'admin' ? 'admin' : 'customer';
            $scene = (string) $template->scene_code;

            $exists = DB::table('message_scene_templates')
                ->where('audience', $audience)
                ->where('scene_code', $scene)
                ->where('channel', $channel)
                ->where('template_code', $code)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('message_scene_templates')->insert([
                'audience' => $audience,
                'scene_code' => $scene,
                'channel' => $channel,
                'template_code' => $code,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // 关联补齐是收敛型修复，回滚会把仍需要的关系一并删掉，故不回滚。
    }
};
