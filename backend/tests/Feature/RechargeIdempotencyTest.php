<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\RechargeRecord;
use App\Models\User;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 管理端手工充值幂等：同一 idempotency_key 10 分钟内仅入账一次，
 * 入账失败释放占位允许修正重试；无 key 保持旧行为。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class RechargeIdempotencyTest extends TestCase
{
    public function test_same_idempotency_key_enters_balance_only_once(): void
    {
        $user = $this->makeUser();
        $service = app(PaymentService::class);
        $key = 'idem-'.uniqid();
        $context = ['operator_id' => 1, 'operator_name' => 'admin-test', 'idempotency_key' => $key];

        $service->adjustBalance($user, 10, '第一次提交', $context);

        $this->expectException(BusinessException::class);
        try {
            $service->adjustBalance($user, 10, '双击重放', $context);
        } finally {
            $this->assertSame(1, RechargeRecord::query()->where('user_id', (int) $user->id)->count());
        }
    }

    public function test_missing_key_keeps_legacy_behavior(): void
    {
        $user = $this->makeUser();
        $service = app(PaymentService::class);
        $context = ['operator_id' => 1, 'operator_name' => 'admin-test'];

        $service->adjustBalance($user, 5, '第一笔', $context);
        $service->adjustBalance($user, 5, '第二笔', $context);

        $this->assertSame(2, RechargeRecord::query()->where('user_id', (int) $user->id)->count());
    }

    public function test_failed_attempt_releases_idempotency_key(): void
    {
        $user = $this->makeUser();
        $service = app(PaymentService::class);
        $key = 'idem-'.uniqid();
        $context = ['operator_id' => 1, 'operator_name' => 'admin-test', 'idempotency_key' => $key];

        try {
            $service->adjustBalance($user, -1000, '超額扣减必失败', $context);
            $this->fail('余额不足扣减应抛异常');
        } catch (BusinessException) {
        }

        // 失败已释放占位：同 key 修正金额后可成功入账。
        $service->adjustBalance($user, 20, '修正后重试', $context);

        $this->assertSame(1, RechargeRecord::query()->where('user_id', (int) $user->id)->count());
        // 成功入账后占位保留 10 分钟，拦截同 key 重放。
        $this->assertTrue(Cache::has('lock:admin:balance-adjust:'.$user->id.':'.md5($key)));
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
