<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Exceptions\BusinessException;
use App\Support\MysqlDumpBinary;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class DatabaseStatusService
{
    private const BACKUP_SUBDIR = 'database-backups';

    private const MAX_BACKUP_RETENTION = 10;

    /**
     * .part 半成品的最长保留时间（秒）。超过这个时间说明导出进程已被强杀，
     * 产物不可能再被改名，可以安全清理。
     */
    private const STALE_PARTIAL_SECONDS = 60 * 60;

    private const OPTIMIZE_MINIMUM_FREE_BYTES = 8 * 1024 * 1024;

    private const OPTIMIZE_MINIMUM_FREE_RATIO = 0.10;

    private const OPTIMIZE_LOCK_KEY = 'system:database:optimize:lock';

    private const OPTIMIZE_LOCK_SECONDS = 60 * 60;

    private const OPTIMIZE_COOLDOWN_KEY = 'system:database:optimize:last_completed_at';

    private const OPTIMIZE_COOLDOWN_SECONDS = 30 * 60;

    /**
     * 整库备份排除的运行日志表（E2-06）：这些表随运行无限增长（合计数百万行，
     * 如 activity_logs 超 110 万行），不属于业务恢复所需数据；排除后单次备份
     * 不再拖入全量日志，其余业务表的结构与数据导出行为保持不变。
     */
    private const BACKUP_EXCLUDED_LOG_TABLES = [
        'activity_logs',
        'integration_plugin_runtime_logs',
        'gateway_logs',
        'schedule_run_logs',
        'schedule_task_runs',
    ];

    public function __construct(
        private readonly DatabaseEngineeringService $engineering,
        private readonly OperationLogService $operationLogs,
    ) {}

    /**
     * @return array{
     *     database: string,
     *     list: list<array{name: string, rows: int, size_mb: float, update_time: ?string}>,
     *     total_count: int,
     *     total_rows: int,
     *     total_size_mb: float,
     *     optimization: array{candidate_count: int, estimated_reclaimable_mb: float, candidates: list<array{name: string, reclaimable_mb: float, fragmentation_ratio: float}>, cooldown_remaining_seconds: int, last_optimized_at: ?string}
     * }
     */
    public function status(): array
    {
        $tables = collect($this->engineering->tableSizeMetrics())
            ->map(fn (array $row): array => [
                'name' => (string) $row['table_name'],
                'rows' => (int) $row['table_rows'],
                'size_mb' => round((float) $row['size_mb'], 2),
                'update_time' => $row['update_time'] ? (string) $row['update_time'] : null,
            ])
            ->values()
            ->all();

        $totalRows = 0;
        $totalSizeMb = 0.0;

        foreach ($tables as $table) {
            $totalRows += (int) $table['rows'];
            $totalSizeMb += (float) $table['size_mb'];
        }

        $candidates = collect($this->engineering->optimizationCandidates(
            self::OPTIMIZE_MINIMUM_FREE_BYTES,
            self::OPTIMIZE_MINIMUM_FREE_RATIO,
        ))
            ->map(static fn (array $candidate): array => [
                'name' => (string) $candidate['table_name'],
                'reclaimable_mb' => round((float) $candidate['reclaimable_mb'], 2),
                'fragmentation_ratio' => round((float) $candidate['fragmentation_ratio'], 4),
            ])
            ->values()
            ->all();
        $cooldown = $this->optimizationCooldown();

        return [
            'database' => (string) DB::getDatabaseName(),
            'list' => $tables,
            'total_count' => count($tables),
            'total_rows' => $totalRows,
            'total_size_mb' => round($totalSizeMb, 2),
            'optimization' => [
                'candidate_count' => count($candidates),
                'estimated_reclaimable_mb' => round(collect($candidates)->sum('reclaimable_mb'), 2),
                'candidates' => $candidates,
                'cooldown_remaining_seconds' => $cooldown['remaining_seconds'],
                'last_optimized_at' => $cooldown['last_optimized_at'],
            ],
        ];
    }

    /**
     * @param  list<string>  $tables
     * @return array{id: string, status: string, message: string, detail: array<string, mixed>}
     */
    public function optimize(array $tables = [], ?int $actorId = null, ?string $ipAddress = null): array
    {
        $selectionMode = $tables === [] ? 'fragmentation' : 'manual';
        $lock = Cache::lock(self::OPTIMIZE_LOCK_KEY, self::OPTIMIZE_LOCK_SECONDS);

        if (! $lock->get()) {
            throw new BusinessException('数据库优化正在进行，请勿重复提交', 40900, 409);
        }

        try {
            if ($selectionMode === 'fragmentation') {
                $cooldown = $this->optimizationCooldown();
                if ($cooldown['remaining_seconds'] > 0) {
                    throw new BusinessException(
                        sprintf('数据库优化刚执行完毕，请在 %d 分钟后重试', (int) ceil($cooldown['remaining_seconds'] / 60)),
                        42900,
                        429,
                    );
                }
            }

            $selected = $selectionMode === 'fragmentation'
                ? $this->engineering->optimizableTables(
                    self::OPTIMIZE_MINIMUM_FREE_BYTES,
                    self::OPTIMIZE_MINIMUM_FREE_RATIO,
                )
                : $this->resolveBaseTables($tables);
            $optimized = [];
            $failed = [];

            foreach ($selected as $table) {
                try {
                    DB::statement('OPTIMIZE TABLE `'.str_replace('`', '``', $table).'`');
                    $optimized[] = $table;
                } catch (Throwable $exception) {
                    $failed[] = [
                        'table' => $table,
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            $status = $failed === [] ? 'completed' : ($optimized === [] ? 'failed' : 'partial');
            $message = match ($status) {
                'completed' => $optimized === [] && $selectionMode === 'fragmentation'
                    ? '未发现需要优化的数据表'
                    : sprintf('已优化 %d 张数据表', count($optimized)),
                'partial' => sprintf('已优化 %d 张表，%d 张失败', count($optimized), count($failed)),
                default => '数据表优化失败',
            };

            if ($selectionMode === 'fragmentation' && $optimized !== []) {
                Cache::put(self::OPTIMIZE_COOLDOWN_KEY, now()->timestamp, self::OPTIMIZE_COOLDOWN_SECONDS);
            }

            $this->operationLogs->write(
                userId: $actorId,
                userType: 'admin',
                action: 'database.optimize',
                module: 'database',
                targetId: null,
                detail: [
                    'status' => $status,
                    'requested_tables' => array_values($tables),
                    'selection_mode' => $selectionMode,
                    'candidate_tables' => $selected,
                    'optimized_tables' => $optimized,
                    'failed_tables' => array_map(static fn (array $item): string => (string) $item['table'], $failed),
                    'failed_count' => count($failed),
                ],
                ipAddress: $ipAddress,
            );

            if ($status === 'failed') {
                throw new RuntimeException($failed[0]['message'] ?? $message);
            }

            return [
                'id' => 'database-optimize',
                'status' => $status,
                'message' => $message,
                'detail' => [
                    'optimized_count' => count($optimized),
                    'failed_count' => count($failed),
                    'selection_mode' => $selectionMode,
                    'optimized_tables' => $optimized,
                    'failed_tables' => $failed,
                ],
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{absolute_path: string, filename: string, size_bytes: int}
     */
    public function createBackup(?int $actorId = null, ?string $ipAddress = null): array
    {
        $connection = DB::connection();
        $driver = (string) $connection->getDriverName();

        if ($driver !== 'mysql') {
            throw new RuntimeException('当前仅支持 MySQL 数据库备份');
        }

        $config = $connection->getConfig();
        $database = (string) ($config['database'] ?? '');
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 3306);
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        if ($database === '' || $username === '') {
            throw new RuntimeException('数据库连接配置不完整，无法导出备份');
        }

        $directory = $this->backupDirectory();
        File::ensureDirectoryExists($directory, 0750);

        $filename = sprintf('backup_%s_%s.sql', date('YmdHis'), Str::lower(Str::random(6)));
        $absolutePath = $directory.DIRECTORY_SEPARATOR.$filename;
        // mysqldump 先写 .part 再原子改名：Process::timeout 超时会直接抛异常，
        // 半成品若直接叫 .sql，就会被下载接口识别成可用备份、还会占掉保留配额。
        $partialPath = $absolutePath.'.part';
        $mysqldump = $this->resolveMysqldumpBinary();

        $command = [
            $mysqldump,
            '--host='.$host,
            '--port='.(string) $port,
            '--user='.$username,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set=utf8mb4',
            '--result-file='.$partialPath,
            ...$this->backupIgnoreTableOptions($database),
            $database,
        ];

        try {
            $result = Process::timeout(600)
                ->env([
                    'MYSQL_PWD' => $password,
                ])
                ->run($command);
        } catch (Throwable $exception) {
            $this->removeBackupFile($partialPath);

            throw $exception;
        }

        if (! $result->successful() || ! File::exists($partialPath) || File::size($partialPath) <= 0) {
            $this->removeBackupFile($partialPath);
            $this->removeBackupFile($absolutePath);

            $error = trim($result->errorOutput() ?: $result->output());
            throw new RuntimeException($error !== '' ? $error : '导出数据库备份失败');
        }

        if (! File::move($partialPath, $absolutePath)) {
            $this->removeBackupFile($partialPath);

            throw new RuntimeException('导出数据库备份失败：整理备份文件时出错');
        }

        $sizeBytes = (int) File::size($absolutePath);

        $this->operationLogs->write(
            userId: $actorId,
            userType: 'admin',
            action: 'database.backup',
            module: 'database',
            targetId: null,
            detail: [
                'filename' => $filename,
                'size_bytes' => $sizeBytes,
                'database' => $database,
            ],
            ipAddress: $ipAddress,
        );

        // 先写操作日志再裁剪：裁剪依赖文件 mtime 排序，时钟回拨等异常下若先删后记，
        // 会出现「产物已被删掉但操作日志仍以为备份可用」。日志落盘后再淘汰旧备份，
        // 保留策略只影响更早的历史产物。
        $this->pruneOldBackups($directory);

        return [
            'absolute_path' => $absolutePath,
            'filename' => $filename,
            'size_bytes' => $sizeBytes,
        ];
    }

    /**
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function resolveBaseTables(array $tables): array
    {
        $available = $this->engineering->baseTables();
        $availableLookup = array_fill_keys($available, true);

        $requested = array_values(array_unique(array_filter(array_map(
            static fn (mixed $table): string => is_string($table) ? trim($table) : '',
            $tables
        ), static fn (string $table): bool => $table !== '')));

        if ($requested === []) {
            return $available;
        }

        $invalid = array_values(array_filter(
            $requested,
            static fn (string $table): bool => ! isset($availableLookup[$table])
        ));

        if ($invalid !== []) {
            throw new InvalidArgumentException('存在无效数据表：'.implode(', ', $invalid));
        }

        return $requested;
    }

    /**
     * @return array{remaining_seconds: int, last_optimized_at: ?string}
     */
    private function optimizationCooldown(): array
    {
        $completedAt = (int) Cache::get(self::OPTIMIZE_COOLDOWN_KEY, 0);
        if ($completedAt <= 0) {
            return ['remaining_seconds' => 0, 'last_optimized_at' => null];
        }

        $remainingSeconds = max(0, self::OPTIMIZE_COOLDOWN_SECONDS - (now()->timestamp - $completedAt));

        return [
            'remaining_seconds' => $remainingSeconds,
            'last_optimized_at' => now()->setTimestamp($completedAt)->toDateTimeString(),
        ];
    }

    private function backupDirectory(): string
    {
        return storage_path('app/private/'.self::BACKUP_SUBDIR);
    }

    /**
     * 备份文件名形态：backup_YYYYmmddHHMMSS_六位随机后缀.sql。
     *
     * 任务 ID 与文件名都来自服务端，下载接口只用它做最后一次形态校验，
     * 保证拼进 storage 路径的永远是这份受控产物。
     */
    public static function isBackupFilename(string $filename): bool
    {
        return preg_match('/^backup_\d{14}_[a-z0-9]{6}\.sql$/', $filename) === 1;
    }

    /**
     * 按文件名解析备份文件的绝对路径，供下载接口使用。
     *
     * 只接受服务端生成的文件名，绝不接受客户端传入的路径片段。
     */
    public function backupPath(string $filename): string
    {
        return $this->backupDirectory().DIRECTORY_SEPARATOR.basename($filename);
    }

    /**
     * mysqldump 的 --ignore-table 选项：每张排除日志表一个参数，
     * 供测试以单元级断言验证参数构造，无需真实执行备份。
     *
     * @return list<string>
     */
    private function backupIgnoreTableOptions(string $database): array
    {
        return array_map(
            static fn (string $table): string => '--ignore-table='.$database.'.'.$table,
            self::BACKUP_EXCLUDED_LOG_TABLES
        );
    }

    /**
     * 解析 mysqldump 路径。
     *
     * 候选路径与探测逻辑统一收敛在 App\Support\MysqlDumpBinary，
     * 避免管理端下载备份与升级前备份各自维护一份而出现「一处能备份、另一处找不到」。
     */
    private function resolveMysqldumpBinary(): string
    {
        return MysqlDumpBinary::resolve();
    }

    private function pruneOldBackups(string $directory): void
    {
        $files = collect(File::files($directory))
            ->filter(static fn ($file): bool => str_ends_with(strtolower($file->getFilename()), '.sql'))
            ->sortByDesc(static fn ($file): int => $file->getMTime())
            ->values();

        foreach ($files->slice(self::MAX_BACKUP_RETENTION) as $file) {
            File::delete($file->getPathname());
        }

        $this->pruneStalePartials($directory);
    }

    /**
     * 清理超龄的 .part 半成品。
     *
     * 进程被强杀（OOM、部署重启）时无法走到 catch 分支，.part 会留在目录里；
     * 保留一小时内的文件，避免误删正在导出的产物。
     */
    private function pruneStalePartials(string $directory): void
    {
        $expiredBefore = now()->getTimestamp() - self::STALE_PARTIAL_SECONDS;

        foreach (File::files($directory) as $file) {
            if (! str_ends_with(strtolower($file->getFilename()), '.sql.part')) {
                continue;
            }

            if ($file->getMTime() < $expiredBefore) {
                File::delete($file->getPathname());
            }
        }
    }

    private function removeBackupFile(string $path): void
    {
        if (File::exists($path)) {
            File::delete($path);
        }
    }
}
