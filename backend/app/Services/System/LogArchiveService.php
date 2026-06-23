<?php

declare(strict_types=1);

namespace App\Services\System;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

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
        'notification_logs' => [
            'date_column' => 'created_at',
            'retain_days' => 180,
            'description' => '通知聚合日志',
        ],
        'email_logs' => [
            'date_column' => 'created_at',
            'retain_days' => 180,
            'description' => '历史邮件日志',
        ],
        'sms_logs' => [
            'date_column' => 'created_at',
            'retain_days' => 180,
            'description' => '历史短信日志',
        ],
        'automation_logs' => [
            'date_column' => 'created_at',
            'retain_days' => 180,
            'description' => '自动化任务业务日志',
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
        $runDirectory = $this->makeRunDirectory($options['base_path'] ?? null, 'archive');
        $chunkSize = max(1, (int) ($options['chunk'] ?? 1000));

        foreach ($manifest['tables'] as $table => &$tableReport) {
            if (! $tableReport['exists'] || $tableReport['eligible_rows'] <= 0) {
                continue;
            }

            $archiveFile = $runDirectory.DIRECTORY_SEPARATOR.$table.'.jsonl';
            $handle = fopen($archiveFile, 'wb');

            if ($handle === false) {
                throw new RuntimeException("Unable to open archive file: {$archiveFile}");
            }

            $exported = 0;
            $deleted = 0;
            $dateColumn = (string) $tableReport['date_column'];
            $cutoff = (string) $tableReport['cutoff'];

            try {
                DB::table($table)
                    ->where($dateColumn, '<', $cutoff)
                    ->orderBy('id')
                    ->chunkById($chunkSize, function ($rows) use ($handle, &$exported, &$deleted, $table): void {
                        $ids = [];

                        foreach ($rows as $row) {
                            $payload = (array) $row;
                            fwrite($handle, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
                            $ids[] = $payload['id'];
                            $exported++;
                        }

                        if ($ids !== []) {
                            $deleted += DB::table($table)->whereIn('id', $ids)->delete();
                        }
                    });
            } finally {
                fclose($handle);
            }

            $tableReport['archive_file'] = $archiveFile;
            $tableReport['exported_rows'] = $exported;
            $tableReport['deleted_rows'] = $deleted;
            $manifest['totals']['exported_rows'] += $exported;
            $manifest['totals']['deleted_rows'] += $deleted;
        }
        unset($tableReport);

        $manifest['manifest_path'] = $runDirectory.DIRECTORY_SEPARATOR.'manifest.json';
        file_put_contents(
            $manifest['manifest_path'],
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL
        );

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
            $archiveFile = (string) ($tableReport['archive_file'] ?? '');
            if ($archiveFile === '' || ! is_file($archiveFile)) {
                $summary['tables'][$table] = [
                    'archive_file' => $archiveFile,
                    'restored_rows' => 0,
                    'status' => 'missing_archive_file',
                ];

                continue;
            }

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
                'exported_rows' => 0,
                'deleted_rows' => 0,
            ];
        }

        return [
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
        $directory = $this->makeRunDirectory($basePath, $prefix);
        $path = $directory.DIRECTORY_SEPARATOR.'manifest.json';
        file_put_contents($path, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL);

        return $path;
    }

    private function makeRunDirectory(?string $basePath, string $prefix): string
    {
        $basePath = $basePath ?: storage_path('app/private/log-archives');
        $directory = rtrim($basePath, DIRECTORY_SEPARATOR.'/\\')
            .DIRECTORY_SEPARATOR.now()->format('Y-m')
            .DIRECTORY_SEPARATOR.$prefix.'_'.now()->format('Ymd_His');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory;
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
