<?php

declare(strict_types=1);

namespace App\Services\ClientServiceConsole;

use App\Exceptions\BusinessException;
use App\Support\CacheKey;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * 实例级变更的并发锁（客户端与管理端共用）。
 *
 * 锁键按「目标用户 + 实例 + 动作」构造，不含端标识：管理员代客操作时传入的
 * 是被操作用户的 id，与用户本人操作落在同一把锁上，两端才会真正串行。
 * 此前管理端各自复制了一份、且把前缀改成 admin，结果两端各锁各的，
 * 「管理员与用户同时重启同一实例」这种并发照旧发生。
 */
class ServiceActionLock
{
    /** 锁持有时长（秒）：覆盖一次上游变更调用的正常耗时 */
    private const LOCK_TTL_SECONDS = 20;

    /** 等待锁的时长（秒）：超过即判定为重复提交 */
    private const LOCK_WAIT_SECONDS = 3;

    /**
     * 以「用户 + 实例 + 动作」维度加缓存锁执行变更，防止并发重复提交。
     *
     * @param  int  $userId  实例归属用户；管理员代客操作时传目标用户 id，不传管理员 id
     */
    public function execute(int $userId, int $serviceId, string $action, callable $callback): mixed
    {
        $lockKey = CacheKey::lock('lock:client:service', sprintf('%d:%d:%s', $userId, $serviceId, sha1($action)));

        try {
            return Cache::lock($lockKey, self::LOCK_TTL_SECONDS)->block(self::LOCK_WAIT_SECONDS, $callback);
        } catch (LockTimeoutException) {
            throw new BusinessException('操作处理中，请勿重复提交', 40900, 409);
        }
    }
}
