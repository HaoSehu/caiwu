<?php

declare(strict_types=1);

use App\Services\Upstream\Contracts\ProvidesConsoleAccess;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\Contracts\ProvidesConsoleNetwork;
use App\Services\Upstream\Contracts\ProvidesConsoleRuntime;
use App\Services\Upstream\Contracts\ProvidesConsoleSecurity;
use App\Services\Upstream\Contracts\ProvidesProvisioning;
use App\Services\Upstream\Contracts\ProvidesRenewal;
use App\Services\Upstream\Contracts\ProvidesScheduledAuthRefresh;
use App\Services\Upstream\Contracts\ProvidesStatusSync;
use Caiwu\Plugins\Servers\HostingPanel\HostingPanelPlugin;
use Caiwu\Plugins\Servers\HostingPanel\Lib\HostingPanelAuthRefreshTask;

return [
    'info' => [
        'domain' => 'upstream',
        'slug' => 'hosting_panel',
        'key' => 'hosting_panel_api',
        'name' => '主机面板接口',
        'version' => '1.0.0',
        'entry' => HostingPanelPlugin::class,
        'capabilities' => [
            ProvidesConsoleAccess::class,
            ProvidesConsoleCatalog::class,
            ProvidesConsoleNetwork::class,
            ProvidesConsoleRuntime::class,
            ProvidesConsoleSecurity::class,
            ProvidesProvisioning::class,
            ProvidesRenewal::class,
            ProvidesScheduledAuthRefresh::class,
            ProvidesStatusSync::class,
        ],
        'extra' => [
            'scheduled_tasks' => [
                HostingPanelAuthRefreshTask::class,
            ],
        ],
    ],
    'config' => [
        'hosting_panel_notice' => [
            'title' => '配置说明',
            'type' => 'notice',
            'theme' => 'info',
            'content' => '该插件承载系统内置主机面板接口的驱动注册，接口地址与 JWT 登录凭证由供应商配置维护；服务级参数（JWT 缓存存储、超时等）见 config idc.hosting_panel_api。',
        ],
        'provider_key' => [
            'title' => '上游标识',
            'type' => 'readonly',
            'value' => 'hosting_panel_api',
            'description' => '供应商绑定 provider_key 为 hosting_panel_api 时使用主机面板接口插件。',
        ],
    ],
];
