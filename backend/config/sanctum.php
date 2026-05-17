<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

$formatStatefulDomain = static function (?string $url): ?string {
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }

    if (! str_contains($url, '://')) {
        return $url;
    }

    $host = parse_url($url, PHP_URL_HOST);
    $port = parse_url($url, PHP_URL_PORT);

    if (! is_string($host) || trim($host) === '') {
        return null;
    }

    return $port ? "{$host}:{$port}" : $host;
};

$defaultStatefulDomains = array_values(array_filter([
    'localhost',
    'localhost:3000',
    'localhost:5173',
    'localhost:5174',
    '127.0.0.1',
    '127.0.0.1:8000',
    '127.0.0.1:5173',
    '127.0.0.1:5174',
    '::1',
    $formatStatefulDomain(env('APP_URL')),
    $formatStatefulDomain(env('FRONTEND_URL')),
    $formatStatefulDomain(env('ADMIN_URL')),
]));

return [
    'stateful' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('SANCTUM_STATEFUL_DOMAINS', implode(',', array_values(array_unique($defaultStatefulDomains)))))
    ))),
    'guard' => ['web'],
    'expiration' => null,
    'idle_timeout' => (int) env('SANCTUM_IDLE_TIMEOUT', 10800),
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
