<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\IntegrationPlugin;
use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Services\Integrations\Plugins\IntegrationPluginService;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Integrations\Plugins\PluginInstaller;
use App\Services\Integrations\Plugins\PluginRuntimeRegistry;
use App\Services\Integrations\Plugins\PluginScanner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PluginLifecycleCorePathTest extends TestCase
{
    use DatabaseTransactions;

    public function test_config_secret_roundtrip_and_empty_submit_preserves_old_secret(): void
    {
        $installer = app(PluginInstaller::class);
        $scanner = app(PluginScanner::class);
        $repository = app(PluginConfigRepository::class);

        $plugin = $installer->install('payment', 'demo_pay');
        $manifest = $scanner->requireManifest('payment', 'demo_pay');

        $repository->save($plugin, $manifest, ['merchant_id' => 'm-001', 'secret_key' => 's3cret']);

        // 解密口径能拿到密钥，展示口径只回 presence 不回明文。
        $resolved = $repository->resolvedConfig($plugin->fresh() ?? $plugin);
        $this->assertSame('m-001', $resolved['merchant_id']);
        $this->assertSame('s3cret', $resolved['secret_key']);

        $display = $repository->displayConfig($plugin->fresh() ?? $plugin);
        $this->assertArrayNotHasKey('secret_key', $display['config']);
        $this->assertTrue((bool) ($display['has_secret_values']['secret_key'] ?? false));

        // 空提交表示保留旧密钥（平台统一语义，secret_input 前端靠它实现不回显编辑）。
        $repository->save($plugin->fresh() ?? $plugin, $manifest, ['merchant_id' => 'm-002', 'secret_key' => '']);

        $resolvedAgain = $repository->resolvedConfig($plugin->fresh() ?? $plugin);
        $this->assertSame('m-002', $resolvedAgain['merchant_id']);
        $this->assertSame('s3cret', $resolvedAgain['secret_key']);
    }

    public function test_single_enabled_domain_blocks_second_enable(): void
    {
        $installer = app(PluginInstaller::class);
        $scanner = app(PluginScanner::class);
        $repository = app(PluginConfigRepository::class);

        $smtp = $installer->install('mail', 'smtp');
        $installer->enable($smtp);

        $demo = $installer->install('mail', 'demo_mail');
        $demoManifest = $scanner->requireManifest('mail', 'demo_mail');
        $repository->save($demo, $demoManifest, ['from_address' => 'demo@example.test', 'from_name' => 'Demo']);

        try {
            $installer->enable($demo);
            $this->fail('单启用域的第二个插件应被拒绝启用');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('请先停用', $exception->getMessage());
        }

        $this->assertTrue(($smtp->fresh() ?? $smtp)->isEnabled());
        $this->assertFalse(($demo->fresh() ?? $demo)->isEnabled());
    }

    public function test_uninstall_blocked_by_references_then_force_deletes(): void
    {
        $installer = app(PluginInstaller::class);
        $service = app(IntegrationPluginService::class);

        $plugin = $installer->install('payment', 'demo_pay');
        $pluginId = (int) $plugin->id;

        DB::table('integration_plugin_bindings')->insert([
            'domain' => 'payment',
            'plugin_id' => $pluginId,
            'binding_type' => 'global',
            'bindable_type' => 'setting',
            'bindable_id' => 0,
            'binding_key' => 'lifecycle-test-binding',
            'provider_key' => 'demo_pay',
            'priority' => 0,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $service->uninstall($plugin);
            $this->fail('存在业务引用时应拒绝卸载');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('仍被业务数据引用', $exception->getMessage());
        }

        $this->assertTrue(IntegrationPlugin::query()->whereKey($pluginId)->exists());

        $result = $service->uninstall($plugin->fresh() ?? $plugin, true);

        $this->assertTrue((bool) ($result['deleted'] ?? false));
        $this->assertSame(0, DB::table('integration_plugin_bindings')->where('plugin_id', $pluginId)->count());
        $this->assertFalse(IntegrationPlugin::query()->whereKey($pluginId)->exists());
    }

    public function test_driver_binding_resolver_prefers_active_binding(): void
    {
        $installer = app(PluginInstaller::class);
        $resolver = app(IntegrationDriverBindingResolver::class);
        $plugin = $installer->install('mail', 'smtp');

        // 孤儿绑定防御：绑定解析 join 插件表强制启用状态，未启用的插件绑定不参与解析。
        $installer->enable($plugin->fresh() ?? $plugin);

        // enable 时 syncDriverBinding 已按 driver_binding 写入 mail_driver 绑定行；
        // 改 provider_key 模拟管理端改绑，绑定行应压过 config 的 'smtp' 兜底。
        $updated = DB::table('integration_plugin_bindings')
            ->where('plugin_id', (int) $plugin->id)
            ->where('binding_key', 'mail_driver')
            ->update(['provider_key' => 'lifecycle-mail-test', 'updated_at' => now()]);
        $this->assertSame(1, $updated);

        $this->assertSame('lifecycle-mail-test', $resolver->mailDriverKey());
        $this->assertSame(
            ['plugin_id' => (int) $plugin->id, 'driver_key' => 'lifecycle-mail-test'],
            $resolver->mailContext(),
        );
    }

    public function test_probe_actions_skip_success_runtime_log(): void
    {
        $installer = app(PluginInstaller::class);
        $scanner = app(PluginScanner::class);
        $repository = app(PluginConfigRepository::class);

        $plugin = $installer->install('payment', 'demo_pay');
        $manifest = $scanner->requireManifest('payment', 'demo_pay');
        $repository->save($plugin, $manifest, ['merchant_id' => 'm-001']);
        $installer->enable($plugin->fresh() ?? $plugin);

        $registry = app(PluginRuntimeRegistry::class);
        $pluginId = (int) $plugin->id;

        $first = $registry->execute('payment', 'demo_pay', 'payment.is_enabled');
        $this->assertTrue((bool) ($first['success'] ?? false));

        // 同请求第二次调用命中 memo，仍成功。
        $second = $registry->execute('payment', 'demo_pay', 'payment.is_enabled');
        $this->assertTrue((bool) ($second['success'] ?? false));

        // 探测动作成功结果不落运行日志（纯噪音），失败才落库。
        $this->assertSame(
            0,
            DB::table('integration_plugin_runtime_logs')
                ->where('plugin_id', $pluginId)
                ->where('action', 'payment.is_enabled')
                ->count(),
        );
    }
}
