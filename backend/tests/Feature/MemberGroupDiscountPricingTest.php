<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MarketingProductGroup;
use App\Models\MemberLevel;
use App\Models\MemberLevelGroupDiscount;
use App\Models\Product;
use App\Models\ThirdProductGroup;
use App\Models\User;
use App\Services\Pricing\MemberGroupDiscountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 会员等级 × 营销组 折扣计价单元验证。
 * 使用 DatabaseTransactions：所有写入在测试结束后回滚，不污染 idc_test 现有数据。
 */
class MemberGroupDiscountPricingTest extends TestCase
{
    use DatabaseTransactions;

    private function makeLevel(int $status = 1): MemberLevel
    {
        return MemberLevel::query()->create([
            'name' => '测试等级'.uniqid(),
            'status' => $status,
        ]);
    }

    private function makeProduct(): Product
    {
        $group = ThirdProductGroup::query()->first();

        return Product::query()->create([
            'product_group_id' => $group?->id,
            'service_type_code' => 'test',
            'product_type' => 'other',
            'pricing' => ['monthly' => 100.00],
            'setup_fee' => 0,
            'status' => 1,
            'sort_order' => 0,
        ]);
    }

    private function makeUserWithLevel(MemberLevel $level): User
    {
        return User::query()->create([
            'email' => 'md_'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'md-tester',
            'member_level_id' => $level->id,
        ]);
    }

    public function test_no_rule_keeps_original_price(): void
    {
        $level = $this->makeLevel();
        $user = $this->makeUserWithLevel($level);
        $product = $this->makeProduct();

        $result = app(MemberGroupDiscountService::class)->applyForProduct($user, (int) $product->id, 100.0);

        $this->assertNull($result);
    }

    public function test_percentage_discount_uses_bates_semantics(): void
    {
        $level = $this->makeLevel();
        $user = $this->makeUserWithLevel($level);
        $product = $this->makeProduct();
        $group = MarketingProductGroup::query()->create(['name' => 'g'.uniqid(), 'sort_order' => 0]);
        $group->items()->create(['product_id' => $product->id]);
        // discount_value=90 表示折后保留 90%，即减免 10%
        MemberLevelGroupDiscount::query()->create([
            'member_level_id' => $level->id,
            'marketing_product_group_id' => $group->id,
            'discount_type' => MemberLevelGroupDiscount::TYPE_PERCENT,
            'discount_value' => 90,
        ]);

        $result = app(MemberGroupDiscountService::class)->applyForProduct($user, (int) $product->id, 100.0);

        $this->assertNotNull($result);
        $this->assertSame('10.00', $result['discount_amount']);
        $this->assertSame('90.00', $result['final_amount']);
    }

    public function test_fixed_discount_floors_at_zero(): void
    {
        $level = $this->makeLevel();
        $user = $this->makeUserWithLevel($level);
        $product = $this->makeProduct();
        $group = MarketingProductGroup::query()->create(['name' => 'g'.uniqid(), 'sort_order' => 0]);
        $group->items()->create(['product_id' => $product->id]);
        MemberLevelGroupDiscount::query()->create([
            'member_level_id' => $level->id,
            'marketing_product_group_id' => $group->id,
            'discount_type' => MemberLevelGroupDiscount::TYPE_FIXED,
            'discount_value' => 150,
        ]);

        $result = app(MemberGroupDiscountService::class)->applyForProduct($user, (int) $product->id, 100.0);

        $this->assertNotNull($result);
        $this->assertSame('100.00', $result['discount_amount']);
        $this->assertSame('0.00', $result['final_amount']);
    }

    public function test_multiple_groups_take_lowest_final_price(): void
    {
        $level = $this->makeLevel();
        $user = $this->makeUserWithLevel($level);
        $product = $this->makeProduct();
        $groupA = MarketingProductGroup::query()->create(['name' => 'a'.uniqid(), 'sort_order' => 0]);
        $groupB = MarketingProductGroup::query()->create(['name' => 'b'.uniqid(), 'sort_order' => 1]);
        $groupA->items()->create(['product_id' => $product->id]);
        $groupB->items()->create(['product_id' => $product->id]);
        MemberLevelGroupDiscount::query()->create([
            'member_level_id' => $level->id,
            'marketing_product_group_id' => $groupA->id,
            'discount_type' => MemberLevelGroupDiscount::TYPE_PERCENT,
            'discount_value' => 90, // 减 10
        ]);
        MemberLevelGroupDiscount::query()->create([
            'member_level_id' => $level->id,
            'marketing_product_group_id' => $groupB->id,
            'discount_type' => MemberLevelGroupDiscount::TYPE_FIXED,
            'discount_value' => 30, // 减 30，更优
        ]);

        $result = app(MemberGroupDiscountService::class)->applyForProduct($user, (int) $product->id, 100.0);

        $this->assertNotNull($result);
        $this->assertSame('30.00', $result['discount_amount']);
        $this->assertSame('70.00', $result['final_amount']);
    }

    public function test_disabled_level_yields_no_discount(): void
    {
        $level = $this->makeLevel(status: 0);
        $user = $this->makeUserWithLevel($level);
        $product = $this->makeProduct();
        $group = MarketingProductGroup::query()->create(['name' => 'g'.uniqid(), 'sort_order' => 0]);
        $group->items()->create(['product_id' => $product->id]);
        MemberLevelGroupDiscount::query()->create([
            'member_level_id' => $level->id,
            'marketing_product_group_id' => $group->id,
            'discount_type' => MemberLevelGroupDiscount::TYPE_PERCENT,
            'discount_value' => 50,
        ]);

        $result = app(MemberGroupDiscountService::class)->applyForProduct($user, (int) $product->id, 100.0);

        $this->assertNull($result);
    }
}
