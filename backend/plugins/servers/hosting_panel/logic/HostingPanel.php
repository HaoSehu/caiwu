<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\HostingPanel\Logic;

use App\Services\Upstream\Contracts\ProvidesConsoleAccess;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\Contracts\ProvidesConsoleNetwork;
use App\Services\Upstream\Contracts\ProvidesConsoleRuntime;
use App\Services\Upstream\Contracts\ProvidesConsoleSecurity;
use App\Services\Upstream\Contracts\ProvidesProvisioning;
use App\Services\Upstream\Contracts\ProvidesRenewal;
use App\Services\Upstream\Contracts\ProvidesScheduledAuthRefresh;
use App\Services\Upstream\Contracts\ProvidesStatusSync;
use App\Services\Upstream\Contracts\ProvidesSupplierFormSchema;
use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use App\Services\Upstream\ProviderKey;
use App\Services\Upstream\Support\AbstractUpstreamServerPlugin;

/**
 * 主机面板接口驱动。
 *
 * 能力承载委托给系统侧 HostingPanelApiTransport（implements 9 个 Provides* 能力接口，
 * 且被 zjmf_finance 插件共享复用，故保留在系统侧而非随迁插件）；
 * 本类按 AbstractUpstreamServerPlugin 的 capabilityCarrier 钩子把能力解析委托出去。
 */
class HostingPanel extends AbstractUpstreamServerPlugin implements ProvidesSupplierFormSchema, UpstreamDriver
{
    private const CAPABILITIES = [
        ProvidesConsoleAccess::class,
        ProvidesConsoleCatalog::class,
        ProvidesConsoleNetwork::class,
        ProvidesConsoleRuntime::class,
        ProvidesConsoleSecurity::class,
        ProvidesProvisioning::class,
        ProvidesRenewal::class,
        ProvidesScheduledAuthRefresh::class,
        ProvidesStatusSync::class,
    ];

    public function __construct(
        private readonly HostingPanelApiTransport $transport,
    ) {}

    public function key(): string
    {
        return ProviderKey::HOSTING_PANEL_API;
    }

    public function label(): string
    {
        return '主机面板接口';
    }

    /**
     * @return array<int, class-string>
     */
    public function capabilities(): array
    {
        return self::CAPABILITIES;
    }

    /**
     * 能力承载者：Transport 已实现全部声明能力，解析直接委托。
     */
    protected function capabilityCarrier(): object
    {
        return $this->transport;
    }

    public function supplierFormSchema(): array
    {
        return [
            'help' => '主机面板接口使用供应商后台地址（api_url）与 JWT 登录凭证完成认证并刷新会话。',
            'fields' => [
                [
                    'key' => 'api_url',
                    'label' => '主机面板地址',
                    'type' => 'url',
                    'required' => true,
                    'placeholder' => 'https://panel.example.com',
                ],
                [
                    'key' => 'api_username',
                    'label' => '登录账号',
                    'type' => 'text',
                    'required' => true,
                ],
                [
                    'key' => 'api_key',
                    'label' => '登录密码/API 密钥',
                    'type' => 'password',
                    'required' => true,
                    'secret' => true,
                    'placeholder' => '编辑时留空则保持原密钥',
                ],
            ],
        ];
    }
}
