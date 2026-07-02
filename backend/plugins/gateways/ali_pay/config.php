<?php

declare(strict_types=1);
use Caiwu\Plugins\Gateways\AliPay\AliPayPlugin;

return [
    'info' => [
        'domain' => 'payment',
        'slug' => 'ali_pay',
        'key' => 'alipay',
        'name' => '支付宝当面付',
        'version' => '1.0.0',
        'entry' => AliPayPlugin::class,
        'capabilities' => ['precreate', 'query', 'refund', 'notify_verify'],
        'extra' => [
            'legacy_settings' => [
                'group' => 'payment',
                'map' => [
                    'alipay_enabled' => 'alipay_enabled',
                    'app_id' => 'alipay_app_id',
                    'private_key' => 'alipay_private_key',
                    'alipay_public_key' => 'alipay_public_key',
                ],
            ],
        ],
    ],
    'config' => [
        'alipay_notice' => [
            'title' => '配置说明',
            'type' => 'notice',
            'theme' => 'warning',
            'content' => '请填写支付宝开放平台应用参数。私钥和公钥保存后不会明文回显。',
        ],
        'alipay_enabled' => [
            'title' => '启用',
            'type' => 'switch',
            'value' => true,
            'required' => true,
            'description' => '关闭后该支付渠道不会作为可用支付方式。',
        ],
        'app_id' => [
            'title' => 'App ID',
            'type' => 'text',
            'value' => '',
            'required' => true,
            'placeholder' => '请输入支付宝应用 App ID',
        ],
        'key_divider' => [
            'title' => '密钥配置',
            'type' => 'divider',
        ],
        'private_key' => [
            'title' => '应用私钥',
            'type' => 'textarea',
            'value' => '',
            'required' => true,
            'secret' => true,
            'rows' => 6,
            'placeholder' => '请输入应用私钥 PEM 内容',
            'description' => '请填写应用私钥，不要填写支付宝公钥。',
        ],
        'alipay_public_key' => [
            'title' => '支付宝公钥',
            'type' => 'textarea',
            'value' => '',
            'required' => true,
            'secret' => true,
            'rows' => 6,
            'placeholder' => '请输入支付宝公钥 PEM 内容',
        ],
        'network_divider' => [
            'title' => '网关与证书',
            'type' => 'divider',
        ],
        'gateway' => [
            'title' => '网关地址',
            'type' => 'url',
            'value' => 'https://openapi.alipay.com/gateway.do',
            'required' => false,
            'placeholder' => '请输入支付宝网关地址',
            'description' => '正式环境通常为 https://openapi.alipay.com/gateway.do，沙箱为 https://openapi-sandbox.dl.alipaydev.com/gateway.do。',
        ],
        'notify_url' => [
            'title' => '异步通知地址',
            'type' => 'url',
            'value' => '',
            'required' => false,
            'placeholder' => '留空时使用系统 APP/前端地址生成',
        ],
        'ssl_verify' => [
            'title' => 'SSL 证书校验',
            'type' => 'switch',
            'value' => true,
            'description' => '生产环境会强制开启；证书链问题请优先配置 CA Bundle。',
        ],
        'ca_bundle' => [
            'title' => 'CA Bundle 路径',
            'type' => 'text',
            'value' => '',
            'required' => false,
            'placeholder' => '例如 C:\\php\\extras\\ssl\\cacert.pem',
            'description' => 'Windows/PHP cURL 缺少根证书时填写 cacert.pem 绝对路径。',
        ],
    ],
];
