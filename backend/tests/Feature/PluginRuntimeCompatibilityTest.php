<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Exceptions\BusinessException;
use App\Models\IntegrationPlugin;
use App\Services\Integrations\Plugins\PluginCompatibility;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Integrations\Plugins\PluginFileLoader;
use App\Services\Integrations\Plugins\PluginInstaller;
use App\Services\Integrations\Plugins\PluginPackageHasher;
use App\Services\Integrations\Plugins\PluginRuntimeRegistry;
use App\Services\Integrations\Plugins\PluginScanner;
use Illuminate\Contracts\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 运行时兼容性校验：注册表解析跳过、动作执行二次拦截。
 *
 * 磁盘清单来自临时插件目录（宿主版本要求 >=99.0.0），注册记录直接落库，
 * 因为安装入口本身已被兼容性校验拒绝，无法走正常 install 路径造出该状态。
 */
class PluginRuntimeCompatibilityTest extends TestCase
{
    use DatabaseTransactions;

    private string $tempDir = '';

    protected function tearDown(): void
    {
        if ($this->tempDir !== '' && is_dir($this->tempDir)) {
            $this->recursiveDelete($this->tempDir);
            $this->tempDir = '';
        }

        parent::tearDown();
    }

    public function test_incompatible_plugin_is_skipped_at_runtime(): void
    {
        $this->seedIncompatiblePlugin('core_too_new', ['core' => '>=99.0.0']);

        $entries = $this->registry()->resolveEntries('payment', PaymentGatewayInterface::class);

        $this->assertSame([], $entries);
    }

    public function test_incompatible_plugin_blocks_direct_action(): void
    {
        $this->seedIncompatiblePlugin('core_too_new', ['core' => '>=99.0.0']);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('与当前运行环境不兼容');

        $this->registry()->execute('payment', 'core_too_new', 'payment.is_enabled');
    }

    public function test_compatible_plugin_still_loads(): void
    {
        $installer = app(PluginInstaller::class);
        $repository = app(PluginConfigRepository::class);
        $scanner = app(PluginScanner::class);

        $plugin = $installer->install('payment', 'demo_pay');
        $manifest = $scanner->requireManifest('payment', 'demo_pay');
        $repository->save($plugin, $manifest, ['merchant_id' => 'm-001', 'secret_key' => 's3cret']);
        $installer->enable($plugin->fresh() ?? $plugin);

        $entries = app(PluginRuntimeRegistry::class)
            ->resolveEntries('payment', PaymentGatewayInterface::class);

        $this->assertCount(1, $entries);
    }

    /**
     * @param  array<string, string>  $requires
     */
    private function seedIncompatiblePlugin(string $slug, array $requires): void
    {
        $directory = $this->tempDir().DIRECTORY_SEPARATOR.'gateways'.DIRECTORY_SEPARATOR.$slug;
        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            $this->fail('无法创建临时插件目录');
        }

        $payload = [
            'domain' => 'payment',
            'slug' => $slug,
            'key' => $slug,
            'name' => '版本不兼容插件',
            'version' => '1.0.0',
            'entry' => 'Caiwu\\Plugins\\Payment\\CoreTooNew\\CoreTooNewPlugin',
            'extra' => ['requires' => $requires],
        ];
        file_put_contents($directory.DIRECTORY_SEPARATOR.'config.php', "<?php\n\nreturn ".var_export($payload, true).";\n");

        IntegrationPlugin::query()->updateOrCreate(
            ['domain' => 'payment', 'slug' => $slug],
            [
                'plugin_key' => $slug,
                'name' => '版本不兼容插件',
                'version' => '1.0.0',
                'provider_class' => null,
                'entry_class' => $payload['entry'],
                'capabilities_json' => [],
                'config_schema_json' => [],
                'status' => IntegrationPlugin::STATUS_ENABLED,
                'installed_at' => now(),
                // 摘要留空表示尚未回填，不参与包完整性拦截，只验证版本区间这一层。
                'source_hash' => null,
            ],
        );
    }

    private function registry(): PluginRuntimeRegistry
    {
        return new PluginRuntimeRegistry(
            app(Container::class),
            new PluginScanner(new Filesystem, $this->tempDir()),
            app(PluginFileLoader::class),
            app(PluginConfigRepository::class),
            app(PluginPackageHasher::class),
            app(PluginCompatibility::class),
        );
    }

    private function tempDir(): string
    {
        if ($this->tempDir === '') {
            $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'plugin-compat-test-'.uniqid('', true);
        }

        if (! is_dir($this->tempDir) && ! @mkdir($this->tempDir, 0777, true) && ! is_dir($this->tempDir)) {
            $this->fail('无法创建临时根目录');
        }

        return $this->tempDir;
    }

    private function recursiveDelete(string $path): void
    {
        foreach (glob($path.DIRECTORY_SEPARATOR.'*') ?: [] as $item) {
            if (is_dir($item)) {
                $this->recursiveDelete($item);

                continue;
            }

            @unlink($item);
        }

        @rmdir($path);
    }
}
