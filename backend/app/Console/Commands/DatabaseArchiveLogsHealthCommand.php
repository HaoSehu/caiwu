<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ArchiveItem;
use App\Services\System\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * 只读检查日志归档健康状态：候选积压、最近成功/失败批次、报告与归档文件规模。
 * 不执行任何删除或归档动作，可由监控和心跳任务轮询。
 */
class DatabaseArchiveLogsHealthCommand extends Command
{
    protected $signature = 'db:archive-logs:health {--json : 以 JSON 输出健康检查结果}';

    protected $description = '只读检查日志归档候选积压、最近成功/失败批次与归档文件规模';

    public function handle(SettingService $settings): int
    {
        $config = (array) config('log_archive', []);
        $runtime = $settings->getLogArchiveConfig();
        $tables = (array) ($config['tables'] ?? []);
        $protocol = strtolower(trim((string) ($config['protocol'] ?? 'v1')));
        $retentionDays = max(1, (int) ($runtime['retention_days'] ?? $config['retention_days'] ?? 30));
        $archiveRoot = trim((string) ($config['archive_root'] ?? ''));
        $reportRoot = trim((string) ($config['report_root'] ?? ''));

        $tableStats = [];
        $totalEligible = 0;
        $unavailableTables = [];

        foreach (array_keys($tables) as $table) {
            try {
                if (! Schema::hasTable($table)) {
                    $reason = '日志表不存在。';
                    $tableStats[$table] = ['available' => false, 'reason' => $reason];
                    $unavailableTables[] = ['table' => $table, 'reason' => $reason];

                    continue;
                }

                $missingColumns = array_values(array_filter(
                    ['id', 'created_at'],
                    static fn (string $column): bool => ! Schema::hasColumn($table, $column),
                ));
                if ($missingColumns !== []) {
                    $reason = '日志表缺少必需列：'.implode(', ', $missingColumns).'。';
                    $tableStats[$table] = [
                        'available' => false,
                        'missing_columns' => $missingColumns,
                        'reason' => $reason,
                    ];
                    $unavailableTables[] = [
                        'table' => $table,
                        'missing_columns' => $missingColumns,
                        'reason' => $reason,
                    ];

                    continue;
                }

                $eligible = (int) DB::table($table)
                    ->whereRaw('created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$retentionDays])
                    ->count();
                $totalEligible += $eligible;

                $tableStats[$table] = [
                    'available' => true,
                    'total' => (int) DB::table($table)->count(),
                    'eligible' => $eligible,
                    'oldest_at' => DB::table($table)->min('created_at') ?: null,
                    'newest_at' => DB::table($table)->max('created_at') ?: null,
                ];
            } catch (\Throwable $exception) {
                $reason = '读取日志表健康信息失败：'.mb_substr($exception->getMessage(), 0, 200);
                $tableStats[$table] = [
                    'available' => false,
                    'reason' => $reason,
                ];
                $unavailableTables[] = [
                    'table' => $table,
                    'reason' => $reason,
                ];
            }
        }

        $audit = $this->auditSummary();
        $v2 = $this->archiveItemsSummary();

        $reports = $this->summarizeNamedFiles($reportRoot, ['run_*.json', 'archive-*.log']);
        $latestReport = $this->latestRunReport($reportRoot);
        $archives = $this->summarizeArchiveFilesRecursive($archiveRoot);

        $daysSinceSuccess = $audit['days_since_last_completed'] ?? null;
        if ($protocol === 'v2') {
            $daysSinceSuccess = $this->daysSince($v2['latest_success_at'] ?? null);
        }
        $latestFailure = $audit['last_failed_at'] ?? null;
        $latestSuccess = $audit['last_completed_at'] ?? null;
        $failureAfterSuccess = $latestFailure !== null
            && $latestSuccess !== null
            && (string) $latestFailure > (string) $latestSuccess;
        $v2LatestFailure = $v2['latest_failed_at'] ?? null;
        $v2LatestSuccess = $v2['latest_success_at'] ?? null;
        $v2FailureAfterSuccess = $v2LatestFailure !== null
            && $v2LatestSuccess !== null
            && (string) $v2LatestFailure > (string) $v2LatestSuccess;
        $v2FailureWithoutSuccess = $protocol === 'v2'
            && $v2LatestFailure !== null
            && $v2LatestSuccess === null;
        // V1 的清理/报告收尾失败可能发生在所有表审计行已标记 completed 之后；
        // 仅聚合 archive_audit_logs 会把这种失败误报为健康。以最新 run_*.json
        // 的终态作为同一批次的补充证据，避免漏报清理阶段失败。
        $latestReportFailed = ($latestReport['status'] ?? null) === 'failed';
        $latestReportUnknown = ($latestReport['status'] ?? null) === 'unknown';
        $auditMetadataUnavailable = ! (bool) ($audit['available'] ?? false);
        $v2HasFailedItems = $protocol === 'v2'
            && (int) ($v2['by_status'][ArchiveItem::STATUS_FAILED] ?? 0) > 0;
        $knownArchiveStatuses = [
            ArchiveItem::STATUS_PLANNED,
            ArchiveItem::STATUS_STAGING,
            ArchiveItem::STATUS_VERIFIED,
            ArchiveItem::STATUS_PUBLISHED,
            ArchiveItem::STATUS_PURGING,
            ArchiveItem::STATUS_PURGED,
            ArchiveItem::STATUS_FAILED,
            ArchiveItem::STATUS_NEEDS_RECOVERY,
        ];
        $unknownArchiveStatuses = array_values(array_diff(
            array_map('strval', array_keys((array) ($v2['by_status'] ?? []))),
            $knownArchiveStatuses,
        ));
        $v2MetadataUnavailable = $protocol === 'v2'
            && ! (bool) ($v2['available'] ?? false);
        // archive_items 只属于 V2 协议。切回 V1 后，历史/残留的 V2 行不能
        // 把当前 V1 归档健康状态误报为 critical 或 warning；诊断字段仍完整
        // 输出，便于切换协议前后人工核对。
        $v2UnknownStatuses = $protocol === 'v2' && $unknownArchiveStatuses !== [];
        $v2NeedsRecovery = $protocol === 'v2'
            && (int) ($v2['by_status'][ArchiveItem::STATUS_NEEDS_RECOVERY] ?? 0) > 0;
        $v2InProgress = $protocol === 'v2' && (int) ($v2['in_progress'] ?? 0) > 0;
        $archiveScanFailed = trim((string) ($archives['error'] ?? '')) !== '';
        $orphanPartCount = (int) ($archives['by_extension']['part'] ?? 0);
        $health = match (true) {
            ! in_array($protocol, ['v1', 'v2'], true) => 'critical',
            $unavailableTables !== [] => 'critical',
            $auditMetadataUnavailable => 'critical',
            $v2MetadataUnavailable => 'critical',
            $archiveScanFailed => 'critical',
            $failureAfterSuccess => 'critical',
            $v2FailureAfterSuccess => 'critical',
            $v2FailureWithoutSuccess => 'critical',
            $v2HasFailedItems => 'critical',
            $v2UnknownStatuses => 'critical',
            $protocol === 'v1' && $latestReportFailed => 'critical',
            $protocol === 'v1' && $latestReportUnknown => 'warning',
            $v2NeedsRecovery => 'critical',
            $orphanPartCount > 0 => 'warning',
            $v2InProgress => 'warning',
            $daysSinceSuccess === null => 'unknown',
            $daysSinceSuccess > 14 => 'critical',
            $daysSinceSuccess > 7 => 'warning',
            default => 'healthy',
        };

        $payload = [
            'generated_at' => now()->toISOString(),
            'protocol' => $protocol,
            'retention_days' => $retentionDays,
            'status' => $health,
            'tables' => $tableStats,
            'unavailable_tables' => $unavailableTables,
            'total_eligible_rows' => $totalEligible,
            'audit' => $audit,
            'v2' => $v2,
            'unknown_archive_statuses' => $unknownArchiveStatuses,
            'reports' => $reports,
            'latest_report' => $latestReport,
            'archives' => $archives,
            'orphan_part_count' => $orphanPartCount,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->info('日志归档健康检查: '.$health);
            $this->line("保留天数: {$retentionDays}, 归档候选: {$totalEligible}");
            $this->line('最近成功批次: '.($payload['audit']['last_completed_at'] ?? '无'));
            $this->line('最近失败批次: '.($payload['audit']['last_failed_at'] ?? '无'));
            $this->line('V2 归档物: '.($payload['v2']['items'] ?? 0).' 个，失败 '.($payload['v2']['by_status'][ArchiveItem::STATUS_FAILED] ?? 0).' 个');
            $this->line('归档文件: '.$payload['archives']['file_count'].' 个 / '.$payload['archives']['bytes'].' bytes');
            if ($orphanPartCount > 0) {
                $this->warn("发现 {$orphanPartCount} 个未完成归档 .part 文件");
            }
            $this->line('报告文件: '.$payload['reports']['file_count'].' 个 / '.$payload['reports']['bytes'].' bytes');
        }

        return $health === 'critical' ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function archiveItemsSummary(): array
    {
        try {
            if (! Schema::hasTable('archive_items')) {
                return [
                    'available' => false,
                    'reason' => 'archive_items 表不存在。',
                    'items' => 0,
                    'batches' => 0,
                    'by_status' => [],
                    'unknown_statuses' => [],
                    'in_progress' => 0,
                    'latest_failed_at' => null,
                    'latest_success_at' => null,
                ];
            }

            $missingColumns = array_values(array_filter(
                ['status', 'batch_id', 'updated_at'],
                static fn (string $column): bool => ! Schema::hasColumn('archive_items', $column),
            ));
            if ($missingColumns !== []) {
                return [
                    'available' => false,
                    'reason' => 'archive_items 表缺少必需列：'.implode(', ', $missingColumns).'。',
                    'missing_columns' => $missingColumns,
                    'items' => 0,
                    'batches' => 0,
                    'by_status' => [],
                    'unknown_statuses' => [],
                    'in_progress' => 0,
                    'latest_failed_at' => null,
                    'latest_success_at' => null,
                ];
            }

            $byStatus = DB::table('archive_items')
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status')
                ->map(static fn (mixed $count): int => (int) $count)
                ->all();
            $inProgressStatuses = [
                ArchiveItem::STATUS_PLANNED,
                ArchiveItem::STATUS_STAGING,
                ArchiveItem::STATUS_VERIFIED,
                ArchiveItem::STATUS_PURGING,
            ];
            $knownStatuses = [
                ArchiveItem::STATUS_PLANNED,
                ArchiveItem::STATUS_STAGING,
                ArchiveItem::STATUS_VERIFIED,
                ArchiveItem::STATUS_PUBLISHED,
                ArchiveItem::STATUS_PURGING,
                ArchiveItem::STATUS_PURGED,
                ArchiveItem::STATUS_FAILED,
                ArchiveItem::STATUS_NEEDS_RECOVERY,
            ];
            $unknownStatuses = array_values(array_diff(
                array_map('strval', array_keys($byStatus)),
                $knownStatuses,
            ));

            $latestFailedAt = DB::table('archive_items')
                ->whereIn('status', [ArchiveItem::STATUS_FAILED, ArchiveItem::STATUS_NEEDS_RECOVERY])
                ->max('updated_at');
            $latestSuccessAt = DB::table('archive_items')
                ->whereIn('status', [ArchiveItem::STATUS_PUBLISHED, ArchiveItem::STATUS_PURGED])
                ->max('updated_at');

            return [
                'available' => true,
                'items' => array_sum($byStatus),
                'batches' => (int) DB::table('archive_items')->distinct('batch_id')->count('batch_id'),
                'by_status' => $byStatus,
                'unknown_statuses' => $unknownStatuses,
                'in_progress' => array_sum(array_intersect_key($byStatus, array_flip($inProgressStatuses))),
                'oldest_in_progress_at' => DB::table('archive_items')->whereIn('status', $inProgressStatuses)->min('updated_at'),
                'latest_failed_at' => $latestFailedAt ? (string) $latestFailedAt : null,
                'latest_success_at' => $latestSuccessAt ? (string) $latestSuccessAt : null,
            ];
        } catch (\Throwable $exception) {
            return [
                'available' => false,
                'reason' => '读取 archive_items 健康信息失败：'.mb_substr($exception->getMessage(), 0, 200),
                'items' => 0,
                'batches' => 0,
                'by_status' => [],
                'unknown_statuses' => [],
                'in_progress' => 0,
                'latest_failed_at' => null,
                'latest_success_at' => null,
            ];
        }
    }

    private function daysSince(?string $timestamp): ?int
    {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }

        try {
            return (int) CarbonImmutable::parse($timestamp)->diffInDays(CarbonImmutable::now());
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function auditSummary(): array
    {
        try {
            if (! Schema::hasTable('archive_audit_logs')) {
                return ['available' => false, 'reason' => 'archive_audit_logs 表不存在。'];
            }

            $missingColumns = array_values(array_filter(
                ['status', 'finished_at'],
                static fn (string $column): bool => ! Schema::hasColumn('archive_audit_logs', $column),
            ));
            if ($missingColumns !== []) {
                return [
                    'available' => false,
                    'reason' => 'archive_audit_logs 表缺少必需列：'.implode(', ', $missingColumns).'。',
                    'missing_columns' => $missingColumns,
                ];
            }

            $byStatus = DB::table('archive_audit_logs')
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status')
                ->all();

            $lastCompletedAt = DB::table('archive_audit_logs')
                ->where('status', 'completed')
                ->max('finished_at');
            $lastFailedAt = DB::table('archive_audit_logs')
                ->where('status', 'failed')
                ->max('finished_at');

            $lastCompletedAt = $lastCompletedAt ? (string) $lastCompletedAt : null;
            $lastFailedAt = $lastFailedAt ? (string) $lastFailedAt : null;

            $daysSinceCompleted = null;
            if ($lastCompletedAt !== null) {
                try {
                    $daysSinceCompleted = (int) CarbonImmutable::parse($lastCompletedAt)->diffInDays(CarbonImmutable::now());
                } catch (\Throwable) {
                    $daysSinceCompleted = null;
                }
            }

            return [
                'available' => true,
                'batches' => array_sum($byStatus),
                'by_status' => $byStatus,
                'last_completed_at' => $lastCompletedAt,
                'last_failed_at' => $lastFailedAt,
                'days_since_last_completed' => $daysSinceCompleted,
            ];
        } catch (\Throwable $exception) {
            return [
                'available' => false,
                'reason' => '读取 archive_audit_logs 健康信息失败：'.mb_substr($exception->getMessage(), 0, 200),
            ];
        }
    }

    /**
     * @param  list<string>  $patterns
     * @return array{file_count: int, bytes: int, last_modified_at: string|null}
     */
    private function summarizeNamedFiles(string $directory, array $patterns): array
    {
        $result = ['file_count' => 0, 'bytes' => 0, 'last_modified_at' => null];
        if ($directory === '' || ! is_dir($directory)) {
            return $result;
        }

        $lastModified = 0;
        foreach ($patterns as $pattern) {
            foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR.'/\\').DIRECTORY_SEPARATOR.$pattern) ?: [] as $path) {
                if (! is_file($path)) {
                    continue;
                }
                $result['file_count']++;
                $result['bytes'] += max(0, (int) filesize($path));
                $mtime = (int) filemtime($path);
                $lastModified = max($lastModified, $mtime);
            }
        }

        $result['last_modified_at'] = $lastModified > 0 ? date('Y-m-d H:i:s', $lastModified) : null;

        return $result;
    }

