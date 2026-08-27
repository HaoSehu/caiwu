<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\ZjmfFinance\Lib;

use App\Models\Supplier;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use App\Services\Upstream\ProviderKey;
use App\Services\Upstream\Support\UpstreamJwtSessionManager;

/**
 * ZJMF 财务接口的 JWT 会话管理器。
 *
 * 登录换票、按剩余有效期写缓存、base64url 解码与缓存仓库回退统一由系统侧
 * UpstreamJwtSessionManager 骨架承载（插件复用系统能力），本类只保留
 * zjmf_finance 特有差异：
 * - 登录端点 /zjmf_api_login 与固定的 provider_key 口径；
 * - 15 分钟心跳的刷新防抖标记（hasActiveRefreshGuard / onJwtRefreshed 钩子）；
 * - 401 自愈时连带清除防抖标记的 forget。
 */
final class ZjmfAuthManager extends UpstreamJwtSessionManager
{
    /** 刷新防抖标记的默认 TTL：JWT 缓存有效期内不再强制重登。 */
    private const DEFAULT_REFRESH_MARKER_TTL_SECONDS = 900;

    public function __construct(
        private readonly HostingPanelApiTransport $transport,
    ) {}

    /**
     * 对外签名保持拆分前不变：默认走防抖的非强制刷新，
     * force=true 时跳过标记直接重新登录上游。
     */
    public function refreshJwt(Supplier $supplier, bool $force = false): string
    {
        return parent::refreshJwt($supplier, $force);
    }

    /**
     * 刷新防抖：标记有效期内且 JWT 缓存未过期时回退普通登录取缓存，
     * 避免 15 分钟心跳对全部供应商产生登录风暴。
     */
    protected function hasActiveRefreshGuard(Supplier $supplier): bool
    {
        return $this->recentlyRefreshed($supplier);
    }

    /**
     * 强制刷新前同时清 JWT 缓存与防抖标记，保证刷新请求必然打到上游。
     */
    protected function forgetJwtBeforeRefresh(Supplier $supplier): void
    {
        $this->forget($supplier);
    }

    /**
     * 刷新成功才写防抖标记；失败不写，下一轮心跳可重试。
     */
    protected function onJwtRefreshed(Supplier $supplier, int $ttlSeconds): void
    {
        $this->markRefreshed($supplier, $ttlSeconds);
    }

    /**
     * 使用供应商 API 账号登录 ZJMF 财务接口，返回完整响应体。
     *
     * @return array<string, mixed>
     */
    public function loginResponse(Supplier $supplier): array
    {
        return $this->transport->request($supplier, 'POST', '/zjmf_api_login', [
            'username' => (string) $supplier->api_username,
            'password' => (string) $supplier->api_key,
        ]);
    }

    /**
     * 主动失效：清 JWT 缓存并同步清除防抖标记，保证自愈路径可立即重登。
     */
    public function forget(Supplier $supplier): void
    {
        $cache = $this->jwtCache();
        $cache->forget($this->jwtCacheKey($supplier));
        // 401 主动失效时同步清除防抖标记，保证自愈路径可立即重登。
        $cache->forget($this->refreshMarkerKey($supplier));
    }

    public function forgetIfUnauthorizedResponse(Supplier $supplier, int $httpCode, mixed $decoded, ?string $jwt): void
    {
        if ($jwt === null || trim($jwt) === '') {
            return;
        }

        if ($httpCode === 401 || (is_array($decoded) && (int) ($decoded['status'] ?? $decoded['code'] ?? $decoded['status_code'] ?? 0) === 401)) {
            $this->forget($supplier);
        }
    }

    public function jwtCacheKey(Supplier $supplier): string
    {
        return 'upstream:'.ProviderKey::ZJMF_FINANCE_API.':jwt:'.$supplier->id;
    }

    /**
     * 会话日志统一前缀。
     */
    protected function sessionLogPrefix(): string
    {
        return '[ZJMF 财务接口] ';
    }

    /**
     * JWT 缓存仓库名来自系统宿主面板配置（插件与其共享同一配置键）。
     */
    protected function configuredJwtCacheStoreName(): string
    {
        $hostingConfig = config('idc.hosting_panel_api', []);

        return trim((string) (
            (is_array($hostingConfig) ? ($hostingConfig['jwt_cache_store'] ?? null) : null)
            ?? 'redis'
        )) ?: 'redis';
    }

    /**
     * 会话成功日志的固定基础字段。
     *
     * @return array<string, mixed>
     */
    protected function sessionBaseLogContext(Supplier $supplier): array
    {
        return [
            'supplier_id' => $supplier->id,
            'provider_key' => ProviderKey::ZJMF_FINANCE_API,
            'cache_store' => $this->configuredJwtCacheStoreName(),
        ];
    }

    /**
     * 登录响应缺少会话时的告警上下文：输出上游状态码摘要而非整体响应体。
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function missingSessionLogContext(Supplier $supplier, array $response): array
    {
        return [
            'supplier_id' => $supplier->id,
            'provider_key' => ProviderKey::ZJMF_FINANCE_API,
            'response_status' => $response['status'] ?? $response['code'] ?? null,
        ];
    }

    private function refreshMarkerKey(Supplier $supplier): string
    {
        return 'upstream:'.ProviderKey::ZJMF_FINANCE_API.':refresh:'.$supplier->id;
    }

    private function recentlyRefreshed(Supplier $supplier): bool
    {
        return trim((string) $this->jwtCache()->get($this->refreshMarkerKey($supplier), '')) !== '';
    }

    private function markRefreshed(Supplier $supplier, int $jwtTtlSeconds): void
    {
        $markerTtl = max(1, min(
            $jwtTtlSeconds > 0 ? $jwtTtlSeconds : self::DEFAULT_JWT_CACHE_TTL_SECONDS,
            self::DEFAULT_REFRESH_MARKER_TTL_SECONDS
        ));

        $this->jwtCache()->put($this->refreshMarkerKey($supplier), (string) time(), now()->addSeconds($markerTtl));
    }
}
