<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseEngineeringCommandTest extends TestCase
{
    public function test_db_audit_core_supports_json_output(): void
    {
        Artisan::call('db:audit-core', ['--json' => true]);

        $output = Artisan::output();

        $this->assertJson($output);
        $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('table_count', $payload);
        $this->assertArrayHasKey('zero_reference_metrics', $payload);
        $this->assertArrayHasKey('orphan_metrics', $payload);
        $this->assertArrayHasKey('trace_id_metrics', $payload);
    }

    public function test_db_audit_foreign_keys_classifies_all_id_columns(): void
    {
        $exitCode = Artisan::call('db:audit-foreign-keys', [
            '--json' => true,
            '--strict' => true,
        ]);

        $output = Artisan::output();
        $this->assertJson($output);

        $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, (int) ($payload['counts']['unclassified'] ?? -1));

        foreach ((array) ($payload['groups']['candidate_fk'] ?? []) as $candidate) {
            $this->assertSame(
                0,
                (int) ($candidate['orphan_count'] ?? -1),
                sprintf('%s.%s has orphan rows', $candidate['table_name'] ?? '', $candidate['column_name'] ?? '')
            );
        }
    }

    public function test_db_normalize_core_relations_rewrites_zero_order_id_to_null(): void
    {
        $userId = $this->ensureUserId();
        $productId = $this->ensureProductId();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $serviceId = (int) DB::table('services')->insertGetId([
                'user_id' => $userId,
                'product_id' => $productId,
                'order_id' => 0,
                'invoice_id' => null,
                'name' => 'db-normalize-test',
                'domain' => '',
                'billing_cycle' => 'monthly',
                'amount' => '1.00',
                'status' => 0,
                'auto_renew' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        Artisan::call('db:normalize-core-relations');

        $this->assertDatabaseHas('services', [
            'id' => $serviceId,
            'order_id' => null,
        ]);
    }

    public function test_db_audit_core_reports_orphan_invoice_order_id(): void
    {
        $userId = $this->ensureUserId();
        $productId = $this->ensureProductId();
        $invoiceNo = 'audit-orphan-'.bin2hex(random_bytes(4));

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::table('invoices')->insert([
                'invoice_no' => $invoiceNo,
                'user_id' => $userId,
                'product_id' => $productId,
                'order_id' => 999999999,
                'type' => 'normal',
                'amount' => '1.00',
                'discount' => '0.00',
                'paid_amount' => '0.00',
                'status' => 0,
                'due_date' => now()->addDay()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        try {
            Artisan::call('db:audit-core', ['--json' => true]);

            $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

            $this->assertGreaterThanOrEqual(
                1,
                (int) ($payload['orphan_metrics']['invoices.order_id->orders.id'] ?? 0)
            );
        } finally {
            DB::table('invoices')->where('invoice_no', $invoiceNo)->delete();
        }
    }

    public function test_db_normalize_core_relations_clears_orphan_invoice_order_id(): void
    {
        $userId = $this->ensureUserId();
        $productId = $this->ensureProductId();
        $invoiceNo = 'norm-orphan-'.bin2hex(random_bytes(4));

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $invoiceId = (int) DB::table('invoices')->insertGetId([
                'invoice_no' => $invoiceNo,
                'user_id' => $userId,
                'product_id' => $productId,
                'order_id' => 999999998,
                'type' => 'normal',
                'amount' => '1.00',
                'discount' => '0.00',
                'paid_amount' => '0.00',
                'status' => 0,
                'due_date' => now()->addDay()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        try {
            Artisan::call('db:normalize-core-relations', ['--json' => true]);

            $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

            $this->assertGreaterThanOrEqual(1, (int) ($payload['invoices_cleared_orphan_order_id'] ?? 0));
            $this->assertDatabaseHas('invoices', [
                'id' => $invoiceId,
                'order_id' => null,
            ]);
        } finally {
            DB::table('invoices')->where('id', $invoiceId)->delete();
        }
    }

    public function test_db_archive_logs_dry_run_outputs_summary(): void
    {
        Artisan::call('db:archive-logs', [
            '--dry-run' => true,
            '--json' => true,
            '--retain-days' => 30,
        ]);

        $output = Artisan::output();
        $this->assertJson($output);

        $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('dry_run', (string) ($payload['mode'] ?? ''));
        $this->assertArrayHasKey('totals', $payload);
    }

    public function test_db_archive_logs_includes_runtime_logs_and_excludes_financial_audit_tables(): void
    {
        Artisan::call('db:archive-logs', [
            '--dry-run' => true,
            '--json' => true,
            '--retain-days' => 30,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $ordinaryTables = (array) ($payload['ordinary_log_tables'] ?? []);
        $excludedTables = (array) ($payload['excluded_audit_tables'] ?? []);

        $this->assertContains('schedule_run_logs', $ordinaryTables);
        $this->assertContains('integration_plugin_runtime_logs', $ordinaryTables);

        foreach ([
            'payments',
            'payment_callbacks',
            'gateway_logs',
            'invoices',
            'invoice_items',
            'account_transactions',
        ] as $table) {
            $this->assertNotContains($table, $ordinaryTables);
            $this->assertContains($table, $excludedTables);
        }
    }

    public function test_db_archive_logs_rejects_financial_audit_table_selection(): void
    {
        $exitCode = Artisan::call('db:archive-logs', [
            '--dry-run' => true,
            '--json' => true,
            '--table' => ['payments'],
        ]);

        $this->assertSame(1, $exitCode);
        $output = Artisan::output();
        $this->assertJson($output);

        $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('audit/financial table', (string) ($payload['error'] ?? ''));
    }

    public function test_db_archive_logs_execute_writes_audited_archive_and_restores_it(): void
    {
        $archivePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'caiwu-log-archive-'.bin2hex(random_bytes(8));
        $logId = null;
        $batchId = null;

        try {
            $logId = (int) DB::table('operation_logs')->insertGetId([
                'action' => 'test.log_archive',
                'created_at' => now()->subDays(10001),
            ]);

            $exitCode = Artisan::call('db:archive-logs', [
                '--execute' => true,
                '--json' => true,
                '--table' => ['operation_logs'],
                '--retain-days' => 10000,
                '--path' => $archivePath,
            ]);

            $this->assertSame(0, $exitCode);
            $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
            $batchId = (string) ($payload['batch_id'] ?? '');
            $table = (array) ($payload['tables']['operation_logs'] ?? []);
            $manifestPath = (string) ($payload['manifest_path'] ?? '');
            $archiveFile = (string) ($table['archive_file'] ?? '');

            $this->assertNotSame('', $batchId);
            $this->assertSame('completed', $table['status'] ?? null);
            $this->assertSame(1, (int) ($table['exported_rows'] ?? 0));
            $this->assertSame(1, (int) ($table['deleted_rows'] ?? 0));
            $this->assertFileExists($manifestPath);
            $this->assertFileExists($archiveFile);
            $this->assertSame(hash_file('sha256', $archiveFile), $table['checksum_sha256'] ?? null);
            $this->assertDatabaseMissing('operation_logs', ['id' => $logId]);
            $this->assertDatabaseHas('archive_audit_logs', [
                'batch_id' => $batchId,
                'table_name' => 'operation_logs',
                'mode' => 'archive',
                'row_count' => 1,
                'status' => 'completed',
            ]);

            $restoreExitCode = Artisan::call('db:archive-logs', [
                '--restore' => $manifestPath,
                '--json' => true,
            ]);

            $this->assertSame(0, $restoreExitCode);
            $this->assertDatabaseHas('operation_logs', ['id' => $logId]);
            $this->assertDatabaseHas('archive_audit_logs', [
                'batch_id' => $batchId,
                'table_name' => 'operation_logs',
                'mode' => 'restore',
                'row_count' => 1,
                'status' => 'completed',
            ]);

            DB::table('operation_logs')->where('id', $logId)->delete();
            file_put_contents($archiveFile, PHP_EOL, FILE_APPEND);

            $invalidRestoreExitCode = Artisan::call('db:archive-logs', [
                '--restore' => $manifestPath,
                '--json' => true,
            ]);

            $this->assertSame(1, $invalidRestoreExitCode);
            $this->assertDatabaseMissing('operation_logs', ['id' => $logId]);
            $this->assertDatabaseHas('archive_audit_logs', [
                'batch_id' => $batchId,
                'table_name' => 'operation_logs',
                'mode' => 'restore',
                'status' => 'failed',
            ]);
        } finally {
            if ($logId !== null) {
                DB::table('operation_logs')->where('id', $logId)->delete();
            }
            if ($batchId !== null && $batchId !== '') {
                DB::table('archive_audit_logs')->where('batch_id', $batchId)->delete();
            }
            File::deleteDirectory($archivePath);
        }
    }

    private function ensureUserId(): int
    {
        $id = (int) DB::table('users')->value('id');
        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('users')->insertGetId([
            'email' => 'db-engineering-test@example.com',
            'password' => bcrypt('password'),
            'nickname' => 'DB Engineering Test',
            'phone' => '13900000001',
            'company' => '',
            'qq' => '',
            'alipay_real_name' => '',
            'alipay_account' => '',
            'status' => 1,
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureProductId(): int
    {
        $id = (int) DB::table('products')->value('id');
        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('products')->insertGetId([
            'product_type' => 'db_engineering',
            'custom_display_name' => 'DB Engineering Test Product',
            'pricing' => json_encode(['monthly' => '1.00'], JSON_THROW_ON_ERROR),
            'setup_fee' => '0.00',
            'stock' => -1,
            'status' => 1,
            'sort_order' => 0,
            'auto_setup' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
