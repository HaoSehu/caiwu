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
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangInventoryAndServiceSyncHook;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangInventoryAndServiceSyncTask;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangScheduledAuthRefreshHook;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangScheduledAuthRefreshTask;
use Caiwu\Plugins\Servers\MofangFinance\MofangFinancePlugin;

return [
    'info' => [
        'domain' => 'upstream',
        'slug' => 'mofang_finance',
        'key' => 'mofang_finance_api',
        'name' => '魔方财务接口',
        'version' => '1.0.0',
        'entry' => MofangFinancePlugin::class,
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
                MofangScheduledAuthRefreshTask::class,
                MofangInventoryAndServiceSyncTask::class,
            ],
            'schedule_hooks' => [
                'plugins.mofang_finance.auth_refresh' => [
                    MofangScheduledAuthRefreshHook::class,
                ],
                'plugins.mofang_finance.inventory_service_sync' => [
                    MofangInventoryAndServiceSyncHook::class,
                ],
            ],
        ],
    ],
    'config' => [
        'mofang_notice' => [
            'title' => '配置说明',
            'type' => 'notice',
            'theme' => 'info',
            'content' => '该插件承载魔方财务上游差异适配，接口地址、账号和密钥由供应商配置维护。',
        ],
        'provider_key' => [
            'title' => '上游标识',
            'type' => 'readonly',
            'value' => 'mofang_finance_api',
            'description' => '供应商绑定 provider_key 必须保持该值，不要别名为 hosting_panel_api。',
        ],
    ],
];
