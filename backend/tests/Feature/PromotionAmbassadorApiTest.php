<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\PromotionAmbassador;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 推广大使管理端 API：鉴权/权限/校验/读写/删除守卫。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class PromotionAmbassadorApiTest extends TestCase
{
    use DatabaseTransactions;

    private function makeAdmin(array $permissions = ['promotion_ambassador.list', 'promotion_ambassador.manage']): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '管理员',
            'status' => 1,
            'role_id' => $role->id,
        ]);
    }

    private function makeAmbassador(array $overrides = []): PromotionAmbassador
    {
        return PromotionAmbassador::query()->create(array_merge([
            'name' => '大使'.uniqid(),
            'reward_rate' => 5.00,
            'status' => 1,
        ], $overrides));
    }

    public function test_requires_admin_authentication(): void
    {
        $this->getJson('/api/v2/admin/promotion-ambassadors')
            ->assertStatus(401);
    }

    public function test_requires_permission(): void
    {
        $admin = $this->makeAdmin(permissions: []);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v2/admin/promotion-ambassadors')
            ->assertStatus(403)
            ->assertJson(fn (AssertableJson $json) => $json->where('code', 40300)->etc());
    }

    public function test_index_lists_ambassadors(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $ambassador = $this->makeAmbassador(['reward_rate' => 12.5]);

        $this->getJson('/api/v2/admin/promotion-ambassadors')
            ->assertStatus(200)
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.list', fn ($list) => collect($list)->contains(
                    fn (array $item) => (int) $item['id'] === (int) $ambassador->id
                        && $item['reward_rate'] === '12.50'
                ))
                ->etc());
    }

    public function test_validation_rejects_rate_out_of_range(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v2/admin/promotion-ambassadors', [
            'name' => '超标大使',
            'reward_rate' => 120,
            'status' => 1,
        ])
            ->assertStatus(422)
            ->assertJson(fn (AssertableJson $json) => $json->where('code', 42200)->etc());
    }

    public function test_validation_rejects_duplicate_name(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $existing = $this->makeAmbassador();

        $this->postJson('/api/v2/admin/promotion-ambassadors', [
            'name' => $existing->name,
            'reward_rate' => 5,
            'status' => 1,
        ])
            ->assertStatus(422);
    }

    public function test_store_creates_ambassador(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v2/admin/promotion-ambassadors', [
            'name' => '推广大使'.uniqid(),
            'reward_rate' => 8.88,
            'status' => 1,
            'remark' => '测试档位',
        ])
            ->assertStatus(200)
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('code', 0)
                ->where('data.reward_rate', '8.88')
                ->etc());

        $this->assertSame(1, PromotionAmbassador::query()->where('remark', '测试档位')->count());
    }

    public function test_update_changes_rate(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $ambassador = $this->makeAmbassador();

        $this->putJson("/api/v2/admin/promotion-ambassadors/{$ambassador->id}", [
            'name' => $ambassador->name,
            'reward_rate' => 15,
            'status' => 1,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.reward_rate', '15.00');

        $this->assertSame('15.00', (string) $ambassador->refresh()->reward_rate);
    }

    public function test_destroy_blocks_ambassador_with_users(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $ambassador = $this->makeAmbassador();

        User::query()->create([
            'email' => 'pa_'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'pa-tester',
            'promotion_ambassador_id' => $ambassador->id,
        ]);

        $this->deleteJson("/api/v2/admin/promotion-ambassadors/{$ambassador->id}")
            ->assertStatus(422);
    }

    public function test_destroy_removes_unused_ambassador(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $ambassador = $this->makeAmbassador();

        $this->deleteJson("/api/v2/admin/promotion-ambassadors/{$ambassador->id}")
            ->assertStatus(200);

        $this->assertNull(PromotionAmbassador::query()->find($ambassador->id));
    }
}
