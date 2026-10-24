<?php

namespace App\Jobs;

use App\Services\System\DatabaseBackupTaskService;
use App\Services\System\DatabaseStatusService;
use App\Support\BackupQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 管理端「导出数据库备份」的队列执行体。
 *
 * 备份靠 mysqldump 子进程跑，整库导出耗时远超 FPM 请求预算，
 * 放在请求里会被 request_terminate_timeout 截断并留下残缺 SQL。
 * 由 queue:work 在 CLI 执行，状态写回 DatabaseBackupTaskService 供前端轮询。
 */
class ExportDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * 备份不可重试：重试会生成第二份产物并覆盖任务状态，
     * 失败后由管理员重新发起即可。
     */
    public int $tries = 1;

    /**
     * 必须大于 DatabaseStatusService::createBackup() 内部 mysqldump 的 600 秒超时，
     * 留出进程收尾与写状态的时间。
     */
    public int $timeout = 900;

    public function __construct(
        public string $taskId,
        public ?int $actorId = null,
        public ?string $ipAddress = null,
    ) {
        // 备份单独走 backup 队列：mysqldump 最长 900s，混在 business 组里会让
        // queue:work 的严格优先级消费把通知/优惠券/默认业务全部排在导出之后。
        // 走 BackupQueue 兜底：.env 写空值时若回退 default 队列，队头阻塞会复现。
        $this->onQueue(BackupQueue::name());
        $this->afterCommit();
    }

    public function handle(DatabaseBackupTaskService $tasks, DatabaseStatusService $databaseStatus): void
    {
        $tasks->markRunning($this->taskId);

        try {
            $backup = $databaseStatus->createBackup($this->actorId, $this->ipAddress);
        } catch (Throwable $exception) {
            $tasks->markFailed($this->taskId, $exception->getMessage());

            throw $exception;
        }

        $tasks->markCompleted($this->taskId, $backup['filename'], $backup['size_bytes']);
    }

    public function failed(Throwable $exception): void
    {
        // handle() 内的 Throwable 已写过失败原因；此处兜底超时、进程被杀等未进入 handle 收尾的路径。
        app(DatabaseBackupTaskService::class)->markFailed($this->taskId, $exception->getMessage());

        Log::error('数据库备份任务执行失败', [
            'task_id' => $this->taskId,
            'error' => $exception->getMessage(),
        ]);
    }
}
