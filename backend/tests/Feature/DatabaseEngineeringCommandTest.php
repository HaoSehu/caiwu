<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

    public function test_db_normalize_core_relations_rewrites_zero_order_id_to_null(): void
    {
        $userId = (int) DB::table('users')->value('id');
        $productId = (int) DB::table('products')->value('id');

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

        Artisan::call('db:normalize-core-relations');

        $this->assertDatabaseHas('services', [
            'id' => $serviceId,
            'order_id' => null,
        ]);
    }

    public function test_db_audit_core_reports_orphan_invoice_order_id(): void
    {
        $userId = (int) DB::table('users')->value('id');
        $invoiceNo = 'audit-orphan-'.bin2hex(random_bytes(4));

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::table('invoices')->insert([
                'invoice_no' => $invoiceNo,
                'user_id' => $userId,
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
        $userId = (int) DB::table('users')->value('id');
        $invoiceNo = 'norm-orphan-'.bin2hex(random_bytes(4));

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $invoiceId = (int) DB::table('invoices')->insertGetId([
                'invoice_no' => $invoiceNo,
                'user_id' => $userId,
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
}
