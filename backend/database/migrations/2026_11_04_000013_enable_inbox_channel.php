<?php

declare(strict_types=1);

use App\Services\Messaging\MessageConfigurationRepository;
use App\Support\MessageGroups;
use App\Support\MessageTypeCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 站内消息渠道默认启用 + 补齐工单两个新场景的配置。
 *
 * 背景：站内消息模板在 000011/000012 建好后，配置里的 message 渠道是
 * 「默认禁用」的初值，管理端显示为关态开关；用户期望它和邮件短信一样默认可用。
 *
 * 本迁移：
 * 1. 为两个仅有站内消息的场景（ticket_pending_reminder / ticket_reopened）建配置；
 * 2. 把所有已声明 message 渠道的场景开启该渠道（并置组启用）。
 *
 * 只开启站内消息，不动邮件与短信的启停。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('message_configurations')) {
            return;
        }

        $repository = app(MessageConfigurationRepository::class);

        // 1. 补建只有站内消息的场景
        foreach (['ticket_pending_reminder', 'ticket_reopened'] as $scene) {
            if (DB::table('message_configurations')->where('audience', 'customer')->where('message_type', $scene)->exists()) {
                continue;
            }

            try {
                $type = MessageTypeCatalog::get('customer', $scene);
            } catch (Throwable) {
                continue;
            }

            $group = MessageGroups::create($type, 'customer', 'customer', '关联客户');
            $group['enabled'] = true;
            if (isset($group['configurations']['message'])) {
                $group['configurations']['message']['enabled'] = true;
            }

            $repository->save('customer', $scene, 0, ['_groups' => [$group]]);
        }

        // 2. 开启所有已声明 message 渠道的场景
        foreach (DB::table('message_configurations')->get(['audience', 'message_type', 'revision']) as $row) {
            try {
                $type = MessageTypeCatalog::get((string) $row->audience, (string) $row->message_type);
            } catch (Throwable) {
                continue;
            }

            if (! in_array('message', $type['channels'], true)) {
                continue;
            }

            $stored = $repository->get((string) $row->audience, (string) $row->message_type);
            $groups = MessageGroups::fromStored((string) $row->audience, $type, $stored['configurations']);

            $changed = false;
            foreach ($groups as $index => $group) {
                if (! isset($group['configurations']['message'])) {
                    continue;
                }

                if (($group['configurations']['message']['enabled'] ?? false) === true) {
                    continue;
                }

                $groups[$index]['configurations']['message']['enabled'] = true;
                $groups[$index]['enabled'] = true;
                $changed = true;
            }

            if ($changed) {
                $repository->save(
                    (string) $row->audience,
                    (string) $row->message_type,
                    (int) $row->revision,
                    ['_groups' => $groups]
                );
            }
        }
    }

    public function down(): void
    {
        // 启用是数据修正，不回滚。
    }
};
