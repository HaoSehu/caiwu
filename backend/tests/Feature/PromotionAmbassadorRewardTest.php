<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\OrderStatus;
use App\Models\Order;
use App\Models\PromotionAmbassador;
use App\Models\ReferralReward;
use App\Models\Setting;
use App\Models\User;
use App\Services\Referral\ReferralService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 返利链路按推广大使档位取费率：指派大使按档位对应业务类型比例（新购 reward_rate / 续费 renewal_reward_rate），
 * 未指派分别回退全局 referral.reward_rate 与 referral.renewal_reward_rate 设置。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class PromotionAmbassadorRewardTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // 测试库存量配置关闭了返利开关，本测试统一开启
        Setting::setValue('referral', 'enabled', '1');
    }

    private function makeUser(string $prefix): User
    {
        return User::query()->create([
            'email' => $prefix.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $prefix.'-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makePaidOrder(User $buyer, float $amount, string $type = 'new'): Order
    {
        return Order::query()->create([
            'order_no' => 'RO'.date('YmdHis').mt_rand(1000, 9999),
            'user_id' => $buyer->id,
            'type' => $type,
            'amount' => $amount,
            'paid_amount' => $amount,
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
        ]);
    }

    public function test_reward_uses_ambassador_rate(): void
    {
        $ambassador = PromotionAmbassador::query()->create([
            'name' => '大使'.uniqid(),
            'reward_rate' => 5.00,
            'status' => 1,
        ]);
        $referrer = $this->makeUser('ref');
        $referrer->forceFill(['promotion_ambassador_id' => $ambassador->id])->save();
        $buyer = $this->makeUser('buyer');
        $buyer->forceFill(['referrer_user_id' => $referrer->id])->save();

        $order = $this->makePaidOrder($buyer, 100.00);

        $reward = app(ReferralService::class)->rewardForPaidOrder($order->refresh());

        $this->assertNotNull($reward);
        $this->assertSame(5.00, (float) $reward->reward_rate);
        $this->assertSame(5.00, (float) $reward->reward_amount);
    }

    public function test_reward_falls_back_to_global_setting_without_ambassador(): void
    {
        Setting::setValue('referral', 'reward_rate', '10');

        $referrer = $this->makeUser('ref2');
        $buyer = $this->makeUser('buyer2');
        $buyer->forceFill(['referrer_user_id' => $referrer->id])->save();

        $order = $this->makePaidOrder($buyer, 100.00);

        $reward = app(ReferralService::class)->rewardForPaidOrder($order->refresh());

        $this->assertNotNull($reward);
        $this->assertSame(10.00, (float) $reward->reward_rate);
        $this->assertSame(10.00, (float) $reward->reward_amount);
    }

    public function test_reward_idempotent_per_order(): void
    {
        $referrer = $this->makeUser('ref3');
        $buyer = $this->makeUser('buyer3');
        $buyer->forceFill(['referrer_user_id' => $referrer->id])->save();

        $order = $this->makePaidOrder($buyer, 200.00);
        $service = app(ReferralService::class);

        $first = $service->rewardForPaidOrder($order->refresh());
        $second = $service->rewardForPaidOrder($order->refresh());

        $this->assertNotNull($first);
        $this->assertSame((int) $first->id, (int) $second?->id);
        $this->assertSame(1, ReferralReward::query()->where('order_id', $order->id)->count());
    }

    public function test_renewal_order_uses_ambassador_renewal_rate(): void
    {
        $ambassador = PromotionAmbassador::query()->create([
            'name' => '续费大使'.uniqid(),
            'reward_rate' => 5.00,
            'renewal_reward_rate' => 3.00,
            'status' => 1,
        ]);
        $referrer = $this->makeUser('ref4');
        $referrer->forceFill(['promotion_ambassador_id' => $ambassador->id])->save();
        $buyer = $this->makeUser('buyer4');
        $buyer->forceFill(['referrer_user_id' => $referrer->id])->save();

        $order = $this->makePaidOrder($buyer, 100.00, 'renew');

        $reward = app(ReferralService::class)->rewardForPaidOrder($order->refresh());

        $this->assertNotNull($reward);
        $this->assertSame(3.00, (float) $reward->reward_rate);
        $this->assertSame(3.00, (float) $reward->reward_amount);
    }

    public function test_renewal_order_falls_back_to_global_setting_without_ambassador(): void
    {
        Setting::setValue('referral', 'renewal_reward_rate', '5');

        $referrer = $this->makeUser('ref5');
        $buyer = $this->makeUser('buyer5');
        $buyer->forceFill(['referrer_user_id' => $referrer->id])->save();

        $order = $this->makePaidOrder($buyer, 100.00, 'renew');

        $reward = app(ReferralService::class)->rewardForPaidOrder($order->refresh());

        $this->assertNotNull($reward);
        $this->assertSame(5.00, (float) $reward->reward_rate);
        $this->assertSame(5.00, (float) $reward->reward_amount);
    }

    public function test_new_order_ignores_renewal_rate(): void
    {
        $ambassador = PromotionAmbassador::query()->create([
            'name' => '偏重大使'.uniqid(),
            'reward_rate' => 2.00,
            'renewal_reward_rate' => 8.00,
            'status' => 1,
        ]);
        $referrer = $this->makeUser('ref6');
        $referrer->forceFill(['promotion_ambassador_id' => $ambassador->id])->save();
        $buyer = $this->makeUser('buyer6');
        $buyer->forceFill(['referrer_user_id' => $referrer->id])->save();

        $order = $this->makePaidOrder($buyer, 100.00, 'new');

        $reward = app(ReferralService::class)->rewardForPaidOrder($order->refresh());

        $this->assertNotNull($reward);
        $this->assertSame(2.00, (float) $reward->reward_rate);
        $this->assertSame(2.00, (float) $reward->reward_amount);
    }
}
