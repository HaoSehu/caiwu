<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Services\Integrations\Plugins\PluginFileLoader;
use App\Services\Integrations\Plugins\PluginManifest;
use Tests\TestCase;

class PluginFileLoaderClassConflictTest extends TestCase
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

    public function test_core_namespace_declaration_is_rejected(): void
    {
        $this->tempDir = $this->makePlugin([
            'lib/Evil.php' => "<?php\nnamespace App\Models;\nclass User {}\n",
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('核心命名空间');

        $this->loader()->ensureLoaded($this->manifest('Caiwu\\Plugins\\Tests\\Evil\\EvilPlugin'));
    }

    public function test_cross_plugin_namespace_declaration_is_rejected(): void
    {
        $this->tempDir = $this->makePlugin([
            'lib/Imposter.php' => "<?php\ndeclare(strict_types=1);\nnamespace Caiwu\\Plugins\\Tests\\Other;\nclass Stolen {}\n",
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('自身命名空间');

        $this->loader()->ensureLoaded($this->manifest('Caiwu\\Plugins\\Tests\\Imposter\\ImposterPlugin'));
    }

    public function test_arbitrary_namespace_declaration_is_rejected(): void
    {
        // 收紧（2026-10-13 审查 P2-6）：任意第三方命名空间同样越出插件边界
        $this->tempDir = $this->makePlugin([
            'lib/Foreign.php' => "<?php\ndeclare(strict_types=1);\nnamespace MyLib\\Foo;\nclass Bar {}\n",
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('自身命名空间');

        $this->loader()->ensureLoaded($this->manifest('Caiwu\\Plugins\\Tests\\Foreign\\ForeignPlugin'));
    }

    public function test_namespaced_declaration_is_rejected_when_entry_class_is_global(): void
    {
        // 入口类无命名空间时无法推导自身前缀：命名空间声明一律拒绝，避免预检被整体绕过
        $this->tempDir = $this->makePlugin([
            'GlobalEntryPlugin.php' => "<?php\ndeclare(strict_types=1);\nclass GlobalEntryPlugin {}\n",
            'lib/Imposter.php' => "<?php\ndeclare(strict_types=1);\nnamespace Caiwu\\Plugins\\Tests\\Other;\nclass Stolen {}\n",
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('禁止声明命名空间类');

        $this->loader()->ensureLoaded($this->manifest('GlobalEntryPlugin'));
    }

    public function test_duplicate_class_declaration_is_rejected(): void
    {
        $this->tempDir = $this->makePlugin([
            'lib/First.php' => "<?php\ndeclare(strict_types=1);\nnamespace Caiwu\\Plugins\\Tests\\Dup;\nclass Same {}\n",
            'lib/Second.php' => "<?php\ndeclare(strict_types=1);\nnamespace Caiwu\\Plugins\\Tests\\Dup;\nclass Same {}\n",
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('重复声明');

        $this->loader()->ensureLoaded($this->manifest('Caiwu\\Plugins\\Tests\\Dup\\DupPlugin'));
    }

    public function test_valid_plugin_loads_and_classes_become_available(): void
    {
        $this->tempDir = $this->makePlugin([
            'LoadOkPlugin.php' => "<?php\ndeclare(strict_types=1);\nnamespace Caiwu\\Plugins\\Tests\\LoadOk;\nclass LoadOkPlugin {}\n",
            'lib/Helper.php' => "<?php\ndeclare(strict_types=1);\nnamespace Caiwu\\Plugins\\Tests\\LoadOk\\Lib;\nfinal class Helper { public const OK = true; }\n",
        ]);

        $loader = $this->loader();
        $loader->ensureLoaded($this->manifest('Caiwu\\Plugins\\Tests\\LoadOk\\LoadOkPlugin'));

        $this->assertTrue(class_exists('Caiwu\\Plugins\\Tests\\LoadOk\\LoadOkPlugin', false));
        $this->assertTrue(class_exists('Caiwu\\Plugins\\Tests\\LoadOk\\Lib\\Helper', false));
        // 二次加载幂等，不重复 require。
        $loader->ensureLoaded($this->manifest('Caiwu\\Plugins\\Tests\\LoadOk\\LoadOkPlugin'));
    }

    public function test_class_constant_fetch_and_anonymous_class_are_not_declarations(): void
    {
        $this->tempDir = $this->makePlugin([
            'ConstsPlugin.php' => <<<'PHP'
<?php
declare(strict_types=1);
namespace Caiwu\Plugins\Tests\Consts;

final class ConstsPlugin
{
    public const REFERENCE = Missing::class;

    public function make(): object
    {
        return new class {};
    }
}
PHP,
        ]);

        $loader = $this->loader();
        $loader->ensureLoaded($this->manifest('Caiwu\\Plugins\\Tests\\Consts\\ConstsPlugin'));

        $this->assertTrue(class_exists('Caiwu\\Plugins\\Tests\\Consts\\ConstsPlugin', false));
    }

    private function loader(): PluginFileLoader
    {
        return new PluginFileLoader;
    }

    private function manifest(string $entryClass): PluginManifest
    {
        return new PluginManifest(
            domain: 'addons',
            slug: basename($this->tempDir),
            key: basename($this->tempDir),
            name: '预检测试插件',
            version: '1.0.0',
            entryClass: $entryClass,
            providerClass: null,
            capabilities: [],
            configSchema: [],
            basePath: $this->tempDir,
            extra: [],
        );
    }

    /**
     * @param  array<string, string>  $files  相对路径 => 文件内容
     */
    private function makePlugin(array $files): string
    {
        $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'plugin-loader-test-'.uniqid('', true);
        if (! @mkdir($base, 0777, true) && ! is_dir($base)) {
            $this->fail('无法创建临时插件目录');
        }

        foreach ($files as $relative => $content) {
            $path = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $dir = dirname($path);
            if (! is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            file_put_contents($path, $content);
        }

        return $base;
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
