<?php

declare(strict_types=1);

namespace TestsUnit;

use App\Exceptions\BusinessException;
use App\Services\Automation\Heartbeat\ScheduledTaskValidator;
use App\Services\Integrations\Plugins\PluginCompatibility;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Integrations\Plugins\PluginFileLoader;
use App\Services\Integrations\Plugins\PluginInstaller;
use App\Services\Integrations\Plugins\PluginManifest;
use App\Services\Integrations\Plugins\PluginPackageHasher;
use App\Services\Integrations\Plugins\PluginProviderRegistry;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Support\Upgrade\UpgradeLedger;
use Illuminate\Contracts\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

/**
 * 插件清单 requires（宿主环境要求）解析与兼容性判定。
 *
 * 覆盖四类口径：缺省不限制、满足放行、不满足阻断、声明非法直接报错；
 * 另外锁定扫描器对 requires 的解析与错误消息透传（不能被「清单内容无效」吞掉）。
 */
class PluginManifestRequiresTest extends TestCase
{
    private string $tempDir = '';

    protected function tearDown(): void
    {
        if ($this->tempDir !== '' && is_dir($this->tempDir)) {
            $this->recursiveDelete($this->tempDir);
            $this->tempDir = '';
        }

        parent::tearDown();
    }

    public function test_missing_requires_means_no_restriction(): void
    {
        $compatibility = $this->compatibility();

        $this->assertSame([], PluginCompatibility::normalize(null));
        $this->assertSame([], PluginCompatibility::normalize([]));
        $this->assertSame([], $compatibility->violations([], '0.0.1'));
    }

    public function test_satisfied_constraints_pass(): void
    {
        $compatibility = $this->compatibility();

        $this->assertSame([], $compatibility->violations(['core' => '>=1.0.0'], '1.0.0'));
        $this->assertSame([], $compatibility->violations(['core' => '>=1.0.0'], '2.3.4'));
        $this->assertSame([], $compatibility->violations(['core' => '>=1.0.0 <9.0.0'], '1.5.0'));
        $this->assertTrue(PluginCompatibility::satisfies('1.0.0', '1.0.0'));
        $this->assertTrue(PluginCompatibility::satisfies('1.0.0', 'v1.0.0'));
        $this->assertSame([], $compatibility->violations(['php' => '>='.PHP_VERSION], PHP_VERSION));
    }

    public function test_unsatisfied_constraints_are_reported(): void
    {
        $compatibility = $this->compatibility();

        $coreProblems = $compatibility->violations(['core' => '>=2.0.0'], '1.2.3');
        $this->assertCount(1, $coreProblems);
        $this->assertStringContainsString('要求宿主程序版本 core >=2.0.0', $coreProblems[0]);
        $this->assertStringContainsString('当前 1.2.3', $coreProblems[0]);

        $phpProblems = $compatibility->violations(['php' => '>=99.0.0'], null);
        $this->assertCount(1, $phpProblems);
        $this->assertStringContainsString('要求 PHP 版本 >=99.0.0', $phpProblems[0]);
    }

    public function test_unknown_host_version_skips_core_check(): void
    {
        // 未安装/未采纳时读不到数据库版本，不能因此阻断既有插件。
        $this->assertSame([], $this->compatibility()->violations(['core' => '>=99.0.0'], null));
        $this->assertSame([], $this->compatibility()->violations(['core' => '>=99.0.0'], ''));
    }

    public function test_assert_compatible_throws_with_plugin_name(): void
    {
        $manifest = new PluginManifest(
            domain: 'payment',
            slug: 'demo_pay',
            key: 'demo_pay',
            name: '示例支付',
            version: '1.0.0',
            entryClass: 'Caiwu\\Plugins\\Payment\\DemoPay\\DemoPayPlugin',
            providerClass: null,
            capabilities: [],
            configSchema: [],
            basePath: sys_get_temp_dir(),
            extra: [],
            requires: ['php' => '>=99.0.0'],
        );

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('示例支付 与当前运行环境不兼容');

        $this->compatibility()->assertCompatible($manifest);
    }

    public function test_unsupported_key_is_rejected(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('只支持 core 与 php');

        PluginCompatibility::normalize(['host' => '>=1.0.0']);
    }

    public function test_non_array_requires_is_rejected(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('requires 必须是数组');

        PluginCompatibility::normalize('>=1.0.0');
    }

