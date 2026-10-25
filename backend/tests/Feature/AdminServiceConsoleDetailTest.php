<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Product;
use App\Models\Role;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ClientServiceConsole\ServiceDetailService;
use App\Support\AdminPermissions;
use Caiwu\Plugins\Servers\DemoServers\DemoServersPlugin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 管理端服务控制台详情页的两段式契约：
 * 1. sync_remote=0 只用本地快照渲染，不打上游；
 * 2. 缺省仍同步上游（保持旧行为，供「刷新远程」与其它调用方使用）；
 * 3. 上游数据由 /remote-status 补丁补齐。
 * 用 demo_servers 插件构造可控上游，全程零网络。
 */
class AdminServiceConsoleDetailTest extends TestCase
{
    use DatabaseTransactions;

    private int $pluginId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginId = (int) DB::table('integration_plugins')->insertGetId([
            'domain' => 'upstream',
            'slug' => 'demo_servers',
            'plugin_key' => 'demo_servers',
            'name' => 'Demo 上游服务',
            'entry_class' => DemoServersPlugin::class,
            'status' => 1,
        ]);
    }

    public function test_sync_remote_disabled_returns_local_snapshot_without_touching_upstream(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        [$user, $service] = $this->makeBoundService();

        $response = $this->getJson("/api/v2/admin/users/{$user->id}/services/{$service->id}?sync_remote=0");

        $response->assertOk()->assertJsonPath('code', 0);
        // 本地快照只有绑定快照里的 status_snapshot，同步上游后才会变成 Active
        $response->assertJsonPath('data.service.upstream.status', 'active');

        $fresh = $service->refresh();
        $this->assertNull(
            $fresh->provision_data['last_synced_at'] ?? null,
            'sync_remote=0 不应写回上游快照时间'
        );
        $this->assertNull(
            Cache::get($this->remoteStateCacheKey($service)),
            'sync_remote=0 不应产生远程状态缓存'
        );
    }

    public function test_default_request_syncs_remote_state_from_upstream(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        [$user, $service] = $this->makeBoundService();

        $this->getJson("/api/v2/admin/users/{$user->id}/services/{$service->id}")
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.service.upstream.status', 'Active');

        $fresh = $service->refresh();
        $this->assertNotNull($fresh->provision_data['last_synced_at'] ?? null, '缺省请求应同步上游并写回快照时间');
        $this->assertNotNull(Cache::get($this->remoteStateCacheKey($service)), '缺省请求应写入远程状态缓存');
    }

    public function test_remote_status_patch_fills_upstream_fields(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        [$user, $service] = $this->makeBoundService();

        $this->getJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/remote-status")
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.service.upstream.status', 'Active')
            // 补丁里的操作系统来自上游主机详情，本地快照里没有
            ->assertJsonPath('data.service.upstream.os', 'Debian 12')
            ->assertJsonPath('data.service.actions.refresh', true);
    }

    public function test_local_snapshot_writes_scoped_cache_instead_of_authoritative_detail_cache(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        [$user, $service] = $this->makeBoundService();

        $this->getJson("/api/v2/admin/users/{$user->id}/services/{$service->id}?sync_remote=0")
            ->assertOk()
            ->assertJsonPath('data.service.upstream.status', 'active');

        $detailService = app(ServiceDetailService::class);
        $fresh = $service->refresh()->loadMissing('product');
        $this->assertNotEmpty(
            Cache::get($detailService->buildDetailResponseCacheKey($fresh, 'snapshot')),
            '本地快照应写入 snapshot 作用域缓存'
        );
        $this->assertNull(
            Cache::get($detailService->buildDetailResponseCacheKey($fresh)),
            '本地快照不能写入权威 detail 缓存，否则缺省请求会读到未同步上游的旧快照'
        );
    }

    public function test_refresh_with_sync_remote_disabled_is_rejected(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        [$user, $service] = $this->makeBoundService();

        $this->getJson("/api/v2/admin/users/{$user->id}/services/{$service->id}?refresh=1&sync_remote=0")
            ->assertStatus(422);
    }

    public function test_sync_remote_rejects_non_boolean_value(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        [$user, $service] = $this->makeBoundService();

        $this->getJson("/api/v2/admin/users/{$user->id}/services/{$service->id}?sync_remote=not-a-bool")
            ->assertStatus(422);
    }

    /**
     * @return array{0: User, 1: Service}
     */
    private function makeBoundService(): array
    {
        $supplier = Supplier::query()->create([
            'name' => '测试供应商-'.uniqid(),
            'code' => 'SUP-DEMO-'.uniqid(),
        ]);
        $supplierBindingId = (int) DB::table('supplier_plugin_bindings')->insertGetId([
            'supplier_id' => $supplier->id,
            'plugin_id' => $this->pluginId,
            'provider_key' => 'demo_servers',
            'status' => 1,
        ]);

        $user = User::query()->create([
            'email' => 'console-detail-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'console-detail-tester',
            'total_sales_amount' => 0,
        ]);
        $product = Product::query()->create([
            'product_group_id' => null,
            'service_type_code' => 'test',
            'product_type' => 'other',
            'pricing' => ['monthly' => 100.00],
            'setup_fee' => 0,
            'status' => 1,
            'sort_order' => 0,
        ]);
        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'name' => '演示实例',
            'billing_cycle' => 'monthly',
            'amount' => 100.00,
            'status' => 1,
            'expires_at' => now()->addMonth(),
        ]);

        DB::table('service_upstream_bindings')->insert([
            'service_id' => $service->id,
            'plugin_id' => $this->pluginId,
            'provider_key' => 'demo_servers',
            'upstream_service_id' => '89841',
            'supplier_plugin_binding_id' => $supplierBindingId,
            'status_snapshot' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $service->refresh()];
    }

    private function remoteStateCacheKey(Service $service): string
    {
        return 'service_console:remote_state:v2:'.$service->id.':'.
            (int) DB::table('supplier_plugin_bindings')->where('plugin_id', $this->pluginId)->value('supplier_id').':89841';
    }

    private function makeAdmin(): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '服务控制台详情测试角色',
            'permissions' => [AdminPermissions::USER_DETAIL],
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '管理员',
            'status' => 1,
            'role_id' => $role->id,
        ]);
    }
}
