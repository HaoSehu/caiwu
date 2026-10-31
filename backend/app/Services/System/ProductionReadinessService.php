<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\ScheduleTaskRun;
use App\Models\ScheduleTick;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ProductionReadinessService
{
    /** @return array{ready: bool, checks: array<string, bool>, reasons: array<string, string>} */
    public function check(): array
    {
        $schedulerLiveness = $this->schedulerIsReady();
        $taskReadiness = $this->taskReadiness();
        $queueReadiness = $this->queueIsReady();
        // Redis 只读探针：未使用 Redis 时视为不适用直接通过，失败原因随 reasons 输出。
        $redisProbe = $this->redisProbe();
        $checks = [
            'database' => $this->databaseIsReady(),
            'cache' => $this->cacheIsReady(),
            'storage' => $this->storageIsReady(),
            // 保留 scheduler 字段表示心跳存活，新增细分项避免“心跳新鲜但任务已失控”。
            'scheduler' => $schedulerLiveness,
            'scheduler_liveness' => $schedulerLiveness,
            'task_readiness' => $taskReadiness,
            'queue' => $queueReadiness,
            'redis' => $redisProbe['ready'],
        ];

        return [
            'ready' => ! in_array(false, $checks, true),
            'checks' => $checks,
            'reasons' => $redisProbe['reason'] === null ? [] : ['redis' => $redisProbe['reason']],
        ];
    }

    private function databaseIsReady(): bool
    {
        try {
            DB::connection()->selectOne('SELECT 1 AS ready');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function cacheIsReady(): bool
    {
        $key = 'health:readiness:'.Str::uuid()->toString();

        try {
            Cache::put($key, 'ready', 10);

            return Cache::get($key) === 'ready';
        } catch (Throwable) {
            return false;
        } finally {
            try {
                Cache::forget($key);
            } catch (Throwable) {
                // 读取或写入已经失败时，清理失败不改变就绪结论。
            }
        }
    }

    private function storageIsReady(): bool
    {
        foreach ([storage_path(), base_path('bootstrap/cache')] as $directory) {
            if (! is_dir($directory) || ! is_writable($directory)) {
                return false;
            }
        }

        return true;
    }

    private function schedulerIsReady(): bool
    {
        try {
            $latestHeartbeat = ScheduleTick::query()->max('triggered_at');
            if (! is_string($latestHeartbeat) || trim($latestHeartbeat) === '') {
                return false;
            }

            $maximumAge = max(60, (int) config('health.scheduler_max_age_seconds', 180));

            return CarbonImmutable::parse($latestHeartbeat)
                ->greaterThanOrEqualTo(CarbonImmutable::now()->subSeconds($maximumAge));
        } catch (Throwable) {
            return false;
        }
    }

    private function taskReadiness(): bool
    {
        try {
            if (! Schema::hasTable('schedule_task_runs')) {
                return false;
            }

            $failureWindow = max(60, (int) config('health.scheduler_task_failure_window_seconds', 900));
            $recentFailure = ScheduleTaskRun::query()
                ->whereIn('status', [
                    ScheduleTaskRun::STATUS_FAILED,
                    ScheduleTaskRun::STATUS_DISPATCH_FAILED,
                ])
                ->where('updated_at', '>=', now()->subSeconds($failureWindow))
                ->exists();
            if ($recentFailure) {
                return false;
            }

            $leaseSeconds = max(
                60,
                (int) config('queue.caiwu_worker_max_timeout', 3600) + 60,
                (int) config('queue.connections.database.retry_after', 3900) + 60,
            );

            return ! ScheduleTaskRun::query()
                ->whereIn('status', [
                    ScheduleTaskRun::STATUS_QUEUED,
                    ScheduleTaskRun::STATUS_RUNNING,
                    ScheduleTaskRun::STATUS_RETRYING,
                ])
                ->where(function ($query) use ($leaseSeconds): void {
                    $cutoff = now()->subSeconds($leaseSeconds);
                    $query
                        ->whereRaw('COALESCE(updated_at, queued_at, created_at) <= ?', [$cutoff])
                        ->orWhere(function ($nested): void {
                            $nested
                                ->whereNull('updated_at')
                                ->whereNull('queued_at')
                                ->whereNull('created_at');
                        });
                })
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function queueIsReady(): bool
    {
        $driver = (string) config('queue.default', 'sync');
        if ($driver === '' || $driver === 'sync') {
            return true;
        }

        if ($driver !== 'database') {
            // Redis/其他驱动的连通性由其连接层和 Worker 负责；这里不伪造“积压为零”。
            return true;
        }

        $config = (array) config('queue.connections.database', []);
        $connection = trim((string) ($config['connection'] ?? '')) ?: (string) config('database.default');
        $table = trim((string) ($config['table'] ?? 'jobs'));

        try {
            if ($table === '' || ! Schema::connection($connection)->hasTable($table)) {
                return false;
            }

            $maxPending = max(1, (int) config('health.queue_max_pending_jobs', 10000));

            return (int) DB::connection($connection)->table($table)->count() <= $maxPending;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Redis 连接只读探针：仅在缓存、会话或队列实际使用 redis 驱动时才执行。
     *
     * 只发送 PING —— 不写入任何键、不 flush、不读取积压指标，避免探针自身产生副作用或污染统计。
     * 所有异常在此处收敛为失败原因返回，绝不向外抛出，以免连带其它健康项一起报错。
     *
     * 缓存、队列、会话可能落在不同连接上（例如缓存走 cache 连接、队列走 default 连接），
     * 因此逐个连接探测并把原因拼在一起，只探第一个命中的连接会漏掉另一层的故障。
     *
     * @return array{applicable: bool, ready: bool, reason: string|null}
     */
    private function redisProbe(): array
    {
        $targets = $this->redisProbeTargets();
        if ($targets === []) {
            // 未使用 redis 的环境（例如测试固定为 array 缓存）视为不适用，直接通过，不出现在失败原因里。
            return ['applicable' => false, 'ready' => true, 'reason' => null];
        }

        $reasons = [];
        foreach ($targets as [$purpose, $connection]) {
            try {
                // PING 是 Redis 唯一的无状态只读命令，不会创建或修改任何键。
                $pong = Redis::connection($connection)->ping();

                // phpredis 返回 true，predis 返回状态对象（字符串化为 PONG），两种客户端都算通过。
                $healthy = $pong === true || strtoupper(trim((string) $pong)) === 'PONG';

                if (! $healthy) {
                    $reasons[] = $purpose.'（'.$connection.'）连接已建立但 PING 返回异常';
                }
            } catch (Throwable $exception) {
                $reasons[] = $purpose.'（'.$connection.'）连接不可用：'.$exception->getMessage();
            }
        }

        return [
            'applicable' => true,
            'ready' => $reasons === [],
            'reason' => $reasons === [] ? null : implode('；', $reasons),
        ];
    }

    /**
     * 解析需要探测的 Redis 连接：返回去重后的 [用途说明, 连接名] 列表，未使用 redis 时返回空数组。
     *
     * @return list<array{0: string, 1: string}>
     */
    private function redisProbeTargets(): array
    {
        $targets = [];
        $seen = [];

        $add = static function (string $purpose, string $connection) use (&$targets, &$seen): void {
            $connection = $connection !== '' ? $connection : 'default';
            // 同一连接被多层共用时只探一次，避免重复 PING 与重复原因文案。
            if (isset($seen[$connection])) {
                return;
            }
            $seen[$connection] = true;
            $targets[] = [$purpose, $connection];
        };

        // 缓存：缓存驱动为 redis 时探测该 store 绑定的连接。
        $cacheStore = (string) config('cache.default', '');
        if ($cacheStore !== '' && (string) config("cache.stores.{$cacheStore}.driver", '') === 'redis') {
            $add('缓存', trim((string) config("cache.stores.{$cacheStore}.connection", '')));
        }

        // 队列：队列驱动为 redis 时探测该队列连接对应的 Redis 连接。
        $queueDriver = (string) config('queue.default', '');
        if ($queueDriver !== '' && (string) config("queue.connections.{$queueDriver}.driver", '') === 'redis') {
            $add('队列', trim((string) config("queue.connections.{$queueDriver}.connection", '')));
        }

        // 会话：会话落在 redis 上同样依赖该连接。
        if ((string) config('session.driver', '') === 'redis') {
            $add('会话', trim((string) config('session.connection', '')));
        }

        return $targets;
    }
}
