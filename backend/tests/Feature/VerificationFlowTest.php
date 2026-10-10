<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Services\Auth\VerificationService;
use App\Services\Verification\VerificationDriverManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\Verification\FakeVerificationDriver;
use Tests\TestCase;

/**
 * 实名认证域回归（B5：V7-02 零覆盖缺口，对应 BACKEND §9「实名认证成功、失败、供应商异常、字段异常」
 * 与「Provider fake 驱动下的本地可重复测试」）：
 * 1. 成功：初始化 → 二维码 → 查询 → 同步，用户状态按内部状态码落库；
 * 2. 失败：认证拒绝把用户置为失败态（3）并清空 verified_at；
 * 3. 供应商异常：驱动返回 400/500 → BusinessException（42200/50000），异常不吞；
 * 4. 字段异常：HTTP 校验拦截非法字段（不触达驱动）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class VerificationFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_success_flow_initializes_qrcode_queries_and_syncs_user(): void
    {
        $driver = new FakeVerificationDriver;
        app(VerificationDriverManager::class)->register($driver);

        $user = $this->makeUser();
        $service = app(VerificationService::class);

        $session = $service->startVerificationSession($user, '张三', '110101199001011234');

        $this->assertSame('cert-fake-001', $session['certify_id']);
        $this->assertNotEmpty($session['url']);
        $this->assertStringContainsString('certify_id=cert-fake-001', (string) $session['url']);
        $this->assertCount(1, $driver->initialized, '初始化必须经驱动完成');
        $this->assertSame('张三', $driver->initialized[0]->realName);

        $user->refresh();
        $this->assertSame(4, (int) $user->verification_status, '初始化后应落「待认证」(4)');
        $this->assertSame('cert-fake-001', (string) $user->verification_certify_id);
        $this->assertSame('张三', (string) $user->real_name);

        $status = $service->queryStatus('cert-fake-001');
        $this->assertSame(1, (int) $status['status'], '查询状态透传驱动结果');

        $synced = $service->syncUserStatus($user, ['status' => 1, 'msg' => '审核通过'], 'cert-fake-001');
        $this->assertSame(2, (int) $synced->verification_status, '成功后用户应落已验证 (2)');
        $this->assertNotNull($synced->verified_at);
    }

    public function test_failed_status_marks_user_failed_and_clears_verified_at(): void
    {
        $driver = new FakeVerificationDriver;
        app(VerificationDriverManager::class)->register($driver);

        $user = $this->makeUser();
        $service = app(VerificationService::class);
        $service->initVerification($user, '李四', '110101199001011234');

        $failed = $service->syncUserStatus(
            $user->refresh(),
            ['status' => 2, 'msg' => '公安库比对不通过'],
            'cert-fake-001'
        );

        $this->assertSame(3, (int) $failed->verification_status, '失败后用户应落失败态 (3)');
        $this->assertNull($failed->verified_at);
        $this->assertSame('公安库比对不通过', (string) $failed->verification_message);
    }

    public function test_provider_business_error_surfaces_exception_and_does_not_persist(): void
    {
        $driver = new FakeVerificationDriver(
            initializeStatus: 400,
            initializeMessage: '供应商实名通道已关闭',
        );
        app(VerificationDriverManager::class)->register($driver);

        $user = $this->makeUser();

        try {
            app(VerificationService::class)->initVerification($user, '王五', '110101199001011234');
            $this->fail('供应商 400 必须抛出业务异常，不得静默');
        } catch (BusinessException $exception) {
            $this->assertSame('供应商实名通道已关闭', $exception->getMessage());
            $this->assertSame(42200, $exception->getErrorCode());
        }

        $this->assertSame(0, (int) $user->refresh()->verification_status, '供应商失败不得落任何实名状态');
    }

    public function test_provider_network_error_surfaces_50000(): void
    {
        $driver = new FakeVerificationDriver(initializeStatus: 500, initializeMessage: '网络超时');
        app(VerificationDriverManager::class)->register($driver);

        try {
            app(VerificationService::class)->initVerification($this->makeUser(), '赵六', '110101199001011234');
            $this->fail('供应商网络错误必须抛出业务异常');
        } catch (BusinessException $exception) {
            $this->assertSame(50000, $exception->getErrorCode());
        }
    }

    public function test_http_validation_rejects_invalid_fields(): void
    {
        Sanctum::actingAs($this->makeUser());

        // 缺少 idcard（required）
        $this->postJson('/api/v2/client/verification/init', ['realname' => '张三'])
            ->assertStatus(422);

        // idcard 长度不足（min:15）
        $this->postJson('/api/v2/client/verification/init', ['realname' => '张三', 'idcard' => '123'])
            ->assertStatus(422);

        // 非法证件类型（in: 白名单）
        $this->postJson('/api/v2/client/verification/init', [
            'realname' => '张三',
            'idcard' => '110101199001011234',
            'cert_type' => 'UNKNOWN_TYPE',
        ])->assertStatus(422);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'verification-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'verification-tester',
            'total_sales_amount' => 0,
            'status' => 1,
        ]);
    }
}