    public function test_malformed_constraint_is_rejected(): void
    {
        try {
            PluginCompatibility::normalize(['core' => 'not-a-version']);
            $this->fail('非法版本约束应被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('requires 格式无效', $exception->getMessage());
            $this->assertStringContainsString('core', $exception->getMessage());
        }
    }

    public function test_empty_constraint_is_rejected(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('requires 格式无效 [php]');

        PluginCompatibility::normalize(['php' => '   ']);
    }

    public function test_manifest_serializes_requires(): void
    {
        $manifest = new PluginManifest(
            domain: 'payment',
            slug: 'demo_pay',
            key: 'demo_pay',
            name: '示例支付',
            version: '1.0.0',
            entryClass: 'Caiwu\\Plugins\\Payment\\DemoPay\\DemoPayPlugin',
            providerClass: null,
            capabilities: [],
            configSchema: [],
            basePath: sys_get_temp_dir(),
            extra: [],
            requires: ['core' => '>=1.0.0', 'php' => '>=8.2'],
        );

        $this->assertSame(['core' => '>=1.0.0', 'php' => '>=8.2'], $manifest->toArray()['requires']);
    }

    public function test_scanner_parses_requires_from_manifest(): void
    {
        $base = $this->makePlugin('requires_demo', [
            'domain' => 'payment',
            'slug' => 'requires_demo',
            'extra' => ['requires' => ['core' => '>=1.0.0', 'php' => '>=8.2']],
        ]);

        $manifest = (new PluginScanner(new Filesystem, $base))->find('payment', 'requires_demo');

        $this->assertInstanceOf(PluginManifest::class, $manifest);
        $this->assertSame(['core' => '>=1.0.0', 'php' => '>=8.2'], $manifest->requires);
    }

    public function test_scanner_keeps_original_message_for_invalid_requires(): void
    {
        $base = $this->makePlugin('requires_bad', [
            'domain' => 'payment',
            'slug' => 'requires_bad',
            'extra' => ['requires' => ['core' => 'not-a-version']],
        ]);

        try {
            (new PluginScanner(new Filesystem, $base))->find('payment', 'requires_bad');
            $this->fail('非法 requires 应阻止清单解析');
        } catch (BusinessException $exception) {
            // 终检点：不能被 readManifest 的兜底包装成「插件清单内容无效」。
            $this->assertStringContainsString('requires 格式无效', $exception->getMessage());
            $this->assertStringNotContainsString('插件清单内容无效', $exception->getMessage());
        }
    }

    public function test_scanner_accepts_plugin_json_manifest(): void
    {
        $base = $this->makeJsonPlugin('requires_json', [
            'domain' => 'payment',
            'slug' => 'requires_json',
            'extra' => ['requires' => ['core' => '>=1.0.0']],
        ]);

        $manifest = (new PluginScanner(new Filesystem, $base))->find('payment', 'requires_json');

        $this->assertInstanceOf(PluginManifest::class, $manifest);
        $this->assertSame(['core' => '>=1.0.0'], $manifest->requires);
    }

    public function test_installer_rejects_plugin_requiring_newer_core(): void
    {
        $base = $this->makePlugin('core_too_new', [
            'extra' => ['requires' => ['core' => '>=99.0.0']],
        ]);

        $installer = new PluginInstaller(
            new PluginScanner(new Filesystem, $base),
            app(PluginFileLoader::class),
            app(PluginConfigRepository::class),
            app(PluginProviderRegistry::class),
            app(ScheduledTaskValidator::class),
            app(PluginPackageHasher::class),
            app(PluginCompatibility::class),
            app(Container::class),
        );

        try {
            $installer->install('payment', 'core_too_new');
            $this->fail('宿主版本不满足的插件不应被安装');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('与当前运行环境不兼容', $exception->getMessage());
            $this->assertStringContainsString('>=99.0.0', $exception->getMessage());
        }
    }

    public function test_real_upstream_plugins_declare_core_requirement(): void
    {
        $scanner = app(PluginScanner::class);

        foreach (['zjmf_finance', 'kanghostx'] as $slug) {
            $manifest = $scanner->find('upstream', $slug);
            $this->assertInstanceOf(PluginManifest::class, $manifest, $slug);
            $this->assertSame(['core' => '>=1.0.0'], $manifest->requires, $slug);
        }
    }

    private function compatibility(): PluginCompatibility
    {
        return new PluginCompatibility(new UpgradeLedger);
    }

    /**
     * 在临时目录下生成 &lt;域目录&gt;/&lt;slug&gt;/config.php。
     *
     * @param  array<string, mixed>  $extraPayload  合并进清单顶层的字段
     */
    private function makePlugin(string $slug, array $extraPayload): string
    {
        $root = $this->tempRoot();
        $directory = $root.DIRECTORY_SEPARATOR.'gateways'.DIRECTORY_SEPARATOR.$slug;
        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            $this->fail('无法创建临时插件目录');
        }

        $payload = array_merge($this->manifestDefaults($slug), $extraPayload);
        file_put_contents(
            $directory.DIRECTORY_SEPARATOR.'config.php',
            "<?php\n\nreturn ".var_export($payload, true).";\n"
        );

        return $root;
    }

    /**
     * 生成 plugin.json 形式的清单（不走 config.php 分支）。
     *
     * @param  array<string, mixed>  $extraPayload
     */
    private function makeJsonPlugin(string $slug, array $extraPayload): string
    {
        $root = $this->tempRoot();
        $directory = $root.DIRECTORY_SEPARATOR.'gateways'.DIRECTORY_SEPARATOR.$slug;
        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            $this->fail('无法创建临时插件目录');
        }

        $payload = array_merge($this->manifestDefaults($slug), $extraPayload);
        file_put_contents(
            $directory.DIRECTORY_SEPARATOR.'plugin.json',
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
        );

        return $root;
    }

    /**
     * @return array<string, mixed>
     */
    private function manifestDefaults(string $slug): array
    {
        return [
            'domain' => 'payment',
            'slug' => $slug,
            'key' => $slug,
            'name' => '版本区间测试插件',
            'version' => '1.0.0',
            'entry' => 'Caiwu\\Plugins\\Payment\\'.ucfirst($slug).'\\'.ucfirst($slug).'Plugin',
        ];
    }

    private function tempRoot(): string
    {
        if ($this->tempDir === '') {
            $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'plugin-requires-test-'.uniqid('', true);
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
