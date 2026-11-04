<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NotificationTemplate;
use App\Services\Messaging\MessageConfigurationRepository;
use App\Support\MessageGroups;
use App\Support\MessageTypeCatalog;
use Illuminate\Console\Command;

/**
 * 把旧 notification_templates 回填为新 message_configurations（S6 第一步）。
 *
 * 规则：
 * - 只处理已映射到场景的模板（scene_code != __unmapped）；
 * - 每个场景生成一个 customer/admin 组，组启用，渠道启停沿用模板 is_enabled；
 * - 渠道正文/主题/厂商模板号原样搬运，不重新渲染；
 * - 已存在配置的场景默认跳过，加 --overwrite 才覆盖（保留当前 revision 语义）。
 *
 * 默认 dry-run，--apply 才写入。回填后旧发送路径仍可工作，两者互不影响。
 */
class BackfillMessageConfigurationsCommand extends Command
{
    protected $signature = 'messages:backfill-configurations
        {--apply : 真正写入；不指定时仅 dry-run 预览}
        {--overwrite : 已存在配置的场景也覆盖（会递增 revision）}';

    protected $description = '把通知模板回填为消息场景配置';

    public function handle(MessageConfigurationRepository $configurations): int
    {
        $apply = (bool) $this->option('apply');
        $overwrite = (bool) $this->option('overwrite');

        $templates = NotificationTemplate::query()
            ->whereNotNull('scene_code')
            ->where('scene_code', '!=', '__unmapped')
            ->orderBy('channel')
            ->orderBy('sort_order')
            ->get();

        if ($templates->isEmpty()) {
            $this->warn('没有已映射的通知模板，跳过');

            return self::SUCCESS;
        }

        $byScene = [];
        foreach ($templates as $template) {
            $audience = $template->audience === 'admin' ? 'admin' : 'customer';
            $byScene[$audience][(string) $template->scene_code][] = $template;
        }

        $planned = 0;
        $skipped = 0;

        foreach ($byScene as $audience => $scenes) {
            foreach ($scenes as $scene => $rows) {
                try {
                    $type = MessageTypeCatalog::get($audience, $scene);
                } catch (\Throwable) {
                    $this->warn("场景不存在，跳过：{$audience}/{$scene}");

                    continue;
                }

                $stored = $configurations->get($audience, $scene);
                if ($stored['revision'] > 0 && ! $overwrite) {
                    $skipped++;
                    $this->line("已存在，跳过：{$audience}/{$scene}（revision {$stored['revision']}）");

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
                    $config['enabled'] = (bool) $template->is_enabled;

                    if ($channel === 'email') {
                        $config['title'] = (string) ($template->subject ?? $config['title'] ?? '');
                        $config['body'] = (string) $template->content;
                    } elseif ($channel === 'sms') {
                        $config['body'] = (string) $template->content;
                        $config['template_id'] = (string) ($template->provider_template_id ?? '');
                    }

                    $group['configurations'][$channel] = $config;
                }

                $planned++;
                $this->line(sprintf(
                    '%s %s/%s：%d 个渠道（%s）',
                    $apply ? '写入' : '计划',
                    $audience,
                    $scene,
                    count($group['configurations']),
                    implode(',', array_keys($group['configurations']))
                ));

                if ($apply) {
                    $configurations->save($audience, $scene, (int) $stored['revision'], ['_groups' => [$group]]);
                }
            }
        }

        $this->info(sprintf(
            '%s：%d 个场景，跳过 %d 个%s',
            $apply ? '已写入' : 'dry-run 完成',
            $planned,
            $skipped,
            $apply ? '' : '（加 --apply 实际写入）'
        ));

        return self::SUCCESS;
    }
}
