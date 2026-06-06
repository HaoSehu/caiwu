<?php

/*
|--------------------------------------------------------------------------
| IDC 业务配置
|--------------------------------------------------------------------------
*/

$mofangFinanceConfig = static function (): array {
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $configured = config('mofang.finance_api');
    if (is_array($configured) && $configured !== []) {
        return $config = $configured;
    }

    $loaded = is_file(__DIR__.'/mofang.php') ? require __DIR__.'/mofang.php' : [];
    $financeApi = is_array($loaded) ? ($loaded['finance_api'] ?? []) : [];

    return $config = is_array($financeApi) ? $financeApi : [];
};

$mofangFinanceFallback = static fn (string $key, mixed $default = null): mixed => $mofangFinanceConfig()[$key] ?? $default;

return [
    // 站点名称
    'site_name' => env('IDC_SITE_NAME', '创欧云'),

    // 订单自动取消时间（分钟）
    'order_auto_cancel_minutes' => 30,

    // 账单逾期宽限天数
    'invoice_overdue_grace_days' => 3,

    // 服务暂停后自动终止天数
    'service_terminate_days' => 30,

    // 自动续费提前天数
    'auto_renew_days_before' => 7,

    // 计费周期映射（天数）
    'billing_cycle_days' => [
        'monthly' => 30,
        'quarterly' => 90,
        'semi' => 180,
        'yearly' => 365,
        'biennial' => 730,
        'triennial' => 1095,
    ],

    // 支持的支付网关
    'payment_gateways' => [
        'balance' => '余额支付',
        'alipay' => '支付宝',
        'wechat' => '微信支付',
    ],
    // 实名认证配置
    'verification' => [
        'api' => env('VERIFICATION_API', ''),
        'key' => env('VERIFICATION_KEY', ''),
        'biz_code' => env('VERIFICATION_BIZ_CODE', 'FACE'),
        'api_endpoint' => env('VERIFICATION_API_ENDPOINT', 'https://idc.stay33.cn/realname/certapi.php'),
        'ssl_verify' => env('VERIFICATION_SSL_VERIFY', env('APP_ENV') !== 'local'),
        'ca_bundle' => env('VERIFICATION_CA_BUNDLE', ''),
        'free_attempts' => env('VERIFICATION_FREE_ATTEMPTS', 3), // 单用户免费认证次数
        'retry_fee' => env('VERIFICATION_RETRY_FEE', 2.00), // 失败后再次认证费用（元）
    ],

    // 主机面板接口
    'hosting_panel_api' => [
        // 部分上游会基于 User-Agent 对既有面板流量做兼容放行，这里保留旧版请求头作为默认值。
        'user_agent' => 'mozilla/5.0 (compatible; msie 5.01; windows nt 5.0)',
        'ssl_verify' => env('HOSTING_PANEL_API_SSL_VERIFY', $mofangFinanceFallback('ssl_verify', env('APP_ENV') !== 'local')),
        'ca_bundle' => env('HOSTING_PANEL_API_CA_BUNDLE', $mofangFinanceFallback('ca_bundle', '')),
        'allowed_hosts' => env('HOSTING_PANEL_API_ALLOWED_HOSTS', $mofangFinanceFallback('allowed_hosts', '')),
        'jwt_cache_store' => env('HOSTING_PANEL_API_JWT_CACHE_STORE', $mofangFinanceFallback('jwt_cache_store', 'redis')),
        'dns_resolver_timeout' => (int) env('HOSTING_PANEL_API_DNS_TIMEOUT', $mofangFinanceFallback('dns_resolver_timeout', 3)),
        'connect_timeout' => (int) env('HOSTING_PANEL_API_CONNECT_TIMEOUT', $mofangFinanceFallback('connect_timeout', 15)), // 从10加到15秒，增加生产容错
        'timeout' => 900,
    ],

    // 短信配置
    'sms' => [
        'api_endpoint' => env('SMS_API_ENDPOINT', 'https://dypnsapi.aliyuncs.com/'),
        'ssl_verify' => env('SMS_SSL_VERIFY', env('APP_ENV') !== 'local'),
        'ca_bundle' => env('SMS_CA_BUNDLE', ''),
    ],

    // GeeTest 行为验证
    'geetest' => [
        'enabled' => env('GEETEST_ENABLED', false),
        'captcha_id' => env('GEETEST_CAPTCHA_ID', ''),
        'captcha_key' => env('GEETEST_CAPTCHA_KEY', ''),
        'ssl_verify' => env('GEETEST_SSL_VERIFY', env('APP_ENV') !== 'local'),
        'ca_bundle' => env('GEETEST_CA_BUNDLE', ''),
    ],

    'vnc_relay' => [
        'host' => env('VNC_RELAY_HOST', '127.0.0.1'),
        'port' => (int) env('VNC_RELAY_PORT', 8100),
        'path' => env('VNC_RELAY_PATH', '/ws/vnc'),
        'ssl_verify' => env('VNC_RELAY_SSL_VERIFY', env('APP_ENV') !== 'local'),
        'ca_bundle' => env('VNC_RELAY_CA_BUNDLE', ''),
        'connect_timeout' => (int) env('VNC_RELAY_CONNECT_TIMEOUT', 10),
    ],

    'frontend' => [
        'dist_path' => env('FRONTEND_DIST_PATH', base_path('../frontend-client/dist')),
    ],

];