    /**
     * 读取最近一份 V1 运行报告的终态。报告文件由归档服务在同一批次内覆盖写入，
     * 因此按 mtime 选最新文件即可覆盖“审计行已完成、清理阶段后失败”的边界。
     * 只读取有限大小，避免损坏/异常报告把健康检查本身拖入高内存占用。
     *
     * @return array{status: string|null, finished_at: string|null, file: string|null, reason: string|null}
     */
    private function latestRunReport(string $directory): array
    {
        $empty = [
            'status' => null,
            'finished_at' => null,
            'file' => null,
            'reason' => null,
        ];
        if ($directory === '' || ! is_dir($directory)) {
            return $empty;
        }

        $files = array_values(array_filter(
            glob(rtrim($directory, DIRECTORY_SEPARATOR.'/\\').DIRECTORY_SEPARATOR.'run_*.json') ?: [],
            static fn (string $path): bool => is_file($path) && ! is_link($path),
        ));
        usort($files, static function (string $left, string $right): int {
            $mtimeOrder = ((int) @filemtime($right)) <=> ((int) @filemtime($left));

            return $mtimeOrder !== 0 ? $mtimeOrder : strcmp($right, $left);
        });
        $path = $files[0] ?? null;
        if ($path === null) {
            return $empty;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return array_merge($empty, [
                'file' => basename($path),
                'reason' => '最近运行报告无法读取。',
                'status' => 'unknown',
            ]);
        }

        try {
            $raw = stream_get_contents($handle, 1024 * 1024);
        } finally {
            fclose($handle);
        }

        if ($raw === false) {
            return array_merge($empty, [
                'file' => basename($path),
                'reason' => '最近运行报告读取失败。',
                'status' => 'unknown',
            ]);
        }

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return array_merge($empty, [
                'file' => basename($path),
                'reason' => '最近运行报告不是有效 JSON。',
                'status' => 'unknown',
            ]);
        }

