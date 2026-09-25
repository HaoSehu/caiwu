<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\CouponStatus;
use App\Constants\UserCouponStatus;
use App\Models\Coupon;
use App\Models\CouponCampaign;
use App\Models\User;
use App\Models\UserCoupon;
use App\Services\Finance\CouponService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * D5B-01 / D1B-03 回归：
 * - 管理端优惠券更新为「部分更新」语义——请求未提交的非必填字段必须保持原值，
 *   不得静默写入默认值（限量变不限量、停用变启用、有效期清空、活动券脱钩）；
 *   显式提交的值（含空值）照常生效。
 * - 管理端列表/详情的 user_ids 输出契约不变：只含「发放型（grant）」领取记录的用户 id。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class AdminCouponPartialUpdateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_partial_update_keeps_unsubmitted_fields(): void
    {
        $service = app(CouponService::class);
        $coupon = $this->makeCoupon([
            'name' => '部分更新保持原值'.uniqid(),
            'code' => 'PU'.uniqid(),
            'description' => '原描述',
            'distribution_type' => 'public',
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 10,
            'min_amount' => 50,
            'max_discount_amount' => 30,
            'billing_cycles' => ['monthly', 'quarterly'],
            'product_ids' => [11, 22],
            'first_order_only' => true,
            'total_usage_limit' => 100,
            'per_user_limit' => 3,
            'status' => CouponStatus::DISABLED,
            'sort_order' => 7,
            'starts_at' => '2026-01-01 00:00:00',
            'expires_at' => '2026-12-31 23:59:59',
            'remark' => '原备注',
        ]);

        // 只提交必填字段 + 名称，其余字段一律不提交
        $result = $service->updateCoupon($coupon, [
            'name' => '部分更新改名'.uniqid(),
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 12.5,
            'distribution_type' => 'public',
        ], ['operator' => 'tester']);

        $coupon->refresh();

        // 提交的字段已生效
        $this->assertSame(12.5, (float) $coupon->discount_value);
        $this->assertSame('fixed', (string) $coupon->discount_type);
        $this->assertSame('first_month', (string) $coupon->discount_scope);
        $this->assertSame('public', (string) $coupon->distribution_type);
        $this->assertSame($result['name'], (string) $coupon->name);

        // 未提交字段保持原值（D5B-01 核心：不得写默认值）
        $this->assertSame(100, (int) $coupon->total_usage_limit);
        $this->assertSame(3, (int) $coupon->per_user_limit);
        $this->assertSame(CouponStatus::DISABLED, (int) $coupon->status);
        $this->assertSame(7, (int) $coupon->sort_order);
        $this->assertSame('2026-01-01 00:00:00', $coupon->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-31 23:59:59', $coupon->expires_at?->format('Y-m-d H:i:s'));
        $this->assertSame('原备注', (string) $coupon->remark);
        $this->assertSame('原描述', (string) $coupon->description);
        $this->assertSame(50.0, (float) $coupon->min_amount);
        $this->assertSame(30.0, (float) $coupon->max_discount_amount);
        $this->assertSame(['monthly', 'quarterly'], $coupon->billing_cycles);
        $this->assertSame([11, 22], $coupon->product_ids);
        $this->assertTrue((bool) $coupon->first_order_only);

        // 响应侧同样保持原值
        $this->assertSame(100, $result['total_usage_limit']);
        $this->assertSame(3, $result['per_user_limit']);
        $this->assertSame(CouponStatus::DISABLED, $result['status']);
        $this->assertSame('2026-01-01 00:00:00', $result['starts_at']);
        $this->assertSame('2026-12-31 23:59:59', $result['expires_at']);
        $this->assertSame('原备注', $result['remark']);
    }

    public function test_partial_update_explicit_values_take_effect(): void
    {
        $service = app(CouponService::class);
        $coupon = $this->makeCoupon([
            'name' => '显式提交生效'.uniqid(),
            'code' => 'EV'.uniqid(),
            'distribution_type' => 'public',
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 10,
            'total_usage_limit' => 100,
            'per_user_limit' => 3,
            'status' => CouponStatus::DISABLED,
            'starts_at' => '2026-01-01 00:00:00',
            'expires_at' => '2026-12-31 23:59:59',
            'remark' => '原备注',
        ]);

        // 显式提交空值表示清空、显式提交新值表示修改——均应生效
        $service->updateCoupon($coupon, [
            'name' => (string) $coupon->name,
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 10,
            'distribution_type' => 'public',
            'total_usage_limit' => null,
            'per_user_limit' => null,
            'status' => CouponStatus::ACTIVE,
            'starts_at' => null,
            'expires_at' => null,
            'remark' => null,
        ], ['operator' => 'tester']);

        $coupon->refresh();

        $this->assertNull($coupon->total_usage_limit);
        $this->assertNull($coupon->per_user_limit);
        $this->assertSame(CouponStatus::ACTIVE, (int) $coupon->status);
        $this->assertNull($coupon->starts_at);
        $this->assertNull($coupon->expires_at);
        $this->assertNull($coupon->remark);
    }

    public function test_partial_update_keeps_campaign_binding(): void
    {
        $service = app(CouponService::class);
        // coupons.coupon_campaign_id 有指向 coupon_campaigns 的外键，先造真实活动行
        $campaign = CouponCampaign::query()->create([
            'name' => '部分更新绑定活动'.uniqid(),
            'trigger_time' => '09:00:00',
            'discount_type' => 'fixed',
            'discount_value' => 10,
            'status' => 1,
            'issue_quantity' => 10,
        ]);
        $coupon = $this->makeCoupon([
            'name' => '活动券部分更新'.uniqid(),
            'code' => 'CP'.uniqid(),
            'coupon_campaign_id' => (int) $campaign->id,
            'distribution_type' => 'public',
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 10,
            'total_usage_limit' => 50,
            'status' => CouponStatus::ACTIVE,
        ]);

        // 编辑表单不提交 coupon_campaign_id，更新后不得脱钩（D5B-01：活动券编辑锁随之失效的风险点）
        $result = $service->updateCoupon($coupon, [
            'name' => (string) $coupon->name,
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 10,
            'distribution_type' => 'public',
            'remark' => '更新备注',
        ], ['operator' => 'tester']);

        $coupon->refresh();

        $this->assertSame((int) $campaign->id, (int) $coupon->coupon_campaign_id);
        $this->assertSame((int) $campaign->id, $result['coupon_campaign_id']);
        $this->assertSame(50, (int) $coupon->total_usage_limit);
    }

    public function test_partial_update_keeps_private_recipients_when_user_ids_absent(): void
    {
        $service = app(CouponService::class);
        $coupon = $this->makeCoupon([
            'name' => '私有券部分更新'.uniqid(),
            'code' => 'PV'.uniqid(),
            'distribution_type' => 'private',
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 10,
            'status' => CouponStatus::ACTIVE,
        ]);

        $userA = $this->makeUser('pva');
        $userB = $this->makeUser('pvb');
        foreach ([$userA, $userB] as $user) {
            UserCoupon::query()->create([
                'coupon_id' => $coupon->id,
                'user_id' => (int) $user->id,
                'receive_type' => 'grant',
                'status' => UserCouponStatus::OWNED,
                'granted_at' => now(),
            ]);
        }

        // 未提交 user_ids：保持现有发放对象不变，且不触发「必须选择用户」校验
        $service->updateCoupon($coupon, [
            'name' => (string) $coupon->name,
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 20,
            'distribution_type' => 'private',
        ], ['operator' => 'tester']);

        $remaining = UserCoupon::query()
            ->where('coupon_id', $coupon->id)
            ->where('receive_type', 'grant')
            ->where('status', UserCouponStatus::OWNED)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame([(int) $userA->id, (int) $userB->id], $remaining);
        $this->assertSame(20.0, (float) $coupon->refresh()->discount_value);
    }

    public function test_admin_transform_returns_grant_user_ids_only(): void
    {
        $service = app(CouponService::class);
        $coupon = $this->makeCoupon([
            'name' => '发放用户回显'.uniqid(),
            'code' => 'GU'.uniqid(),
            'distribution_type' => 'private',
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 10,
            'status' => CouponStatus::ACTIVE,
        ]);

        $userA = $this->makeUser('gua');
        $userB = $this->makeUser('gub');
        $userC = $this->makeUser('guc');

        // 两个「发放型」用户 + 一个「领取型（claim）」记录：user_ids 契约只含发放型
        foreach ([$userA, $userB] as $user) {
            UserCoupon::query()->create([
                'coupon_id' => $coupon->id,
                'user_id' => (int) $user->id,
                'receive_type' => 'grant',
                'status' => UserCouponStatus::OWNED,
                'granted_at' => now(),
            ]);
        }
        UserCoupon::query()->create([
            'coupon_id' => $coupon->id,
            'user_id' => (int) $userC->id,
            'receive_type' => 'claim',
            'status' => UserCouponStatus::OWNED,
            'claimed_at' => now(),
        ]);

        $expectedIds = [(int) $userA->id, (int) $userB->id];

        // 详情/更新路径（单券按需查询）
        $detail = $service->updateCoupon($coupon, [
            'name' => (string) $coupon->name,
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 10,
            'distribution_type' => 'private',
            'user_ids' => $expectedIds,
        ], ['operator' => 'tester']);
        $this->assertEqualsCanonicalizing($expectedIds, $detail['user_ids']);
        $this->assertSame(3, $detail['recipient_count']);

        // 列表路径（按页批量查询）
        $list = $service->adminList(['keyword' => (string) $coupon->name]);
        $item = collect($list->items())->firstOrFail(fn (array $row) => (int) $row['id'] === (int) $coupon->id);
        $this->assertEqualsCanonicalizing($expectedIds, $item['user_ids']);
        $this->assertSame(3, $item['recipient_count']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeCoupon(array $overrides = []): Coupon
    {
        return Coupon::query()->create([
            ...$overrides,
        ]);
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
}
