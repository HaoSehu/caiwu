<?php

declare(strict_types=1);

use Caiwu\Plugins\Addons\DemoStyle\DemoStylePlugin;
use Caiwu\Plugins\Addons\DemoStyle\Lib\DemoStyleHook;
use Caiwu\Plugins\Addons\DemoStyle\Lib\DemoStyleScheduledTask;

return [
    'info' => [
        'domain' => 'addons',
        'slug' => 'demo_style',
        'key' => 'demo_style',
        'name' => 'Demo Style 功能扩展',
        'version' => '1.0.0',
        'entry' => DemoStylePlugin::class,
        // 显式 demo 标记：管理端列表/安装/升级按此识别示例插件（demo_ 前缀启发式仅作兼容兜底）。
        'demo' => true,
        'capabilities' => [
            'addon.schedule_hook',
            'addon.scheduled_task',
        ],
        'extra' => [
            // 2026-10-11 收口：addons 域当前仅支持调度扩展（schedule_hooks/scheduled_tasks）。
            // 原声明的 admin_entry/client_entry/public_entry（addon.admin.index 等）自始没有
            // 平台消费方（无路由、无渲染），属死扩展点，已删除；受控 addon action 通道
            // 如未来立项，须带权限码、白名单与审计后再恢复。
            'schedule_hooks' => [
                'addons.demo_style.refresh' => [
                    DemoStyleHook::class,
                ],
            ],
            'scheduled_tasks' => [
                DemoStyleScheduledTask::class,
            ],
        ],
    ],
    'config' => [
        'style_notice' => [
            'title' => 'ZJMF Addon 结构参考',
            'type' => 'notice',
            'theme' => 'info',
            'content' => '该示例参考 ZJMF addons/demo_style 的目录语义，但运行时使用 Caiwu 统一插件 execute() 入口。',
        ],
        'theme_name' => [
            'title' => '主题名称',
            'type' => 'text',
            'value' => 'classic-blue',
            'required' => true,
            'placeholder' => 'classic-blue',
        ],
        'accent_color' => [
            'title' => '强调色',
            'type' => 'text',
            'value' => '#2563eb',
            'required' => false,
            'placeholder' => '#2563eb',
        ],
        'enabled' => [
            'title' => '启用扩展',
            'type' => 'switch',
            'value' => true,
            'description' => '关闭后仍可安装插件，但业务入口应由调用方隐藏。',
        ],
    ],
];
