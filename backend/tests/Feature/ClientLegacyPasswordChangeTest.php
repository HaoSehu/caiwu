<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Services\Auth\ClientLoginFlowService;
use App\Services\Auth\Contracts\LegacyPasswordVerifier as LegacyPasswordVerifierContract;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * D5A-01 回归护栏：ZJMF 旧哈希（###md5）用户在「原密码」二次确认路径上
 * 必须与登录一致，经 LegacyPasswordVerifier 校验——登录能过，改密/改绑也要能过。
 */
class ClientLegacyPasswordChangeTest extends TestCase
{
    use DatabaseTransactions;

    private const LEGACY_OLD_PASSWORD = 'legacy-old-pass';

    private const NEW_PASSWORD = 'brand-new-pass-1';

    protected function setUp(): void
    {
        parent::setUp();

        // 插件 provider 依赖 integration_plugins 表的启用记录，且插件类经运行时 require
        // 而非 composer autoload；测试内注册与 ZjmfLegacyPasswordVerifier 同格式的校验器
        // 替身，保证本用例覆盖的聚合校验路径与生产一致。
        $this->app->tag([ZjmfMd5LegacyVerifierFake::class], 'auth.legacy_password_verifiers');
    }

    public function test_legacy_hash_user_can_change_password_with_old_password(): void
    {
        $user = $this->legacyHashUser();
        Sanctum::actingAs($user);

        $this->putJson('/api/v2/client/password', [
            'oldPassword' => self::LEGACY_OLD_PASSWORD,
            'newPassword' => self::NEW_PASSWORD,
            'confirmPassword' => self::NEW_PASSWORD,
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertNotNull($fresh);
        // 改密成功后旧哈希被替换为新的 bcrypt 哈希，新密码即刻生效
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, (string) $fresh->password));
        $this->assertFalse(str_starts_with((string) $fresh->password, '###'));
    }

    public function test_legacy_hash_user_wrong_old_password_rejected(): void
    {
        $user = $this->legacyHashUser();
        Sanctum::actingAs($user);

        $this->putJson('/api/v2/client/password', [
            'oldPassword' => 'wrong-old-pass',
            'newPassword' => self::NEW_PASSWORD,
            'confirmPassword' => self::NEW_PASSWORD,
        ])->assertStatus(422);

        // 原密码校验失败时不得改写密码
        $fresh = $user->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('###'.md5(self::LEGACY_OLD_PASSWORD), (string) $fresh->password);
    }

    /**
     * 提现账户改绑等敏感操作的密码二次确认（verifyPasswordWithRiskControl）
     * 同样必须接受旧哈希用户的正确原密码。
     */
    public function test_risk_control_confirmation_accepts_legacy_password(): void
    {
        $user = $this->legacyHashUser();
        $flow = app(ClientLoginFlowService::class);

        // 正确旧密码：不抛异常
        $flow->verifyPasswordWithRiskControl($user, self::LEGACY_OLD_PASSWORD, '127.0.0.1');

        $wrongThrown = false;
        try {
            $flow->verifyPasswordWithRiskControl($user, 'wrong-old-pass', '127.0.0.1');
        } catch (BusinessException $exception) {
            $wrongThrown = true;
            $this->assertSame(42200, $exception->getErrorCode());
        }
        $this->assertTrue($wrongThrown, '错误密码未被拒绝');
    }

    /**
     * 旧哈希用户：密码字段为 ZJMF 格式（### + 32 位 md5）。
     * 通过 DB 直写绕开 User 模型的 hashed cast（否则会被再次 bcrypt 包装）。
     */
    private function legacyHashUser(): User
    {
        $user = User::factory()->create([
            'password_change_alert' => false, // 关闭改密邮件提醒，避免测试触发外部通知
        ]);

        DB::table('users')->where('id', $user->id)->update([
            'password' => '###'.md5(self::LEGACY_OLD_PASSWORD),
        ]);

        return $user->refresh();
    }
}

/**
 * ZJMF 旧哈希校验器替身：与 plugins/servers/zjmf_finance 的 ZjmfLegacyPasswordVerifier
 * 同格式（### + 32 位 md5，命中标记 needsPasswordRehash）。
 */
final class ZjmfMd5LegacyVerifierFake implements LegacyPasswordVerifierContract
{
    public function verify(string $plaintext, string $stored, bool &$needsPasswordRehash = false): ?bool
    {
        if (! $this->isZjmfMd5Hash($stored)) {
            return null;
        }

        if (! hash_equals(substr($stored, 3), md5($plaintext))) {
            return false;
        }

        $needsPasswordRehash = true;

        return true;
    }

    private function isZjmfMd5Hash(string $stored): bool
    {
        return str_starts_with($stored, '###')
            && strlen($stored) === 35
            && preg_match('/^###[a-f0-9]{32}$/i', $stored) === 1;
    }
}
