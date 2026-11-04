<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Exceptions\BusinessException;
use App\Models\AdminUser;
use App\Models\NotificationTemplate;
use App\Models\Role;
use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Support\MessageGroups;
use App\Support\MessageTypeCatalog;
use Illuminate\Support\Facades\DB;

/**
 * 管理端「消息配置」聚合读取：把固定场景目录、已存配置与模板目录合成视图。
 *
 * 列表按受众返回一行一个场景（受众页签数据源）；
 * 详情额外给出接收组、渠道选项、接收人候选与机器人状态占位。
 */
final class MessageSceneQueryService
{
    public function __construct(
        private readonly MessageConfigurationRepository $configurations,
        private readonly MessageChannelCapabilityResolver $capabilities,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(string $audience): array
    {
        $stored = $this->configurations->all($audience);
        $scenes = $audience === 'admin' ? MessageTypeCatalog::admin() : MessageTypeCatalog::customer();

        $rows = [];
        // 一次查出全部场景的模板渠道，避免每场景重复查库（原先单次列表 120 次 SQL）
        $templateChannelsByScene = $this->templateChannelsMap($audience);

        foreach ($scenes as $code => $type) {
            $row = $stored[$code] ?? ['revision' => 0, 'configurations' => []];
            $templateChannels = $templateChannelsByScene[$code] ?? [];
            $groups = $this->alignGroups(
                $audience,
                $type,
                MessageGroups::fromStored($audience, $type, $row['configurations']),
                $templateChannels
            );
            $channels = [];
            foreach ($groups as $group) {
                foreach (array_keys((array) $group['configurations']) as $channel) {
                    $config = $group['configurations'][$channel] ?? [];
                    $channels[$channel] = ($channels[$channel] ?? false) || ($group['enabled'] && ($config['enabled'] ?? false));
                }
            }

            $locked = $this->lockedChannelsFor($code, $templateChannels);

            $rows[] = [
                'code' => $code,
                'name' => $type['name'],
                'description' => $type['description'],
                'purpose' => $this->purpose($code),
                'revision' => (int) $row['revision'],
                'metadata' => $this->metadata($audience, $type),
                'channels' => $channels,
                // 该场景实际挂载了模板的渠道：没有模板即表示该渠道无法发送，
                // 管理端应显示为「禁用且不可操作」，而不是一个能点但永远不会生效的开关。
                'sendable_channels' => array_values(array_diff($templateChannels, array_keys($locked))),
                // 被插件能力锁定的渠道 => 锁定原因。锁定后开关禁用且不可操作。
                'locked_channels' => $locked,
                'enabled' => in_array(true, $channels, true),
            ];
        }

        return $rows;
    }

    /**
     * 把存量配置按当前场景目录校准：补齐新增渠道、移除已不再声明的渠道。
     *
     * 历史配置是按「当时的模板渠道」生成的，之后补了模板（如 2026-11-04 补齐短信）
     * 却没回填配置，导致批量配置勾选该渠道时找不到键而静默跳过。
     * 校准只补齐「目录声明但配置缺失」的渠道（默认禁用），已有配置不动。
     *
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    public function alignGroupsWithCatalog(string $audience, string $code, array $groups): array
    {
        $type = MessageTypeCatalog::get($audience, $code);

        if ($groups === []) {
            $groups = MessageGroups::fromStored($audience, $type, []);
        }

        return $this->alignGroups($audience, $type, $groups, $this->templateChannels($audience, $code));
    }

    /**
     * 校准的具体实现：模板渠道由调用方传入，避免每场景重复查库。
     *
     * @param  array<string, mixed>  $type
     * @param  list<array<string, mixed>>  $groups
     * @param  list<string>  $templateChannels
     * @return list<array<string, mixed>>
     */
    private function alignGroups(string $audience, array $type, array $groups, array $templateChannels): array
    {
        if ($groups === []) {
            $groups = MessageGroups::fromStored($audience, $type, []);
        }

        foreach ($groups as $index => $group) {
            $kind = (string) ($group['kind'] ?? '');
            // 渠道以「组类型允许」∪「该场景实际有模板」为准：
            // 只有模板存在才能发送，目录声明只是候选，不能反向限制已有模板。
            $allowed = array_values(array_unique(array_merge(
                array_intersect(MessageGroups::channelsFor($kind), $type['channels']),
                array_intersect($templateChannels, MessageGroups::channelsFor($kind))
            )));

            $configurations = (array) ($group['configurations'] ?? []);

            // 补齐缺失渠道：保留已有动作，新渠道给默认值（禁用）
            foreach ($allowed as $channel) {
                if (! array_key_exists($channel, $configurations)) {
                    $configurations[$channel] = MessageGroups::defaultChannelConfiguration($type, $channel);
                }
            }

            // 移除既不在目录、也没有模板的渠道（如已下线的公众号）
            foreach (array_keys($configurations) as $channel) {
                if (! in_array($channel, $allowed, true)) {
                    unset($configurations[$channel]);
                }
            }

            $groups[$index]['configurations'] = $configurations;
        }

        return $groups;
    }

    /**
     * 一次性查出某受众全部场景的模板渠道，键为场景编码。
     *
     * @return array<string, list<string>>
     */
    private function templateChannelsMap(string $audience): array
    {
        $rows = DB::table('message_scene_templates')
            ->where('audience', $audience)
            ->get(['scene_code', 'channel']);

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->scene_code][] = (string) $row->channel;
        }

