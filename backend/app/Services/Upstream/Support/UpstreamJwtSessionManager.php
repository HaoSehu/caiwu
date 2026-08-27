<?php

declare(strict_types=1);

namespace App\Services\Upstream\Support;

use App\Exceptions\BusinessException;
use App\Models\Supplier;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 上游面板 JWT 会话缓存管理器骨架。
 *
 * 承载「登录换 JWT — 按剩余有效期写入缓存 — 缺会话告警 — 主动失效」的通用流程，
 * 系统侧共享传输层与各上游插件的认证管理器共用本骨架，
 * 仅通过下列受保护扩展点定制差异：
 * - 日志口径：sessionLogPrefix / sessionBaseLogContext / missingSessionLogContext；
 * - 缓存落点：jwtCacheKey / configuredJwtCacheStoreName；
 * - TTL 收口策略：clampResolvedJwtCacheTtl（剩余不足安全余量时兜底缓存还是不缓存）；
 * - 刷新扩展点：hasActiveRefreshGuard（刷新防抖）、forgetJwtBeforeRefresh、onJwtRefreshed。
 *
 * 各端日志字段顺序、异常类型与文案保持与拆分前一致，属于对外可见行为。
 */
abstract class UpstreamJwtSessionManager
{
    /** JWT 无法解析出 exp 时使用的默认缓存时长（秒）。 */
    protected const DEFAULT_JWT_CACHE_TTL_SECONDS = 1800;

    /** 缓存安全余量（秒）：剩余有效期扣除该值后再入库，避免缓存临期会话。 */
    protected const MIN_JWT_CACHE_TTL_SECONDS = 300;

    /** 单条 JWT 缓存的最长时长（秒）。 */
    protected const MAX_JWT_CACHE_TTL_SECONDS = 7200;

    /**
     * 登录换取 JWT 并按剩余有效期写入缓存；命中缓存时不再触发上游登录。
     */
    public function login(Supplier $supplier): string
    {
        $cacheKey = $this->jwtCacheKey($supplier);
        $cachedJwt = trim((string) $this->jwtCache()->get($cacheKey, ''));

        if ($cachedJwt !== '') {
            return $cachedJwt;
        }

        $startedAt = microtime(true);
        $response = $this->loginResponse($supplier);
        $jwt = trim((string) ($response['jwt'] ?? ''));

        if ($jwt === '') {
            $this->safeLog('warning', $this->sessionLogPrefix().'JWT响应缺少会话', $this->missingSessionLogContext($supplier, $response));

            throw new BusinessException('供应商接口认证失败，请检查接口配置', 42200);
        }

        $ttlSeconds = $this->resolveJwtCacheTtlSeconds($jwt);
        $this->storeJwtCacheEntry($cacheKey, $jwt, $ttlSeconds);

        $this->safeLog('info', $this->sessionLogPrefix().'JWT缓存写入', $this->sessionSummaryLogContext($supplier, [
            'ttl_seconds' => $ttlSeconds,
            'duration_ms' => $this->elapsedMilliseconds($startedAt),
        ]));

        return $jwt;
    }

    /**
     * 强制重新登录上游并覆写 JWT 缓存；子类可通过 hasActiveRefreshGuard
     * 为非强制路径提供刷新防抖，避免心跳周期产生登录风暴。
     */
    public function refreshJwt(Supplier $supplier, bool $force = true): string
    {
        if (! $force && $this->hasActiveRefreshGuard($supplier)) {
            return $this->login($supplier);
        }

        $this->forgetJwtBeforeRefresh($supplier);

        $startedAt = microtime(true);
        $response = $this->loginResponse($supplier);
        $jwt = trim((string) ($response['jwt'] ?? ''));

        if ($jwt === '') {
            $this->safeLog('warning', $this->sessionLogPrefix().'JWT刷新响应缺少会话', $this->missingSessionLogContext($supplier, $response));

            throw new BusinessException('供应商接口认证刷新失败，请稍后重试', 42200);
        }

        $ttlSeconds = $this->resolveJwtCacheTtlSeconds($jwt);
        $this->storeJwtCacheEntry($this->jwtCacheKey($supplier), $jwt, $ttlSeconds);
        $this->onJwtRefreshed($supplier, $ttlSeconds);

        $this->safeLog('info', $this->sessionLogPrefix().'JWT强制刷新', $this->sessionSummaryLogContext($supplier, [
            'ttl_seconds' => $ttlSeconds,
            'duration_ms' => $this->elapsedMilliseconds($startedAt),
        ]));

        return $jwt;
    }

    /**
     * 登录换取 JWT 的完整上游请求。
     *
     * @return array<string, mixed>
     */
    abstract protected function loginResponse(Supplier $supplier): array;

    /**
     * JWT 缓存键；供应商维度隔离，防止跨供应商复用会话。
     */
    abstract protected function jwtCacheKey(Supplier $supplier): string;

