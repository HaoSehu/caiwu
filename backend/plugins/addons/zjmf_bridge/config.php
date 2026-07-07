<?php

declare(strict_types=1);

use Caiwu\Plugins\Addons\ZjmfBridge\ZjmfBridgePlugin;

return [
    'info' => [
        'domain' => 'addons',
        'slug' => 'zjmf_bridge',
        'key' => 'zjmf_bridge',
        'name' => 'ZJMF Bridge 接口插件',
        'version' => '1.0.0',
        'entry' => ZjmfBridgePlugin::class,
        'capabilities' => [
            'zjmf.bridge',
            'zjmf.dispatch',
            'zjmf.auth',
            'zjmf.finance',
            'zjmf.product',
            'zjmf.service',
            'zjmf.ticket',
            'zjmf.reconcile',
        ],
        'extra' => [
            'base_path_config' => 'zjmf_bridge.base_path',
            'core_boundary' => [
                'route_file' => 'routes/zjmf.php',
                'middleware' => [
                    'zjmf.enabled',
                    'zjmf.signature',
                    'zjmf.client',
                    'zjmf.actor',
                    'zjmf.log',
                ],
            ],
        ],
    ],
    'config' => [
        'bridge_notice' => [
            'title' => '入口边界说明',
            'type' => 'notice',
            'theme' => 'info',
            'content' => 'ZJMF URL、签名和 JWT 校验仍由核心受控路由处理，校验通过后的 API 行为由本 addon 执行。',
        ],
        'enabled' => [
            'title' => '启用插件内业务处理',
            'type' => 'switch',
            'value' => true,
            'description' => '关闭后插件仍可安装，但 /zjmf/v1/* 会返回插件业务未启用。',
        ],
    ],
];
