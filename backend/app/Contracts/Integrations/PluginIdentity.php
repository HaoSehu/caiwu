<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

/**
 * 插件运行时身份（A-01）。
 *
 * 由插件适配器实现，让支付、实名等消费方在业务链路中直接拿到所属插件的
 * id、plugin_key、slug 与名称，而不必再按 gateway_key/driver_key 反查
 * integration_plugins。未实现该接口的适配器由调用方沿用既有反查行为。
 *
 * 标识语义（A-03）：
 * - slug：插件目录名，同时是 (domain, slug) 唯一键的一半；
 * - plugin_key：插件自报的稳定标识，(domain, plugin_key) 唯一；
 * - gateway_key / driver_key：宿主侧业务标识，由插件 manifest 的 key 提供，
 *   支付与实名分别用 PaymentGatewayCode::normalize() 和绑定表 provider_key 归一。
 */
interface PluginIdentity
{
    public function pluginId(): ?int;

    public function pluginKey(): string;

    public function pluginSlug(): string;

    public function pluginName(): string;
}
