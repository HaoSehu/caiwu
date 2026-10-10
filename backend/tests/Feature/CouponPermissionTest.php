<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 券权限点拆分负向回归（2026-10-10 卡片裁决：券生命周期权限与商品目录解耦）。
 *
 * 锁定对外契约：
 * 1. 无 coupon.manage 访问券写接口 → 403（40300）；持 coupon.manage → 放行；
 * 2. 无 coupon.list 访问券读接口 → 403；持 coupon.list → 放行；
 * 3. 仅持 PRODUCT_MANAGE 不再越权访问券接口（解耦生效，杜绝「点后 403」源头）。
 *
 * 使用 DatabaseTransactions，自造管理员/角色，测试结束回滚，不依赖库内残留数据。
 */
class CouponPermissionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creating_coupon_requires_coupon_manage(): void
    {
        Sanctum::actingAs($this->makeAdmin([]));

        $this->postJson('/api/v2/admin/coupons', $this->couponPayload())
            ->assertStatus(403)
            ->assertJsonPath('code', 40300);
    }

    public function test_creating_coupon_passes_with_coupon_manage(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::COUPON_MANAGE]));

        $this->postJson('/api/v2/admin/coupons', $this->couponPayload())
            ->assertOk()
            ->assertJsonPath('message', '优惠券创建成功');
    }

    public function test_listing_coupons_requires_coupon_list(): void
    {
        Sanctum::actingAs($this->makeAdmin([]));

        $this->getJson('/api/v2/admin/coupons')
            ->assertStatus(403)
            ->assertJsonPath('code', 40300);
    }

    public function test_listing_coupons_passes_with_coupon_list(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::COUPON_LIST]));

        $this->getJson('/api/v2/admin/coupons')->assertOk();
    }

    public function test_product_manage_alone_no_longer_grants_coupon_access(): void
    {
        // 解耦关键断言：商品目录权限不得横向扩散到券域
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::PRODUCT_MANAGE]));

        $this->getJson('/api/v2/admin/coupons')
            ->assertStatus(403)
            ->assertJsonPath('code', 40300);

        $this->postJson('/api/v2/admin/coupons', $this->couponPayload())
            ->assertStatus(403)
            ->assertJsonPath('code', 40300);
    }

    /**
     * @return array<string, mixed>
     */
    private function couponPayload(): array
    {
        return [
            'name' => '权限测试券'.uniqid(),
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
            'distribution_type' => 'public',
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '券权限负向测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '券权限测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
