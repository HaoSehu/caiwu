<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\MarketingProductGroup;
use App\Models\MemberLevel;
use App\Models\MemberLevelGroupDiscount;
use App\Models\Product;
use App\Models\User;
use App\Services\Pricing\MemberGroupDiscountService;
use App\Services\User\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 手工调级验证：等级为纯手工配置，管理员设置/置空后落库，
 * 用户表与投影表双写一致；无自动定级链路。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class MemberLevelManualAdjustTest extends TestCase
{
    use DatabaseTransactions;

    private function makeLevel(int $status = 1): MemberLevel
    {
        return MemberLevel::query()->create([
            'name' => '等级'.uniqid(),
            'status' => $status,
        ]);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'ml_'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'ml-tester',
            'total_sales_amount' => 0,
        ]);
    }

    public function test_adjust_sets_level_on_user(): void
    {
        $level = $this->makeLevel();
        $user = $this->makeUser();

        app(UserService::class)->adjustMemberLevel($user, (int) $level->id);

        $fresh = User::query()->find($user->id);
        $this->assertSame((int) $level->id, (int) $fresh->member_level_id);
    }

    public function test_adjust_to_null_unsets_level(): void
    {
        $level = $this->makeLevel();
        $user = $this->makeUser();
        app(UserService::class)->adjustMemberLevel($user, (int) $level->id);

        app(UserService::class)->adjustMemberLevel(User::query()->find($user->id), null);

        $fresh = User::query()->find($user->id);
        $this->assertNull($fresh->member_level_id);
    }

    public function test_adjust_rejects_unknown_level(): void
    {
        $user = $this->makeUser();

        $this->expectException(BusinessException::class);
        app(UserService::class)->adjustMemberLevel($user, 999999);
    }

    public function test_discount_still_effective_after_level_assign(): void
    {
        $level = $this->makeLevel();
        $user = $this->makeUser();
        app(UserService::class)->adjustMemberLevel($user, (int) $level->id);

        $group = MarketingProductGroup::query()->create(['name' => 'g'.uniqid(), 'sort_order' => 0]);
        $product = Product::query()->create([
            'product_group_id' => null,
            'service_type_code' => 'test',
            'product_type' => 'other',
            'pricing' => ['monthly' => 200.00],
            'setup_fee' => 0,
            'status' => 1,
            'sort_order' => 0,
        ]);
        $group->items()->create(['product_id' => $product->id]);
        MemberLevelGroupDiscount::query()->create([
            'member_level_id' => $level->id,
            'marketing_product_group_id' => $group->id,
            'discount_type' => MemberLevelGroupDiscount::TYPE_PERCENT,
            'discount_value' => 80,
        ]);

        $result = app(MemberGroupDiscountService::class)
            ->applyForProduct($user, (int) $product->id, 200.0);

        $this->assertNotNull($result);
        $this->assertSame('40.00', $result['discount_amount']);
        $this->assertSame('160.00', $result['final_amount']);
    }
}
