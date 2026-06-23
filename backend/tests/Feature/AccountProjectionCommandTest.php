<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\User\UserAccountProjectionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class AccountProjectionCommandTest extends BaseTestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        app('db')->setDefaultConnection('sqlite');

        $this->createSchema();
    }

    public function test_account_check_projections_reports_missing_accounts(): void
    {
        $before = app(UserAccountProjectionService::class)->inspect();
        $this->insertUserWithoutAccount('account-check-'.uniqid('', true).'@example.test');

        Artisan::call('account:check-projections', ['--json' => true]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(
            (int) $before['users_without_account'] + 1,
            (int) $payload['users_without_account']
        );
        $this->assertArrayHasKey('balance_mismatch_users_vs_accounts', $payload);
    }

    public function test_account_backfill_user_accounts_dry_run_does_not_write(): void
    {
        $userId = $this->insertUserWithoutAccount('account-dry-run-'.uniqid('', true).'@example.test');

        Artisan::call('account:backfill-user-accounts', ['--json' => true]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue((bool) $payload['dry_run']);
        $this->assertSame(0, (int) $payload['inserted']);
        $this->assertDatabaseMissing('user_accounts', ['user_id' => $userId]);
    }

    public function test_account_backfill_user_accounts_execute_inserts_missing_accounts(): void
    {
        $backupPath = null;

        try {
            $userId = $this->insertUserWithoutAccount('account-execute-'.uniqid('', true).'@example.test');

            Artisan::call('account:backfill-user-accounts', [
                '--execute' => true,
                '--json' => true,
            ]);

            $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
            $backupPath = $payload['backup_path'] ?? null;

            $this->assertFalse((bool) $payload['dry_run']);
            $this->assertGreaterThanOrEqual(1, (int) $payload['inserted']);
            $this->assertDatabaseHas('user_accounts', [
                'user_id' => $userId,
                'cash_balance' => '123.45',
                'credit_limit' => '67.89',
                'referral_frozen_balance' => '1.11',
                'referral_available_balance' => '2.22',
                'referral_pending_withdrawal_balance' => '3.33',
                'referral_withdrawn_balance' => '4.44',
            ]);
            $this->assertIsString($backupPath);
            $this->assertFileExists($backupPath);
        } finally {
            if (is_string($backupPath) && File::exists($backupPath)) {
                File::delete($backupPath);
            }
        }
    }

    public function test_account_backfill_user_accounts_execute_can_sync_legacy_user_projection(): void
    {
        $backupPath = null;
        $legacyBackupPath = null;

        try {
            $userId = $this->insertUserWithoutAccount('account-sync-'.uniqid('', true).'@example.test');
            DB::table('user_accounts')->insert([
                'user_id' => $userId,
                'cash_balance' => '500.00',
                'credit_limit' => '88.88',
                'referral_frozen_balance' => '9.00',
                'referral_available_balance' => '8.00',
                'referral_pending_withdrawal_balance' => '7.00',
                'referral_withdrawn_balance' => '6.00',
                'version' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Artisan::call('account:backfill-user-accounts', [
                '--execute' => true,
                '--sync-legacy-users' => true,
                '--json' => true,
            ]);

            $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
            $backupPath = $payload['backup_path'] ?? null;
            $legacyBackupPath = $payload['legacy_sync_backup_path'] ?? null;

            $this->assertSame(1, (int) $payload['legacy_users_synced']);
            $this->assertDatabaseHas('users', [
                'id' => $userId,
                'balance' => '500.00',
                'credit_limit' => '88.88',
                'referral_frozen_amount' => '9.00',
                'referral_available_amount' => '8.00',
                'referral_withdrawing_amount' => '7.00',
                'referral_withdrawn_amount' => '6.00',
            ]);
            $this->assertIsString($legacyBackupPath);
            $this->assertFileExists($legacyBackupPath);
        } finally {
            foreach ([$backupPath, $legacyBackupPath] as $path) {
                if (is_string($path) && File::exists($path)) {
                    File::delete($path);
                }
            }
        }
    }

    private function createSchema(): void
    {
        Schema::dropIfExists('user_accounts');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->string('nickname')->default('');
            $table->string('phone')->nullable()->unique();
            $table->tinyInteger('status')->default(1);
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('referral_frozen_amount', 12, 2)->default(0);
            $table->decimal('referral_available_amount', 12, 2)->default(0);
            $table->decimal('referral_withdrawing_amount', 12, 2)->default(0);
            $table->decimal('referral_withdrawn_amount', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('user_accounts', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->primary();
            $table->decimal('cash_balance', 12, 2)->default(0);
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('referral_frozen_balance', 12, 2)->default(0);
            $table->decimal('referral_available_balance', 12, 2)->default(0);
            $table->decimal('referral_pending_withdrawal_balance', 12, 2)->default(0);
            $table->decimal('referral_withdrawn_balance', 12, 2)->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
    }

    private function insertUserWithoutAccount(string $email): int
    {
        return (int) DB::table('users')->insertGetId([
            'email' => $email,
            'password' => bcrypt('password'),
            'nickname' => '',
            'phone' => null,
            'status' => 1,
            'balance' => '123.45',
            'credit_limit' => '67.89',
            'referral_frozen_amount' => '1.11',
            'referral_available_amount' => '2.22',
            'referral_withdrawing_amount' => '3.33',
            'referral_withdrawn_amount' => '4.44',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
