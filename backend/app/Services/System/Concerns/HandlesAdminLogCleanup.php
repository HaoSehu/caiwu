<?php

declare(strict_types=1);

namespace App\Services\System\Concerns;

use App\Models\GatewayLog;
use App\Models\MessageLog;
use App\Models\ScheduleRunLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait HandlesAdminLogCleanup
{
    public function getCleanupOverview(): array
    {
        $fileSnapshot = $this->laravelLogFileSnapshot();
        $cacheVersion = (int) Cache::get(self::CLEANUP_OVERVIEW_CACHE_VERSION_KEY, 1);
        $cacheKey = "admin_logs:cleanup_overview:{$cacheVersion}:{$fileSnapshot['signature']}";

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(self::CLEANUP_OVERVIEW_CACHE_TTL_SECONDS),
            function () use ($fileSnapshot) {
                unset($fileSnapshot['signature']);

                return [
                    'database' => [
                        'sms' => MessageLog::query()->where('channel', 'sms')->count(),
                        'email' => MessageLog::query()->where('channel', 'email')->count(),
                        'api' => $this->baseApiLogQuery()->count(),
                        'admin_login' => $this->baseAdminLoginLogQuery()->count(),
                        'activity' => $this->activityLogCount(),
                        'schedule_run' => ScheduleRunLog::query()->count(),
                        'gateway' => GatewayLog::query()->count(),
                    ],
                    // 文件日志只做只读展示；生命周期由日志轮转（daily，默认 14 天）管理
                    'file' => $fileSnapshot,
                    'supported_cleanup_types' => [
                        ['value' => 'sms', 'label' => '短信日志'],
                        ['value' => 'email', 'label' => '邮件日志'],
                        ['value' => 'api', 'label' => 'API日志'],
                        ['value' => 'admin_login', 'label' => '管理员登录日志'],
                        ['value' => 'activity', 'label' => '业务审计日志'],
                        ['value' => 'schedule_run', 'label' => '调度执行日志'],
                        // gateway：支付证据，只读预览，不得随普通清理物理删除
                        ['value' => 'all_db', 'label' => '全部数据库日志'],
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

        if ($type === 'all_db') {
            DB::transaction(function () use ($cutoff, &$affected) {
                $affected['sms'] = MessageLog::query()->where('channel', 'sms')->where('created_at', '<', $cutoff)->delete();
                $affected['email'] = MessageLog::query()->where('channel', 'email')->where('created_at', '<', $cutoff)->delete();
                $affected['api'] = $this->baseApiLogQuery()
                    ->where('created_at', '<', $cutoff)
                    ->delete();
                $affected['admin_login'] = $this->baseAdminLoginLogQuery()
                    ->where('created_at', '<', $cutoff)
                    ->delete();
                $affected['activity'] = $this->deleteActivityLogsBefore($cutoff);
                $affected['schedule_run'] = ScheduleRunLog::query()->where('created_at', '<', $cutoff)->delete();
                // gateway 为支付证据：不随 all_db 清理物理删除，仅保留只读预览
            });
        } else {
            DB::transaction(function () use ($type, $cutoff, &$affected) {
                if ($type === 'sms') {
                    $affected['sms'] = MessageLog::query()->where('channel', 'sms')->where('created_at', '<', $cutoff)->delete();
                }

                if ($type === 'email') {
                    $affected['email'] = MessageLog::query()->where('channel', 'email')->where('created_at', '<', $cutoff)->delete();
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

                if ($type === 'activity') {
                    $affected['activity'] = $this->deleteActivityLogsBefore($cutoff);
                }

                if ($type === 'schedule_run') {
                    $affected['schedule_run'] = ScheduleRunLog::query()->where('created_at', '<', $cutoff)->delete();
                }

                // gateway 为支付证据，单独 type 也不得物理删除（校验层已排除）
            });
        }

        // 文件日志（laravel-*.log）不再提供清理：生产 daily 轮转自带生命周期，
        // 旧实现整文件重写还会绕过审计并破坏并发写入。
        $this->bumpCleanupOverviewCacheVersion();

        return [
            'type' => $type,
            'keep_days' => $keepDays,
            'cutoff_at' => $cutoff->format('Y-m-d H:i:s'),
            'affected' => $affected,
        ];
    }

    /**
     * 只读扫描 storage/logs 下的 Laravel 日志文件（daily 通道为 laravel-YYYY-MM-DD.log）。
     * signature 不进响应，仅用于概览缓存键感知目录变化。
     *
     * @return array<string, mixed>
     */
    private function laravelLogFileSnapshot(): array
    {
        $files = collect(glob(storage_path('logs/*.log')) ?: [])
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'size_bytes' => (int) filesize($path),
                'updated_at' => date('Y-m-d H:i:s', (int) filemtime($path)),
            ])
            ->sortByDesc('updated_at')
            ->values();

        return [
            'directory' => 'storage/logs',
            'file_count' => $files->count(),
            'total_size_bytes' => (int) $files->sum('size_bytes'),
            'latest_updated_at' => $files->first()['updated_at'] ?? null,
            'files' => $files->take(30)->all(),
            'signature' => $files->count().':'.$files->sum('size_bytes').':'.($files->first()['updated_at'] ?? ''),
        ];
    }

    private function activityLogCount(): int
    {
        // operation_logs 已停写并转只读遗留表，业务审计清理只作用于唯一真源 activity_logs；
        // operation_logs 存量由 30 天归档统一消化，不通过管理端清理删除
        return Schema::hasTable('activity_logs') ? (int) DB::table('activity_logs')->count() : 0;
    }

    private function deleteActivityLogsBefore(Carbon $cutoff): int
    {
        if (! Schema::hasTable('activity_logs')) {
            return 0;
        }

        return (int) DB::table('activity_logs')->where('created_at', '<', $cutoff)->delete();
    }

    private function bumpCleanupOverviewCacheVersion(): void
    {
        Cache::forever(
            self::CLEANUP_OVERVIEW_CACHE_VERSION_KEY,
            (int) Cache::get(self::CLEANUP_OVERVIEW_CACHE_VERSION_KEY, 1) + 1
        );
    }
}
