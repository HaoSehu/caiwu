<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

class RequestContext
{
    /**
     * 客户端操作的审计上下文（actor + 来源 + 链路），供服务层写操作日志使用。
     *
     * @return array<string, mixed>
     */
    public static function forClient(Request $request, string $actorType = 'client'): array
    {
        $user = $request->user();

        return [
            'actor_type' => $actorType,
            'actor_user_id' => (int) ($user?->id ?? 0),
            'actor_name' => (string) ($user?->display_name ?? $user?->nickname ?? $user?->email ?? ''),
            'ip_address' => (string) $request->ip(),
            'trace_id' => (string) $request->header('X-Request-Id', ''),
            'request_origin' => self::resolveRequestOrigin($request),
        ];
    }

    private static function resolveRequestOrigin(Request $request): string
    {
        $origin = trim((string) $request->headers->get('Origin', ''));
        if ($origin !== '') {
            return rtrim($origin, '/');
        }

        $referer = trim((string) $request->headers->get('Referer', ''));
        if ($referer !== '') {
            $parts = parse_url($referer);
            if (is_array($parts)) {
                $scheme = strtolower((string) ($parts['scheme'] ?? ''));
                $host = (string) ($parts['host'] ?? '');
                $port = (int) ($parts['port'] ?? 0);

                if ($scheme !== '' && $host !== '') {
                    $defaultPort = $scheme === 'https' ? 443 : 80;

                    if ($port > 0 && $port !== $defaultPort) {
                        return sprintf('%s://%s:%d', $scheme, $host, $port);
                    }

                    return sprintf('%s://%s', $scheme, $host);
                }
            }
        }

        return rtrim($request->getSchemeAndHttpHost(), '/');
    }
}
