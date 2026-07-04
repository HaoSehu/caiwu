<?php

declare(strict_types=1);

use Caiwu\Plugins\Gateways\YiPay\YiPayPlugin;

return [
    'info' => [
        'domain' => 'payment',
        'slug' => 'yi_pay',
        'key' => 'yipay',
        'name' => '易支付',
        'version' => '1.0.0',
        'entry' => YiPayPlugin::class,
        'capabilities' => ['precreate', 'query', 'refund', 'notify_verify'],
    ],
    'config' => [
        'yipay_notice' => [
            'title' => '配置说明',
            'type' => 'notice',
            'theme' => 'warning',
            'content' => '请填写易支付平台商户 PID 与商户密钥。商户密钥保存后不会明文回显。',
        ],
        'enabled' => [
            'title' => '启用',
            'type' => 'switch',
            'value' => true,
            'required' => true,
            'description' => '关闭后该支付渠道不会作为可用支付方式。',
        ],
        'merchant_id' => [
            'title' => '商户 PID',
            'type' => 'text',
            'value' => '',
            'required' => true,
            'placeholder' => '请输入易支付商户 PID',
        ],
        'merchant_key' => [
            'title' => '商户密钥 KEY',
            'type' => 'password',
            'value' => '',
            'required' => true,
            'secret' => true,
            'placeholder' => '请输入易支付商户密钥',
        ],
        'payment_type' => [
            'title' => '支付方式',
            'type' => 'select',
            'value' => 'alipay',
            'required' => true,
            'options' => [
                ['label' => '支付宝', 'value' => 'alipay'],
                ['label' => '微信支付', 'value' => 'wxpay'],
            ],
        ],
        'channel_id' => [
            'title' => '支付渠道 ID',
            'type' => 'text',
            'value' => '',
            'required' => false,
            'placeholder' => '不填则由易支付随机分配渠道',
        ],
        'device' => [
            'title' => '设备类型',
            'type' => 'select',
            'value' => 'pc',
            'required' => false,
            'options' => [
                ['label' => 'PC', 'value' => 'pc'],
                ['label' => '手机', 'value' => 'mobile'],
            ],
        ],
    ],
];
