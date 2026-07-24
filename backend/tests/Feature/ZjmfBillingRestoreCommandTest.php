<?php

declare(strict_types=1);

namespace Tests\Feature;

use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfBillingRestoreService;
use Tests\TestCase;

class ZjmfBillingRestoreCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->activateIntegrationPluginForTest('upstream', 'zjmf_finance');
    }

    public function test_restore_zjmf_billing_requires_confirmation_phrase(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'restore-sql-');
        file_put_contents($path, '');

        try {
            $this->artisan('finance:restore-zjmf-billing', [
                'dump' => $path,
                '--dry-run' => true,
            ])->assertExitCode(2);
        } finally {
            @unlink($path);
        }
    }

    public function test_restore_zjmf_billing_accepts_zjmf_confirmation_phrase(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'restore-sql-');
        file_put_contents($path, '');

        $this->app->instance(ZjmfBillingRestoreService::class, $this->fakeRestoreService());

        try {
            $this->artisan('finance:restore-zjmf-billing', [
                'dump' => $path,
                '--confirm' => 'RESTORE_ZJMF_BILLING',
                '--dry-run' => true,
            ])->assertExitCode(0);
        } finally {
            @unlink($path);
        }
    }

    public function test_restore_zjmf_billing_rejects_legacy_mofang_confirmation_phrase(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'restore-sql-');
        file_put_contents($path, '');

        $this->app->instance(ZjmfBillingRestoreService::class, $this->fakeRestoreService());

        try {
            $this->artisan('finance:restore-zjmf-billing', [
                'dump' => $path,
                '--confirm' => 'RESTORE_MOFANG_BILLING',
                '--dry-run' => true,
            ])->assertExitCode(2);
        } finally {
            @unlink($path);
        }
    }

    private function fakeRestoreService(): ZjmfBillingRestoreService
    {
        return new class extends ZjmfBillingRestoreService
        {
            public function restoreFromSqlDump(string $dumpPath, bool $dryRun = false): array
            {
                return [
                    'dry_run' => $dryRun,
                    'invoices' => 0,
                    'balance_logs' => 0,
                    'user_balances' => 0,
                    'skipped_missing_users' => 0,
                    'skipped_deleted_invoices' => 0,
                ];
            }
        };
    }
}
