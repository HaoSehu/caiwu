<?php

declare(strict_types=1);

namespace App\Services\ClientServiceConsole;

use App\Exceptions\BusinessException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class ServiceActionLock
{
    /**
     * 以「用户 + 实例 + 动作」维度加缓存锁执行变更，防止并发重复提交。
     */
    public function execute(int $userId, int $serviceId, string $action, callable $callback): mixed
    {
        $lockKey = sprintf('lock:client:service:%d:%d:%s', $userId, $serviceId, sha1($action));

        try {
            return Cache::lock($lockKey, 20)->block(3, $callback);
        } catch (LockTimeoutException) {
            throw new BusinessException('操作处理中，请勿重复提交', 40900, 409);
        }
    }
}
