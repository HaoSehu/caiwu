<?php

declare(strict_types=1);

namespace App\Services\ClientServiceConsole;

use App\Support\PublicUrl;

/**
 * VNC Relay 客户端 Origin 判定。
 *
 * 两类来源：
 * 1. 浏览器端：Origin 必须等于签发 token 时固化的 console 域（allowed_origin）；
 * 2. 移动端 App（原生 Flutter）：无法伪造 console 域，按 allowed_app_origins 白名单放行。
 *
 * 白名单随 token 载荷下发（allowed_app_origins），缺省时回落
 * `idc.vnc_relay.app_origins` 配置，保证旧 token（升级前签发）也能连上。
 */
class VncOriginPolicy
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function isAllowed(string $origin, array $params = []): bool
    {
        $actualOrigin = $this->normalizeOrigin($origin);
        if ($actualOrigin === '') {
            return false;
        }

        $allowedOrigin = $this->normalizeOrigin((string) ($params['allowed_origin'] ?? ''));
        if ($allowedOrigin === '') {
            $allowedOrigin = $this->normalizeOrigin(PublicUrl::console());
        }
        if ($allowedOrigin !== '' && strcasecmp($allowedOrigin, $actualOrigin) === 0) {
            return true;
        }

        foreach ($this->resolveAppOrigins($params) as $appOrigin) {
            $normalized = $this->normalizeOrigin($this->withDefaultScheme($appOrigin));
            if ($normalized !== '' && strcasecmp($normalized, $actualOrigin) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * App Origin 白名单：token 载荷显式给出的列表优先（含空列表），
     * 只有载荷完全没有该键时才回落到全局配置。
     *
     * 区分「显式空列表」与「缺键」：前者表示签发时明确不允许 App 访问，
     * 若也回落全局配置，将来就无法按用户/服务收紧 App 入口。
     *
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function resolveAppOrigins(array $params = []): array
    {
        $origins = array_key_exists('allowed_app_origins', $params)
            ? $params['allowed_app_origins']
            : config('idc.vnc_relay.app_origins', []);

        if (! is_array($origins)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $origin): string => trim((string) $origin), $origins),
            static fn (string $origin): bool => $origin !== ''
        ));
    }

    /**
     * App Origin 配置允许写裸值（如 `caiwu-app`），补默认 https scheme 后再归一，
     * 与浏览器 Origin（`https://console.example.com`）使用同一比较口径。
     */
    public function withDefaultScheme(string $origin): string
    {
        $origin = trim($origin);
        if ($origin === '' || preg_match('#^[a-z][a-z0-9+.-]*://#i', $origin) === 1) {
            return $origin;
        }

        return 'https://'.$origin;
    }

    /**
     * 归一为 `scheme://host[:port]`（小写、去默认端口）；非法输入返回空串。
     */
    public function normalizeOrigin(string $origin): string
    {
        $origin = trim($origin);
        if ($origin === '') {
            return '';
        }

        $parts = parse_url($origin);
        if (! is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme === '' || $host === '') {
            return '';
        }

        $port = (int) ($parts['port'] ?? 0);
        $defaultPort = $scheme === 'https' ? 443 : 80;

        if ($port > 0 && $port !== $defaultPort) {
            return sprintf('%s://%s:%d', $scheme, $host, $port);
        }

        return sprintf('%s://%s', $scheme, $host);
    }
}
