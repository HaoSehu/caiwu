<?php

$deriveConsoleUrl = static function (?string $frontendUrl): ?string {
    $frontendUrl = trim((string) $frontendUrl);
    if ($frontendUrl === '') {
        return null;
    }

    $parts = parse_url($frontendUrl);
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (! str_starts_with($host, 'www.')) {
        return null;
    }

    $scheme = (string) ($parts['scheme'] ?? 'https');
    $port = isset($parts['port']) ? ':'.(string) $parts['port'] : '';

    return $scheme.'://console.'.substr($host, 4).$port;
};

$defaultAllowedOrigins = array_values(array_filter([
    env('FRONTEND_URL'),
    env('CLIENT_CONSOLE_URL') ?: $deriveConsoleUrl(env('FRONTEND_URL')),
    env('ADMIN_URL'),
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:5174',
    'http://127.0.0.1:5174',
    'http://localhost:5175',
    'http://127.0.0.1:5175',
]));

$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', env('CORS_ALLOWED_ORIGINS', implode(',', $defaultAllowedOrigins)))
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_unique($allowedOrigins)),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
