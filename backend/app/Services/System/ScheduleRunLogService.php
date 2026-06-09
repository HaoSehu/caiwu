<?php

namespace App\Services\System;

use App\Models\ScheduleRunLog;
use Illuminate\Support\Carbon;
use Throwable;

class ScheduleRunLogService
{
    public function record(string $taskName, callable $callback): mixed
    {
        $startedAt = Carbon::now();
        $startMs = (int) (microtime(true) * 1000);

        try {
            $result = $callback();
            $endMs = (int) (microtime(true) * 1000);

            ScheduleRunLog::query()->create([
                'task_name' => $taskName,
                'status' => 'success',
                'duration_ms' => $endMs - $startMs,
                'summary' => is_array($result) ? $result : null,
                'started_at' => $startedAt,
                'finished_at' => Carbon::now(),
            ]);

            return $result;
        } catch (Throwable $e) {
            $endMs = (int) (microtime(true) * 1000);

            ScheduleRunLog::query()->create([
                'task_name' => $taskName,
                'status' => 'failed',
                'duration_ms' => $endMs - $startMs,
                'error_msg' => mb_substr($e->getMessage(), 0, 2000),
                'started_at' => $startedAt,
                'finished_at' => Carbon::now(),
            ]);

            throw $e;
        }
    }

    public function getScheduleStatus(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $query = ScheduleRunLog::query()->orderByDesc('id');

        if (! empty($filters['task_name'])) {
            $query->where('task_name', $filters['task_name']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('task_name', 'like', "%{$keyword}%")
                    ->orWhere('error_msg', 'like', "%{$keyword}%");
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    public function getHealthOverview(): array
    {
        $tasks = ScheduleRunLog::query()
            ->selectRaw('task_name, MAX(finished_at) as last_run_at, COUNT(*) as total_runs')
            ->selectRaw('SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed_count')
            ->selectRaw('AVG(duration_ms) as avg_duration_ms')
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('task_name')
            ->get();

        return $tasks->map(function ($task) {
            $lastRun = $task->last_run_at ? Carbon::parse($task->last_run_at) : null;
            $minutesSinceLastRun = $lastRun ? (int) $lastRun->diffInMinutes(now()) : null;

            return [
                'task_name' => $task->task_name,
                'last_run_at' => $lastRun?->toDateTimeString(),
                'minutes_since_last_run' => $minutesSinceLastRun,
                'health' => $this->evaluateHealth($minutesSinceLastRun),
                'total_runs_24h' => (int) $task->total_runs,
                'failed_count_24h' => (int) $task->failed_count,
                'avg_duration_ms' => (int) round($task->avg_duration_ms),
            ];
        })->all();
    }

    private function evaluateHealth(?int $minutesSinceLastRun): string
    {
        if ($minutesSinceLastRun === null) {
            return 'unknown';
        }

        if ($minutesSinceLastRun <= 30) {
            return 'healthy';
        }

        if ($minutesSinceLastRun <= 120) {
            return 'warning';
        }

        return 'critical';
    }
}
