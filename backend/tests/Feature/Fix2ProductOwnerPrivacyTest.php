<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ProductType;
use App\Constants\ServiceStatus;
use App\Models\AdminUser;
use App\Models\Product;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M-84：商品业主列表的隐私输出与关键词检索守卫。
 *
 * product.list 是 visitor 默认角色持有的权限；此前列表原样下发 email/phone，
 * 且 keyword 经 User::scopeSearch 可命中邮箱/手机号，等于给无隐私权限的账号
 * 留了明文反查口子。本用例锁定：
 * 1. 无 privacy.view_raw 时列表 email/phone 必须是脱敏值；
 * 2. 关键词里夹带邮箱等隐私线索时请求被 422 拒绝；
 * 3. 持 privacy.view_raw 的账号不受影响（明文 + 关键词检索均可用）。
 */
class Fix2ProductOwnerPrivacyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_list_masks_email_and_phone_without_raw_privacy_permission(): void
    {
        $product = $this->makeProduct();
        $owner = $this->makeOwner($product);

        Sanctum::actingAs($this->makeAdmin([AdminPermissions::PRODUCT_LIST]));

        $this->getJson('/api/v2/admin/products/'.$product->id.'/owners?page=1&page_size=20')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.email', 'm***@example.com')
            ->assertJsonPath('data.list.0.phone', '138****8000')
            // 昵称不可读时 display_name 回落到 email，同样不能明文下发。
            ->assertJsonPath('data.list.0.display_name', 'm***@example.com');
    }

    public function test_owner_list_rejects_privacy_keyword_without_raw_privacy_permission(): void
    {
        $product = $this->makeProduct();
        $this->makeOwner($product);

        Sanctum::actingAs($this->makeAdmin([AdminPermissions::PRODUCT_LIST]));

        $this->getJson('/api/v2/admin/products/'.$product->id.'/owners?keyword=masked.owner@example.com')
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('data.errors.keyword.0', '缺少原始隐私查看权限，不能使用邮箱、手机号、证件号等隐私关键词筛选');
    }

    public function test_owner_list_keeps_raw_values_and_keyword_search_for_raw_privacy_admin(): void
    {
        $product = $this->makeProduct();
        $this->makeOwner($product);

        Sanctum::actingAs($this->makeAdmin([
            AdminPermissions::PRODUCT_LIST,
            AdminPermissions::PRIVACY_VIEW_RAW,
        ]));

        $this->getJson('/api/v2/admin/products/'.$product->id.'/owners?keyword=masked.owner@example.com')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.email', 'masked.owner@example.com')
            ->assertJsonPath('data.list.0.phone', '13800138000');
    }

    private function makeOwner(Product $product): User
    {
        $owner = User::factory()->create([
            'email' => 'masked.owner@example.com',
            'phone' => '13800138000',
            // 不可读昵称会让 display_name 回落到 email，用于覆盖同响应内的旁路泄漏。
            'nickname' => '???',
        ]);

        Service::query()->create([
            'user_id' => $owner->id,
            'product_id' => $product->id,
            'name' => 'owner-svc-'.uniqid(),
            'billing_cycle' => 'monthly',
            'amount' => '10.00',
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        return $owner;
    }

    private function makeProduct(): Product
    {
        return Product::query()->create([
            'product_type' => ProductType::CLOUD_SERVER,
            'service_type_code' => ProductType::CLOUD_SERVER,
            'status' => 1,
            'stock' => -1,
            'pricing' => ['monthly' => '10.00'],
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '商品业主隐私测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '商品业主隐私测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
