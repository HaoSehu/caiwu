<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\Schedule\ClearQueueJobsRequest;
use App\Http\Requests\Admin\V2\Schedule\ListQueueJobsRequest;
use App\Http\Requests\Admin\V2\Schedule\ListScheduleTaskRunsRequest;
use App\Http\Requests\Admin\V2\Schedule\RetryScheduleTaskRunRequest;
use App\Http\Requests\Admin\V2\Schedule\ShowScheduleOverviewRequest;
use App\Http\Requests\Admin\V2\Schedule\ShowScheduleTaskRunRequest;
use App\Http\Requests\Admin\V2\Schedule\TriggerScheduleTaskRequest;
use App\Http\Resources\Admin\V2\AdminActionResultResource;
use App\Http\Resources\Admin\V2\AdminQueueJobResource;
use App\Http\Resources\Admin\V2\AdminScheduleOverviewResource;
use App\Http\Resources\Admin\V2\AdminScheduleTaskRunResource;
use App\Services\Admin\V2\AdminOperationalActionV2Service;
use App\Services\Automation\QueueJobService;
use App\Services\Automation\ScheduleTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ScheduleTaskController extends Controller
{
    public function __construct(
        private readonly AdminOperationalActionV2Service $actions,
        private readonly ScheduleTaskService $scheduleTasks,
        private readonly QueueJobService $queueJobs,
    ) {}

    public function overview(ShowScheduleOverviewRequest $request): JsonResponse
    {
        return $this->success(AdminScheduleOverviewResource::make(
            $this->scheduleTasks->overview()
        )->resolve());
    }

    public function trigger(TriggerScheduleTaskRequest $request): JsonResponse
    {
        try {
            $result = $this->actions->triggerScheduleTask(
                $request->task(),
                $request->user()?->id ? (int) $request->user()->id : null,
            );
        } catch (InvalidArgumentException $exception) {
            return $this->error(42200, $exception->getMessage());
        }

        return $this->success(AdminActionResultResource::make($result)->resolve(), (string) $result['message']);
    }

    public function index(ListScheduleTaskRunsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $filters['status'] = $request->statuses();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['page_size'] ?? 20)));

        return $this->paginate(
            $this->scheduleTasks->paginateRuns($filters, $page, $perPage),
            AdminScheduleTaskRunResource::class,
        );
    }

    public function show(ShowScheduleTaskRunRequest $request, int $run): JsonResponse
    {
        $taskRun = $this->scheduleTasks->runDetail($run);
        if ($taskRun === null) {
            return $this->error(40400, '运行记录不存在');
        }

        return $this->success([
            'run' => AdminScheduleTaskRunResource::make($taskRun)->resolve(),
        ]);
    }

    public function retry(RetryScheduleTaskRunRequest $request, int $run): JsonResponse
    {
        if ($this->scheduleTasks->runDetail($run) === null) {
            return $this->error(40400, '运行记录不存在');
        }

        try {
            $result = $this->actions->retryScheduleTaskRun(
                runId: $run,
                adminUserId: $request->user()?->id ? (int) $request->user()->id : null,
                reason: $request->reason(),
                ipAddress: $request->ip(),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->error(42200, $exception->getMessage());
        }

        return $this->success(AdminActionResultResource::make($result)->resolve(), (string) $result['message']);
    }

    public function queueJobs(ListQueueJobsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return $this->paginate(
            $this->queueJobs->paginateJobs(
                $request->queue(),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['page_size'] ?? 20),
            ),
            AdminQueueJobResource::class,
        );
    }

    public function deleteQueueJob(Request $request, string $queue, int $jobId): JsonResponse
    {
        try {
            $job = $this->queueJobs->deleteJob(
                $queue,
                $jobId,
                adminUserId: $request->user()?->id ? (int) $request->user()->id : null,
                ipAddress: $request->ip(),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->error(42200, $exception->getMessage());
        }

        return $this->success(AdminActionResultResource::make([
            'id' => (int) ($job['id'] ?? 0),
            'status' => 'completed',
            'message' => '队列任务已删除',
            'detail' => [
                'type' => 'schedule_queue_job_delete',
                'job' => [
                    'kind' => (string) ($job['kind'] ?? ''),
                    'queue' => (string) ($job['queue'] ?? ''),
                    'display_name' => (string) ($job['display_name'] ?? ''),
                ],
            ],
        ])->resolve(), '队列任务已删除');
    }

    public function clearQueueJobs(ClearQueueJobsRequest $request, string $queue): JsonResponse
    {
        try {
            $deletedCount = $this->queueJobs->clearJobs(
                $queue,
                adminUserId: $request->user()?->id ? (int) $request->user()->id : null,
                ipAddress: $request->ip(),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->error(42200, $exception->getMessage());
        }

        return $this->success(AdminActionResultResource::make([
            'id' => 0,
            'status' => 'completed',
            'message' => '队列已清空',
            'detail' => [
                'type' => 'schedule_queue_clear',
                'queue' => [
                    'kind' => $queue,
                    'deleted_count' => $deletedCount,
                ],
            ],
        ])->resolve(), "队列已清空，共删除 {$deletedCount} 条任务");
    }
}
