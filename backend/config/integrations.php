<?php

use App\Constants\PaymentGatewayCode;

return [
    // 插件包完整性强制开关：false 为观察模式（摘要不一致只记错误日志，仍加载插件），
    // 确认存量插件全部回填 source_hash 后再改为 true 才真正阻断注册。
    'plugin_integrity_enforce' => (bool) env('PLUGIN_INTEGRITY_ENFORCE', false),
    'payments' => [
        'default' => env('PAYMENT_GATEWAY_DEFAULT', PaymentGatewayCode::ALIPAY),
        'drivers' => [
            PaymentGatewayCode::ALIPAY => [
                'name' => '支付宝当面付',
                'provider' => PaymentGatewayCode::ALIPAY,
            ],
            PaymentGatewayCode::YIPAY => [
                'name' => '易支付',
                'provider' => PaymentGatewayCode::YIPAY,
            ],
        ],
    ],
    'identity' => [
        'default' => env('IDENTITY_PROVIDER_DEFAULT', 'stay33'),
        'drivers' => [
            'stay33' => [
                'name' => 'Stay33 实名认证',
            ],
        ],
    ],
    'sms' => [
        'default' => env('SMS_PROVIDER_DEFAULT', 'aliyun'),
        'drivers' => [
            'aliyun' => [
                'name' => '阿里云短信',
            ],
        ],
    ],
    'mail' => [
        'default' => env('MAIL_DRIVER_DEFAULT', 'smtp'),
        'drivers' => [
            'smtp' => [
                'name' => 'Single SMTP',
            ],
            'multi_smtp_round_robin' => [
                'name' => 'Multi SMTP Round Robin',
            ],
        ],
    ],
];
