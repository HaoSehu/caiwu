<?php

declare(strict_types=1);

namespace App\Services\System\Concerns;

use App\Models\ActivityLog;
use App\Models\AdminLoginLog;
use App\Models\GatewayLog;
use App\Models\MessageLog;
use App\Models\ScheduleRunLog;
use App\Support\SchemaMetadataCache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
                        'admin_login' => $this->adminLoginLogCount(),
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

    /**
     * 清理或预览日志（对齐 howstack-bos prune 的「默认预览、显式执行」语义）。
     *
     * $payload['dry_run'] 为 true 时只统计截止时间前的待删行数，不执行任何删除。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function cleanup(array $payload): array
    {
        $type = trim((string) ($payload['type'] ?? ''));
        $keepDays = (int) ($payload['keep_days'] ?? 0);
        $dryRun = (bool) ($payload['dry_run'] ?? false);
        $cutoff = now()->subDays($keepDays)->startOfDay();
        $targets = $this->cleanupTargets($cutoff);
        $selected = $type === 'all_db'
            ? $targets
            : [$type => $targets[$type] ?? ['children' => [], 'primary' => []]];
        $affected = [];

        if ($dryRun) {
            foreach ($selected as $key => $target) {
                // 预览只统计主表行数，不把子表（投递尝试）计入「影响条数」。
                $affected[$key] = $this->countQueries($target['primary']);
            }
        } else {
            DB::transaction(function () use ($selected, &$affected): void {
                foreach ($selected as $key => $target) {
                    // 先删子表（投递尝试），再删主表，避免 message_logs 删除后留下孤儿行。
                    $this->deleteQueries($target['children']);
                    $affected[$key] = $this->deleteQueries($target['primary']);
                }
            });

            $this->bumpCleanupOverviewCacheVersion();
        }

        // 文件日志（laravel-*.log）不再提供清理：生产 daily 轮转自带生命周期，
        // 旧实现整文件重写还会绕过审计并破坏并发写入。

        return [
            'type' => $type,
            'keep_days' => $keepDays,
            'dry_run' => $dryRun,
            'cutoff_at' => $cutoff->format('Y-m-d H:i:s'),
            'affected' => $affected,
        ];
    }

    /**
     * 各清理类型对应的查询集合；children 先于 primary 删除，避免孤儿行。
     *
     * admin_login 只清理独立登录日志表；activity_logs 中的历史 admin.login 行
     * 归属于 activity（业务审计），避免 all_db 时重复计数与重复删除。
     *
     * @return array<string, array{children: list<EloquentBuilder|QueryBuilder>, primary: list<EloquentBuilder|QueryBuilder>}>
     */
    private function cleanupTargets(Carbon $cutoff): array
    {
        $targets = [
            'sms' => [
                'children' => $this->messageAttemptCleanupQueries($cutoff, 'sms'),
                'primary' => [MessageLog::query()->where('channel', 'sms')->where('created_at', '<', $cutoff)],
            ],
            'email' => [
                'children' => $this->messageAttemptCleanupQueries($cutoff, 'email'),
                'primary' => [MessageLog::query()->where('channel', 'email')->where('created_at', '<', $cutoff)],
            ],
            'api' => [
                'children' => [],
                'primary' => [$this->baseApiLogQuery()->where('created_at', '<', $cutoff)],
            ],
            'activity' => [
                'children' => [],
                'primary' => $this->activityLogCleanupQueries($cutoff),
            ],
            'schedule_run' => [
                'children' => [],
                'primary' => [ScheduleRunLog::query()->where('created_at', '<', $cutoff)],
            ],
        ];

        if (SchemaMetadataCache::hasTable('admin_login_logs')) {
            $targets['admin_login'] = [
                'children' => [],
                'primary' => [AdminLoginLog::query()->where('logged_at', '<', $cutoff)],
            ];
        } else {
            // 迁移执行前的存量环境：登录记录仍在 activity_logs。
            $targets['admin_login'] = [
                'children' => [],
                'primary' => [ActivityLog::query()
                    ->where('module', 'auth')
                    ->where('action', 'admin.login')
                    ->where('created_at', '<', $cutoff)],
            ];
        }

        return $targets;
    }

    /**
     * 待删 message_logs 对应的投递尝试（子表先删）。
     *
     * @return list<QueryBuilder>
     */
    private function messageAttemptCleanupQueries(Carbon $cutoff, string $channel): array
    {
        if (! SchemaMetadataCache::hasTable('message_delivery_attempts')) {
            return [];
        }

        return [DB::table('message_delivery_attempts')
            ->whereIn('message_log_id', function ($query) use ($cutoff, $channel): void {
                $query->select('id')
                    ->from('message_logs')
                    ->where('channel', $channel)
                    ->where('created_at', '<', $cutoff);
            })];
    }

    /**
     * @param  list<EloquentBuilder|QueryBuilder>  $queries
     */
    private function countQueries(array $queries): int
    {
        return array_sum(array_map(
            static fn (EloquentBuilder|QueryBuilder $query): int => (int) $query->count(),
            $queries,
        ));
    }

    /**
     * @param  list<EloquentBuilder|QueryBuilder>  $queries
     */
    private function deleteQueries(array $queries): int
    {
        return array_sum(array_map(
            static fn (EloquentBuilder|QueryBuilder $query): int => (int) $query->delete(),
            $queries,
        ));
    }

    private function adminLoginLogCount(): int
    {
        if (SchemaMetadataCache::hasTable('admin_login_logs')) {
            return (int) DB::table('admin_login_logs')->count();
        }

        return (int) ActivityLog::query()
            ->where('module', 'auth')
            ->where('action', 'admin.login')
            ->count();
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
        // 业务审计清理只作用于唯一真源 activity_logs；operation_logs 已于 2026-10-25 删除。
        return Schema::hasTable('activity_logs') ? (int) DB::table('activity_logs')->count() : 0;
    }

    /**
     * @return list<EloquentBuilder|QueryBuilder>
     */
    private function activityLogCleanupQueries(Carbon $cutoff): array
    {
        if (! Schema::hasTable('activity_logs')) {
            return [];
        }

        return [DB::table('activity_logs')->where('created_at', '<', $cutoff)];
    }

    private function bumpCleanupOverviewCacheVersion(): void
    {
        Cache::forever(
            self::CLEANUP_OVERVIEW_CACHE_VERSION_KEY,
            (int) Cache::get(self::CLEANUP_OVERVIEW_CACHE_VERSION_KEY, 1) + 1
        );
    }
}
