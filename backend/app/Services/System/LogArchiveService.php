<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\ArchiveAuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class LogArchiveService
{
    /**
     * Ordinary operational logs only. Financial audit tables such as
     * payments/account_transactions/payment_callbacks are intentionally absent.
     */
    private const POLICIES = [
        'operation_logs' => [
            'date_column' => 'created_at',
            'retain_days' => 90,
            'description' => 'API/后台操作日志',
        ],
        'message_logs' => [
            'date_column' => 'created_at',
            'retain_days' => 180,
            'description' => '短信/邮件统一消息日志',
        ],
        'automation_logs' => [
            'date_column' => 'created_at',
            'retain_days' => 180,
            'description' => '自动化任务业务日志',
        ],
        'schedule_run_logs' => [
            'date_column' => 'created_at',
            'retain_days' => 30,
            'description' => '调度任务运行日志',
        ],
        'integration_plugin_runtime_logs' => [
            'date_column' => 'created_at',
            'retain_days' => 180,
            'description' => '插件运行审计日志',
        ],
    ];

    private const EXCLUDED_AUDIT_TABLES = [
        'account_transactions',
        'payments',
        'payment_callbacks',
        'invoices',
        'invoice_items',
        'gateway_logs',
        'activity_logs',
    ];

    /**
     * @param  array{tables?: list<string>, retain_days?: int|null, chunk?: int|null, base_path?: string|null}  $options
     * @return array<string, mixed>
     */
    public function dryRun(array $options = []): array
    {
        $manifest = $this->buildManifest('dry_run', $options);
        $manifest['report_path'] = $this->writeManifest($manifest, $options['base_path'] ?? null, 'dry-run');

        return $manifest;
    }

    /**
     * @param  array{tables?: list<string>, retain_days?: int|null, chunk?: int|null, base_path?: string|null}  $options
     * @return array<string, mixed>
     */
    public function archive(array $options = []): array
    {
        $manifest = $this->buildManifest('archive', $options);
        $runDirectory = $this->makeRunDirectory(
            $options['base_path'] ?? null,
            'archive',
            (string) $manifest['batch_id'],
        );
        $chunkSize = max(1, (int) ($options['chunk'] ?? 1000));
        $manifest['manifest_path'] = $runDirectory.DIRECTORY_SEPARATOR.'manifest.json';
        $this->writeManifestToPath($manifest, (string) $manifest['manifest_path']);

        foreach ($manifest['tables'] as $table => &$tableReport) {
            if (! $tableReport['exists'] || $tableReport['eligible_rows'] <= 0) {
                $tableReport['status'] = 'skipped';

                continue;
            }

            $dateColumn = (string) $tableReport['date_column'];
            $cutoff = (string) $tableReport['cutoff'];
            $auditLog = $this->createAuditLog(
                batchId: (string) $manifest['batch_id'],
                table: $table,
                mode: 'archive',
            );

            try {
                $export = $this->exportTable(
                    table: $table,
                    dateColumn: $dateColumn,
                    cutoff: $cutoff,
                    chunkSize: $chunkSize,
                    runDirectory: $runDirectory,
                );

                $tableReport = array_merge($tableReport, $export, [
                    'status' => 'exported',
                    'error_message' => null,
                ]);
                $manifest['totals']['exported_rows'] += (int) $export['exported_rows'];
                $this->writeManifestToPath($manifest, (string) $manifest['manifest_path']);
                $this->finishAuditLog($auditLog, [
                    'row_count' => (int) $export['exported_rows'],
                    'file_path' => (string) $export['archive_file'],
                    'file_size' => (int) $export['file_size'],
                    'checksum_sha256' => (string) $export['checksum_sha256'],
                    'status' => 'exported',
                ]);

                $deleted = $this->deleteExportedRows(
                    table: $table,
                    dateColumn: $dateColumn,
                    cutoff: $cutoff,
                    maxId: (int) $export['max_id'],
                    chunkSize: $chunkSize,
                );

                $tableReport['deleted_rows'] = $deleted;
                $tableReport['status'] = 'completed';
                $manifest['totals']['deleted_rows'] += $deleted;
                $this->writeManifestToPath($manifest, (string) $manifest['manifest_path']);
                $this->finishAuditLog($auditLog, [
                    'row_count' => (int) $export['exported_rows'],
                    'file_path' => (string) $export['archive_file'],
                    'file_size' => (int) $export['file_size'],
                    'checksum_sha256' => (string) $export['checksum_sha256'],
                    'status' => 'completed',
                ]);
            } catch (Throwable $exception) {
                $tableReport['status'] = 'failed';
                $tableReport['error_message'] = $exception->getMessage();
                $this->writeManifestToPath($manifest, (string) $manifest['manifest_path']);
                $this->finishAuditLog($auditLog, [
                    'status' => 'failed',
                    'error_message' => mb_substr($exception->getMessage(), 0, 500),
                ]);

                throw $exception;
            }
        }
        unset($tableReport);

        $this->writeManifestToPath($manifest, (string) $manifest['manifest_path']);

        return $manifest;
    }

    /**
     * @return array<string, mixed>
     */
    public function restore(string $manifestPath, int $chunkSize = 1000): array
    {
        if (! is_file($manifestPath)) {
            throw new InvalidArgumentException("Manifest file not found: {$manifestPath}");
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest) || ($manifest['mode'] ?? '') !== 'archive') {
            throw new InvalidArgumentException('Only archive manifests can be restored.');
        }

        $chunkSize = max(1, $chunkSize);
        $summary = [
            'manifest_path' => $manifestPath,
            'restored_at' => now()->toISOString(),
            'tables' => [],
        ];

        foreach ((array) ($manifest['tables'] ?? []) as $table => $tableReport) {
            $this->resolvePolicies([$table]);

            $archiveFile = (string) ($tableReport['archive_file'] ?? '');
            $auditLog = $this->createAuditLog(
                batchId: (string) ($manifest['batch_id'] ?? Str::uuid()->toString()),
                table: $table,
                mode: 'restore',
            );
            if ($archiveFile === '' || ! is_file($archiveFile)) {
                $summary['tables'][$table] = [
                    'archive_file' => $archiveFile,
                    'restored_rows' => 0,
                    'status' => 'missing_archive_file',
                ];
                $this->finishAuditLog($auditLog, [
                    'status' => 'failed',
                    'error_message' => 'Archive file is missing.',
                ]);

                continue;
            }

            $expectedChecksum = trim((string) ($tableReport['checksum_sha256'] ?? ''));
            $expectedFileSize = $tableReport['file_size'] ?? null;
            $actualFileSize = filesize($archiveFile);
            $actualChecksum = hash_file('sha256', $archiveFile);

            if (
                $expectedChecksum === ''
                || $actualChecksum === false
                || ! hash_equals($expectedChecksum, $actualChecksum)
                || ($expectedFileSize !== null && (int) $expectedFileSize !== $actualFileSize)
            ) {
                $summary['tables'][$table] = [
                    'archive_file' => $archiveFile,
                    'restored_rows' => 0,
                    'status' => 'integrity_check_failed',
                ];
                $this->finishAuditLog($auditLog, [
                    'file_path' => $archiveFile,
                    'file_size' => $actualFileSize === false ? null : $actualFileSize,
                    'checksum_sha256' => $actualChecksum === false ? null : $actualChecksum,
                    'status' => 'failed',
                    'error_message' => 'Archive file checksum or size validation failed.',
                ]);

                continue;
            }

            try {
                $restored = 0;
                $buffer = [];
                $handle = fopen($archiveFile, 'rb');
                if ($handle === false) {
                    throw new RuntimeException("Unable to open archive file: {$archiveFile}");
                }

                try {
                    while (($line = fgets($handle)) !== false) {
                        $row = json_decode($line, true);
                        if (! is_array($row)) {
                            continue;
                        }

                        $buffer[] = $row;
                        if (count($buffer) >= $chunkSize) {
                            $restored += $this->insertRows($table, $buffer);
                            $buffer = [];
                        }
                    }

                    if ($buffer !== []) {
                        $restored += $this->insertRows($table, $buffer);
                    }
                } finally {
                    fclose($handle);
                }

                $summary['tables'][$table] = [
                    'archive_file' => $archiveFile,
                    'restored_rows' => $restored,
                    'status' => 'restored',
                ];
                $this->finishAuditLog($auditLog, [
                    'row_count' => $restored,
                    'file_path' => $archiveFile,
                    'file_size' => $actualFileSize,
                    'checksum_sha256' => $actualChecksum,
                    'status' => 'completed',
                ]);
            } catch (Throwable $exception) {
                $this->finishAuditLog($auditLog, [
                    'file_path' => $archiveFile,
                    'file_size' => $actualFileSize,
                    'checksum_sha256' => $actualChecksum,
                    'status' => 'failed',
                    'error_message' => mb_substr($exception->getMessage(), 0, 500),
                ]);

                throw $exception;
            }
        }

        return $summary;
    }

    /**
     * @param  array{tables?: list<string>, retain_days?: int|null, chunk?: int|null, base_path?: string|null}  $options
     * @return array<string, mixed>
     */
    private function buildManifest(string $mode, array $options): array
    {
        $policies = $this->resolvePolicies($options['tables'] ?? []);
        $retainOverride = isset($options['retain_days']) ? (int) $options['retain_days'] : null;
        $now = CarbonImmutable::now();
        $tables = [];

        foreach ($policies as $table => $policy) {
            $dateColumn = (string) $policy['date_column'];
            $retainDays = $retainOverride !== null ? max(1, $retainOverride) : (int) $policy['retain_days'];
            $cutoff = $now->subDays($retainDays);
            $exists = Schema::hasTable($table) && Schema::hasColumn($table, $dateColumn);

            $tables[$table] = [
                'description' => $policy['description'],
                'exists' => $exists,
                'date_column' => $dateColumn,
                'retain_days' => $retainDays,
                'cutoff' => $cutoff->toDateTimeString(),
                'total_rows' => $exists ? (int) DB::table($table)->count() : 0,
                'eligible_rows' => $exists ? (int) DB::table($table)->where($dateColumn, '<', $cutoff)->count() : 0,
                'oldest_at' => $exists ? DB::table($table)->min($dateColumn) : null,
                'newest_at' => $exists ? DB::table($table)->max($dateColumn) : null,
                'archive_file' => null,
                'file_size' => null,
                'checksum_sha256' => null,
                'exported_rows' => 0,
                'deleted_rows' => 0,
                'max_id' => null,
                'status' => 'pending',
                'error_message' => null,
            ];
        }

        return [
            'batch_id' => Str::uuid()->toString(),
            'mode' => $mode,
            'generated_at' => $now->toISOString(),
            'database' => DB::getDatabaseName(),
            'chunk' => max(1, (int) ($options['chunk'] ?? 1000)),
            'ordinary_log_tables' => array_keys($policies),
            'excluded_audit_tables' => self::EXCLUDED_AUDIT_TABLES,
            'tables' => $tables,
            'totals' => [
                'eligible_rows' => array_sum(array_column($tables, 'eligible_rows')),
                'exported_rows' => 0,
                'deleted_rows' => 0,
            ],
        ];
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, array<string, mixed>>
     */
    private function resolvePolicies(array $tables): array
    {
        if ($tables === []) {
            return self::POLICIES;
        }

        $resolved = [];
        foreach ($tables as $table) {
            $table = trim((string) $table);
            if ($table === '') {
                continue;
            }

            if (in_array($table, self::EXCLUDED_AUDIT_TABLES, true)) {
                throw new InvalidArgumentException("{$table} is an audit/financial table and is not part of ordinary log archiving.");
            }

            if (! array_key_exists($table, self::POLICIES)) {
                throw new InvalidArgumentException("Unsupported log archive table: {$table}");
            }

            $resolved[$table] = self::POLICIES[$table];
        }

        return $resolved;
    }

    private function writeManifest(array $manifest, ?string $basePath, string $prefix): string
    {
        $directory = $this->makeRunDirectory($basePath, $prefix, (string) $manifest['batch_id']);
        $path = $directory.DIRECTORY_SEPARATOR.'manifest.json';
        $this->writeManifestToPath($manifest, $path);

        return $path;
    }

    private function writeManifestToPath(array $manifest, string $path): void
    {
        $content = json_encode(
            $manifest,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
        ).PHP_EOL;

        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException("Unable to write archive manifest: {$path}");
        }
    }

    private function makeRunDirectory(?string $basePath, string $prefix, string $batchId): string
    {
        $basePath = $basePath ?: storage_path('app/private/log-archives');
        $directory = rtrim($basePath, DIRECTORY_SEPARATOR.'/\\')
            .DIRECTORY_SEPARATOR.now()->format('Y-m')
            .DIRECTORY_SEPARATOR.$prefix.'_'.now()->format('Ymd_His').'_'.substr(str_replace('-', '', $batchId), 0, 8);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory;
    }

    /**
     * @return array{archive_file: string, file_size: int, checksum_sha256: string, exported_rows: int, deleted_rows: int, max_id: int}
     */
    private function exportTable(
        string $table,
        string $dateColumn,
        string $cutoff,
        int $chunkSize,
        string $runDirectory,
    ): array {
        $archiveFile = $runDirectory.DIRECTORY_SEPARATOR.$table.'.jsonl';
        $temporaryFile = $archiveFile.'.part';
        $handle = fopen($temporaryFile, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open archive file: {$temporaryFile}");
        }

        $checksum = hash_init('sha256');
        $exported = 0;
        $maxId = 0;

        try {
            try {
                DB::table($table)
                    ->where($dateColumn, '<', $cutoff)
                    ->orderBy('id')
                    ->chunkById($chunkSize, function ($rows) use ($handle, $checksum, &$exported, &$maxId): void {
                        foreach ($rows as $row) {
                            $payload = (array) $row;
                            $line = json_encode(
                                $payload,
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                            ).PHP_EOL;
                            $this->writeArchiveLine($handle, $line);
                            hash_update($checksum, $line);
                            $exported++;
                            $maxId = max($maxId, (int) $payload['id']);
                        }
                    });

                if (! fflush($handle)) {
                    throw new RuntimeException("Unable to flush archive file: {$temporaryFile}");
                }
                if (function_exists('fsync') && ! fsync($handle)) {
                    throw new RuntimeException("Unable to sync archive file: {$temporaryFile}");
                }
            } finally {
                fclose($handle);
            }
        } catch (Throwable $exception) {
            @unlink($temporaryFile);

            throw $exception;
        }

        if (! rename($temporaryFile, $archiveFile)) {
            @unlink($temporaryFile);

            throw new RuntimeException("Unable to finalize archive file: {$archiveFile}");
        }

        $fileSize = filesize($archiveFile);
        if ($fileSize === false) {
            throw new RuntimeException("Unable to inspect archive file: {$archiveFile}");
        }

        return [
            'archive_file' => $archiveFile,
            'file_size' => $fileSize,
            'checksum_sha256' => hash_final($checksum),
            'exported_rows' => $exported,
            'deleted_rows' => 0,
            'max_id' => $maxId,
        ];
    }

    private function writeArchiveLine(mixed $handle, string $line): void
    {
        $remaining = $line;

        while ($remaining !== '') {
            $written = fwrite($handle, $remaining);
            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to write archive data.');
            }

            $remaining = substr($remaining, $written);
        }
    }

    private function deleteExportedRows(
        string $table,
        string $dateColumn,
        string $cutoff,
        int $maxId,
        int $chunkSize,
    ): int {
        $deleted = 0;

        do {
            $ids = DB::table($table)
                ->where($dateColumn, '<', $cutoff)
                ->where('id', '<=', $maxId)
                ->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id');

            $count = $ids->count();
            if ($count === 0) {
                break;
            }

            $deleted += DB::table($table)->whereIn('id', $ids->all())->delete();
        } while ($count === $chunkSize);

        return $deleted;
    }

    private function createAuditLog(string $batchId, string $table, string $mode): ArchiveAuditLog
    {
        return ArchiveAuditLog::query()->create([
            'batch_id' => $batchId,
            'table_name' => $table,
            'mode' => $mode,
            'row_count' => 0,
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function finishAuditLog(ArchiveAuditLog $auditLog, array $attributes): void
    {
        $auditLog->forceFill(array_merge($attributes, [
            'finished_at' => now(),
        ]))->save();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertRows(string $table, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        return (int) DB::table($table)->insertOrIgnore($rows);
    }
}
