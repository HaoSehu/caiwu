<?php

declare(strict_types=1);

namespace App\Support\Upgrade;

use RuntimeException;

/**
 * 升级互斥锁：同一部署节点同一时间只允许一个 db:upgrade 执行。
 *
 * 使用 flock 而非 Cache::lock —— caiwu 的缓存驱动随部署变化（redis / file / array），
 * 而升级必须在大盘无关依赖都不可用的最坏情况下仍能互斥，因此直接落在本地文件系统。
 * 非阻塞加锁：拿不到锁说明已有升级在跑，直接失败而不是排队（升级不应被静默延后）。
 */
final class UpgradeLock
{
    private const DEFAULT_FILENAME = 'upgrade.lock';

    public function __construct(private readonly ?string $lockPath = null) {}

    /**
     * 在独占锁内执行回调。
     *
     * @template T
     *
     * @param  callable(): T  $callback  需要串行执行的操作
     * @return T 回调返回值
     *
     * @throws RuntimeException 锁文件无法创建或已被占用时抛出
     */
    public function synchronized(callable $callback): mixed
    {
        $path = $this->lockPath ?? storage_path('framework/'.self::DEFAULT_FILENAME);
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('无法创建升级锁目录：'.$directory);
        }

        $handle = fopen($path, 'c');
        if ($handle === false) {
            throw new RuntimeException('无法创建升级锁文件：'.$path);
        }

        try {
            if (! flock($handle, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('另一个数据库升级正在执行，请等待其结束后重试。');
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * 当前使用的锁文件绝对路径。
     */
    public function path(): string
    {
        return $this->lockPath ?? storage_path('framework/'.self::DEFAULT_FILENAME);
    }
}
