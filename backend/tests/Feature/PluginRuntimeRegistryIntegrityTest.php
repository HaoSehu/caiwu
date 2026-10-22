<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Exceptions\BusinessException;
use App\Models\IntegrationPlugin;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Integrations\Plugins\PluginInstaller;
use App\Services\Integrations\Plugins\PluginPackageHasher;
use App\Services\Integrations\Plugins\PluginRuntimeRegistry;
use App\Services\Integrations\Plugins\PluginScanner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use ReflectionProperty;
use Tests\TestCase;

class PluginRuntimeRegistryIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // 摘要校验结果按进程缓存，测试之间必须清空，否则命中上一个用例的放行结果。
        $cache = new ReflectionProperty(PluginRuntimeRegistry::class, 'packageIntegrityCache');
        $cache->setValue(null, []);
    }

    public function test_install_and_upgrade_record_package_source_hash(): void
    {
        $installer = app(PluginInstaller::class);
        $hasher = app(PluginPackageHasher::class);

        $plugin = $installer->install('payment', 'demo_pay');
        $recorded = (string) ($plugin->fresh() ?? $plugin)->source_hash;

        $this->assertNotSame('', $recorded);
        $this->assertSame(
            $hasher->packageHash(base_path('plugins/gateways/demo_pay')),
            $recorded,
        );

        $plugin->forceFill(['source_hash' => 'stale-source-hash'])->save();
        $upgraded = $installer->upgrade($plugin->fresh() ?? $plugin);
        $upgradedPlugin = $upgraded['plugin'] instanceof IntegrationPlugin ? $upgraded['plugin'] : $plugin;

        $this->assertSame($recorded, (string) $upgradedPlugin->source_hash);
    }

    public function test_plugin_with_stale_source_hash_is_skipped_when_enforced(): void
    {
        Config::set('integrations.plugin_integrity_enforce', true);

        $plugin = $this->enabledDemoPay();
        $plugin->forceFill(['source_hash' => 'stale-source-hash'])->save();

        $entries = app(PluginRuntimeRegistry::class)
            ->resolveEntries('payment', PaymentGatewayInterface::class);

        $this->assertSame([], $this->filterEntriesByKey($entries, 'demo_pay'));
    }

    public function test_plugin_with_stale_source_hash_is_loaded_in_observation_mode(): void
    {
        Config::set('integrations.plugin_integrity_enforce', false);

        $plugin = $this->enabledDemoPay();
        $plugin->forceFill(['source_hash' => 'stale-source-hash'])->save();

        $entries = app(PluginRuntimeRegistry::class)
            ->resolveEntries('payment', PaymentGatewayInterface::class);

        $this->assertCount(1, $this->filterEntriesByKey($entries, 'demo_pay'));
    }

    public function test_plugin_with_missing_source_hash_is_still_loaded(): void
    {
        Config::set('integrations.plugin_integrity_enforce', true);

        $plugin = $this->enabledDemoPay();
        $plugin->forceFill(['source_hash' => null])->save();

        $entries = app(PluginRuntimeRegistry::class)
            ->resolveEntries('payment', PaymentGatewayInterface::class);

        // 空摘要表示「尚未回填」，不按篡改处理；回填命令与 error 日志负责收口。
        $this->assertCount(1, $this->filterEntriesByKey($entries, 'demo_pay'));
    }

    public function test_stale_package_blocks_plugin_execution_when_enforced(): void
    {
        Config::set('integrations.plugin_integrity_enforce', true);

        $plugin = $this->enabledDemoPay();
        $plugin->forceFill(['source_hash' => 'stale-source-hash'])->save();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('插件包内容与安装记录不一致');

        app(PluginRuntimeRegistry::class)->execute('payment', 'demo_pay', 'payment.is_enabled');
    }

    /**
     * 安装、配置并启用 demo_pay，返回启用后的注册记录。
     *
     * @return IntegrationPlugin 已启用且 source_hash 为安装时值的插件记录
     */
    private function enabledDemoPay(): IntegrationPlugin
    {
        $installer = app(PluginInstaller::class);
        $scanner = app(PluginScanner::class);
        $repository = app(PluginConfigRepository::class);

        $plugin = $installer->install('payment', 'demo_pay');
        $manifest = $scanner->requireManifest('payment', 'demo_pay');
        $repository->save($plugin, $manifest, ['merchant_id' => 'm-001', 'secret_key' => 's3cret']);

        return $installer->enable($plugin->fresh() ?? $plugin);
    }

    /**
     * @param  array<int, mixed>  $entries
     * @return array<int, mixed>
     */
    private function filterEntriesByKey(array $entries, string $key): array
    {
        return array_values(array_filter(
            $entries,
            static fn ($entry): bool => method_exists($entry, 'key') && $entry->key() === $key,
        ));
    }
}
