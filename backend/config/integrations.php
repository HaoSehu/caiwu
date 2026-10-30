<?php

use App\Constants\PaymentGatewayCode;

return [
    // 插件包完整性强制开关：默认 true，摘要与安装时记录不一致时拒绝加载插件。
    // 仅在需要临时观察存量差异的环境显式设置 PLUGIN_INTEGRITY_ENFORCE=false。
    // 注意：source_hash 为空的存量记录仍会放行并记 error，须执行
    // php artisan plugins:backfill-source-hash --execute 补齐后才会真正受控。
    'plugin_integrity_enforce' => (bool) env('PLUGIN_INTEGRITY_ENFORCE', true),
    // 各域只声明默认驱动 key；可用驱动一律来自已安装插件与绑定表，
    // 不在此重复登记，避免出现「配置里列了某个驱动、实际并没有对应插件」的假清单。
    'payments' => [
        'default' => env('PAYMENT_GATEWAY_DEFAULT', PaymentGatewayCode::ALIPAY),
    ],
    'identity' => [
        'default' => env('IDENTITY_PROVIDER_DEFAULT', 'stay33'),
    ],
    'sms' => [
        'default' => env('SMS_PROVIDER_DEFAULT', 'aliyun'),
    ],
    'mail' => [
        'default' => env('MAIL_DRIVER_DEFAULT', 'smtp'),
    ],
];
