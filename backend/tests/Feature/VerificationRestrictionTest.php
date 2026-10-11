<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\MemberLevel;
use App\Models\Setting;
use App\Models\User;
use App\Services\Finance\RechargePaymentService;
use App\Services\Verification\VerificationGateService;
use App\Services\Verification\VerificationRestrictionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M11 未实名自动限制回归：
 * 1. enabled=true 时按裁决默认 scope 拦截未实名用户（purchase/recharge/withdrawal/
 *    service_transfer_receive 开、renewal 关、grace_days=0）；
 * 2. 拦截异常 data.error_code=verification_required（前端引导实名）；
 * 3. enabled=false（裁决：默认 false，上线不自动拦截，管理员显式开启）时全放行；
 * 4. 宽限期（grace_days>0，按 users.created_at）内放行、过期后拦截；
 * 5. exempt_member_level_ids 命中豁免放行；
 * 6. HTTP 层拦截（POST /recharge）返回结构化 error_code；
 * 7. 管理员代充值（RechargePaymentService::adjustBalance，operator=admin）不经过
 *    实名 Gate，不受限（结构性豁免）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class VerificationRestrictionTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        // 事务回滚不清理 Setting 静态组缓存，显式失效防止用例间串扰
        Setting::forgetCachedGroup(VerificationRestrictionService::SETTING_GROUP);
        Setting::forgetCachedGroup('users');

        parent::tearDown();
    }

    public function test_enabled_blocks_unverified_user_for_default_scopes(): void
    {
        $this->saveConfig(['enabled' => true]);

        $user = $this->makeUser();
        $gate = app(VerificationGateService::class);

        $this->assertFalse($gate->allowed($user, VerificationGateService::SCOPE_PURCHASE));
        $this->assertFalse($gate->allowed($user, VerificationGateService::SCOPE_RECHARGE));
        $this->assertFalse($gate->allowed($user, VerificationGateService::SCOPE_WITHDRAWAL));
        $this->assertFalse($gate->allowed($user, VerificationGateService::SCOPE_SERVICE_TRANSFER_RECEIVE));
        // 裁决：renewal 默认关闭
        $this->assertTrue($gate->allowed($user, VerificationGateService::SCOPE_RENEWAL));
    }

    public function test_ensure_can_throws_structured_verification_required_error(): void
    {
        $this->saveConfig(['enabled' => true]);

        $user = $this->makeUser();

        try {
            app(VerificationGateService::class)->ensureCan($user, VerificationGateService::SCOPE_PURCHASE);
            $this->fail('未实名用户 purchase 必须被拦截');
        } catch (BusinessException $exception) {
            $this->assertSame(40300, $exception->getErrorCode());

            $payload = $exception->render()->getData(true);
            $this->assertSame('verification_required', $payload['data']['error_code'] ?? null);
            $this->assertSame('purchase', $payload['data']['scope'] ?? null);
        }
    }

    public function test_disabled_by_default_allows_everything(): void
    {
        // 裁决：总开关 enabled 默认 false——上线不自动拦截，管理员显式开启后才生效。
        // 不写入任何配置，验证默认全放行。
        $user = $this->makeUser();
        $gate = app(VerificationGateService::class);

        foreach (VerificationRestrictionService::SCOPES as $scope) {
            $this->assertTrue($gate->allowed($user, $scope), "scope={$scope} 在默认配置下必须放行");
        }

        app(VerificationGateService::class)->ensureCan($user, VerificationGateService::SCOPE_PURCHASE);
        $this->addToAssertionCount(1);
    }

    public function test_verified_user_passes_all_scopes(): void
    {
        $this->saveConfig(['enabled' => true]);

        $verified = $this->makeUser(['is_verified' => 1, 'verification_status' => 2]);
        $gate = app(VerificationGateService::class);

        foreach (VerificationRestrictionService::SCOPES as $scope) {
            $this->assertTrue($gate->allowed($verified, $scope), "已实名用户 scope={$scope} 必须放行");
        }
    }

    public function test_grace_period_allows_recent_registration_and_expires(): void
    {
        $this->saveConfig(['enabled' => true, 'grace_days' => 7]);

        $gate = app(VerificationGateService::class);

        // created_at 不在 User::$fillable 内，须造行后 forceFill 模拟注册时间。
        $freshUser = $this->makeUser();
        $freshUser->forceFill(['created_at' => now()->subDays(2)])->save();
        $freshUser->refresh();
        $this->assertTrue($gate->allowed($freshUser, VerificationGateService::SCOPE_PURCHASE), '宽限期内必须放行');
        $this->assertSame(5, $gate->graceDaysRemaining($freshUser, 7));

        $staleUser = $this->makeUser();
        $staleUser->forceFill(['created_at' => now()->subDays(8)])->save();
        $staleUser->refresh();
        $this->assertFalse($gate->allowed($staleUser, VerificationGateService::SCOPE_PURCHASE), '宽限期已过必须拦截');
    }

    public function test_exempt_member_level_passes(): void
    {
        // users.member_level_id FK→member_levels，先造等级父行再引用。
        $level = MemberLevel::query()->create(['name' => 'm11-exempt-'.uniqid(), 'status' => 1]);
        $this->saveConfig(['enabled' => true, 'exempt_member_level_ids' => [(int) $level->id]]);

        $exempted = $this->makeUser(['member_level_id' => (int) $level->id]);
        $gate = app(VerificationGateService::class);

        $this->assertTrue($gate->allowed($exempted, VerificationGateService::SCOPE_PURCHASE));
        $this->assertTrue($gate->isExempted($exempted));
        $this->assertSame([], $gate->restrictedScopes($exempted));
    }

    public function test_scope_disabled_passes_even_when_enabled(): void
    {
        $this->saveConfig(['enabled' => true, 'scope' => ['purchase' => false]]);

        $user = $this->makeUser();
        $gate = app(VerificationGateService::class);

        $this->assertTrue($gate->allowed($user, VerificationGateService::SCOPE_PURCHASE), 'scope 关闭时必须放行');
        $this->assertFalse($gate->allowed($user, VerificationGateService::SCOPE_RECHARGE), '其他 scope 仍正常拦截');
    }

    public function test_recharge_api_returns_verification_required_error(): void
    {
        $this->saveConfig(['enabled' => true]);

        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v2/client/recharge', ['amount' => 10])
            ->assertStatus(403)
            ->assertJsonPath('data.error_code', 'verification_required');
    }

    public function test_recharge_api_passes_when_disabled(): void
    {
        // 不写配置（enabled 默认 false）：请求不再被实名 Gate 拦截，
        // 后续因未配置支付网关抛业务异常（非 verification_required）。
        Sanctum::actingAs($this->makeUser());

        try {
            $this->postJson('/api/v2/client/recharge', ['amount' => 10]);
        } catch (BusinessException $exception) {
            $payload = $exception->render()->getData(true);
            $this->assertNotSame(
                'verification_required',
                $payload['data']['error_code'] ?? null,
                'enabled=false 时不得返回实名拦截错误'
            );
        }

        $this->addToAssertionCount(1);
    }

    public function test_admin_recharge_for_client_is_not_restricted(): void
    {
        // 管理员代充值（operator_type=admin）走 RechargePaymentService::adjustBalance，
        // 不经过 VerificationGateService，结构性豁免：即使 enabled=true 也不得返回实名拦截。
        $this->saveConfig(['enabled' => true]);

        $user = $this->makeUser();

        try {
            app(RechargePaymentService::class)->adjustBalance(
                $user,
                10.0,
                'M11 管理员代充值豁免测试',
                [
                    'operator_type' => 'admin',
                    'operator_id' => 1,
                    'operator_name' => 'm11-test-admin',
                    'trace_id' => 'm11-test-'.uniqid(),
                    'ip_address' => '127.0.0.1',
                    'idempotency_key' => 'm11-test-'.uniqid(),
                ],
            );
        } catch (BusinessException $exception) {
            $payload = $exception->render()->getData(true);
            $this->assertNotSame(
                'verification_required',
                $payload['data']['error_code'] ?? null,
                '管理员代充值不受实名限制拦截'
            );
        }

        $this->addToAssertionCount(1);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function saveConfig(array $overrides): void
    {
        app(VerificationRestrictionService::class)->save($overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeUser(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'email' => 'm11-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'm11-tester',
            'total_sales_amount' => 0,
            'status' => 1,
            'is_verified' => 0,
            'verification_status' => 0,
        ], $overrides));
    }
}