        foreach ($map as $code => $channels) {
            $map[$code] = array_values(array_unique($channels));
        }

        return $map;
    }

    /**
     * 按已查到的模板渠道计算锁定渠道 => 原因。
     *
     * @param  list<string>  $templateChannels
     * @return array<string, string>
     */
    private function lockedChannelsFor(string $code, array $templateChannels): array
    {
        if (! in_array('sms', $templateChannels, true)) {
            return [];
        }

        $reason = $this->capabilities->smsLockReason($code);

        return $reason === '' ? [] : ['sms' => $reason];
    }

    /**
     * 该场景实际挂载的模板渠道。
     *
     * @return list<string>
     */
    private function templateChannels(string $audience, string $code): array
    {
        return DB::table('message_scene_templates')
            ->where('audience', $audience)
            ->where('scene_code', $code)
            ->pluck('channel')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 真正可发送的渠道 = 有模板 且 未被插件能力锁定。
     *
     * @param  list<string>  $templateChannels
     * @return list<string>
     */
    private function sendableChannelsFor(array $templateChannels, array $locked): array
    {
        return array_values(array_diff($templateChannels, array_keys($locked)));
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(string $audience, string $code): array
    {
        $type = MessageTypeCatalog::get($audience, $code);
        $stored = $this->configurations->get($audience, $code);

        // 模板渠道只查一次，校准与锁定判断复用
        $templateChannels = $this->templateChannels($audience, $code);
        $groups = $this->alignGroups(
            $audience,
            $type,
            MessageGroups::fromStored($audience, $type, $stored['configurations']),
            $templateChannels
        );
        $locked = $this->lockedChannelsFor($code, $templateChannels);

        return [
            'code' => $code,
            'name' => $type['name'],
            'description' => $type['description'],
            'purpose' => $this->purpose($code),
            'revisions' => [$audience => (int) $stored['revision']],
            'metadata' => $this->metadata($audience, $type),
            'groups' => $groups,
            'sendable_channels' => $this->sendableChannelsFor($templateChannels, $locked),
            'locked_channels' => $locked,
            'group_templates' => $this->groupTemplates($audience, $type),
            'channel_options' => $this->channelOptions($groups),
            'recipient_options' => $this->recipientOptions(),
            'templates' => $this->templates($code),
        ];
    }

    /**
     * 管理端保存前的结构校验：组类型/渠道/接收策略必须落在白名单内。
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function validateGroups(string $audience, string $code, array $payload): array
    {
        $type = MessageTypeCatalog::get($audience, $code);
        $raw = $payload['groups'] ?? null;

        if (! is_array($raw) || $raw === [] || count($raw) > 10) {
            throw new BusinessException('接收组数量无效', 42200, 422);
        }

        $normalized = [];
        foreach (array_values($raw) as $index => $group) {
            if (! is_array($group)) {
                throw new BusinessException("第 {$index} 个接收组格式无效", 42200, 422);
            }

            $kind = (string) ($group['kind'] ?? '');
            $allowed = ($audience === 'customer') ? ['customer'] : ['admin'];

            if (! in_array($kind, $allowed, true)) {
                throw new BusinessException('接收组类型无效', 42200, 422);
            }

            $configurations = [];
            foreach (MessageGroups::channelsFor($kind) as $channel) {
                if (! in_array($channel, $type['channels'], true)) {
                    continue;
                }

                $config = $group['configurations'][$channel] ?? null;
                if (! is_array($config)) {
                    continue;
                }

                $configurations[$channel] = [
                    'enabled' => (bool) ($config['enabled'] ?? false),
                    ...($channel === 'email' || $channel === 'sms' ? [
                        'channel_id' => max(0, (int) ($config['channel_id'] ?? 0)),
                        'channel_revision' => max(0, (int) ($config['channel_revision'] ?? 0)),
                    ] : []),
                    ...(array_key_exists('title', $config) ? ['title' => mb_substr((string) $config['title'], 0, 255)] : []),
                    ...(array_key_exists('body', $config) ? ['body' => mb_substr((string) $config['body'], 0, 20000)] : []),
                    ...(array_key_exists('template_id', $config) ? ['template_id' => mb_substr((string) $config['template_id'], 0, 128)] : []),
                    ...(array_key_exists('variables', $config) ? ['variables' => mb_substr((string) $config['variables'], 0, 4096)] : []),
                ];
            }

            $recipients = (array) ($group['recipients'] ?? []);
            $mode = (string) ($recipients['mode'] ?? 'founder');
            if (! in_array($mode, MessageGroups::RECIPIENT_MODES, true)) {
                throw new BusinessException('接收范围无效', 42200, 422);
            }

            $ids = array_values(array_map('intval', (array) ($recipients['ids'] ?? [])));
            if ($mode !== 'founder' && $ids === []) {
                throw new BusinessException('请选择接收对象', 42200, 422);
            }

            $normalized[] = [
                'id' => mb_substr((string) ($group['id'] ?? $kind), 0, 40),
                'name' => mb_substr((string) ($group['name'] ?? MessageGroups::KINDS[$kind]), 0, 64),
                'kind' => $kind,
                'enabled' => (bool) ($group['enabled'] ?? false),
                'recipients' => ['mode' => $mode, 'ids' => $mode === 'founder' ? [] : $ids],
                'configurations' => $configurations,
            ];
        }

        return $normalized;
    }

    /** @param  array<string, mixed>  $type */
    private function metadata(string $audience, array $type): array
    {
        $labels = MessageTypeCatalog::variableLabels();

        return [
            $audience => [
                'code' => $type['code'],
                'name' => $type['name'],
                'description' => $type['description'],
                'channels' => $type['channels'],
                'variables' => array_map(
                    static fn (string $key): array => ['key' => $key, 'label' => $labels[$key] ?? $key],
                    $type['variables']
                ),
            ],
        ];
    }

    /** @param  array<string, mixed>  $type */
    private function groupTemplates(string $audience, array $type): array
    {
        $kinds = $audience === 'customer' ? ['customer'] : ['admin'];

        $templates = [];
        foreach ($kinds as $kind) {
            $templates[$kind] = MessageGroups::create($type, $kind, $kind, MessageGroups::KINDS[$kind]);
        }

        return $templates;
    }

    /**
     * 渠道可选项：邮件/短信列出已绑定的驱动候选，站内消息与公众号暂无外部通道。
     *
     * @param  list<array<string, mixed>>  $groups
     * @return array<string, list<array{id: int, name: string, available: bool}>>
     */
    private function channelOptions(array $groups): array
    {
        $options = ['email' => [], 'sms' => [], 'message' => [], 'wechat' => []];

        $resolver = app(IntegrationDriverBindingResolver::class);

        foreach ([
            'email' => $resolver->mailDriverCandidates(),
            'sms' => $resolver->smsDriverCandidates(),
        ] as $channel => $candidates) {
            foreach (array_values($candidates) as $index => $key) {
                $options[$channel][] = [
                    'id' => $index + 1,
                    'name' => (string) $key,
                    'available' => $index === 0,
                ];
            }
        }

        return $options;
    }

    /**
     * 接收人候选：管理员按角色/管理员列表，部门未接入返回空。
     *
     * @return array<string, list<array{id: int, name: string}>>
     */
    private function recipientOptions(): array
    {
        return [
            'founder' => [],
            'departments' => [],
            'roles' => Role::query()->orderBy('id')->limit(200)
                ->get(['id', 'name'])
                ->map(static fn ($role): array => ['id' => (int) $role->id, 'name' => (string) $role->name])
                ->values()->all(),
            'members' => AdminUser::query()->where('status', 1)->orderBy('id')->limit(500)
                ->get(['id', 'username', 'nickname'])
                ->map(static fn ($admin): array => ['id' => (int) $admin->id, 'name' => (string) ($admin->nickname ?: $admin->username)])
                ->values()->all(),
        ];
    }

    /**
     * 场景关联的通知模板（来自 message_scene_templates 关联表）。
     *
     * @return list<array<string, mixed>>
     */
    private function templates(string $code): array
    {
        $rows = DB::table('message_scene_templates')
            ->where('scene_code', $code)
            ->orderBy('sort_order')
            ->get(['channel', 'template_code']);

        $result = [];
        foreach ($rows as $row) {
            $template = NotificationTemplate::query()
                ->where('channel', $row->channel)
                ->where('code', $row->template_code)
                ->first();

            if (! $template instanceof NotificationTemplate) {
                continue;
            }

            $result[] = [
                'channel' => (string) $template->channel,
                'code' => (string) $template->code,
                'name' => (string) $template->name,
                'is_enabled' => (bool) $template->is_enabled,
            ];
        }

        return $result;
    }

    private function purpose(string $code): string
    {
        return in_array($code, ['register_verification', 'login_verification', 'password_reset'], true)
            ? 'verification'
            : 'notification';
    }
}
