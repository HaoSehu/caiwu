<?php

return [
    'stateful' => [],
    'guard' => [],
    'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 1440),  // 默认 24 小时（分钟），可通过 .env 调整；配合 idle_timeout 双重保护
    'idle_timeout' => (int) env('SANCTUM_IDLE_TIMEOUT', 10800),
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
];