    /**
     * 会话日志统一前缀，由各实现按自身上游产品命名（含尾随空格）。
     */
    abstract protected function sessionLogPrefix(): string;

    /**
     * 配置声明的 JWT 缓存仓库名（未经兜底转换的原始值）。
     */
    abstract protected function configuredJwtCacheStoreName(): string;

    /**
     * 会话生命周期成功日志的固定基础字段（supplier_id/provider_key/cache_store 等）。
     *
     * @return array<string, mixed>
     */
    abstract protected function sessionBaseLogContext(Supplier $supplier): array;

    /**
     * 登录响应缺少会话时的告警上下文；各端对响应体的摘要口径不同。
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    abstract protected function missingSessionLogContext(Supplier $supplier, array $response): array;

    /**
     * TTL 收口策略：按安全余量折算后的剩余秒数如何入库。
     * 默认语义——剩余不足以覆盖安全余量时返回 0（本次不缓存），
     * 让下一个请求直接重新登录拿新 JWT，而不是把失效 token 缓存一段时间
     * 导致必然先 401 再重登。
     */
    protected function clampResolvedJwtCacheTtl(int $ttlSeconds): int
    {
        if ($ttlSeconds <= 0) {
            return 0;
        }

        return min($ttlSeconds, self::MAX_JWT_CACHE_TTL_SECONDS);
    }

    /**
     * 刷新防抖守卫：非强制刷新时若守卫仍然有效则回退普通登录取缓存，
     * 不触发上游登录。默认无守卫（每次都强制重登）。
     */
    protected function hasActiveRefreshGuard(Supplier $supplier): bool
    {
        return false;
    }

    /**
     * 强制刷新登录前清除旧会话；默认只清 JWT 缓存，
     * 子类可连带清理自有标记（如刷新防抖标记）。
     */
    protected function forgetJwtBeforeRefresh(Supplier $supplier): void
    {
        $this->jwtCache()->forget($this->jwtCacheKey($supplier));
    }

    /**
     * JWT 刷新成功写库后的扩展点（如写入刷新防抖标记）；默认无动作。
     */
    protected function onJwtRefreshed(Supplier $supplier, int $ttlSeconds): void {}

    /**
     * 按 JWT 剩余有效期折算缓存时长：
     * exp 缺失时使用默认时长，其余交给收口策略处理。
     */
    protected function resolveJwtCacheTtlSeconds(string $jwt): int
    {
        $payload = $this->decodeJwtPayload($jwt);
        $expiresAt = (int) ($payload['exp'] ?? 0);

        if ($expiresAt <= 0) {
            return self::DEFAULT_JWT_CACHE_TTL_SECONDS;
        }

        // 剩余有效期扣除 MIN_JWT_CACHE_TTL_SECONDS 安全余量后交由收口策略
        return $this->clampResolvedJwtCacheTtl($expiresAt - time() - self::MIN_JWT_CACHE_TTL_SECONDS);
    }

    /**
     * 解码 JWT 中段 payload；格式非法时返回空数组。
     *
     * @return array<string, mixed>
     */
    protected function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) < 2) {
            return [];
        }

        $decoded = json_decode($this->decodeBase64Url($parts[1]), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function decodeBase64Url(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }

    private function storeJwtCacheEntry(string $cacheKey, string $jwt, int $ttlSeconds): void
    {
        $this->jwtCache()->put($cacheKey, $jwt, now()->addSeconds($ttlSeconds));
    }

    /**
     * 会话日志的汇总上下文：固定基础字段 + 本次生命周期的时长信息。
     *
     * @param  array<string, int>  $lifecycle
     * @return array<string, mixed>
     */
    private function sessionSummaryLogContext(Supplier $supplier, array $lifecycle): array
    {
        return array_merge($this->sessionBaseLogContext($supplier), $lifecycle);
    }

    /**
     * 取配置声明的缓存仓库；仓库不可用时告警并回退 file 默认仓库，
     * 保证缓存放障时认证链路依旧可用。开放给宿主组合自有缓存辅助方法。
     */
    protected function jwtCache(): CacheRepository
    {
        $store = trim($this->configuredJwtCacheStoreName());

        try {
            return Cache::store($store !== '' ? $store : config('cache.default', 'redis'));
        } catch (\Throwable $exception) {
            $this->safeLog('warning', $this->sessionLogPrefix().'JWT缓存仓库不可用，回退默认缓存仓库', [
                'store' => $store,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return Cache::store(config('cache.default', 'file'));
        }
    }

    /**
     * 日志写入不得拖垮主流程，静默吞掉日志通道的任何异常。
     *
     * @param  array<string, mixed>  $context
     */
    protected function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::log($level, $message, $context);
        } catch (\Throwable) {
        }
    }

    /**
     * 计时口径统一暴露给宿主，保持各端请求耗时的计算规则一致。
     */
    protected function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
