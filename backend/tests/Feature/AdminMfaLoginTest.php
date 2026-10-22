<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminLoginLog;
use App\Models\AdminUser;
use App\Models\Role;
use App\Services\Auth\AdminMfaService;
use App\Support\AdminPermissions;
use App\Support\Security\TotpService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 管理员双因素认证（SEC-1）：
 * 1. 未启用双因素时登录行为完全不变（回归保护）；
 * 2. 启用后密码正确只发挑战凭证，不签令牌、不写登录成功痕迹；
 * 3. 挑战凭证一次性消费，验证码错误即刻失效；
 * 4. 恢复码可替代动态码完成登录，且用后即焚；
 * 5. 绑定分两步、解绑需密码 + 验证码；
 * 6. 应急命令可强制解绑。
 */
class AdminMfaLoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.stores.redis_volatile' => ['driver' => 'array']]);
    }

    public function test_login_without_mfa_still_returns_token(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->postJson('/api/v2/admin/login', [
            'username' => $admin->username,
            'password' => 'secret123',
        ]);

        $response->assertOk();
        $this->assertNotSame('', (string) $response->json('data.token'));
        $this->assertFalse((bool) $response->json('data.mfa_required'));
        $this->assertSame('', (string) $response->json('data.challenge_token'));
        $this->assertFalse((bool) $response->json('data.admin.mfa_enabled'));
        $this->assertNotNull(AdminUser::query()->findOrFail($admin->id)->last_login_at);
    }

    public function test_login_with_mfa_returns_challenge_without_token(): void
    {
        [$admin, $secret] = $this->makeMfaAdmin();

        $response = $this->postJson('/api/v2/admin/login', [
            'username' => $admin->username,
            'password' => 'secret123',
        ]);

        $response->assertOk();
        $this->assertTrue((bool) $response->json('data.mfa_required'));
        $this->assertSame('', (string) $response->json('data.token'));
        $this->assertNotSame('', (string) $response->json('data.challenge_token'));
        $this->assertTrue((bool) $response->json('data.admin.mfa_enabled'));

        // 密码正确不等于登录成功：登录痕迹与成功日志都不能出现。
        $this->assertNull(AdminUser::query()->findOrFail($admin->id)->last_login_at);
        $this->assertSame(0, AdminLoginLog::query()->where('admin_user_id', $admin->id)->count());
        $this->assertSame(0, $admin->tokens()->count());

        $this->assertNotEmpty($secret);
    }

    public function test_verify_mfa_with_valid_code_returns_token(): void
    {
        [$admin, $secret] = $this->makeMfaAdmin();

        $challenge = $this->loginForChallenge($admin);
        $code = app(TotpService::class)->code($secret);

        $response = $this->postJson('/api/v2/admin/login/mfa', [
            'challenge_token' => $challenge,
            'code' => $code,
        ]);

        $response->assertOk();
        $this->assertNotSame('', (string) $response->json('data.token'));
        $this->assertFalse((bool) $response->json('data.mfa_required'));
        $this->assertNotNull(AdminUser::query()->findOrFail($admin->id)->last_login_at);

        $log = AdminLoginLog::query()->where('admin_user_id', $admin->id)->first();
        $this->assertInstanceOf(AdminLoginLog::class, $log);
        $this->assertSame(AdminLoginLog::STATUS_SUCCESS, $log->status);
    }

    public function test_verify_mfa_with_invalid_code_burns_challenge(): void
    {
        [$admin, $secret] = $this->makeMfaAdmin();

        $challenge = $this->loginForChallenge($admin);

        $this->postJson('/api/v2/admin/login/mfa', [
            'challenge_token' => $challenge,
            'code' => '000000',
        ])->assertStatus(401);

        $this->assertSame(0, $admin->tokens()->count());

        $log = AdminLoginLog::query()->where('admin_user_id', $admin->id)->first();
        $this->assertInstanceOf(AdminLoginLog::class, $log);
        $this->assertSame(AdminLoginLog::STATUS_FAILED, $log->status);
        $this->assertSame(AdminLoginLog::REASON_MFA_INVALID, $log->failure_reason);

        // 挑战凭证已被消费，即使这次给对了码也不能再用。
        $this->postJson('/api/v2/admin/login/mfa', [
            'challenge_token' => $challenge,
            'code' => app(TotpService::class)->code($secret),
        ])->assertStatus(410);
    }

    public function test_recovery_code_can_complete_login_once(): void
    {
        [$admin, $secret, $recoveryCodes] = $this->makeMfaAdmin(withRecoveryCodes: true);

        $challenge = $this->loginForChallenge($admin);

        $response = $this->postJson('/api/v2/admin/login/mfa', [
            'challenge_token' => $challenge,
            'code' => $recoveryCodes[0],
        ]);

        $response->assertOk();
        $this->assertNotSame('', (string) $response->json('data.token'));

        // 恢复码一次性：用掉后数组里少一条，且再拿同一个码无法登录。
        $remaining = AdminUser::query()->findOrFail($admin->id)->mfa_recovery_codes;
        $this->assertIsArray($remaining);
        $this->assertCount(count($recoveryCodes) - 1, $remaining);

        $second = $this->loginForChallenge($admin);
        $this->postJson('/api/v2/admin/login/mfa', [
            'challenge_token' => $second,
            'code' => $recoveryCodes[0],
        ])->assertStatus(401);

        $this->assertNotEmpty($secret);
    }

    public function test_setup_then_enable_returns_recovery_codes(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $setup = $this->postJson('/api/v2/admin/auth/mfa/setup')->assertOk();
        $secret = (string) $setup->json('data.secret');
        $this->assertNotSame('', $secret);
        $this->assertStringStartsWith('otpauth://totp/', (string) $setup->json('data.otpauth_uri'));

        // 确认前密钥不落库：中途放弃不会把账号锁死。
        $this->assertNull(AdminUser::query()->findOrFail($admin->id)->mfa_secret);

        $enable = $this->postJson('/api/v2/admin/auth/mfa/enable', [
            'code' => app(TotpService::class)->code($secret),
        ])->assertOk();

        $codes = (array) $enable->json('data.recovery_codes');
        $this->assertCount(8, $codes);

        $fresh = AdminUser::query()->findOrFail($admin->id);
        $this->assertTrue($fresh->hasMfaEnabled());
        $this->assertNotSame($secret, (string) $fresh->mfa_secret);
        $this->assertSame($secret, Crypt::decryptString((string) $fresh->mfa_secret));
        // 恢复码只落哈希，接口返回的明文不应出现在库中。
        foreach ((array) $fresh->mfa_recovery_codes as $hash) {
            $this->assertNotContains($hash, $codes);
        }
    }

    public function test_enable_rejects_wrong_code_and_expired_setup(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v2/admin/auth/mfa/enable', ['code' => '123456'])->assertStatus(422);

        $setup = $this->postJson('/api/v2/admin/auth/mfa/setup')->assertOk();
        $secret = (string) $setup->json('data.secret');

        $this->postJson('/api/v2/admin/auth/mfa/enable', ['code' => '000000'])->assertStatus(422);

        // 用对码仍然可以完成绑定——输错一次不应该作废整次绑定。
        $this->postJson('/api/v2/admin/auth/mfa/enable', [
            'code' => app(TotpService::class)->code($secret),
        ])->assertOk();

        $this->assertTrue(AdminUser::query()->findOrFail($admin->id)->hasMfaEnabled());
    }

    public function test_disable_requires_password_and_code(): void
    {
        [$admin, $secret] = $this->makeMfaAdmin();
        Sanctum::actingAs(AdminUser::query()->findOrFail($admin->id));

        $code = app(TotpService::class)->code($secret);

        $this->postJson('/api/v2/admin/auth/mfa/disable', [
            'password' => 'wrong-password',
            'code' => $code,
        ])->assertStatus(422);

        $this->postJson('/api/v2/admin/auth/mfa/disable', [
            'password' => 'secret123',
            'code' => '000000',
        ])->assertStatus(422);

        $this->assertTrue(AdminUser::query()->findOrFail($admin->id)->hasMfaEnabled());

        $this->postJson('/api/v2/admin/auth/mfa/disable', [
            'password' => 'secret123',
            'code' => $code,
        ])->assertOk();

        $fresh = AdminUser::query()->findOrFail($admin->id);
        $this->assertFalse($fresh->hasMfaEnabled());
        $this->assertNull($fresh->mfa_secret);
        $this->assertNull($fresh->mfa_recovery_codes);
    }

    public function test_force_disable_command_clears_mfa(): void
    {
        [$admin] = $this->makeMfaAdmin();

        $this->artisan('admin:disable-mfa', ['username' => $admin->username])
            ->expectsOutputToContain('已启用双因素认证')
            ->assertSuccessful();

        // 未加 --force 只检查不写库。
        $this->assertTrue(AdminUser::query()->findOrFail($admin->id)->hasMfaEnabled());

        $this->artisan('admin:disable-mfa', ['username' => $admin->username, '--force' => true])
            ->assertSuccessful();

        $this->assertFalse(AdminUser::query()->findOrFail($admin->id)->hasMfaEnabled());
    }

    public function test_challenge_is_bound_to_the_issuing_user_agent(): void
    {
        [$admin, $secret] = $this->makeMfaAdmin();

        // 用 A 浏览器签发挑战
        $challenge = $this->loginForChallenge($admin, 'Mozilla/5.0 (Windows NT 10.0) AdminBrowser/1.0');

        // B 浏览器拿着同一挑战提交正确动态码：环境不一致必须拒绝，且挑战已被消费。
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh) OtherBrowser/9.9')
            ->postJson('/api/v2/admin/login/mfa', [
                'challenge_token' => $challenge,
                'code' => app(TotpService::class)->code($secret),
            ])
            ->assertStatus(403);

        $this->assertSame(0, $admin->tokens()->count(), '环境不一致不得签发令牌');

        $log = AdminLoginLog::query()->where('admin_user_id', $admin->id)->first();
        $this->assertInstanceOf(AdminLoginLog::class, $log);
        $this->assertSame(AdminLoginLog::STATUS_FAILED, $log->status);
        $this->assertSame(AdminLoginLog::REASON_MFA_INVALID, $log->failure_reason);

        // 原浏览器重走密码登录仍可正常完成，环境校验只拦跨设备复用。
        $next = $this->loginForChallenge($admin, 'Mozilla/5.0 (Windows NT 10.0) AdminBrowser/1.0');
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) AdminBrowser/1.0')
            ->postJson('/api/v2/admin/login/mfa', [
                'challenge_token' => $next,
                'code' => app(TotpService::class)->code($secret),
            ])
            ->assertOk();
    }

    public function test_user_agent_normalization_ignores_case_and_whitespace(): void
    {
        [$admin, $secret] = $this->makeMfaAdmin();

        // 签发与提交换行/大小写/多余空白不同但归一化后相同的 UA，必须放行：
        // 否则同一浏览器因为 header 排版差异就被锁在门外。
        $challenge = $this->loginForChallenge($admin, "Mozilla/5.0 (Windows NT 10.0)\r\n   AdminBrowser/1.0");

        $this->withHeader('User-Agent', 'mozilla/5.0 (windows nt 10.0) adminbrowser/1.0')
            ->postJson('/api/v2/admin/login/mfa', [
                'challenge_token' => $challenge,
                'code' => app(TotpService::class)->code($secret),
            ])
            ->assertOk();
    }

    public function test_recovery_code_cannot_be_consumed_twice_by_two_challenges(): void
    {
        [$admin, , $recoveryCodes] = $this->makeMfaAdmin(withRecoveryCodes: true);
        $code = $recoveryCodes[0];

        // 同一恢复码并发提交两个挑战：第二个必须失败，只允许一次成功。
        $first = $this->loginForChallenge($admin);
        $second = $this->loginForChallenge($admin);

        $this->postJson('/api/v2/admin/login/mfa', [
            'challenge_token' => $first,
            'code' => $code,
        ])->assertOk();

        $this->postJson('/api/v2/admin/login/mfa', [
            'challenge_token' => $second,
            'code' => $code,
        ])->assertStatus(401);

        $remaining = AdminUser::query()->findOrFail($admin->id)->mfa_recovery_codes;
        $this->assertCount(count($recoveryCodes) - 1, (array) $remaining, '同一恢复码只能被消耗一次');
    }

    public function test_recovery_code_consumption_locks_the_row_and_rejects_stale_instances(): void
    {
        [$admin, , $recoveryCodes] = $this->makeMfaAdmin(withRecoveryCodes: true);
        $code = $recoveryCodes[0];
        $adminId = (int) $admin->id;

        // 模拟两个请求各自在登录流程早期加载了一份实例：两份内存里的
        // mfa_recovery_codes 都还是「未消费」的旧数组，这正是并发重复使用的来源。
        $firstRequest = AdminUser::query()->findOrFail($adminId);
        $secondRequest = AdminUser::query()->findOrFail($adminId);

        $service = app(AdminMfaService::class);

        DB::enableQueryLog();
        $this->assertTrue($service->verify($firstRequest, $code), '第一个请求应能消费恢复码');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $lockedRead = false;
        foreach ($queries as $query) {
            if (str_contains(strtolower((string) ($query['query'] ?? '')), 'for update')) {
                $lockedRead = true;
                break;
            }
        }
        $this->assertTrue($lockedRead, '消费恢复码必须走事务内加锁读，否则两个请求会同时读到同一份旧数组');

        // 第二个请求拿着陈旧实例来验同一个码：锁内重读会看到它已被移除，必须失败。
        $this->assertFalse($service->verify($secondRequest, $code), '同一恢复码不得被第二个请求再次消费');

        $remaining = (array) AdminUser::query()->findOrFail($adminId)->mfa_recovery_codes;
        $this->assertCount(count($recoveryCodes) - 1, $remaining, '只允许消耗一条恢复码');
    }

    public function test_mfa_setup_endpoint_is_rate_limited(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        // 每次调用都会覆盖待启用密钥，不限流可被反复刷写；第 11 次必须被限流挡住。
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v2/admin/auth/mfa/setup')->assertOk();
        }

        $this->postJson('/api/v2/admin/auth/mfa/setup')->assertStatus(429);
    }

    private function makeAdmin(): AdminUser
    {
        $token = uniqid();
        $role = Role::query()->create([
            'name' => 'role_'.$token,
            'label' => '双因素测试角色',
            'permissions' => [AdminPermissions::LOG_LIST],
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.$token,
            'password' => 'secret123',
            'nickname' => '双因素测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }

    /**
     * @return array{0: AdminUser, 1: string, 2: list<string>}
     */
    private function makeMfaAdmin(bool $withRecoveryCodes = false): array
    {
        $admin = $this->makeAdmin();
        $secret = app(TotpService::class)->generateSecret();

        $recoveryCodes = $withRecoveryCodes
            ? array_map(static fn (int $i): string => 'RC'.str_pad((string) $i, 8, 'X'), range(1, 3))
            : [];

        $admin->forceFill([
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_enabled_at' => now(),
            'mfa_recovery_codes' => array_map(static fn (string $code): string => Hash::make($code), $recoveryCodes),
        ])->save();

        return [$admin, $secret, $recoveryCodes];
    }

    private function loginForChallenge(AdminUser $admin, string $userAgent = 'Tests/AdminMfaAgent'): string
    {
        $response = $this->withHeader('User-Agent', $userAgent)
            ->postJson('/api/v2/admin/login', [
                'username' => $admin->username,
                'password' => 'secret123',
            ])->assertOk();

        return (string) $response->json('data.challenge_token');
    }
}
