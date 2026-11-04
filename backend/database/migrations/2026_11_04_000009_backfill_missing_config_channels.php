<?php

declare(strict_types=1);

use App\Services\Messaging\MessageConfigurationRepository;
use App\Support\MessageGroups;
use App\Support\MessageTypeCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 回填存量消息配置缺失的渠道。
 *
 * 背景：配置是在模板补齐之前回填的，之后场景目录新增了渠道（如 2026-11-04
 * 给 real_name_approved 补 email），但已存配置里没有对应键。
 * 结果是管理端该渠道显示为禁用，且批量配置勾选它会被静默跳过。
 *
 * 本迁移按当前场景目录为每条存量配置补齐缺失渠道（默认禁用），已有渠道不动。
 * 只补齐、不删除、不改已存的启停状态，重复执行安全。
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

        foreach (DB::table('message_configurations')->get(['audience', 'message_type', 'revision']) as $row) {
            try {
                $type = MessageTypeCatalog::get((string) $row->audience, (string) $row->message_type);
            } catch (Throwable) {
                // 场景已从目录移除：保留原配置不动
                continue;
            }

            $stored = $repository->get((string) $row->audience, (string) $row->message_type);
            $groups = MessageGroups::fromStored((string) $row->audience, $type, $stored['configurations']);

            $changed = false;

            foreach ($groups as $index => $group) {
                $allowed = array_values(array_intersect(
                    MessageGroups::channelsFor((string) $group['kind']),
                    $type['channels']
                ));

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

            $repository->save(
                (string) $row->audience,
                (string) $row->message_type,
                (int) $row->revision,
                ['_groups' => $groups]
            );
        }
    }

    public function down(): void
    {
        // 渠道回填是数据补齐，不回滚。
    }
};
