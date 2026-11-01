<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Content\Storage\MediaMigrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 媒体库本地/远程双向迁移的队列执行体。
 *
 * 逐文件执行由 MediaMigrationService 承担，单文件失败已归集进批次进度、不中断批次；
 * Job 级失败（超时、进程被杀）由 failed() 兜底标记批次。
 */
class MigrateMediaFilesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * 迁移不可整批重试：重试会重复上传已成功项（外链变化导致引用二次替换），
     * 失败项通过「ids 范围 = 失败清单」单独重跑。
     */
    public int $tries = 1;

    /** 单文件串行 + 厂商限速下，900 秒覆盖数百个中小文件；超出由 failed() 标记批次失败。 */
    public int $timeout = 900;

    /** @var array<int, int> */
    public array $ids;

    public function __construct(
        public string $batchId,
        public string $direction,
        public ?string $driver,
        array $ids,
        public bool $deleteSource,
        public ?int $actorId = null,
    ) {
        $this->ids = array_values(array_map('intval', $ids));
        // default 属业务队列组（config/queue.php 的 caiwu_business_queues 含 default），迁移是普通业务负载。
        $this->onQueue('default');
        $this->afterCommit();
    }

    public function handle(MediaMigrationService $migrations): void
    {
        $migrations->run($this->batchId, $this->direction, $this->driver, $this->ids, $this->deleteSource);
    }

    public function failed(Throwable $exception): void
    {
        app(MediaMigrationService::class)->markFailed($this->batchId, $exception->getMessage());

        Log::error('媒体库迁移任务执行失败', [
            'batch_id' => $this->batchId,
            'direction' => $this->direction,
            'error' => $exception->getMessage(),
        ]);
    }
}
