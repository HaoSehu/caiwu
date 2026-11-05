<?php

declare(strict_types=1);

use App\Services\Messaging\MessageConfigurationRepository;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 修正 000017 已写入的多档模板配置：同一场景同一渠道此前按 sort_order 取到最后一档
 * （invoice_payment_reminder 的 email 正文是「第 3 次支付提醒」、service_due 是「第 2 次续费提醒」），
 * 新环境/本次迁移已执行过的库都会带着这份错误正文。
 *
 * 本迁移把这几条渠道正文重置为 sort_order 最靠前的模板（即「第 1 次」档），
 * 与 000017 修正后的行为一致。其余场景/渠道不动。
 */
return new class extends Migration
{
    /** 场景 => 需要重取首档模板的渠道 */
    private const TARGETS = [
        'invoice_payment_reminder' => ['email'],
        'service_due' => ['email'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('message_configurations') || ! Schema::hasTable('notification_templates')) {
            return;
        }

        $repository = app(MessageConfigurationRepository::class);

        foreach (self::TARGETS as $scene => $channels) {
            $stored = $repository->get('customer', $scene);
            if ($stored['revision'] === 0) {
                continue;
            }

            $groups = $stored['configurations']['_groups'] ?? [];
            if (! is_array($groups) || $groups === []) {
                continue;
            }

            $changed = false;

            foreach ($channels as $channel) {
                $template = DB::table('notification_templates')
                    ->where('channel', $channel)
                    ->where('scene_code', $scene)
                    ->where('is_enabled', 1)
                    ->orderBy('sort_order')
                    ->first(['subject', 'content']);

                if ($template === null) {
                    continue;
                }

                foreach ($groups as $index => $group) {
                    if (! isset($group['configurations'][$channel])) {
                        continue;
                    }

                    $groups[$index]['configurations'][$channel]['title'] = (string) ($template->subject ?? '');
                    $groups[$index]['configurations'][$channel]['body'] = (string) $template->content;
                    $changed = true;
                }
            }

            if ($changed) {
                $repository->save('customer', $scene, (int) $stored['revision'], ['_groups' => $groups]);
            }
        }
    }

    public function down(): void
    {
        // 文案修正不回滚。
    }
};
