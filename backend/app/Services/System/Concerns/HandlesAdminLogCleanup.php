<?php

declare(strict_types=1);

namespace App\Services\System\Concerns;

use App\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait HandlesAdminLogCleanup
{
    public function getCleanupOverview(): array
    {
        $logPath = storage_path('logs/laravel.log');
        $fileModifiedAt = is_file($logPath) ? (int) filemtime($logPath) : 0;
        $fileSize = is_file($logPath) ? (int) filesize($logPath) : 0;
        $cacheKey = "admin_logs:cleanup_overview:{$fileModifiedAt}:{$fileSize}";

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(self::CLEANUP_OVERVIEW_CACHE_TTL_SECONDS),
            function () use ($logPath) {
                $logEntries = $this->readLaravelLogEntries(10000);
                $taskLogCount = collect($logEntries)->whereNotNull('task_key')->count();
                $systemLogCount = collect($logEntries)->whereNull('task_key')->count();

                return [
                    'database' => [
                        'sms' => NotificationLog::query()->where('channel', 'sms')->count(),
                        'email' => NotificationLog::query()->where('channel', 'email')->count(),
                        'api' => $this->baseApiLogQuery()->count(),
                        'admin_login' => $this->baseAdminLoginLogQuery()->count(),
                    ],
                    'file' => [
                        'path' => $logPath,
                        'exists' => is_file($logPath),
                        'size_bytes' => is_file($logPath) ? (int) filesize($logPath) : 0,
                        'updated_at' => is_file($logPath) ? date('Y-m-d H:i:s', (int) filemtime($logPath)) : null,
                        'task_log_count' => $taskLogCount,
                        'system_log_count' => $systemLogCount,
                    ],
                    'supported_cleanup_types' => [
                        ['value' => 'sms', 'label' => '短信日志'],
                        ['value' => 'email', 'label' => '邮件日志'],
                        ['value' => 'api', 'label' => 'API日志'],
                        ['value' => 'admin_login', 'label' => '管理员登录日志'],
                        ['value' => 'task', 'label' => '定时任务日志'],
                        ['value' => 'system', 'label' => '系统日志'],
                        ['value' => 'all_db', 'label' => '全部数据库日志'],
                        ['value' => 'all_file', 'label' => '全部文件日志'],
                        ['value' => 'all', 'label' => '全部日志'],
                    ],
                ];
            }
        );
    }

    public function cleanup(array $payload): array
    {
        $type = trim((string) ($payload['type'] ?? ''));
        $keepDays = (int) ($payload['keep_days'] ?? 0);
        $cutoff = now()->subDays($keepDays)->startOfDay();
        $affected = [];

        if ($type === 'all' || $type === 'all_db') {
            DB::transaction(function () use ($cutoff, &$affected) {
                $affected['sms'] = NotificationLog::query()->where('channel', 'sms')->where('created_at', '<', $cutoff)->delete();
                $affected['email'] = NotificationLog::query()->where('channel', 'email')->where('created_at', '<', $cutoff)->delete();
                $affected['api'] = $this->baseApiLogQuery()
                    ->where('created_at', '<', $cutoff)
                    ->delete();
                $affected['admin_login'] = $this->baseAdminLoginLogQuery()
                    ->where('created_at', '<', $cutoff)
                    ->delete();
            });
        } else {
            DB::transaction(function () use ($type, $cutoff, &$affected) {
                if ($type === 'sms') {
                    $affected['sms'] = NotificationLog::query()->where('channel', 'sms')->where('created_at', '<', $cutoff)->delete();
                }

                if ($type === 'email') {
                    $affected['email'] = NotificationLog::query()->where('channel', 'email')->where('created_at', '<', $cutoff)->delete();
                }

                if ($type === 'api') {
                    $affected['api'] = $this->baseApiLogQuery()
                        ->where('created_at', '<', $cutoff)
                        ->delete();
                }

                if ($type === 'admin_login') {
                    $affected['admin_login'] = $this->baseAdminLoginLogQuery()
                        ->where('created_at', '<', $cutoff)
                        ->delete();
                }
            });
        }

        if ($type === 'all' || $type === 'all_file' || $type === 'task' || $type === 'system') {
            $fileCleanup = $this->cleanupFileLogs($type, $cutoff);
            $affected = array_merge($affected, $fileCleanup);
        }

        return [
            'type' => $type,
            'keep_days' => $keepDays,
            'cutoff_at' => $cutoff->format('Y-m-d H:i:s'),
            'affected' => $affected,
        ];
    }

    private function cleanupFileLogs(string $type, Carbon $cutoff): array
    {
        $logPath = storage_path('logs/laravel.log');
        $affected = [];

        if (! is_file($logPath)) {
            return $affected;
        }

        $content = file_get_contents($logPath);
        if ($content === false || $content === '') {
            return $affected;
        }

        $lines = explode("\n", $content);
        $filteredLines = [];
        $taskRemovedCount = 0;
        $systemRemovedCount = 0;

        foreach ($lines as $line) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                $filteredLines[] = $line;

                continue;
            }

            $logDate = $this->extractLogDate($line);
            if ($logDate === null) {
                $filteredLines[] = $line;

                continue;
            }

            $shouldRemove = false;
            $isTaskLog = $this->isTaskLogLine($line);

            if ($type === 'task' || $type === 'all' || $type === 'all_file') {
                if ($isTaskLog && $logDate < $cutoff) {
                    $shouldRemove = true;
                    $taskRemovedCount++;
                }
            }

            if ($type === 'system' || $type === 'all' || $type === 'all_file') {
                if (! $isTaskLog && $logDate < $cutoff) {
                    $shouldRemove = true;
                    $systemRemovedCount++;
                }
            }

            if ($shouldRemove) {
            } else {
                $filteredLines[] = $line;
            }
        }

        if ($taskRemovedCount > 0 || $systemRemovedCount > 0) {
            $newContent = implode("\n", $filteredLines);
            if (substr($newContent, -1) !== "\n") {
                $newContent .= "\n";
            }
            file_put_contents($logPath, $newContent);
        }

        if ($type === 'task' || $type === 'all' || $type === 'all_file') {
            $affected['task'] = $taskRemovedCount;
        }
        if ($type === 'system' || $type === 'all' || $type === 'all_file') {
            $affected['system'] = $systemRemovedCount;
        }

        return $affected;
    }

    private function extractLogDate(string $line): ?Carbon
    {
        if (preg_match('/^\[([^\]]+)\]/', $line, $matches)) {
            try {
                return Carbon::parse(trim($matches[1]));
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function isTaskLogLine(string $line): bool
    {
        foreach (self::TASK_META as $meta) {
            foreach ((array) ($meta['log_keywords'] ?? []) as $keyword) {
                if ($keyword !== '' && str_contains($line, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }
}
