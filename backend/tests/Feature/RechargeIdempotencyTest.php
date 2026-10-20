<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\ActivityLog;
use App\Models\RechargeRecord;
use App\Models\User;
use App\Services\Finance\RechargePaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 管理端手工充值幂等：同一 idempotency_key 10 分钟内仅入账一次，
 * 入账失败释放占位允许修正重试；缺失幂等键一律拒绝（服务端强制，G3-3）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class RechargeIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_same_idempotency_key_enters_balance_only_once(): void
    {
        $user = $this->makeUser();
        $service = app(RechargePaymentService::class);
        $key = 'idem-'.uniqid();
        $context = ['operator_id' => 1, 'operator_name' => 'admin-test', 'idempotency_key' => $key];

        $service->adjustBalance($user, 10, '第一次提交', $context);

        // S1-3：成功路径写入 activity_logs 审计行。
        $this->assertSame(1, ActivityLog::query()
            ->where('action', 'balance.adjust.completed')
            ->where('subject_id', (int) $user->id)
            ->count());

        $this->expectException(BusinessException::class);
        try {
            $service->adjustBalance($user, 10, '双击重放', $context);
        } finally {
            $this->assertSame(1, RechargeRecord::query()->where('user_id', (int) $user->id)->count());
        }
    }

    public function test_missing_key_is_rejected(): void
    {
        $user = $this->makeUser();
        $service = app(RechargePaymentService::class);

        $this->expectException(BusinessException::class);
        try {
            $service->adjustBalance($user, 5, '缺少幂等键', ['operator_id' => 1, 'operator_name' => 'admin-test']);
        } finally {
            $this->assertSame(0, RechargeRecord::query()->where('user_id', (int) $user->id)->count());
        }
    }

    public function test_failed_attempt_can_be_retried_with_same_key_and_payload(): void
    {
        $user = $this->makeUser();
        $service = app(RechargePaymentService::class);
        $key = 'idem-'.uniqid();
        $context = ['operator_id' => 1, 'operator_name' => 'admin-test', 'idempotency_key' => $key];

        try {
            $service->adjustBalance($user, -1000, '超额扣减必失败', $context);
            $this->fail('余额不足扣减应抛异常');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('余额不足', $exception->getMessage());
        }

        // failed 状态允许同键同体重试：仍然因余额不足失败，而不是被判为「异体请求」。
        try {
            $service->adjustBalance($user, -1000, '超额扣减必失败', $context);
            $this->fail('余额不足扣减应继续抛业务异常');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('余额不足', $exception->getMessage());
        }

        // 改金额必须换新幂等键（同键异体 409 由下一条用例锁定）。
        $service->adjustBalance($user, 20, '修正后重试', ['operator_id' => 1, 'operator_name' => 'admin-test', 'idempotency_key' => 'idem-'.uniqid()]);

        $this->assertSame(1, RechargeRecord::query()->where('user_id', (int) $user->id)->count());
        $this->assertSame('20.00', $user->fresh()->balance);
    }

    public function test_same_key_with_different_amount_is_rejected_as_conflict(): void
    {
        $user = $this->makeUser();
        $service = app(RechargePaymentService::class);
        $key = 'idem-'.uniqid();
        $context = ['operator_id' => 1, 'operator_name' => 'admin-test', 'idempotency_key' => $key];

        $service->adjustBalance($user, 10, '首次提交', $context);

        try {
            $service->adjustBalance($user, 30, '换金额复用旧键', $context);
            $this->fail('同键异体必须拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('同一幂等键', $exception->getMessage());
        }

        $this->assertSame('10.00', $user->fresh()->balance);
        $this->assertSame(1, RechargeRecord::query()->where('user_id', (int) $user->id)->count());
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'recharge-idem-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'recharge-idem-tester',
            'total_sales_amount' => 0,
        ]);
    }
}
