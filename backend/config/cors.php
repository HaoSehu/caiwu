<?php

$normalizeOrigin = static function (?string $url): ?string {
    $parts = parse_url(trim((string) $url));
    if (! is_array($parts)) {
        return null;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
        return null;
    }

    $port = (int) ($parts['port'] ?? 0);
    $suffix = $port > 0 && ! (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))
        ? ':'.$port
        : '';

    return $scheme.'://'.$host.$suffix;
};

$baseOrigins = array_values(array_filter([
    $normalizeOrigin(env('FRONTEND_URL')),
    $normalizeOrigin(env('CLIENT_CONSOLE_URL')),
    $normalizeOrigin(env('ADMIN_URL')),
]));

// CDN 已强制 HTTPS：不再为 https 来源补 http 对偶，明文页面发起的跨域请求直接拒绝。
$allowedOrigins = $baseOrigins;

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $allowedOrigins,
    // www 端存在裸域与 www 共站的事实（CDN 已强制 HTTPS，仅放行 https 来源）。
    'allowed_origins_patterns' => ['#^https://(www\.)?coyjs\.cn$#'],
    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-Request-Id',
        'X-Idempotency-Key',
    ],
    'exposed_headers' => ['Content-Disposition', 'Retry-After', 'X-Request-Id'],
    // 预检结果允许浏览器缓存 24h：origins 为精确 allowlist 且无凭证变化，可安全缓存，
    // 避免每个跨域写请求都付一次完整 OPTIONS 预检往返（此前 max_age=0 导致写路径延迟近似翻倍）。
    'max_age' => 86400,
    'supports_credentials' => false,
];
