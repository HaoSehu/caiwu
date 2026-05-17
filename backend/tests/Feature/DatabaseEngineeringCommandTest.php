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
        $this->assertTrue((bool) ($payload['dry_run'] ?? false));
        $this->assertArrayHasKey('summary', $payload);
    }
}
