<?php

declare(strict_types=1);

use App\Models\NotificationTemplate;
use App\Services\Messaging\MessageConfigurationRepository;
use App\Support\MessageGroups;
use App\Support\MessageTypeCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * 补齐已落库场景配置里缺失或未初始化的渠道。
 *
 * 背景：`2026_11_04_000017_seed_message_configurations` 只给 `revision = 0` 的场景
 * 建配置，已存在的配置「完全不动」。这带来两个问题：
 *
 * 1. 某场景后来新增了渠道模板，但配置里没有该渠道 → 管理端看不到开关；
 * 2. 配置建立时该渠道的模板还不存在，于是 `title` / `body` 停留在
 *    `MessageGroups::defaultChannelConfiguration` 生成的占位文案（例如
 *    「创欧云 - 账单支付提醒」），而不是模板里真正的
 *    「【{{site_name}}】第1次支付提醒 #{{invoice_no}}」。
 *
 * 本迁移对这两种情况都按种子迁移的口径修正：
 *   - 渠道缺失 → 补齐，`enabled` 沿用模板 `is_enabled`；
 *   - 渠道存在但 `title`/`body` **仍是默认占位**（未被管理端改过）→ 用模板覆盖；
 *   - 管理端改过的文案（不等于默认占位值）**一字不动**。
 *
 * 已完整且已初始化的不处理，可安全重复执行。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('message_configurations')
            || ! Schema::hasTable('notification_templates')
            || ! Schema::hasTable('message_scene_templates')) {
            return;
        }

        $repository = app(MessageConfigurationRepository::class);

        // 场景 → 渠道 → [是否有启用模板, 首档模板（sort_order 最靠前）]
        $templatesByScene = [];
        $links = NotificationTemplate::query()
            ->whereNotNull('scene_code')
            ->where('scene_code', '!=', '__unmapped')
            ->whereIn('channel', ['email', 'sms', 'message'])
            ->orderBy('sort_order')
            ->get(['channel', 'audience', 'scene_code', 'is_enabled', 'subject', 'content', 'provider_template_id']);

        foreach ($links as $row) {
            $audience = $row->audience === 'admin' ? 'admin' : 'customer';
            $scene = (string) $row->scene_code;
            $channel = (string) $row->channel;

            $templatesByScene[$audience][$scene][$channel] ??= [
                'enabled' => false,
                'template' => $row,
            ];
            $templatesByScene[$audience][$scene][$channel]['enabled'] =
                $templatesByScene[$audience][$scene][$channel]['enabled'] || (bool) $row->is_enabled;
        }

        foreach ($templatesByScene as $audience => $scenes) {
            foreach ($scenes as $scene => $channels) {
                $stored = $repository->get($audience, $scene);
                if ($stored['revision'] === 0) {
                    // 未落库的场景由种子迁移 / detail 自动校准，不在此处理。
                    continue;
                }

                try {
                    $type = MessageTypeCatalog::get($audience, $scene);
                } catch (Throwable) {
                    continue;
                }

                $groups = MessageGroups::fromStored($audience, $type, $stored['configurations']);
                if ($groups === []) {
                    continue;
                }

                $changed = false;
                foreach ($groups as $index => $group) {
                    $kind = (string) ($group['kind'] ?? '');
                    $configured = (array) ($group['configurations'] ?? []);
                    $allowed = array_intersect(array_keys($channels), MessageGroups::channelsFor($kind));

                    foreach ($allowed as $channel) {
                        $defaults = MessageGroups::defaultChannelConfiguration($type, $channel);
                        $isNew = ! isset($configured[$channel]);
                        $current = $isNew ? $defaults : (array) $configured[$channel];

                        // 只有「文案仍是默认占位」才允许被模板覆盖。
                        // 不看 enabled：开关状态可能是管理端开的，与文案是否被改过无关。
                        $untouched = ($defaults['title'] ?? null) === ($current['title'] ?? null)
                            && ($defaults['body'] ?? null) === ($current['body'] ?? null);

                        if (! $isNew && ! $untouched) {
                            continue;
                        }

                        $template = $channels[$channel]['template'];

                        if ($isNew) {
                            $current = $defaults;
                            $current['enabled'] = (bool) $channels[$channel]['enabled'];
                        }

                        if ($channel === 'email') {
                            $current['title'] = (string) ($template->subject ?? $current['title'] ?? '');
                            $current['body'] = (string) $template->content;
                        } elseif ($channel === 'sms') {
                            $current['body'] = (string) $template->content;
                            $current['template_id'] = (string) ($template->provider_template_id ?? '');
                        } elseif ($channel === 'message') {
                            $current['body'] = (string) $template->content;
                        }

                        $configured[$channel] = $current;
                        $changed = true;
                    }

                    $groups[$index]['configurations'] = $configured;
                }

                if (! $changed) {
                    continue;
                }

                $repository->save(
                    $audience,
                    $scene,
                    (int) $stored['revision'],
                    ['_groups' => $groups],
                );
            }
        }
    }

    public function down(): void
    {
        // 数据修复没有可靠的反向判据（无法区分「本迁移补的」与「管理端后来启用的」），
        // 因此不回滚。
    }
};