        if (! is_array($payload)) {
            return array_merge($empty, [
                'file' => basename($path),
                'reason' => '最近运行报告结构无效。',
                'status' => 'unknown',
            ]);
        }

        $status = strtolower(trim((string) ($payload['status'] ?? '')));
        if ($status === '') {
            // 旧版 dry-run/演练报告可能只有 mode 等摘要字段，不把缺少终态
            // 的历史文件升级为当前健康告警；只有明确损坏的 JSON 才标 unknown。
            return [
                'status' => null,
                'finished_at' => isset($payload['finished_at']) ? (string) $payload['finished_at'] : null,
                'file' => basename($path),
                'reason' => '最近运行报告缺少终态，保留为兼容摘要。',
            ];
        }
        if (! in_array($status, ['completed', 'failed'], true)) {
            return array_merge($empty, [
                'file' => basename($path),
                'reason' => '最近运行报告缺少有效终态。',
                'status' => 'unknown',
                'finished_at' => isset($payload['finished_at']) ? (string) $payload['finished_at'] : null,
            ]);
        }

        return [
            'status' => $status,
            'finished_at' => isset($payload['finished_at']) ? (string) $payload['finished_at'] : null,
            'file' => basename($path),
            'reason' => null,
        ];
    }

    /** @return array{file_count: int, bytes: int, last_modified_at: string|null, by_extension: array<string, int>, error?: string} */
    private function summarizeArchiveFilesRecursive(string $directory): array
    {
        $result = ['file_count' => 0, 'bytes' => 0, 'last_modified_at' => null, 'by_extension' => []];
        if ($directory === '' || ! is_dir($directory)) {
            return $result;
        }

        $lastModified = 0;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }
                $extension = strtolower((string) $file->getExtension());
                if (! in_array($extension, ['log', 'csv', 'part', 'json'], true)) {
                    continue;
                }
                $result['file_count']++;
                $result['bytes'] += max(0, (int) $file->getSize());
                $result['by_extension'][$extension] = (int) ($result['by_extension'][$extension] ?? 0) + 1;
                $mtime = (int) $file->getMTime();
                $lastModified = max($lastModified, $mtime);
            }
        } catch (\Throwable $exception) {
            $result['error'] = '读取归档目录失败：'.mb_substr($exception->getMessage(), 0, 200);
        }

        $result['last_modified_at'] = $lastModified > 0 ? date('Y-m-d H:i:s', $lastModified) : null;

        return $result;
    }
}
