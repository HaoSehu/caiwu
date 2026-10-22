<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Services\Integrations\Plugins\PluginPackageHasher;
use Tests\TestCase;

class PluginPackageHasherTest extends TestCase
{
    /** @var list<string> */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            if (is_dir($dir)) {
                $this->recursiveDelete($dir);
            }
        }

        $this->tempDirs = [];

        parent::tearDown();
    }

    public function test_empty_directory_hashes_to_a_stable_value(): void
    {
        $hasher = new PluginPackageHasher;
        $dir = $this->makeDir([]);

        $first = $hasher->packageHash($dir);
        $second = $hasher->packageHash($dir);

        $this->assertSame($first, $second);
        $this->assertSame('sha256', $this->hashAlgorithmName($first));
        $this->assertSame(0, $hasher->describePackage($dir)['files']);
    }

    public function test_any_single_byte_change_changes_the_hash(): void
    {
        $hasher = new PluginPackageHasher;
        $dir = $this->makeDir(['config.php' => "<?php return ['version' => '1.0.0'];
"]);

        $before = $hasher->packageHash($dir);
        file_put_contents($dir.'/config.php', "<?php return ['version' => '1.0.1'];
");
        $after = $hasher->packageHash($dir);

        $this->assertNotSame($before, $after);
    }

    public function test_identical_content_in_different_files_produces_different_hashes(): void
    {
        $hasher = new PluginPackageHasher;

        $left = $this->makeDir(['a.php' => 'same']);
        $right = $this->makeDir(['b.php' => 'same']);

        $this->assertNotSame($hasher->packageHash($left), $hasher->packageHash($right));
    }

    public function test_file_count_guard_rejects_oversized_package(): void
    {
        $hasher = new PluginPackageHasher(maxFiles: 2);
        $dir = $this->makeDir(['a.php' => '1', 'b.php' => '2', 'c.php' => '3']);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('文件数超过上限');

        $hasher->packageHash($dir);
    }

    public function test_byte_guard_rejects_oversized_package(): void
    {
        $hasher = new PluginPackageHasher(maxBytes: 4);
        $dir = $this->makeDir(['a.php' => '12345']);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('总字节数超过上限');

        $hasher->packageHash($dir);
    }

    public function test_symbolic_link_is_rejected(): void
    {
        if (! function_exists('symlink')) {
            $this->markTestSkipped('当前环境禁用了 symlink，无法构造符号链接用例');
        }

        $dir = $this->makeDir(['a.php' => '1']);
        $target = $this->makeDir(['target.php' => '1']);

        if (! @symlink($target.'/target.php', $dir.'/link.php')) {
            $this->markTestSkipped('当前环境不支持创建符号链接');
        }

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('不允许符号链接');

        (new PluginPackageHasher)->packageHash($dir);
    }

    public function test_env_and_node_modules_are_rejected(): void
    {
        $hasher = new PluginPackageHasher;

        $withEnv = $this->makeDir(['.env' => 'APP_KEY=x']);
        try {
            $hasher->packageHash($withEnv);
            $this->fail('含 .env 的插件包应被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('环境变量文件', $exception->getMessage());
        }

        $withNodeModules = $this->makeDir(['node_modules/a/index.js' => 'x']);
        try {
            $hasher->packageHash($withNodeModules);
            $this->fail('含 node_modules 的插件包应被拒绝');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('依赖构建目录', $exception->getMessage());
        }
    }

    public function test_ds_store_is_ignored(): void
    {
        $hasher = new PluginPackageHasher;

        $plain = $this->makeDir(['a.php' => '1']);
        $withMeta = $this->makeDir(['a.php' => '1', '.DS_Store' => 'junk']);

        $this->assertSame($hasher->packageHash($plain), $hasher->packageHash($withMeta));
    }

    public function test_vendor_directory_participates_but_skips_name_rules(): void
    {
        $hasher = new PluginPackageHasher;
        $dir = $this->makeDir(['vendor/sdk/.env.example' => 'APP_KEY=']);

        $describe = $hasher->describePackage($dir);

        $this->assertSame(1, $describe['files']);

        file_put_contents($dir.'/vendor/sdk/.env.example', 'APP_KEY=changed');
        $this->assertNotSame($describe['hash'], $hasher->packageHash($dir));
    }

    public function test_describe_package_exposes_only_hash_files_bytes(): void
    {
        $hasher = new PluginPackageHasher;
        $dir = $this->makeDir(['config.php' => '<?php return [];', 'src/a.php' => '<?php']);

        $describe = $hasher->describePackage($dir);

        // 只暴露调用方真正消费的三个键：多出来的摘要维度（如 fingerprint）
        // 没有消费者，属于必须删掉的死代码，回归断言防止它被顺手加回来。
        $this->assertSame(['hash', 'files', 'bytes'], array_keys($describe));
        $this->assertSame(2, $describe['files']);
        $this->assertSame(strlen('<?php return [];') + strlen('<?php'), $describe['bytes']);
        $this->assertSame($hasher->packageHash($dir), $describe['hash']);
    }

    public function test_missing_directory_is_rejected(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('插件目录不存在');

        (new PluginPackageHasher)->packageHash(sys_get_temp_dir().'/plugin-package-hasher-missing-'.uniqid('', true));
    }

    /**
     * @param  array<string, string>  $files  相对路径 => 内容
     * @return string 临时插件目录绝对路径
     */
    private function makeDir(array $files): string
    {
        $base = sys_get_temp_dir().'/plugin-package-hasher-'.uniqid('', true);
        mkdir($base, 0777, true);

        foreach ($files as $relative => $content) {
            $path = $base.'/'.$relative;
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($path, $content);
        }

        $this->tempDirs[] = $base;

        return $base;
    }

    private function hashAlgorithmName(string $hash): string
    {
        return strlen($hash) === 64 && ctype_xdigit($hash) ? 'sha256' : 'unknown';
    }

    private function recursiveDelete(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->recursiveDelete($path.'/'.$entry);
        }

        @rmdir($path);
    }
}
