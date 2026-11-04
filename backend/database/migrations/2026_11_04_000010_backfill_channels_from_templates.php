<?php

declare(strict_types=1);

use App\Services\Messaging\MessageConfigurationRepository;
use App\Support\MessageGroups;
use App\Support\MessageTypeCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 按「实际模板」补齐存量配置缺失的渠道。
 *
 * 两个成因：
 * 1. 配置在模板补齐之前回填，之后新增的渠道没有键；
 * 2. MessageGroups 的管理员组渠道白名单此前不含 sms，导致管理员场景的短信模板
 *    无法进入配置（白名单已同步修正）。
 *
 * 规则：渠道来源 = 组类型允许 ∩ 场景目录声明，再并上「该场景实际挂载的模板渠道」。
 * 只有模板存在才能发送，目录声明不能反向限制已有模板。
 *
 * 只补齐、不删除、不改已存启停状态，重复执行安全。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('message_configurations')
            || ! Schema::hasTable('message_scene_templates')) {
            return;
        }

        $repository = app(MessageConfigurationRepository::class);
        $fixed = 0;

        foreach (DB::table('message_configurations')->get(['audience', 'message_type', 'revision']) as $row) {
            $audience = (string) $row->audience;
            $code = (string) $row->message_type;

            try {
                $type = MessageTypeCatalog::get($audience, $code);
            } catch (Throwable) {
                continue;
            }

            $templateChannels = DB::table('message_scene_templates')
                ->where('audience', $audience)
                ->where('scene_code', $code)
                ->pluck('channel')
                ->unique()
                ->values()
                ->all();

            $stored = $repository->get($audience, $code);
            $groups = MessageGroups::fromStored($audience, $type, $stored['configurations']);

            $changed = false;

            foreach ($groups as $index => $group) {
                $kind = (string) $group['kind'];
                $kindChannels = MessageGroups::channelsFor($kind);

                $allowed = array_values(array_unique(array_merge(
                    array_values(array_intersect($kindChannels, $type['channels'])),
                    array_values(array_intersect($templateChannels, $kindChannels))
                )));

                foreach ($allowed as $channel) {
                    if (array_key_exists($channel, $group['configurations'])) {
                        continue;
                    }

                    $groups[$index]['configurations'][$channel] = MessageGroups::defaultChannelConfiguration($type, $channel);
                    $changed = true;
                }
            }

            if (! $changed) {
                continue;
            }

            $repository->save($audience, $code, (int) $row->revision, ['_groups' => $groups]);
            $fixed++;
        }
    }

    public function down(): void
    {
        // 渠道补齐是数据修正，不回滚。
    }
};
