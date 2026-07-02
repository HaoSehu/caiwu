<?php

declare(strict_types=1);

use Caiwu\Plugins\Mail\DemoMail\DemoMailPlugin;

return [
    'info' => [
        'domain' => 'mail',
        'slug' => 'demo_mail',
        'key' => 'demo_mail',
        'name' => 'Demo 邮件',
        'version' => '1.0.0',
        'entry' => DemoMailPlugin::class,
        'capabilities' => ['html', 'smtp_like'],
    ],
    'config' => [
        'demo_notice' => ['title' => '演示插件', 'type' => 'notice', 'theme' => 'info', 'content' => '该插件用于模拟邮件发送，不连接真实 SMTP 服务。'],
        'from_address' => ['title' => '发件邮箱', 'type' => 'email', 'value' => 'demo@example.test', 'required' => true, 'placeholder' => '请输入发件邮箱'],
        'from_name' => ['title' => '发件名称', 'type' => 'text', 'value' => 'Demo Mail', 'required' => true, 'placeholder' => '请输入发件名称'],
        'api_token' => ['title' => 'API Token', 'type' => 'password', 'value' => '', 'required' => false, 'secret' => true, 'placeholder' => '请输入 API Token'],
    ],
];
