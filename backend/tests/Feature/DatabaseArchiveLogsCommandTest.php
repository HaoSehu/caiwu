<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DatabaseArchiveLogsCommandTest extends TestCase
{
    private string $temporaryRoot;

    private string $archiveRoot;

    private string $reportRoot;

    private string $defaultsFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'caiwu-pt-archive-test-'.bin2hex(random_bytes(8));
        $this->archiveRoot = $this->temporaryRoot.DIRECTORY_SEPARATOR.'archives';
        $this->reportRoot = $this->temporaryRoot.DIRECTORY_SEPARATOR.'reports';
        $this->defaultsFile = $this->temporaryRoot.DIRECTORY_SEPARATOR.'pt-archiver.cnf';
        File::ensureDirectoryExists($this->temporaryRoot);
        file_put_contents($this->defaultsFile, "[client]\nhost=127.0.0.1\nuser=test\npassword=test\n");

        config()->set([
            'log_archive.archive_root' => $this->archiveRoot,
            'log_archive.report_root' => $this->reportRoot,
            'log_archive.mount_point' => null,
            'log_archive.pt_archiver_binary' => 'pt-archiver-test',
            'log_archive.pt_archiver_defaults_file' => $this->defaultsFile,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryRoot);

        parent::tearDown();
    }

    public function test_dry_run_covers_eight_log_tables_and_has_no_archive_side_effects(): void
    {
        Process::fake(fn () => Process::result(output: 'pt-archiver test'));

        $exitCode = Artisan::call('db:archive-logs', [
            '--dry-run' => true,
            '--json' => true,
            '--concurrency' => 2,
            '--batch-size' => 500,
            '--sleep-seconds' => 0,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('dry_run', $payload['mode'] ?? null);
        $this->assertSame('completed', $payload['status'] ?? null);
        $this->assertSame(30, (int) ($payload['retention_days'] ?? 0));
        $this->assertSame(8, count((array) ($payload['ordinary_log_tables'] ?? [])));
        $this->assertContains('activity_logs', (array) $payload['ordinary_log_tables']);
        $this->assertContains('gateway_logs', (array) $payload['ordinary_log_tables']);
        $this->assertContains('schedule_task_runs', (array) $payload['ordinary_log_tables']);
        $this->assertDirectoryDoesNotExist($this->archiveRoot);
        $this->assertFileExists((string) $payload['report_path']);
        $this->assertFileExists((string) $payload['execution_log']);

        foreach ((array) $payload['tables'] as $table) {
            $this->assertSame('completed', $table['status'] ?? null);
            $this->assertSame('created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)', $table['where'] ?? null);
            $this->assertSame(0, (int) ($table['deleted_rows'] ?? -1));
        }

        Process::assertRan(function (PendingProcess $process): bool {
            $command = (array) $process->command;

            return in_array('--dry-run', $command, true)
                && in_array('--purge', $command, true)
                && in_array('--output-format=csv', $command, true)
                && in_array('--where=created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)', $command, true);
        });
    }

    public function test_command_rejects_financial_tables_before_starting_pt_archiver(): void
    {
        Process::fake();

        $exitCode = Artisan::call('db:archive-logs', [
            '--dry-run' => true,
            '--json' => true,
            '--table' => ['payments'],
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('audit/financial table', (string) ($payload['error'] ?? ''));
        Process::assertNothingRan();
    }

    public function test_execute_builds_csv_archive_updates_audit_and_cleans_expired_files(): void
    {
        $logId = (int) DB::table('operation_logs')->insertGetId([
            'action' => 'test.pt_archive',
            'created_at' => now()->subDays(31),
        ]);
        $expiredFile = $this->archiveRoot.DIRECTORY_SEPARATOR.'message_logs'.DIRECTORY_SEPARATOR.'message_logs_20250101.log';
        File::ensureDirectoryExists(dirname($expiredFile));
        file_put_contents($expiredFile, "id,created_at\n1,2025-01-01 00:00:00\n");
        touch($expiredFile, now()->subDays(181)->getTimestamp());

        Process::fake(function (PendingProcess $process) use ($logId) {
            $command = (array) $process->command;
            if (in_array('--version', $command, true)) {
                return Process::result(output: 'pt-archiver 3.7.1');
            }

            $fileArgument = collect($command)->first(
                static fn (string $argument): bool => str_starts_with($argument, '--file='),
            );
            $archiveFile = substr((string) $fileArgument, strlen('--file='));
            File::ensureDirectoryExists(dirname($archiveFile));
            file_put_contents($archiveFile, "id,action,created_at\n{$logId},test.pt_archive,2026-01-01 00:00:00\n");

            return Process::result(output: "DELETE 1\n");
        });

        try {
            $exitCode = Artisan::call('db:archive-logs', [
                '--execute' => true,
                '--json' => true,
                '--table' => ['operation_logs'],
                '--path' => $this->archiveRoot,
                '--batch-size' => 500,
                '--sleep-seconds' => 1,
            ]);

            $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
            $table = (array) ($payload['tables']['operation_logs'] ?? []);
            $batchId = (string) ($payload['batch_id'] ?? '');
            $archiveFile = (string) ($table['archive_file'] ?? '');

            $this->assertSame(0, $exitCode);
            $this->assertSame('completed', $payload['status'] ?? null);
            $this->assertSame('completed', $table['status'] ?? null);
            $this->assertSame(1, (int) ($table['exported_rows'] ?? 0));
            $this->assertSame(1, (int) ($table['deleted_rows'] ?? 0));
            $this->assertStringEndsWith(
                'operation_logs'.DIRECTORY_SEPARATOR.'operation_logs_'.now()->format('Ymd').'.log',
                $archiveFile,
            );
            $this->assertFileExists($archiveFile);
            $handle = fopen($archiveFile, 'rb');
            $this->assertNotFalse($handle);
            $header = fgets($handle);
            fclose($handle);
            $this->assertSame('id,action,created_at', trim((string) $header));
            $this->assertSame(hash_file('sha256', $archiveFile), $table['checksum_sha256'] ?? null);
            $this->assertFileDoesNotExist($expiredFile);
            $this->assertSame(1, (int) ($payload['cleanup']['deleted_files'] ?? 0));
            $this->assertDatabaseHas('archive_audit_logs', [
                'batch_id' => $batchId,
                'table_name' => 'operation_logs',
                'mode' => 'archive',
                'row_count' => 1,
                'status' => 'completed',
            ]);

            Process::assertRan(function (PendingProcess $process): bool {
                $command = (array) $process->command;

                return in_array('--purge', $command, true)
                    && in_array('--commit-each', $command, true)
                    && in_array('--limit=500', $command, true)
                    && in_array('--sleep=1', $command, true)
                    && ! in_array('--dry-run', $command, true);
            });
        } finally {
            DB::table('operation_logs')->where('id', $logId)->delete();
            if (isset($batchId) && $batchId !== '') {
                DB::table('archive_audit_logs')->where('batch_id', $batchId)->delete();
            }
        }
    }

    public function test_execute_records_failed_table_and_returns_failure(): void
    {
        Process::fake(function (PendingProcess $process) {
            return in_array('--version', (array) $process->command, true)
                ? Process::result(output: 'pt-archiver 3.7.1')
                : Process::result(errorOutput: 'simulated archiver failure', exitCode: 2);
        });

        $exitCode = Artisan::call('db:archive-logs', [
            '--execute' => true,
            '--json' => true,
            '--table' => ['gateway_logs'],
            '--path' => $this->archiveRoot,
        ]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $batchId = (string) ($payload['batch_id'] ?? '');

        try {
            $this->assertSame(1, $exitCode);
            $this->assertSame('failed', $payload['status'] ?? null);
            $this->assertSame('failed', $payload['tables']['gateway_logs']['status'] ?? null);
            $this->assertStringContainsString(
                'simulated archiver failure',
                (string) ($payload['tables']['gateway_logs']['error_message'] ?? ''),
            );
            $this->assertDatabaseHas('archive_audit_logs', [
                'batch_id' => $batchId,
                'table_name' => 'gateway_logs',
                'status' => 'failed',
            ]);
        } finally {
            if ($batchId !== '') {
                DB::table('archive_audit_logs')->where('batch_id', $batchId)->delete();
            }
        }
    }
}
