<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\System\StaleRecordPruneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaleRecordPruneTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sanctum.expiration' => 1440,
            'sanctum.idle_timeout' => 10800,
            'auth.passwords.users.expire' => 60,
        ]);
    }

    public function test_execute_deletes_only_stale_records(): void
    {
        // 先 dry-run 测出存量中已过期行数，避免对测试库存量分布做任何假设。
        $eligibleBefore = (int) app(StaleRecordPruneService::class)->prune(true)['tables']['personal_access_tokens']['eligible'];
        $countBefore = (int) DB::table('personal_access_tokens')->count();

        $this->insertAccessToken('expired-by-expires-at', ['expires_at' => now()->subHour(), 'created_at' => now()->subHours(2)]);
        $this->insertAccessToken('expired-by-age', ['created_at' => now()->subHours(25)]);
        $this->insertAccessToken('expired-by-idle', ['created_at' => now()->subHours(10), 'last_used_at' => now()->subHours(4)]);
        $this->insertAccessToken('active', ['created_at' => now()->subHour(), 'last_used_at' => now()->subMinutes(5)]);

        DB::table('password_reset_tokens')->insert([
            ['email' => 'stale@example.test', 'token' => 'stale', 'created_at' => now()->subMinutes(61)],
            ['email' => 'fresh@example.test', 'token' => 'fresh', 'created_at' => now()->subMinutes(30)],
        ]);

        DB::table('failed_jobs')->insert([
            ['uuid' => 'stale-uuid', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => '', 'failed_at' => now()->subDays(31)],
            ['uuid' => 'fresh-uuid', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => '', 'failed_at' => now()->subDays(2)],
        ]);

        app(StaleRecordPruneService::class)->prune(false);

        // 令牌表剩余 = 存量中未过期行 + 本用例的 active 行；三行过期用例数据全部删除。
        $this->assertSame($countBefore - $eligibleBefore + 1, (int) DB::table('personal_access_tokens')->count());
        $this->assertSame(
            ['active'],
            DB::table('personal_access_tokens')->whereIn('name', ['expired-by-expires-at', 'expired-by-age', 'expired-by-idle', 'active'])->pluck('name')->all(),
        );

        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', 'stale@example.test')->count());
        $this->assertSame(1, DB::table('password_reset_tokens')->where('email', 'fresh@example.test')->count());
        $this->assertSame(0, DB::table('failed_jobs')->where('uuid', 'stale-uuid')->count());
        $this->assertSame(1, DB::table('failed_jobs')->where('uuid', 'fresh-uuid')->count());
    }

    public function test_dry_run_counts_without_deleting(): void
    {
        $resultBefore = app(StaleRecordPruneService::class)->prune(true);

        $this->insertAccessToken('expired-by-age', ['created_at' => now()->subHours(25)]);
        DB::table('password_reset_tokens')->insert([
            ['email' => 'stale@example.test', 'token' => 'stale', 'created_at' => now()->subMinutes(61)],
        ]);
        DB::table('failed_jobs')->insert([
            ['uuid' => 'stale-uuid', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => '', 'failed_at' => now()->subDays(31)],
        ]);

        $result = app(StaleRecordPruneService::class)->prune(true);

        $this->assertSame('dry_run', $result['mode']);
        $this->assertSame($resultBefore['tables']['personal_access_tokens']['eligible'] + 1, $result['tables']['personal_access_tokens']['eligible']);
        $this->assertSame(0, $result['tables']['personal_access_tokens']['deleted']);
        $this->assertSame($resultBefore['tables']['password_reset_tokens']['eligible'] + 1, $result['tables']['password_reset_tokens']['eligible']);
        $this->assertSame($resultBefore['tables']['failed_jobs']['eligible'] + 1, $result['tables']['failed_jobs']['eligible']);
        $this->assertSame(0, $result['totals']['deleted']);

        $this->assertSame(1, DB::table('personal_access_tokens')->where('name', 'expired-by-age')->count());
        $this->assertSame(1, DB::table('password_reset_tokens')->where('email', 'stale@example.test')->count());
        $this->assertSame(1, DB::table('failed_jobs')->where('uuid', 'stale-uuid')->count());
    }

    public function test_never_used_token_is_pruned_by_age_not_idle(): void
    {
        $this->insertAccessToken('never-used-but-fresh', ['created_at' => now()->subMinutes(10)]);
        $this->insertAccessToken('never-used-stale', ['created_at' => now()->subHours(25)]);

        app(StaleRecordPruneService::class)->prune(false);

        // 按本用例造的 name 集合过滤断言，不对测试库存量分布做假设。
        $this->assertSame(
            ['never-used-but-fresh'],
            DB::table('personal_access_tokens')->whereIn('name', ['never-used-but-fresh', 'never-used-stale'])->pluck('name')->all(),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertAccessToken(string $name, array $overrides = []): void
    {
        DB::table('personal_access_tokens')->insert(array_merge([
            'tokenable_type' => 'App\\Models\\User',
            'tokenable_id' => 1,
            'name' => $name,
            'token' => hash('sha256', $name),
            'abilities' => '["*"]',
            'expires_at' => null,
            'last_used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
