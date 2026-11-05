<?php

declare(strict_types=1);

use App\Models\NotificationTemplate;
use App\Services\Messaging\MessageConfigurationRepository;
use App\Support\MessageGroups;
use App\Support\MessageTypeCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * 补齐消息场景配置（迁移级种子）。
 *
 * 背景：2026-11-04 的配置回填只有 `messages:backfill-configurations` 命令，
 * 新环境/新部署若忘了执行 --apply，所有 sendNow 会静默返回 false（不写 messages、
 * 不抛异常），账单提醒、付款成功、退款等通知全部丢失且没有任何痕迹。
 *
 * 本迁移按现有模板把「尚未配置」的场景建成默认配置：
 * - 渠道启停沿用模板 is_enabled（与回填命令口径一致）；
 * - 已存在的配置完全不动（无 revision 冲突、不覆盖管理端手工调整）；
 * - 重复执行安全。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('message_configurations')
            || ! Schema::hasTable('notification_templates')) {
            return;
        }

        $repository = app(MessageConfigurationRepository::class);

        $templates = NotificationTemplate::query()
            ->whereNotNull('scene_code')
            ->where('scene_code', '!=', '__unmapped')
            ->whereIn('channel', ['email', 'sms', 'message'])
            ->orderBy('channel')
            ->orderBy('sort_order')
            ->get(['channel', 'code', 'audience', 'subject', 'content', 'is_enabled', 'provider_template_id', 'scene_code']);

        $byScene = [];
        foreach ($templates as $template) {
            $audience = $template->audience === 'admin' ? 'admin' : 'customer';
            $byScene[$audience][(string) $template->scene_code][] = $template;
        }

        foreach ($byScene as $audience => $scenes) {
            foreach ($scenes as $scene => $rows) {
                try {
                    $type = MessageTypeCatalog::get($audience, $scene);
                } catch (Throwable) {
                    continue;
                }

                if ($repository->get($audience, $scene)['revision'] > 0) {
                    continue;
                }

                $kind = $audience === 'admin' ? 'admin' : 'customer';
                $group = MessageGroups::create($type, $kind, $kind, MessageGroups::KINDS[$kind]);
                $group['enabled'] = true;

                foreach ($rows as $template) {
                    $channel = (string) $template->channel;
                    if (! array_key_exists($channel, $group['configurations'])) {
                        continue;
                    }

                    $config = $group['configurations'][$channel];

                    // 同一场景同一渠道可能挂多条模板（账单提醒 100011/012/013、续费提醒 100018/019）。
                    // 配置只能保存一份正文，取 sort_order 最靠前的一条作为默认（即「第 1 次提醒」）；
                    // 其余档位由调用方传 templateCode，MessageDispatcher::applySceneTemplate 按模板覆盖。
                    // 若取最后一条，新环境未跑回填命令时首次提醒会显示「第 3 次支付提醒」。
                    if (isset($config['__seeded'])) {
                        continue;
                    }
                    $config['__seeded'] = true;
                    $config['enabled'] = $config['enabled'] || (bool) $template->is_enabled;

                    if ($channel === 'email') {
                        $config['title'] = (string) ($template->subject ?? $config['title'] ?? '');
                        $config['body'] = (string) $template->content;
                    } elseif ($channel === 'sms') {
                        $config['body'] = (string) $template->content;
                        $config['template_id'] = (string) ($template->provider_template_id ?? '');
                    } elseif ($channel === 'message') {
                        $config['body'] = (string) $template->content;
                    }

                    $group['configurations'][$channel] = $config;
                }

                // __seeded 只是迁移内部哨兵，不能落进配置
                foreach ($group['configurations'] as $channel => $config) {
                    unset($group['configurations'][$channel]['__seeded']);
                }

                $repository->save($audience, $scene, 0, ['_groups' => [$group]]);
            }
        }
    }

    public function down(): void
    {
        // 配置回填是数据补齐，不回滚。
    }
};
