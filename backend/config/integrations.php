<?php

use App\Constants\PaymentGatewayCode;

return [
    'payments' => [
        'default' => env('PAYMENT_GATEWAY_DEFAULT', PaymentGatewayCode::ALIPAY_F2F_PLUGIN),
        'drivers' => [
            PaymentGatewayCode::ALIPAY_F2F_PLUGIN => [
                'name' => '支付宝当面付',
                'provider' => PaymentGatewayCode::ALIPAY,
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
    'upstream' => [
        'default' => env('UPSTREAM_PROVIDER_DEFAULT', ''),
        'preserve_provider_keys' => [
            'mofang_finance_api',
            'hosting_panel_api',
        ],
    ],
];
