<?php

declare(strict_types=1);

namespace App\Services\System;

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
     *     total_size_mb: float
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

        return [
            'database' => (string) DB::getDatabaseName(),
            'list' => $tables,
            'total_count' => count($tables),
            'total_rows' => $totalRows,
            'total_size_mb' => round($totalSizeMb, 2),
        ];
    }

    /**
     * @param  list<string>  $tables
     * @return array{id: string, status: string, message: string, detail: array<string, mixed>}
     */
    public function optimize(array $tables = [], ?int $actorId = null, ?string $ipAddress = null): array
    {
        $selected = $this->resolveBaseTables($tables);
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
            'completed' => sprintf('已优化 %d 张数据表', count($optimized)),
            'partial' => sprintf('已优化 %d 张表，%d 张失败', count($optimized), count($failed)),
            default => '数据表优化失败',
        };

        $this->operationLogs->write(
            userId: $actorId,
            userType: 'admin',
            action: 'database.optimize',
            module: 'database',
            targetId: null,
            detail: [
                'status' => $status,
                'requested_tables' => array_values($tables),
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
                'optimized_tables' => $optimized,
                'failed_tables' => $failed,
            ],
        ];
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
            '--result-file='.$absolutePath,
            $database,
        ];

        $result = Process::timeout(600)
            ->env([
                'MYSQL_PWD' => $password,
            ])
            ->run($command);

        if (! $result->successful() || ! File::exists($absolutePath) || File::size($absolutePath) <= 0) {
            if (File::exists($absolutePath)) {
                File::delete($absolutePath);
            }

            $error = trim($result->errorOutput() ?: $result->output());
            throw new RuntimeException($error !== '' ? $error : '导出数据库备份失败');
        }

        $this->pruneOldBackups($directory);

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

    private function backupDirectory(): string
    {
        return storage_path('app/private/'.self::BACKUP_SUBDIR);
    }

    private function resolveMysqldumpBinary(): string
    {
        $candidates = array_values(array_filter([
            env('MYSQLDUMP_PATH'),
            'D:\\BtSoft\\mysql\\MySQL8.0\\bin\\mysqldump.exe',
            'D:\\BtSoft\\mysql\\MySQL5.7\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
            'mysqldump',
        ], static fn (mixed $path): bool => is_string($path) && trim($path) !== ''));

        foreach ($candidates as $candidate) {
            $path = trim($candidate);
            if ($path === 'mysqldump') {
                return $path;
            }

            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException('未找到 mysqldump，请配置 MYSQLDUMP_PATH 或安装 MySQL 客户端');
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
    }
}
