<?php

$normalizeFrontendUrl = static function (?string $url, ?string $schemeSourceUrl): string {
    $normalized = trim((string) $url);
    if ($normalized === '') {
        return '';
    }

    if (preg_match('/^[a-z][a-z\d+\-.]*:\/\//i', $normalized)) {
        return rtrim($normalized, '/');
    }

    $fallback = trim((string) $schemeSourceUrl);
    $scheme = 'http';
    if (preg_match('/^([a-z][a-z\d+\-.]*):\/\//i', $fallback, $matches)) {
        $scheme = strtolower((string) ($matches[1] ?? 'http'));
    }

    return rtrim($scheme.'://'.ltrim($normalized, '/'), '/');
};

return [
    'name' => env('APP_NAME', 'Laravel'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'frontend_url' => $normalizeFrontendUrl(env('FRONTEND_URL', ''), env('APP_URL', 'http://localhost')),
    'admin_url' => $normalizeFrontendUrl(env('ADMIN_URL', ''), env('APP_URL', 'http://localhost')),
    'timezone' => env('APP_TIMEZONE', 'Asia/Shanghai'),
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
];
