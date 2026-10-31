<?php

declare(strict_types=1);

namespace App\Support\Queue;

use App\Support\SchemaMetadataCache;
use Illuminate\Support\Facades\Log;

/**
 * 队列驱动能力判定的单一实现。
 *
 * 原先 PaidOrderBusinessFlowDispatcher、TicketService、ScheduleTaskTriggerService
 * 各有一份逐字相同的 shouldUseQueue()，连 databaseQueueIsReady() 都已经分叉成
 * 「Schema::connection()->hasTable()」与「SchemaMetadataCache::hasTable()」两种写法。
 * 判定错误会让 Job 压在根本不存在的队列表上静默失败，所以收成一处。
 */
final class QueueDriverCapability
{
    /**
     * 当前配置下能否真正把 Job 压进队列。
     *
     * false 表示调用方应回退到 dispatchAfterResponse/同步执行，而不是丢掉通知。
     *
     * @param  string  $logScope  日志定位前缀，例如「[支付后业务流]」
     */
    public static function shouldUseQueue(string $logScope): bool
    {
        $driver = (string) config('queue.default', 'sync');

        if ($driver === '' || $driver === 'sync') {
            return false;
        }

        if ($driver === 'database') {
            return self::databaseQueueIsReady($logScope);
        }

        if ($driver === 'redis') {
            return self::redisAvailable();
        }

        return true;
    }

    /**
     * Redis 队列只有 phpredis 扩展一条路径。
     *
     * 历史实现是「extension_loaded('redis') || class_exists(Predis\Client::class)」，
     * 但 composer 从未声明 predis/predis，vendor 里也没有该包，class_exists 分支
     * 恒为假。这里保留扩展检查，删掉死分支，避免读代码的人按 Predis 接口去排障。
     */
    public static function redisAvailable(): bool
    {
        return extension_loaded('redis');
    }

    /**
     * database 队列的就绪判定：队列表存在才允许 dispatch。
     *
     * 表结构元数据走 SchemaMetadataCache（进程内缓存），避免每次发 Job 都查
     * information_schema；连接名按 queue.connections.database.connection 解析，
     * 未配置时用默认连接。
     *
     * @param  string  $logScope  日志定位前缀
     */
    public static function databaseQueueIsReady(string $logScope): bool
    {
        $config = (array) config('queue.connections.database', []);
        $connection = trim((string) ($config['connection'] ?? ''));
        $table = (string) ($config['table'] ?? 'jobs');

        try {
            return $table !== '' && SchemaMetadataCache::hasTable($table, $connection !== '' ? $connection : null);
        } catch (\Throwable $exception) {
            Log::warning($logScope.' 检查队列表失败，回退为 afterResponse/同步执行', [
                'connection' => $connection,
                'table' => $table,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return false;
        }
    }
}
