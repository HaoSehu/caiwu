<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Models\AdminUser;
use App\Models\Role;
use App\Services\Messaging\MessageConfigurationRepository;
use App\Support\AdminPermissions;
use App\Support\MessageGroups;
use App\Support\MessageTypeCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 管理端「消息配置」场景接口契约回归（对齐 howstack /api/admin/v1/message-scenes）：
 * 1. 列表按受众返回固定场景目录；
 * 2. 详情给出接收组、渠道选项与场景模板；
 * 3. 保存走修订号，冲突返回 409；
 * 4. 批量配置只改渠道开关。
 * 权限沿用 settings.view / settings.manage（计划决策日志 2026-11-03）。
 */
class AdminMessageSceneTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_lists_customer_scenes_with_metadata(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::SETTINGS_VIEW]));

        $response = $this->getJson('/api/v2/admin/message-scenes?audience=customer')
            ->assertOk();

        $rows = $response->json('data.data');
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);

        $codes = array_column($rows, 'code');
        $this->assertContains('order_paid', $codes);
        $this->assertContains('register_verification', $codes);

        $verification = collect($rows)->firstWhere('code', 'register_verification');
        $this->assertSame('verification', $verification['purpose']);
        $this->assertSame('注册验证码', $verification['name']);
    }

    public function test_show_returns_groups_and_templates(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::SETTINGS_VIEW]));

        $detail = $this->getJson('/api/v2/admin/message-scenes/order_paid?audience=customer')
            ->assertOk()
            ->json('data');

        $this->assertSame('order_paid', $detail['code']);
        $this->assertSame('customer', $detail['groups'][0]['kind']);
        $this->assertContains('email', $detail['metadata']['customer']['channels']);
        // group_templates 以组类型为键（客户场景只有 customer）
        $this->assertArrayHasKey('customer', $detail['group_templates']);
        $this->assertArrayHasKey('email', $detail['group_templates']['customer']['configurations']);
    }

    public function test_save_bumps_revision_and_rejects_stale_revision(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::SETTINGS_MANAGE]));

        // 测试库可能已有真实配置：先清掉，让修订号从 0 开始
        DB::table('message_configurations')
            ->where('audience', 'customer')
            ->whereIn('message_type', ['order_paid', 'service_activated'])
            ->delete();

        $type = MessageTypeCatalog::get('customer', 'order_paid');
        $group = MessageGroups::create($type, 'customer', 'customer', '关联客户');
        $group['enabled'] = true;
        $group['configurations']['email']['enabled'] = true;
        $group['configurations']['email']['channel_id'] = 1;

        $payload = [
            'audience' => 'customer',
            'revisions' => ['customer' => 0],
            'groups' => [$group],
        ];

        $first = $this->putJson('/api/v2/admin/message-scenes/order_paid', $payload)
            ->assertOk();

        $this->assertSame(1, $first->json('data.revisions.customer'));

        $this->putJson('/api/v2/admin/message-scenes/order_paid', $payload)
            ->assertStatus(409);
    }

    public function test_batch_toggles_channel_enabled_flag(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::SETTINGS_MANAGE]));

        DB::table('message_configurations')
            ->where('audience', 'customer')
            ->whereIn('message_type', ['order_paid', 'service_activated'])
            ->delete();

        $response = $this->postJson('/api/v2/admin/message-scenes/batch', [
            'audience' => 'customer',
            'channel' => 'email',
            'enabled' => false,
            'items' => [
                ['code' => 'order_paid', 'revision' => 0],
                ['code' => 'service_activated', 'revision' => 0],
            ],
        ])->assertOk();

        $this->assertSame(2, $response->json('data.updated'));

        $detail = $this->getJson('/api/v2/admin/message-scenes/order_paid?audience=customer')
            ->assertOk()
            ->json('data');

        foreach ($detail['groups'] as $group) {
            if (isset($group['configurations']['email'])) {
                $this->assertFalse($group['configurations']['email']['enabled']);
            }
        }
    }

    /**
     * 存量配置缺渠道时（历史回填早于模板补齐），批量配置必须先按场景目录补齐，
     * 否则勾选该渠道会被静默跳过——这是「批量配置不管用」的真实成因。
     */
    public function test_batch_works_when_stored_config_missing_a_channel(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::SETTINGS_MANAGE]));

        // 造一份只有 email、没有 sms 的存量配置，模拟历史数据
        $repository = app(MessageConfigurationRepository::class);
        $type = MessageTypeCatalog::get('customer', 'order_paid');
        $group = MessageGroups::create($type, 'customer', 'customer', '关联客户');
        unset($group['configurations']['sms']);
        $group['enabled'] = true;
        $group['configurations']['email']['enabled'] = true;

        $revision = $repository->save('customer', 'order_paid', (int) $repository->get('customer', 'order_paid')['revision'], ['_groups' => [$group]]);

        // 批量启用短信
        $this->postJson('/api/v2/admin/message-scenes/batch', [
            'audience' => 'customer',
            'channel' => 'sms',
            'enabled' => true,
            'items' => [['code' => 'order_paid', 'revision' => $revision]],
        ])->assertOk();

        $detail = $this->getJson('/api/v2/admin/message-scenes/order_paid?audience=customer')
            ->assertOk()
            ->json('data');

        $smsEnabled = false;
        foreach ($detail['groups'] as $savedGroup) {
            if (isset($savedGroup['configurations']['sms'])) {
                $smsEnabled = $smsEnabled || $savedGroup['configurations']['sms']['enabled'] === true;
            }
        }

        $this->assertTrue($smsEnabled, '批量启用短信必须生效，即使存量配置里原本没有 sms 键');
    }

    public function test_view_permission_cannot_write(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::SETTINGS_VIEW]));

        $this->putJson('/api/v2/admin/message-scenes/order_paid', [
            'audience' => 'customer',
            'revisions' => ['customer' => 0],
            'groups' => [],
        ])->assertStatus(403);
    }

    private function makeAdmin(array $permissions): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '消息配置测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '消息配置测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
