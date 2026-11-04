<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\IntegrationPlugin;
use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Services\Integrations\Plugins\PluginDomain;

/**
 * 插件能力 → 消息场景可用性。
 *
 * 背景：短信插件在 config.php 里声明 capabilities（如阿里云号码认证只有 verify_code，
 * demo_sms/stay33 有 verify_code + message），但此前没有任何业务逻辑消费这个声明，
 * 导致「只能用验证码的插件」仍会出现业务短信开关，管理员点了也不会生效。
 *
 * 本服务把「当前绑定插件的能力」翻译成「哪些场景的短信渠道可发送」，供
 * 1) 发送链路跳过不支持的场景；2) 管理端把对应开关锁定为禁用且不可操作。
 */
final class MessageChannelCapabilityResolver
{
    /** 验证码类场景：只有声明 verify_code 能力的插件可发 */
    private const VERIFICATION_SCENES = [
        'register_verification',
        'login_verification',
        'password_reset',
    ];

    public function __construct(
        private readonly IntegrationDriverBindingResolver $bindingResolver,
    ) {}

    /**
     * 请求级缓存：插件能力与插件名在一次请求内不会变化。
     *
     * 列表接口按场景逐个判断，30 个场景会重复查同一行插件记录；
     * 这里缓存后单次请求只查一次。
     *
     * @var array{capabilities?: list<string>|null, name?: string, driver_key?: string}
     */
    private array $cache = [];

    /** 当前绑定的短信驱动 key；绑定关系一次请求内不变，缓存后避免反复查库 */
    private function driverKey(): string
    {
        return $this->cache['driver_key'] ??= $this->bindingResolver->smsDriverKey();
    }

    /**
     * 当前绑定的短信插件是否支持指定场景。
     *
     * 未安装/未绑定插件时返回 true（保持旧行为，不因插件缺失而阻断发送）。
     */
    public function smsSupportsScene(string $scene): bool
    {
        $capabilities = $this->smsCapabilities();

        if ($capabilities === null) {
            return true;
        }

        $required = $this->requiredSmsCapability($scene);

        return in_array($required, $capabilities, true);
    }

    /**
     * 该场景的短信渠道是否被插件能力锁定（锁定 = 不可操作）。
     */
    public function smsChannelLocked(string $scene): bool
    {
        return ! $this->smsSupportsScene($scene);
    }

    /**
     * 当前绑定短信插件的能力清单；插件不存在时返回 null（表示不限制）。
     *
     * @return list<string>|null
     */
    public function smsCapabilities(): ?array
    {
        if (array_key_exists('capabilities', $this->cache)) {
            return $this->cache['capabilities'];
        }

        $driverKey = $this->driverKey();

        $plugin = IntegrationPlugin::query()
            ->where('domain', PluginDomain::SMS)
            ->where('plugin_key', $driverKey)
            ->first(['capabilities_json', 'status']);

        if (! $plugin instanceof IntegrationPlugin) {
            return $this->cache['capabilities'] = null;
        }

        $capabilities = $plugin->capabilities_json;

        return $this->cache['capabilities'] = is_array($capabilities)
            ? array_values(array_filter($capabilities, 'is_string'))
            : [];
    }

    /**
     * 场景需要的短信能力标识。
     *
     * 验证码类需要 verify_code；其余业务通知需要 message。
     */
    public function requiredSmsCapability(string $scene): string
    {
        return in_array($scene, self::VERIFICATION_SCENES, true) ? 'verify_code' : 'message';
    }

    /**
     * 当前绑定插件的名称，用于管理端提示文案。
     */
    public function smsPluginName(): string
    {
        if (isset($this->cache['name'])) {
            return $this->cache['name'];
        }

        $driverKey = $this->driverKey();

        $name = IntegrationPlugin::query()
            ->where('domain', PluginDomain::SMS)
            ->where('plugin_key', $driverKey)
            ->value('name');

        return $this->cache['name'] = is_string($name) && $name !== '' ? $name : $driverKey;
    }

    /**
     * 该场景在短信渠道的锁定原因；未锁定时返回空字符串。
     */
    public function smsLockReason(string $scene): string
    {
        if (! $this->smsChannelLocked($scene)) {
            return '';
        }

        $capability = $this->requiredSmsCapability($scene);
        $label = $capability === 'verify_code' ? '验证码' : '业务通知';

        return sprintf('当前短信插件「%s」不支持%s类消息', $this->smsPluginName(), $label);
    }

    /**
     * 验证码类场景清单（供管理端判断用）。
     *
     * @return list<string>
     */
    public function verificationScenes(): array
    {
        return self::VERIFICATION_SCENES;
    }
}
