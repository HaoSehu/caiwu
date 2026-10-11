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
        // 显式 demo 标记：管理端按此识别示例插件（demo_ 前缀启发式仅作兼容兜底）。
        'demo' => true,
        'entry' => DemoSmsPlugin::class,
        'capabilities' => ['verify_code', 'message'],
    ],
    'config' => [
        'demo_notice' => ['title' => '演示插件', 'type' => 'notice', 'theme' => 'info', 'content' => '该插件只模拟短信发送结果，用于验证插件流程。'],
        'access_key' => ['title' => 'Access Key', 'type' => 'text', 'value' => 'demo_access_key', 'required' => true, 'placeholder' => '请输入 Access Key'],
        'secret_key' => ['title' => 'Secret Key', 'type' => 'password', 'value' => '', 'required' => false, 'secret' => true, 'placeholder' => '请输入 Secret Key'],
        'sign_name' => ['title' => '短信签名', 'type' => 'text', 'value' => 'Demo 签名', 'required' => true, 'placeholder' => '请输入短信签名'],
        'template_code' => ['title' => '验证码模板编号', 'type' => 'text', 'value' => '100001', 'required' => true, 'placeholder' => '请输入模板编号', 'description' => '验证码测试使用系统短信模板编号。'],
        'rate_limit_divider' => ['title' => '验证码限流', 'type' => 'divider'],
        'rate_limit_enabled' => ['title' => '启用短信验证码限流', 'type' => 'switch', 'value' => true, 'required' => false, 'description' => '限制使用此插件发送短信验证码的单 IP 频率。'],
        'ip_minute_limit' => ['title' => '单 IP 每分钟上限', 'type' => 'number', 'value' => 6, 'required' => false, 'min' => 0, 'step' => 1, 'description' => '设为 0 表示不限制。'],
        'template_review_divider' => ['title' => '模拟审核', 'type' => 'divider'],
        'review_mode' => ['title' => '模拟审核模式', 'type' => 'select', 'value' => 'manual', 'required' => false, 'options' => ['manual' => '提交后先 pending（完整闭环）', 'auto_approve' => '提交即过审（联调快路径）'], 'description' => 'sms.templates.push 提交后的模拟审核行为。'],
        'simulate_approve_seconds' => ['title' => '模拟过审等待秒数', 'type' => 'number', 'value' => 60, 'required' => false, 'min' => 0, 'step' => 1, 'description' => 'pending 状态经过该秒数后回查自动变为 approved，模拟运营商审核回调。'],
    ],
];
