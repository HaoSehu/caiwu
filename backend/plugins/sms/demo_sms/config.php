<?php

declare(strict_types=1);

use Caiwu\Plugins\Sms\DemoSms\DemoSmsPlugin;

return [
    'info' => [
        'domain' => 'sms',
        'slug' => 'demo_sms',
        'key' => 'demo_sms',
        'name' => 'Demo 短信',
        'version' => '1.0.0',
        'entry' => DemoSmsPlugin::class,
        'capabilities' => ['verify_code'],
    ],
    'config' => [
        'demo_notice' => ['title' => '演示插件', 'type' => 'notice', 'theme' => 'info', 'content' => '该插件只模拟短信发送结果，用于验证插件流程。'],
        'access_key' => ['title' => 'Access Key', 'type' => 'text', 'value' => 'demo_access_key', 'required' => true, 'placeholder' => '请输入 Access Key'],
        'secret_key' => ['title' => 'Secret Key', 'type' => 'password', 'value' => '', 'required' => false, 'secret' => true, 'placeholder' => '请输入 Secret Key'],
        'sign_name' => ['title' => '短信签名', 'type' => 'text', 'value' => 'Demo 签名', 'required' => true, 'placeholder' => '请输入短信签名'],
        'template_code' => ['title' => '模板编号', 'type' => 'text', 'value' => 'DEMO_001', 'required' => true, 'placeholder' => '请输入模板编号'],
    ],
];
