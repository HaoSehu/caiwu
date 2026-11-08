<?php

declare(strict_types=1);

use Caiwu\Plugins\Sms\Stay33\Stay33Plugin;

return [
    'info' => [
        'domain' => 'sms',
        'slug' => 'stay33',
        'key' => 'stay33',
        'name' => 'MC云短信',
        'version' => '2.0.0',
        'entry' => Stay33Plugin::class,
        'capabilities' => ['verify_code', 'message'],
        'extra' => [
            'driver_binding' => [
                'binding_key' => 'sms_driver',
                'provider_key' => 'stay33',
            ],
        ],
    ],
    'config' => [
        'credential_notice' => [
            'title' => '配置说明',
            'type' => 'notice',
            'theme' => 'warning',
            'content' => '本插件对接 MCloud v10 开放 API。在 MCloud 短信控制台右上角获取 UID，在「访问控制 → API 密钥」创建 API Key；短信签名需先提交审核，发信通道 ID 可在控制台通道列表查看。发送前请确认账号已开通短信产品且有可用额度。',
        ],
        'uid' => [
            'title' => '账号 UID',
            'type' => 'text',
            'value' => '',
            'required' => false,
            'placeholder' => '请输入 MCloud 账号 UID',
            'description' => '控制台右上角显示的 UID（数字 ID 或用户 UUID），不是登录用户名。',
        ],
        'api_key' => [
            'title' => 'API 密钥',
            'type' => 'password',
            'value' => '',
            'required' => false,
            'secret' => true,
            'placeholder' => '请输入 MCloud API 密钥',
            'description' => '在「访问控制 → API 密钥」创建。已配置时不会明文回显，留空表示不修改。',
        ],
        'sign_name' => [
            'title' => '短信签名',
            'type' => 'text',
            'value' => '',
            'required' => true,
            'placeholder' => '请输入签名名称，不含外层【】',
            'description' => '通过官方 signature 参数下发，无需写进短信正文。',
        ],
        'channel' => [
            'title' => '发信通道',
            'type' => 'number',
            'value' => '',
            'required' => false,
            'min' => 0,
            'step' => 1,
            'placeholder' => '可留空',
            'description' => 'MCloud 发信通道 ID（channel_id）。留空表示由上游使用账号第一个可用通道。',
        ],
        // 旧版插件的用户名字段，隐藏保留：PluginConfigRepository 保存时按当前
        // schema 重建配置，删掉该字段会让已保存的值在下次保存时被丢弃。
        'username' => [
            'title' => '旧版用户名（兼容）',
            'type' => 'text',
            'value' => '',
            'required' => false,
            'visible' => false,
        ],
        // 不在本地做验证码限流：MCloud 短信控制台自带防刷设置
        // （黑名单、同号码每日/每小时上限、同号码最小间隔、账号总量上限），
        // 本地重复限流只会误伤正常用户。
    ],
];
