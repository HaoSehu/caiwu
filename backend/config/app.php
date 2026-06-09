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

$deriveConsoleUrl = static function (?string $consoleUrl, ?string $frontendUrl, ?string $schemeSourceUrl) use ($normalizeFrontendUrl): string {
    $configured = $normalizeFrontendUrl($consoleUrl, $schemeSourceUrl);
    if ($configured !== '') {
        return $configured;
    }

    $frontend = $normalizeFrontendUrl($frontendUrl, $schemeSourceUrl);
    if ($frontend === '') {
        return '';
    }

    $parts = parse_url($frontend);
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (! str_starts_with($host, 'www.')) {
        return $frontend;
    }

    $scheme = (string) ($parts['scheme'] ?? 'https');
    $port = isset($parts['port']) ? ':'.(string) $parts['port'] : '';
    $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';

    return rtrim($scheme.'://console.'.substr($host, 4).$port.$path, '/');
};

return [
    'name' => env('APP_NAME', 'Laravel'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'frontend_url' => $normalizeFrontendUrl(env('FRONTEND_URL', ''), env('APP_URL', 'http://localhost')),
    'client_console_url' => $deriveConsoleUrl(env('CLIENT_CONSOLE_URL', ''), env('FRONTEND_URL', ''), env('APP_URL', 'http://localhost')),
    'admin_url' => $normalizeFrontendUrl(env('ADMIN_URL', ''), env('APP_URL', 'http://localhost')),
    'timezone' => env('APP_TIMEZONE', 'Asia/Shanghai'),
    'locale' => env('APP_LOCALE', 'zh_CN'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'zh_CN'),
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
