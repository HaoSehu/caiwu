<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UpstreamBillingRestoreCommandTest extends TestCase
{
    public function test_restore_upstream_billing_requires_confirmation_phrase(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'restore-sql-');
        file_put_contents($path, '');

        try {
            $this->artisan('finance:restore-upstream-billing', [
                'dump' => $path,
                '--dry-run' => true,
            ])->assertExitCode(2);
        } finally {
            @unlink($path);
        }
    }

    public function test_restore_upstream_billing_dry_run_keeps_foreign_key_children_intact(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'restore-sql-');
        file_put_contents($path, '');

        $beforeCount = DB::table('payment_callbacks')->count();

        try {
            $this->artisan('finance:restore-upstream-billing', [
                'dump' => $path,
                '--confirm' => 'RESTORE_MOFANG_BILLING',
                '--dry-run' => true,
            ])->assertExitCode(0);
        } finally {
            @unlink($path);
        }

        $this->assertSame($beforeCount, DB::table('payment_callbacks')->count());
    }
}
