<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\IntegrationPlugin;
use App\Models\Role;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 媒体库存储区候选端点回归：
 * 1. 无可用远程插件时，列表恒含 local（本地存储）且不影响既有媒体库链路；
 * 2. 未启用或未声明 media.storage 能力的插件不出现在候选列表；
 * 3. 启用且声明 media.storage 的 addons 插件出现在候选列表，并携带显示名。
 */
class AdminMediaStorageDriverTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs($this->makeAdmin([AdminPermissions::CONTENT_LIST]));
    }

    public function test_drivers_endpoint_returns_local_when_no_remote_plugin_enabled(): void
    {
        $response = $this->getJson('/api/v2/admin/media-storage-drivers');

        $response->assertOk();
        $response->assertJsonPath('data.drivers.0.slug', 'local');
        $response->assertJsonPath('data.drivers.0.name', '本地存储');

        $slugs = array_column($response->json('data.drivers'), 'slug');
        $this->assertNotContains('ftp', $slugs);
    }

    public function test_drivers_endpoint_ignores_plugin_without_media_storage_capability(): void
    {
        IntegrationPlugin::query()->create([
            'domain' => PluginDomain::ADDONS,
            'slug' => 'media-cap-neg',
            'plugin_key' => 'media_cap_neg',
            'name' => '无能力占位插件',
            'entry_class' => 'App\\Plugins\\Demo',
            'status' => IntegrationPlugin::STATUS_ENABLED,
            'capabilities_json' => ['other.capability'],
        ]);

        $response = $this->getJson('/api/v2/admin/media-storage-drivers');

        $response->assertOk();
        $slugs = array_column($response->json('data.drivers'), 'slug');
        $this->assertNotContains('media-cap-neg', $slugs);
    }

    public function test_drivers_endpoint_lists_enabled_media_storage_plugin(): void
    {
        IntegrationPlugin::query()->create([
            'domain' => PluginDomain::ADDONS,
            'slug' => 'ftp',
            'plugin_key' => 'ftp',
            'name' => 'FTP 远程存储',
            'entry_class' => 'App\\Plugins\\Demo',
            'status' => IntegrationPlugin::STATUS_ENABLED,
            'capabilities_json' => ['media.storage'],
        ]);

        $response = $this->getJson('/api/v2/admin/media-storage-drivers');

        $response->assertOk();
        $drivers = $response->json('data.drivers');
        $slugs = array_column($drivers, 'slug');
        $this->assertContains('ftp', $slugs);

        $ftp = array_values(array_filter($drivers, fn (array $driver): bool => $driver['slug'] === 'ftp'))[0];
        $this->assertSame('FTP 远程存储', $ftp['name']);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '媒体库存储区测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '媒体库存储区测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
