<?php

declare(strict_types=1);

return [
    'finance_api' => [
        'ssl_verify' => env('MOFANG_FINANCE_SSL_VERIFY', env('APP_ENV') !== 'local'),
        'ca_bundle' => env('MOFANG_FINANCE_CA_BUNDLE', ''),
        'allowed_hosts' => env('MOFANG_FINANCE_ALLOWED_HOSTS', ''),
        'jwt_cache_store' => env('MOFANG_FINANCE_JWT_CACHE_STORE', 'redis'),
        'dns_resolver_timeout' => (int) env('MOFANG_FINANCE_DNS_TIMEOUT', 3),
        'connect_timeout' => (int) env('MOFANG_FINANCE_CONNECT_TIMEOUT', 15),
    ],
];
