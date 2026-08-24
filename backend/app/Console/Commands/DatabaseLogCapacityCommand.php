<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * 只读容量基线采集：每张表行数/数据/索引字节、日志表合计、storage 日志目录与归档目录体积、磁盘可用空间。
 * 用于生产 P0 只读盘点和归档/空间回收前后对比，不执行任何写入。
 */
class DatabaseLogCapacityCommand extends Command
{
    protected $signature = 'db:logs:capacity {--json : 以 JSON 输出容量基线}';

    protected $description = '只读输出数据库日志表容量与日志目录体积基线';

    public function handle(): int
    {
        try {
            $database = (string) DB::getDatabaseName();
            $tables = $this->tableSizes($database);

            $logTables = array_keys((array) config('log_archive.tables', []));
            $excludedLogTables = array_values((array) config('log_archive.excluded_tables', []));
            $logTableBytes = 0;
            $logTableStats = [];

            foreach (array_unique(array_merge($logTables, $excludedLogTables)) as $table) {
                if (! isset($tables[$table])) {
                    continue;
                }
                $bytes = (int) $tables[$table]['data_bytes'] + (int) $tables[$table]['index_bytes'];
                $logTableBytes += $bytes;
                $logTableStats[$table] = $tables[$table] + ['total_bytes' => $bytes];
            }

            $archiveRoot = rtrim((string) config('log_archive.archive_root', ''), DIRECTORY_SEPARATOR.'/\\');
            $storageLogs = $this->directorySize(storage_path('logs'));
            $archiveDir = $archiveRoot !== '' && is_dir($archiveRoot) ? $this->directorySize($archiveRoot) : ['bytes' => 0, 'files' => 0];
            $disk = [
                'free_bytes' => @disk_free_space(storage_path()) ?: 0,
                'total_bytes' => @disk_total_space(storage_path()) ?: 0,
            ];

            $payload = [
                'generated_at' => now()->toISOString(),
                'database' => $database,
                'tables' => $tables,
                'log_tables' => $logTableStats,
                'summary' => [
                    'log_tables_total_bytes' => $logTableBytes,
                    'database_total_bytes' => array_sum(array_map(
                        static fn (array $t): int => (int) $t['data_bytes'] + (int) $t['index_bytes'],
                        $tables,
                    )),
                ],
                'storage_logs' => $storageLogs,
                'log_archives' => $archiveDir,
                'disk' => $disk,
            ];

            if ((bool) $this->option('json')) {
                $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            } else {
                $this->line("数据库: {$database}");
                $this->line('日志表合计: '.$payload['summary']['log_tables_total_bytes'].' bytes / 库总计: '.$payload['summary']['database_total_bytes'].' bytes');

                foreach ($logTableStats as $table => $stat) {
                    $this->line(sprintf('- %s: rows=%d data=%d index=%d', $table, (int) $stat['rows'], (int) $stat['data_bytes'], (int) $stat['index_bytes']));
                }

                $this->line('storage/logs: '.$storageLogs['bytes'].' bytes / '.$storageLogs['files'].' 文件');
                $this->line('log-archives: '.$archiveDir['bytes'].' bytes / '.$archiveDir['files'].' 文件');
                $this->line('磁盘可用: '.$disk['free_bytes'].' / '.$disk['total_bytes'].' bytes');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ((bool) $this->option('json')) {
                $this->line(json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } else {
                $this->error($exception->getMessage());
            }

            return self::FAILURE;
        }
    }

    /** @return array<string, array{rows: int, data_bytes: int, index_bytes: int}> */
    private function tableSizes(string $database): array
    {
        $rows = DB::select(
            'SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$database],
        );
        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row->TABLE_NAME] = [
                'rows' => max(0, (int) $row->TABLE_ROWS),
                'data_bytes' => max(0, (int) $row->DATA_LENGTH),
                'index_bytes' => max(0, (int) $row->INDEX_LENGTH),
            ];
        }

        return $result;
    }

    /** @return array{bytes: int, files: int} */
    private function directorySize(string $directory): array
    {
        if (! is_dir($directory)) {
            return ['bytes' => 0, 'files' => 0];
        }

        $bytes = 0;
        $files = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->isLink()) {
                continue;
            }
            $bytes += max(0, (int) $file->getSize());
            $files++;
        }

        return ['bytes' => $bytes, 'files' => $files];
    }
}
