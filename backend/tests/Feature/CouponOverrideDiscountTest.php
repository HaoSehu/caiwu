<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\AdminUser;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCoupon;
use App\Services\Finance\CouponService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * G-1 覆盖价券（override）契约测试（2026-10-10 卡片裁决：保守 0.01 口径）。
 *
 * 锁定：
 * 1. 覆盖价即用户实付（应付=覆盖价），减免额=原价-覆盖价；
 * 2. 最低应收 0.01（与现封顶 amount-0.01 一致，不产生 0 元账单）；
 * 3. 覆盖价 ≥ 原价时无优惠，视为不可用（预览抛异常、可用列表剔除）；
 * 4. 非 override 类型回归不受影响；
 * 5. 请求层覆盖价 < 0.01 被拒。
 *
 * 使用 DatabaseTransactions，自造券/用户/商品，测试结束回滚。
 */
class CouponOverrideDiscountTest extends TestCase
{
    use DatabaseTransactions;

    public function test_override_preview_uses_cover_price_as_payable(): void
    {
        [$user, $userCoupon, $product] = $this->fixture('override', 10.0);

        $payload = app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new'
        );

        $this->assertIsArray($payload);
        $this->assertSame('override', $payload['discount_type']);
        $this->assertSame('90.00', $payload['discount_amount'], '减免额应为 原价-覆盖价');
        $this->assertSame('10.00', $payload['final_amount'], '应付应等于覆盖价');
        $this->assertSame('一口价 ¥10.00', $payload['discount_label']);
    }

    public function test_override_floors_payable_at_one_cent(): void
    {
        [$user, $userCoupon, $product] = $this->fixture('override', 0.01);

        $payload = app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new'
        );

        $this->assertIsArray($payload);
        $this->assertSame('99.99', $payload['discount_amount'], '减免额封顶为 原价-0.01');
        $this->assertSame('0.01', $payload['final_amount'], '最低应收 0.01，不产生 0 元账单');
    }

    public function test_override_is_unusable_when_cover_not_lower_than_amount(): void
    {
        [$user, $userCoupon, $product] = $this->fixture('override', 200.0);

        // 覆盖价 ≥ 原价：无优惠，预览抛出「不可用」
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('当前优惠券不可用');

        app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new'
        );
    }

    public function test_available_list_includes_override_and_excludes_non_discounting_override(): void
    {
        [$user, $userCoupon, $product] = $this->fixture('override', 10.0);

        $available = app(CouponService::class)->availableCouponsForCheckout(
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new'
        );

        $this->assertCount(1, $available);
        $this->assertSame((int) $userCoupon->id, (int) $available[0]['id']);
        $this->assertSame('10.00', (string) $available[0]['final_amount']);

        // 覆盖价高于原价：列表剔除（选中后结账必败的同型问题不得引入）
        $userCoupon->coupon->forceFill(['discount_value' => 200.0])->save();

        $availableAfter = app(CouponService::class)->availableCouponsForCheckout(
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new'
        );

        $this->assertSame([], $availableAfter);
    }

    public function test_non_override_types_regression(): void
    {
        [$user, $userCoupon, $product] = $this->fixture('fixed', 5.0);

        $payload = app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new'
        );

        $this->assertIsArray($payload);
        $this->assertSame('5.00', $payload['discount_amount']);
        $this->assertSame('95.00', $payload['final_amount']);
        $this->assertSame('立减 ¥5.00', $payload['discount_label']);
    }

    public function test_request_rejects_override_cover_below_one_cent(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->postJson('/api/v2/admin/coupons', [
            'name' => '覆盖价下限券'.uniqid(),
            'discount_type' => 'override',
            'discount_scope' => 'first_month',
            'discount_value' => 0,
            'distribution_type' => 'public',
        ])->assertStatus(422);

        // 合法下限 0.01 放行
        $this->postJson('/api/v2/admin/coupons', [
            'name' => '覆盖价下限券'.uniqid(),
            'discount_type' => 'override',
            'discount_scope' => 'first_month',
            'discount_value' => 0.01,
            'distribution_type' => 'public',
        ])->assertOk();
    }

    /**
     * @return array{0: User, 1: UserCoupon, 2: Product}
     */
    private function fixture(string $discountType, float $discountValue): array
    {
        $user = User::factory()->create();
        $coupon = app(CouponService::class)->createCoupon([
            'name' => '覆盖价券'.uniqid(),
            'code' => 'OVERRIDE'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => $discountType,
            'discount_scope' => 'first_month',
            'discount_value' => $discountValue,
            'min_amount' => 0,
            'status' => 1,
        ], ['operator' => 'tester']);

        $userCoupon = UserCoupon::query()
            ->where('user_id', (int) $user->id)
            ->where('coupon_id', (int) $coupon['id'])
            ->firstOrFail();

        $product = Product::query()->create([
            'name' => '覆盖价商品'.uniqid(),
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '100.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'auto_setup' => 0,
        ]);

        return [$user, $userCoupon, $product];
    }

    private function makeAdmin(): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '覆盖价测试角色',
            'permissions' => [AdminPermissions::COUPON_MANAGE],
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '覆盖价测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
