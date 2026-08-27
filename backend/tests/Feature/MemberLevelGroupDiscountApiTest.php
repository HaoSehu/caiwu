<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\MarketingProductGroup;
use App\Models\MemberLevel;
use App\Models\MemberLevelGroupDiscount;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 会员折扣矩阵管理端 API：鉴权/权限/校验/读写/删除行。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class MemberLevelGroupDiscountApiTest extends TestCase
{
    use DatabaseTransactions;

    private function makeAdmin(array $permissions = ['member_level.list', 'member_level.manage']): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '测试角色',
            'permissions' => $permissions,
        ]);

        $admin = AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '管理员',
            'status' => 1,
            'role_id' => $role->id,
        ]);

        return $admin;
    }

    private function makeLevel(): MemberLevel
    {
        return MemberLevel::query()->create([
            'name' => '矩阵等级'.uniqid(),
            'status' => 1,
        ]);
    }

    private function makeGroup(string $name = '营销组'): MarketingProductGroup
    {
        return MarketingProductGroup::query()->create([
            'name' => $name.uniqid(),
            'sort_order' => 0,
        ]);
    }

    public function test_requires_admin_authentication(): void
    {
        $level = $this->makeLevel();

        $this->getJson("/api/v2/admin/member-levels/{$level->id}/group-discounts")
            ->assertStatus(401);
    }

    public function test_requires_permission(): void
    {
        $admin = $this->makeAdmin(permissions: []);
        Sanctum::actingAs($admin);
        $level = $this->makeLevel();

        $this->getJson("/api/v2/admin/member-levels/{$level->id}/group-discounts")
            ->assertStatus(403)
            ->assertJson(fn (AssertableJson $json) => $json->where('code', 40300)->etc());
    }

    public function test_validation_rejects_invalid_discount_value(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $level = $this->makeLevel();
        $group = $this->makeGroup();

        $this->putJson("/api/v2/admin/member-levels/{$level->id}/group-discounts", [
            'rules' => [
                [
                    'marketing_product_group_id' => $group->id,
                    'discount_type' => 1,
                    'discount_value' => 120, // 百分比 > 100 非法
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJson(fn (AssertableJson $json) => $json->where('code', 42200)->etc());
    }

    public function test_index_returns_groups_with_assigned_rule(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $level = $this->makeLevel();
        $group = $this->makeGroup();

        MemberLevelGroupDiscount::query()->create([
            'member_level_id' => $level->id,
            'marketing_product_group_id' => $group->id,
            'discount_type' => MemberLevelGroupDiscount::TYPE_PERCENT,
            'discount_value' => 90,
        ]);

        $this->getJson("/api/v2/admin/member-levels/{$level->id}/group-discounts")
            ->assertOk()
            ->assertJsonPath('data.member_level.name', $level->name)
            ->assertJsonPath('data.groups.0.discount.discount_type', 1)
            ->assertJsonPath('data.groups.0.discount.discount_value', '90.00');
    }

    public function test_sync_replaces_rules_and_discount_takes_effect(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $level = $this->makeLevel();
        $group = $this->makeGroup();

        $this->putJson("/api/v2/admin/member-levels/{$level->id}/group-discounts", [
            'rules' => [
                [
                    'marketing_product_group_id' => $group->id,
                    'discount_type' => 2,
                    'discount_value' => 20,
                ],
            ],
        ])
            ->assertOk();

        // 回读矩阵行数
        $this->assertSame(1, MemberLevelGroupDiscount::query()
            ->where('member_level_id', $level->id)
            ->count());

        // 空包清空矩阵
        $this->putJson("/api/v2/admin/member-levels/{$level->id}/group-discounts", ['rules' => []])
            ->assertOk();
        $this->assertSame(0, MemberLevelGroupDiscount::query()
            ->where('member_level_id', $level->id)
            ->count());
    }
}
