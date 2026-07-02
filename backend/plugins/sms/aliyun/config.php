<?php

declare(strict_types=1);
use Caiwu\Plugins\Sms\Aliyun\AliyunPlugin;

return [
    'info' => [
        'domain' => 'sms',
        'slug' => 'aliyun',
        'key' => 'aliyun',
        'name' => '阿里云短信',
        'version' => '1.0.0',
        'entry' => AliyunPlugin::class,
        'capabilities' => ['verify_code'],
        'extra' => [
            'legacy_settings' => [
                'group' => 'notification',
                'map' => [
                    'access_key' => 'sms_access_key',
                    'secret_key' => 'sms_secret_key',
                    'sign_name' => 'sms_sign_name',
                    'template_code' => 'sms_template_code',
                ],
            ],
            'selection_setting' => [
                'group' => 'notification',
                'key' => 'sms_driver',
                'value' => 'aliyun',
            ],
        ],
    ],
    'config' => [
        'credential_notice' => ['title' => '配置说明', 'type' => 'notice', 'theme' => 'warning', 'content' => '请使用拥有短信发送权限的阿里云 AccessKey，密钥保存后不会明文回显。'],
        'access_key' => ['title' => 'Access Key', 'type' => 'password', 'value' => '', 'required' => true, 'secret' => true, 'placeholder' => '请输入 Access Key ID'],
        'secret_key' => ['title' => 'Secret Key', 'type' => 'password', 'value' => '', 'required' => true, 'secret' => true, 'placeholder' => '请输入 Access Key Secret'],
        'template_divider' => ['title' => '短信模板', 'type' => 'divider'],
        'sign_name' => ['title' => '短信签名', 'type' => 'text', 'value' => '', 'required' => true, 'placeholder' => '请输入短信签名'],
        'template_code' => ['title' => '模板编号', 'type' => 'text', 'value' => '', 'required' => true, 'placeholder' => '请输入短信模板编号', 'description' => '发送验证码时使用的模板编号。'],
    ],
];
