<?php

declare(strict_types=1);

use App\Services\Messaging\MessageConfigurationRepository;
use App\Support\MessageGroups;
use App\Support\MessageTypeCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 移除四个场景的邮件与短信渠道，只保留站内消息。
 *
 * 用户裁决（2026-11-07）：删除「信用额账单已生成」「实名认证未通过」
 * 「工单重新开启」「系统重装成功」四个场景的邮件与短信。
 *
 * 处理内容：
 * 1. notification_templates：删除四个场景（scene_code 命中）的 email / sms 模板；
 * 2. message_scene_templates：删除对应渠道关联；
 * 3. message_configurations：把已存配置里的 email / sms 渠道键移除，保留 message 渠道；
 * 4. settings：清理这些模板的旧 setting key（邮件 subject/content/enabled、短信
 *    content/provider_template_id/enabled），避免管理端残留无用键。
 *
 * 幂等：全部按条件删除，重复执行安全。站内消息（message 渠道）不在此范围。
 */
return new class extends Migration
{
    /** @var list<string> */
    private const SCENES = [
        'credit_invoice_created',
        'real_name_rejected',
        'ticket_reopened',
        'service_reinstalled',
    ];

    /** @var list<string> */
    private const CHANNELS = ['email', 'sms'];

    /**
     * 本次删除涉及的模板编号（常量声明，不依赖运行时查表）。
     *
     * 写在常量里而不是查 notification_templates：迁移失败重跑时模板可能已被删掉，
     * 查表会得到空集合，导致 settings 清理被静默跳过。
     *
     * @var array<string, string>
     */
    private const TEMPLATE_CODES_BY_SCENE = [
        'credit_invoice_created' => '100020',
        'real_name_rejected' => '100045',
        'ticket_reopened' => '100064',
        'service_reinstalled' => '100009',
    ];

    public function up(): void
    {
        // 先清 settings 再删模板：模板删除后再推导不出编号，
        // 迁移中途失败重跑时会漏清（详见 pruneSettings 的说明）。
        $this->pruneSettings();

        if (Schema::hasTable('notification_templates')) {
            DB::table('notification_templates')
                ->whereIn('scene_code', self::SCENES)
                ->whereIn('channel', self::CHANNELS)
                ->delete();
        }

        if (Schema::hasTable('message_scene_templates')) {
            DB::table('message_scene_templates')
                ->whereIn('scene_code', self::SCENES)
                ->whereIn('channel', self::CHANNELS)
                ->delete();
        }

        $this->pruneConfigurations();
    }

    public function down(): void
    {
        // 删除属用户裁决的数据收敛，不回滚；需要恢复时重建模板与关联。
    }

    /**
     * 清理被删模板的旧 setting key。
     *
     * 编号取自常量而非查表：无论首次执行、中途失败重跑还是模板早被删掉，
     * 结果都一致，不会出现「重跑时取不到编号 → 静默跳过清理」。
     */
    private function pruneSettings(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $codes = self::TEMPLATE_CODES_BY_SCENE;
        $keys = [];
        foreach ($codes as $code) {
            foreach (['email_template_subject_', 'email_template_content_', 'email_template_enabled_'] as $prefix) {
                $keys[] = $prefix.$code;
            }
            foreach (['sms_template_content_', 'sms_template_provider_template_id_', 'sms_template_enabled_'] as $prefix) {
                $keys[] = $prefix.$code;
            }
        }

        DB::table('settings')
            ->whereIn('item_key', $keys)
            ->delete();
    }

    /**
     * 从已存场景配置中移除 email / sms 渠道，保留 message。
     */
    private function pruneConfigurations(): void
    {
        if (! Schema::hasTable('message_configurations')) {
            return;
        }

        $repository = app(MessageConfigurationRepository::class);

        foreach (DB::table('message_configurations')
            ->where('audience', 'customer')
            ->whereIn('message_type', self::SCENES)
            ->get(['message_type', 'revision']) as $row) {
            $scene = (string) $row->message_type;

            try {
                $type = MessageTypeCatalog::get('customer', $scene);
            } catch (Throwable) {
                // 场景已从目录移除时按剩余配置原样处理
                $type = null;
            }

            $stored = $repository->get('customer', $scene);
            $groups = $type !== null
                ? MessageGroups::fromStored('customer', $type, $stored['configurations'])
                : (array) ($stored['configurations']['_groups'] ?? []);

            if ($groups === []) {
                continue;
            }

            $changed = false;
            foreach ($groups as $index => $group) {
                $configurations = (array) ($group['configurations'] ?? []);

                foreach (self::CHANNELS as $channel) {
                    if (array_key_exists($channel, $configurations)) {
                        unset($configurations[$channel]);
                        $changed = true;
                    }
                }

                $groups[$index]['configurations'] = $configurations;
            }

            if ($changed) {
                $repository->save('customer', $scene, (int) $stored['revision'], ['_groups' => $groups]);
            }
        }
    }
};
