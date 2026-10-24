<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Exceptions\BusinessException;
use App\Jobs\ExportDatabaseBackupJob;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * 管理端数据库备份任务编排与状态机。
 *
 * 备份走 mysqldump 子进程，不能在 FPM 请求里同步跑（宝塔默认 request_terminate_timeout
 * 会在导出途中切断请求，留下不完整产物）。因此请求只负责建任务、投递队列任务，
 * 由 queue:work 在 CLI 执行，进度状态存缓存供前端轮询。
 */
class DatabaseBackupTaskService
{
    /**
     * 任务状态有效期。导出完成后的结果（文件名、大小）只需覆盖用户下载窗口，
     * 备份文件本身由 DatabaseStatusService 的保留策略（最近 10 个）管理。
     */
    private const TASK_TTL_SECONDS = 2 * 60 * 60;

    private const STATUS_QUEUED = 'queued';

    private const STATUS_RUNNING = 'running';

    private const STATUS_COMPLETED = 'completed';

    private const STATUS_FAILED = 'failed';

    /**
     * 在途标记有效期。必须覆盖「排队 + Job timeout(900s)」的最长链路，
     * 否则任务还在跑标记就过期，去重会失效。
     *
     * 排队一侧并不确定：queue:drain 每分钟拉起一轮 worker，前一轮可能正卡在
     * --max-time=50 的收尾上，最坏要等近一分钟才轮到 backup 组；再叠加 900 秒
     * 导出，1800 秒只剩十分钟余量。取 3600 秒留足一倍冗余，仍小于任务状态的
     * 7200 秒；代价是进程被强杀且 failed() 未触发时，标记最迟 60 分钟才自动解封。
     */
    private const IN_FLIGHT_TTL_SECONDS = 3600;

    /**
     * 建任务并投递队列任务，返回任务 ID。
     *
     * 同一时间只允许一个备份任务在途：备份是低频人工操作，并发只会产出多份
     * dump 并互相挤占保留配额，且都串在同一个 worker 上排队。
     *
     * @return array{id: string, status: string, task_id: string, message: string}
     */
    public function dispatch(?int $actorId, ?string $ipAddress): array
    {
        $taskId = Str::uuid()->toString();

        if (! Cache::add(CacheKey::databaseBackupInFlight(), $taskId, self::IN_FLIGHT_TTL_SECONDS)) {
            throw new BusinessException('已有备份任务正在执行，请勿重复提交', 40900, 409);
        }

        try {
            $this->write($taskId, [
                'status' => self::STATUS_QUEUED,
                'message' => '备份任务已提交，正在排队执行',
                'filename' => null,
                'size_bytes' => null,
                'created_at' => now()->toDateTimeString(),
                'finished_at' => null,
            ]);

            ExportDatabaseBackupJob::dispatch($taskId, $actorId, $ipAddress);
        } catch (Throwable $exception) {
            // 投递失败同样要把记录收敛为 failed：否则任务会挂着「排队中」直到
            // TASK_TTL_SECONDS 到期，用户重提被在途标记挡住时看不到任何失败原因。
            // markFailed 内部已收回在途标记。
            $this->markFailed($taskId, '备份任务投递失败：'.$exception->getMessage());

            throw $exception;
        }

        return [
            'id' => $taskId,
            'status' => self::STATUS_QUEUED,
            'task_id' => $taskId,
            'message' => '备份任务已提交，完成后可下载备份文件',
        ];
    }

    /**
     * @return array{status: string, message: string, filename: ?string, size_bytes: ?int, created_at: ?string, finished_at: ?string}|null
     */
    public function find(string $taskId): ?array
    {
        $task = Cache::get(CacheKey::databaseBackupTask($taskId));

        if (! is_array($task)) {
            return null;
        }

        return [
            'status' => (string) ($task['status'] ?? self::STATUS_QUEUED),
            'message' => (string) ($task['message'] ?? ''),
            'filename' => isset($task['filename']) ? (string) $task['filename'] : null,
            'size_bytes' => isset($task['size_bytes']) ? (int) $task['size_bytes'] : null,
            'created_at' => isset($task['created_at']) ? (string) $task['created_at'] : null,
            'finished_at' => isset($task['finished_at']) ? (string) $task['finished_at'] : null,
        ];
    }

    public function markRunning(string $taskId): void
    {
        $this->merge($taskId, [
            'status' => self::STATUS_RUNNING,
            'message' => '正在导出数据库备份',
        ]);
    }

    public function markCompleted(string $taskId, string $filename, int $sizeBytes): void
    {
        $this->merge($taskId, [
            'status' => self::STATUS_COMPLETED,
            'message' => '备份已完成，可以下载',
            'filename' => $filename,
            'size_bytes' => $sizeBytes,
            'finished_at' => now()->toDateTimeString(),
        ]);

        $this->releaseInFlight($taskId);
    }

    public function markFailed(string $taskId, string $message): void
    {
        $this->merge($taskId, [
            'status' => self::STATUS_FAILED,
            'message' => $message !== '' ? $message : '备份失败',
            'finished_at' => now()->toDateTimeString(),
        ]);

        $this->releaseInFlight($taskId);
    }

    /**
     * 按任务 ID 比对后再清在途标记：避免旧任务收尾时把新任务的标记误删。
     */
    private function releaseInFlight(string $taskId): void
    {
        $key = CacheKey::databaseBackupInFlight();

        if (Cache::get($key) === $taskId) {
            Cache::forget($key);
        }
    }

    /**
     * 任务已完成才返回备份文件名；返回前校验文件名形态，
     * 避免任何外部输入拼进子进程或下载路径。
     */
    public function completedFilename(string $taskId): ?string
    {
        $task = $this->find($taskId);

        if ($task === null || $task['status'] !== self::STATUS_COMPLETED) {
            return null;
        }

        $filename = (string) ($task['filename'] ?? '');

        return DatabaseStatusService::isBackupFilename($filename) ? $filename : null;
    }

    /**
     * @param  array<string, mixed>  $task
     */
    private function write(string $taskId, array $task): void
    {
        Cache::put(CacheKey::databaseBackupTask($taskId), $task, self::TASK_TTL_SECONDS);
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private function merge(string $taskId, array $patch): void
    {
        $task = Cache::get(CacheKey::databaseBackupTask($taskId));
        $task = is_array($task) ? $task : [];

        $this->write($taskId, array_merge($task, $patch));
    }
}
