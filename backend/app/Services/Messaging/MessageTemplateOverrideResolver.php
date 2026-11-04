<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Exceptions\BusinessException;
use App\Models\IntegrationPlugin;
use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\Integrations\Plugins\PluginScanner;

/**
 * 插件强制覆盖模板内容（用户裁决：插件优先）。
 *
 * 插件在 config.php 的 `info.extra.template_overrides` 里声明要接管的模板字段：
 *
 * ```php
 * 'extra' => [
 *     'template_overrides' => [
 *         '100001' => [
 *             'subject' => '【{{site_name}}】您的验证码',
 *             'content' => '您的验证码是 {{code}}，{{expire_minutes}} 分钟内有效。',
 *             'provider_template_id' => 'SMS_123456',
 *         ],
 *     ],
 * ],
 * ```
 *
 * 语义（插件优先）：
 * - 插件声明的字段，管理端读取时以插件值为准，管理员无法修改；
 * - 管理端已保存的值会被插件值覆盖，而不是相反；
 * - 插件卸载或不再声明该字段时，回退到数据库里管理员保存的值。
 *
 * 只覆盖 `subject` / `content` / `provider_template_id` 三个字段，
 * 不触碰启用状态（启停由管理端与能力闸门共同决定）。
 */
final class MessageTemplateOverrideResolver
{
    /** 允许插件接管的字段白名单 */
    public const OVERRIDABLE_FIELDS = ['subject', 'content', 'provider_template_id'];

    public function __construct(
        private readonly IntegrationDriverBindingResolver $bindingResolver,
        private readonly PluginScanner $scanner,
    ) {}

    /**
     * 当前绑定的短信插件声明的模板覆盖。
     *
     * @return array<string, array<string, string>> 模板编码 => [字段 => 值]
     */
    public function smsOverrides(): array
    {
        return $this->overridesFor(PluginDomain::SMS, $this->bindingResolver->smsDriverKey());
    }

    /**
     * 当前绑定的邮件插件声明的模板覆盖。
     *
     * @return array<string, array<string, string>>
     */
    public function mailOverrides(): array
    {
        return $this->overridesFor(PluginDomain::MAIL, $this->bindingResolver->mailDriverKey());
    }

    /**
     * 指定渠道的模板覆盖。
     *
     * @return array<string, array<string, string>>
     */
    public function overridesForChannel(string $channel): array
    {
        return match ($channel) {
            'sms' => $this->smsOverrides(),
            'email' => $this->mailOverrides(),
            default => [],
        };
    }

    /**
     * 应用覆盖：返回合并后的模板数据。
     *
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    public function apply(array $template): array
    {
        $channel = (string) ($template['channel'] ?? '');
        $code = (string) ($template['code'] ?? '');

        if ($channel === '' || $code === '') {
            return $template;
        }

        $override = $this->overridesForChannel($channel)[$code] ?? null;

        if (! is_array($override)) {
            return $template;
        }

        $overridden = [];

        foreach (self::OVERRIDABLE_FIELDS as $field) {
            if (array_key_exists($field, $override)) {
                $template[$field] = (string) $override[$field];
                $overridden[] = $field;
            }
        }

        $template['overridden_fields'] = $overridden;

        return $template;
    }

    /**
     * 该模板有哪些字段被插件接管。
     *
     * @return list<string>
     */
    public function overriddenFields(string $channel, string $code): array
    {
        $override = $this->overridesForChannel($channel)[$code] ?? null;

        if (! is_array($override)) {
            return [];
        }

        return array_values(array_filter(
            self::OVERRIDABLE_FIELDS,
            static fn (string $field): bool => array_key_exists($field, $override)
        ));
    }

    /**
     * 把插件覆盖写回数据库，使插件优先在「数据层」也成立。
     *
     * 管理端保存模板时调用：只要字段被插件接管，就忽略管理员提交的值、写入插件值。
     *
     * @param  array<string, mixed>  $fields  管理员提交的字段
     * @return array<string, mixed> 实际应写入的字段
     */
    public function enforceOverride(string $channel, string $code, array $fields): array
    {
        $override = $this->overridesForChannel($channel)[$code] ?? null;

        if (! is_array($override)) {
            return $fields;
        }

        foreach (self::OVERRIDABLE_FIELDS as $field) {
            if (array_key_exists($field, $override)) {
                $fields[$field] = (string) $override[$field];
            }
        }

        return $fields;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function overridesFor(string $domain, string $driverKey): array
    {
        $plugin = IntegrationPlugin::query()
            ->where('domain', $domain)
            ->where('plugin_key', $driverKey)
            ->first(['config_schema_json', 'status']);

        if (! $plugin instanceof IntegrationPlugin) {
            return [];
        }

        // 覆盖声明在插件 config.php 的 info.extra 里，安装时随 manifest 落库。
        $manifestExtra = $this->manifestExtra($domain, $driverKey);

        if ($manifestExtra === null) {
            return [];
        }

        $declared = $manifestExtra['template_overrides'] ?? null;

        if (! is_array($declared)) {
            return [];
        }

        $result = [];
        foreach ($declared as $code => $fields) {
            if (! is_array($fields)) {
                continue;
            }

            $normalized = [];
            foreach (self::OVERRIDABLE_FIELDS as $field) {
                if (array_key_exists($field, $fields) && is_scalar($fields[$field])) {
                    $normalized[$field] = (string) $fields[$field];
                }
            }

            if ($normalized !== []) {
                $result[(string) $code] = $normalized;
            }
        }

        return $result;
    }

    /**
     * 从已安装插件的清单里取 extra 段。
     *
     * @return array<string, mixed>|null
     */
    private function manifestExtra(string $domain, string $driverKey): ?array
    {
        $row = IntegrationPlugin::query()
            ->where('domain', $domain)
            ->where('plugin_key', $driverKey)
            ->first(['slug']);

        if (! $row instanceof IntegrationPlugin) {
            return null;
        }

        try {
            // Scanner 内部按 (domain, slug) 缓存清单，不会每次 require config.php
            $manifest = $this->scanner->requireManifest($domain, (string) $row->slug);
        } catch (BusinessException) {
            return null;
        }

        return $manifest->extra === [] ? null : $manifest->extra;
    }
}
