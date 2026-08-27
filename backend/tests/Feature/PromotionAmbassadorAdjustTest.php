<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\PromotionAmbassador;
use App\Models\User;
use App\Services\Referral\MemberLevelService;
use App\Services\User\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 推广大使指派验证：管理员指派/清空后落库，未指派用户按全局配置兜底。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class PromotionAmbassadorAdjustTest extends TestCase
{
    use DatabaseTransactions;

    private function makeAmbassador(float $rate = 5.00): PromotionAmbassador
    {
        return PromotionAmbassador::query()->create([
            'name' => '大使'.uniqid(),
            'reward_rate' => $rate,
            'status' => 1,
        ]);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'amb_'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'amb-tester',
            'total_sales_amount' => 0,
        ]);
    }

    public function test_adjust_sets_ambassador_on_user(): void
    {
        $ambassador = $this->makeAmbassador();
        $user = $this->makeUser();

        app(UserService::class)->adjustPromotionAmbassador($user, (int) $ambassador->id);

        $fresh = User::query()->find($user->id);
        $this->assertSame((int) $ambassador->id, (int) $fresh->promotion_ambassador_id);
    }

    public function test_adjust_to_null_unassigns_ambassador(): void
    {
        $ambassador = $this->makeAmbassador();
        $user = $this->makeUser();
        app(UserService::class)->adjustPromotionAmbassador($user, (int) $ambassador->id);

        app(UserService::class)->adjustPromotionAmbassador(User::query()->find($user->id), null);

        $fresh = User::query()->find($user->id);
        $this->assertNull($fresh->promotion_ambassador_id);
    }

    public function test_adjust_rejects_unknown_ambassador(): void
    {
        $user = $this->makeUser();

        $this->expectException(BusinessException::class);
        app(UserService::class)->adjustPromotionAmbassador($user, 999999);
    }

    public function test_member_level_create_ignores_reward_rate_payload(): void
    {
        // 等级不再承载返利比例：payload 带 reward_rate 应被忽略而非报错
        $level = app(MemberLevelService::class)->create([
            'name' => '无返利等级'.uniqid(),
            'reward_rate' => 50,
            'status' => 1,
        ]);

        $this->assertNull($level->getAttribute('reward_rate'));
    }
}
