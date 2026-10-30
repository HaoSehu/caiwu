<?php

declare(strict_types=1);

use Caiwu\Plugins\Captcha\Vaptcha\VaptchaPlugin;

return [
    'info' => [
        'domain' => 'captcha',
        'slug' => 'vaptcha',
        'key' => 'vaptcha',
        'name' => 'VAPTCHA 智能人机验证',
        'version' => '1.0.0',
        'entry' => VaptchaPlugin::class,
        'capabilities' => ['config', 'verify', 'script'],
        'extra' => [
            'driver_binding' => [
                'binding_key' => 'captcha_driver',
                'provider_key' => 'vaptcha',
            ],
        ],
    ],
    'config' => [
        'basic_notice' => [
            'title' => '配置说明',
            'type' => 'notice',
            'theme' => 'info',
            'content' => '请在 VAPTCHA 控制台创建验证单元并填写 VID 与 VKEY。VID 可下发前端，VKEY 只保存在服务端，保存后不会明文回显。',
        ],
        'vid' => [
            'title' => 'VID',
            'type' => 'text',
            'value' => '',
            'required' => true,
            'placeholder' => '请输入 VAPTCHA VID',
            'description' => '来自 VAPTCHA 控制台的验证单元 VID，用于前端初始化。',
        ],
        'replay_ttl_seconds' => [
            'title' => '防重放窗口（秒）',
            'type' => 'number',
            'value' => 600,
            'required' => false,
            'placeholder' => '600',
            'description' => '同一次行为验证结果在多少秒内禁止重复提交，默认 600，允许范围 60-1800。',
        ],
        'vkey' => [
            'title' => 'VKEY',
            'type' => 'password',
            'value' => '',
            'required' => true,
            'secret' => true,
            'placeholder' => '请输入 VAPTCHA VKEY',
            'description' => '来自 VAPTCHA 控制台的服务端密钥，仅用于后端二次验证。',
        ],
    ],
];
