<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 接收组：消息场景内的「一组接收对象 + 一组渠道配置」。
 *
 * 对齐 howstack-bos 的 MessageGroups。组是策略边界：
 * 同一场景可有多组（关联客户 / 内部人员 / 群机器人），组内再按渠道声明启停与模板。
 * 同一接收人在重叠组中、同一渠道只发送一次，取最先命中的组。
 */
final class MessageGroups
{
    /** 组类型白名单；robot 的机器人渠道本期只留字段不实现投递（见计划决策日志） */
    public const KINDS = [
        'customer' => '关联客户',
        'admin' => '内部人员',
        'robot' => '群机器人',
    ];

    /** 组类型 → 允许渠道。管理员组同样支持短信（管理端短信模板已存在） */
    private const CHANNELS_BY_KIND = [
        'customer' => ['email', 'sms', 'message', 'wechat'],
        'admin' => ['email', 'sms', 'message'],
        'robot' => [],
    ];

    /** 接收范围模式；founder 无 ids，其余必须给出非空 ids */
    public const RECIPIENT_MODES = ['founder', 'departments', 'roles', 'members'];

    /** @return list<string> 组类型允许的渠道 */
    public static function channelsFor(string $kind): array
    {
        return self::CHANNELS_BY_KIND[$kind] ?? [];
    }

    /** @return string 组类型对应的受众（robot 归 admin 展示） */
    public static function audienceFor(string $kind): string
    {
        return $kind === 'customer' ? 'customer' : 'admin';
    }

    /**
     * 新建一个默认关闭的接收组。
     *
     * @param  array<string, mixed>  $type  场景定义（MessageTypeCatalog::get 的返回）
     * @return array<string, mixed>
     */
    public static function create(array $type, string $kind, string $id, string $name): array
    {
        $configurations = [];
        foreach (array_values(array_intersect(self::channelsFor($kind), $type['channels'])) as $channel) {
            $configurations[$channel] = self::defaultChannelConfiguration($type, $channel);
        }

        return [
            'id' => $id,
            'name' => $name,
            'kind' => $kind,
            'enabled' => true,
            'recipients' => ['mode' => 'founder', 'ids' => []],
            'configurations' => $configurations,
        ];
    }

    /**
     * 单渠道默认配置。默认关闭：新建场景不会意外开始发送。
     *
     * @param  array<string, mixed>  $type
     * @return array<string, mixed>
     */
    public static function defaultChannelConfiguration(array $type, string $channel): array
    {
        $config = ['enabled' => false];

        if (in_array($channel, ['email', 'sms'], true)) {
            $config['channel_id'] = 0;
            $config['channel_revision'] = 0;
        }

        if (in_array($channel, ['email', 'message'], true)) {
            $config['title'] = '{{site_name}} - '.$type['name'];
        }

        if ($channel !== 'wechat') {
            $lines = [$type['description'].'。'];
            foreach ($type['variables'] as $key) {
                if (in_array($key, ['site_name', 'customer_name'], true)) {
                    continue;
                }
                $label = MessageTypeCatalog::variableLabels()[$key] ?? $key;
                $lines[] = $label.'：{{'.$key.'}}';
            }
            $config['body'] = implode("\n", $lines);
        }

        if (in_array($channel, ['sms', 'wechat'], true)) {
            $config['template_id'] = '';
        }

        return $config;
    }

    /**
     * 读取已存配置并补齐默认值；兼容旧结构（无 _groups 时按受众生成单组）。
     *
     * @param  array<string, mixed>  $type
     * @param  array<string, mixed>  $stored
     * @return list<array<string, mixed>>
     */
    public static function fromStored(string $audience, array $type, array $stored): array
    {
        if (isset($stored['_groups']) && is_array($stored['_groups'])) {
            return array_values($stored['_groups']);
        }

        $kinds = $audience === 'customer' ? ['customer'] : ['admin'];

        return array_map(
            static fn (string $kind): array => self::create($type, $kind, $kind, self::KINDS[$kind]),
            $kinds
        );
    }

    /** 组是否生效：组本身启用且至少一个渠道启用 */
    public static function enabled(array $group): bool
    {
        if (! ($group['enabled'] ?? false)) {
            return false;
        }

        foreach ((array) ($group['configurations'] ?? []) as $config) {
            if (($config['enabled'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }
}
