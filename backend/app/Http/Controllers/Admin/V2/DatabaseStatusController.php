<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\Database\ExportDatabaseBackupRequest;
use App\Http\Requests\Admin\V2\Database\OptimizeDatabaseTablesRequest;
use App\Http\Requests\Admin\V2\Database\ShowDatabaseBackupTaskRequest;
use App\Http\Requests\Admin\V2\Database\ShowDatabaseStatusRequest;
use App\Http\Resources\Admin\V2\AdminActionResultResource;
use App\Http\Resources\Admin\V2\AdminDatabaseStatusResource;
use App\Services\System\DatabaseBackupTaskService;
use App\Services\System\DatabaseStatusService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseStatusController extends Controller
{
    public function __construct(
        private readonly DatabaseStatusService $databaseStatus,
        private readonly DatabaseBackupTaskService $backupTasks,
    ) {}

    public function status(ShowDatabaseStatusRequest $request): JsonResponse
    {
        return $this->success(
            AdminDatabaseStatusResource::make($this->databaseStatus->status())->resolve()
        );
    }

    public function optimize(OptimizeDatabaseTablesRequest $request): JsonResponse
    {
        try {
            $result = $this->databaseStatus->optimize(
                $request->tables(),
                $request->user()?->id ? (int) $request->user()->id : null,
                $request->ip(),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->error(42200, $exception->getMessage());
        } catch (RuntimeException $exception) {
            return $this->error(50000, $exception->getMessage());
        }

        return $this->success(
            AdminActionResultResource::make($result)->resolve(),
            (string) $result['message']
        );
    }

    /**
     * 投递备份任务并立即返回任务 ID，下载走 showBackup / downloadBackup。
     *
     * 备份在 FPM 请求内同步执行会被 request_terminate_timeout 截断，
     * 因此这里只排队，真正的 mysqldump 由 queue:work 在 CLI 执行。
     */
    public function exportBackup(ExportDatabaseBackupRequest $request): JsonResponse
    {
        try {
            $result = $this->backupTasks->dispatch(
                $request->user()?->id ? (int) $request->user()->id : null,
                $request->ip(),
            );
        } catch (InvalidArgumentException $exception) {
            // 备份队列名配错时给出可读提示，而不是落到兜底 renderer 变成 HTTP 500。
            return $this->error(42200, $exception->getMessage());
        }

        return $this->success(
            AdminActionResultResource::make($result)->resolve(),
            (string) $result['message']
        );
    }

    public function showBackup(ShowDatabaseBackupTaskRequest $request): JsonResponse
    {
        $task = $this->backupTasks->find($request->taskId());

        if ($task === null) {
            return $this->error(40400, '备份任务不存在或已过期');
        }

        return $this->success($task);
    }

    public function downloadBackup(ShowDatabaseBackupTaskRequest $request): BinaryFileResponse|JsonResponse
    {
        $filename = $this->backupTasks->completedFilename($request->taskId());

        if ($filename === null) {
            return $this->error(40400, '备份文件尚未生成或已过期');
        }

        $path = $this->databaseStatus->backupPath($filename);

        if (! is_file($path)) {
            return $this->error(40400, '备份文件已被清理，请重新导出');
        }

        // 不在响应结束后删除产物：连接中断（浏览器取消、网络抖动）也会走本分支，
        // 删了文件再点下载就是 404。保留策略本来就把目录收敛在最近 10 份。
        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/sql',
                'File_name' => $filename,
            ]);
    }
}
