<?php

declare(strict_types=1);
use Caiwu\Plugins\Sms\Aliyun\AliyunPlugin;

return [
    'info' => [
        'domain' => 'sms',
        'slug' => 'aliyun',
        'key' => 'aliyun',
        'name' => '阿里云号码认证短信',
        'version' => '1.0.0',
        'entry' => AliyunPlugin::class,
        // 本插件只接入阿里云号码认证服务（dypnsapi），仅支持其内置验证码模板，
        // 不具备通用短信服务的业务模板发送能力。
        'capabilities' => ['verify_code'],
        'extra' => [
            'driver_binding' => [
                'binding_key' => 'sms_driver',
                'provider_key' => 'aliyun',
            ],
            // 阿里云号码认证短信的赠送模板为验证码类（100001–100005），
            // 插件优先接管对应模板的短信正文，管理端只读不可改。
            // 占位符使用系统短信模板语法 {code} / {min}（渲染时按此替换），
            // 不是阿里云 API 的 ${code} 写法。
            'template_overrides' => [
                '100001' => [
                    'content' => '您的验证码为{code}。尊敬的客户，以上验证码{min}分钟内有效，请注意保密，切勿告知他人。',
                ],
            ],
        ],
    ],
    'config' => [
        'credential_notice' => ['title' => '配置说明', 'type' => 'notice', 'theme' => 'warning', 'content' => '请使用拥有短信发送权限的阿里云 AccessKey，密钥保存后不会明文回显。验证码模板编号由插件按业务场景内置选择。'],
        'access_key' => ['title' => 'Access Key', 'type' => 'password', 'value' => '', 'required' => true, 'secret' => true, 'placeholder' => '请输入 Access Key ID'],
        'secret_key' => ['title' => 'Secret Key', 'type' => 'password', 'value' => '', 'required' => true, 'secret' => true, 'placeholder' => '请输入 Access Key Secret'],
        'sign_name' => ['title' => '短信签名', 'type' => 'text', 'value' => '', 'required' => true, 'placeholder' => '请输入阿里云号码认证短信签名', 'description' => '请填写阿里云号码认证控制台中可用于发送验证码的短信签名。'],
        'rate_limit_divider' => ['title' => '验证码限流', 'type' => 'divider'],
        'rate_limit_enabled' => ['title' => '启用短信验证码限流', 'type' => 'switch', 'value' => true, 'required' => false, 'description' => '限制使用此插件发送短信验证码的单 IP 频率。'],
        'ip_minute_limit' => ['title' => '单 IP 每分钟上限', 'type' => 'number', 'value' => 6, 'required' => false, 'min' => 0, 'step' => 1, 'description' => '设为 0 表示不限制。'],
    ],
];
