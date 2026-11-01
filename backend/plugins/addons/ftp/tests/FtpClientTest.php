<?php

declare(strict_types=1);

namespace Tests\Unit\Plugins\Addons\Ftp;

use Tests\TestCase;

// 插件类由运行时 PluginFileLoader 按需 require，测试中需手动引入
require_once __DIR__.'/../lib/FtpSupport.php';
require_once __DIR__.'/../lib/FtpSigner.php';
require_once __DIR__.'/../lib/FtpClient.php';
require_once __DIR__.'/../logic/Ftp.php';

use Caiwu\Plugins\Addons\Ftp\Lib\FtpClient;
use Caiwu\Plugins\Addons\Ftp\Lib\FtpSigner;
use Caiwu\Plugins\Addons\Ftp\Lib\FtpSupport;
use Caiwu\Plugins\Addons\Ftp\Logic\Ftp;

/**
 * FTP 插件单元测试：纯函数 + 传输桩，不发真实网络请求。
 */
final class FtpClientTest extends TestCase
{
    /** 构造基础配置（占位凭据） */
    private function baseConfig(): array
    {
        return [
            'host' => 'ftp.example.com',
            'port' => 21,
            'username' => 'test-user',
            'password' => 'test-pass',
            'root_dir' => '/',
            'passive' => true,
            'use_ssl' => false,
            'timeout' => 30,
            'public_domain' => 'https://cdn.example.com',
            'url_prefix' => '',
            'object_prefix' => 'media',
        ];
    }

    /**
     * 可注入的 FTP 传输桩：记录调用并按脚本返回结果。
     */
    private function makeTransport(array $script): object
    {
        return new class($script)
        {
            public array $calls = [];

            public function __construct(private readonly array $script) {}

            public function __call(string $name, array $args): mixed
            {
                $this->calls[] = ['method' => $name, 'args' => $args];
                if (array_key_exists($name, $this->script)) {
                    $value = $this->script[$name];
                    if (is_callable($value)) {
                        return $value(...$args);
                    }

                    return $value;
                }

                return true;
            }
        };
    }

    // ---------- object_key 校验 ----------

    public function test_object_key_valid(): void
    {
        $this->assertTrue(FtpSupport::isValidObjectKey('media/img.jpg'));
        $this->assertTrue(FtpSupport::isValidObjectKey('a.jpg'));
        $this->assertTrue(FtpSupport::isValidObjectKey('media/sub/a-b_c.d.jpg'));
    }

    public function test_object_key_invalid(): void
    {
        $this->assertFalse(FtpSupport::isValidObjectKey(''));
        $this->assertFalse(FtpSupport::isValidObjectKey('/media/a.jpg'));
        $this->assertFalse(FtpSupport::isValidObjectKey('media/../a.jpg'));
        $this->assertFalse(FtpSupport::isValidObjectKey('media/名字.jpg'));
        $this->assertFalse(FtpSupport::isValidObjectKey(str_repeat('a', 501)));
    }

    public function test_object_key_double_dot_segment(): void
    {
        $this->assertFalse(FtpSupport::isValidObjectKey('a/../b.jpg'));
        $this->assertFalse(FtpSupport::isValidObjectKey('..'));
    }

    // ---------- object_prefix 解析（lead 裁决语义） ----------

    public function test_resolve_object_key_full_key_passthrough(): void
    {
        // 完整对象键（含斜杠）原样使用，不拼前缀
        $this->assertSame('media/img.jpg', FtpSupport::resolveObjectKey('media/img.jpg', 'media'));
        $this->assertSame('media/img.jpg', FtpSupport::resolveObjectKey('media/img.jpg', ''));
    }

    public function test_resolve_object_key_bare_filename_gets_prefix(): void
    {
        // 裸文件名才拼前缀（兼容手动调用）
        $this->assertSame('media/a.jpg', FtpSupport::resolveObjectKey('a.jpg', 'media'));
        $this->assertSame('a.jpg', FtpSupport::resolveObjectKey('a.jpg', ''));
        $this->assertSame('media/a.jpg', FtpSupport::resolveObjectKey('a.jpg', '/media/'));
    }

    // ---------- 外链拼接 ----------

    public function test_build_url(): void
    {
        $this->assertSame(
            'https://cdn.example.com/media/img_20300101_abc.jpg',
            FtpSupport::buildUrl('https://cdn.example.com', '', 'media/img_20300101_abc.jpg')
        );
        $this->assertSame(
            'https://cdn.example.com/static/media/img.jpg',
            FtpSupport::buildUrl('https://cdn.example.com/', '/static', 'media/img.jpg')
        );
        // 路径段单独编码：空格变 %20，斜杠保留
        $this->assertSame(
            'https://cdn.example.com/media/a%20b.jpg',
            FtpSupport::buildUrl('https://cdn.example.com', '', 'media/a b.jpg')
        );
    }

    public function test_signer_encode_path(): void
    {
        $this->assertSame('media/a%20b.jpg', FtpSigner::encodePath('media/a b.jpg'));
        $this->assertSame('a/b', FtpSigner::encodePath('a/b'));
    }

    // ---------- 错误归一 ----------

    public function test_failure_message_prefix(): void
    {
        $result = FtpSupport::failure('upstream_rejected', '上传失败');
        $this->assertFalse($result['success']);
        $this->assertSame('FTP 存储：上传失败', $result['message']);
        $this->assertSame('upstream_rejected', $result['data']['code']);
    }

    public function test_normalize_error_masks_secret_like_messages(): void
    {
        // 上游错误消息疑似密钥时替换为通用文案，不透传
        $e = new \RuntimeException('auth failed for token sk-1234567890');
        $result = FtpSupport::normalizeError($e);
        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString('sk-1234567890', $result['message']);
    }

    // ---------- 传输桩：put 全流程 ----------

    public function test_put_success_with_stub(): void
    {
        $local = $this->makeLocalFile('hello-ftp', 'txt');

        $transport = $this->makeTransport([
            'size' => static fn ($conn, $remote) => strlen('hello-ftp'),
        ]);
        $client = new FtpClient($this->baseConfig(), $transport);

        $result = $client->put($local['path'], 'media/'.$local['name'], 'https://cdn.example.com', '');

        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame('media/'.$local['name'], $result['data']['object_key']);
        $this->assertSame('https://cdn.example.com/media/'.$local['name'], $result['data']['url']);
        $this->assertSame(9, $result['data']['size']);
        // 逐级建目录被调用
        $methods = array_column($transport->calls, 'method');
        $this->assertContains('isDir', $methods);
        $this->assertContains('put', $methods);
        @unlink($local['path']);
    }

    public function test_put_size_mismatch_cleans_up(): void
    {
        $local = $this->makeLocalFile('hello-ftp', 'txt');

        $transport = $this->makeTransport([
            'size' => static fn ($conn, $remote) => 999, // 远端尺寸不符
        ]);
        $client = new FtpClient($this->baseConfig(), $transport);

        $result = $client->put($local['path'], 'media/'.$local['name'], 'https://cdn.example.com', '');

        $this->assertFalse($result['success']);
        $this->assertSame('upstream_rejected', $result['data']['code']);
        // 尽力删除半个文件
        $methods = array_column($transport->calls, 'method');
        $this->assertContains('delete', $methods);
        @unlink($local['path']);
    }

    public function test_put_auth_failed(): void
    {
        $local = $this->makeLocalFile('x', 'txt');
        $transport = $this->makeTransport([
            'login' => false,
        ]);
        $client = new FtpClient($this->baseConfig(), $transport);

        $result = $client->put($local['path'], 'media/a.jpg', 'https://cdn.example.com', '');

        $this->assertFalse($result['success']);
        $this->assertSame('auth_failed', $result['data']['code']);
        @unlink($local['path']);
    }

    public function test_put_connect_failed(): void
    {
        $local = $this->makeLocalFile('x', 'txt');
        $transport = $this->makeTransport([
            'connect' => false,
        ]);
        $client = new FtpClient($this->baseConfig(), $transport);

        $result = $client->put($local['path'], 'media/a.jpg', 'https://cdn.example.com', '');

        $this->assertFalse($result['success']);
        $this->assertSame('connect_failed', $result['data']['code']);
        @unlink($local['path']);
    }

    // ---------- 传输桩：get / delete / exists / test ----------

    public function test_get_success_with_stub(): void
    {
        $remoteContent = 'remote-content-123';
        $transport = $this->makeTransport([
            'size' => static fn ($conn, $remote) => strlen($remoteContent),
            'get' => static function ($conn, $remote, $tempPath) use ($remoteContent): bool {
                file_put_contents($tempPath, $remoteContent);

                return true;
            },
        ]);
        $client = new FtpClient($this->baseConfig(), $transport);

        $result = $client->get('media/a.jpg', storage_path('app/tmp'));

        $this->assertTrue($result['success'], $result['message']);
        $this->assertFileExists($result['data']['temp_path']);
        $this->assertSame(strlen($remoteContent), $result['data']['size']);
        $this->assertStringStartsWith((string) realpath(storage_path('app/tmp')), (string) realpath($result['data']['temp_path']));
        @unlink($result['data']['temp_path']);
    }

    public function test_get_remote_missing_fails(): void
    {
        $transport = $this->makeTransport([
            'get' => false,
        ]);
        $client = new FtpClient($this->baseConfig(), $transport);

        $result = $client->get('media/a.jpg', storage_path('app/tmp'));

        $this->assertFalse($result['success']);
        $this->assertSame('upstream_rejected', $result['data']['code']);
    }

    public function test_delete_missing_object_is_success(): void
    {
        $transport = $this->makeTransport([
            'exists' => false,
        ]);
        $client = new FtpClient($this->baseConfig(), $transport);

        $result = $client->delete('media/a.jpg');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['missing']);
        $this->assertFalse($result['data']['deleted']);
    }

    public function test_delete_success(): void
    {
        $transport = $this->makeTransport([
            'exists' => true,
            'delete' => true,
        ]);
        $client = new FtpClient($this->baseConfig(), $transport);

        $result = $client->delete('media/a.jpg');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['deleted']);
        $this->assertFalse($result['data']['missing']);
    }

    public function test_exists_with_stub(): void
    {
        $transport = $this->makeTransport([
            'exists' => true,
        ]);
        $client = new FtpClient($this->baseConfig(), $transport);

        $result = $client->exists('media/a.jpg');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['exists']);
    }

    public function test_storage_test_action_with_stub(): void
    {
        $transport = $this->makeTransport([]);
        $client = new FtpClient($this->baseConfig(), $transport);

        $result = $client->test();

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['ok']);
        $this->assertSame('ftp://ftp.example.com:21', $result['data']['endpoint']);
        $this->assertSame('/', $result['data']['root']);
        $this->assertIsInt($result['data']['latency_ms']);
    }

    // ---------- root_dir 归一化 ----------

    public function test_root_dir_normalization(): void
    {
        // 默认 '/' 归一为空串（对象键直接拼根）
        $client = new FtpClient($this->baseConfig());
        $this->assertSame('', $client->rootDir());
        $this->assertSame('/media/a.jpg', $client->remotePath('media/a.jpg'));

        // 带目录前缀
        $config = $this->baseConfig();
        $config['root_dir'] = '/storage';
        $client = new FtpClient($config);
        $this->assertSame('/storage/media/a.jpg', $client->remotePath('media/a.jpg'));
    }

    // ---------- 裁决修复回归 ----------

    /** logic execute 前置拦截：非法 object_key 时直接拒绝，不发起任何连接 */
    public function test_logic_execute_rejects_invalid_object_key(): void
    {
        $logic = new Ftp;

        $result = $logic->execute([
            'action' => 'storage.delete',
            'payload' => ['object_key' => '/media/a.jpg'],
            'config' => $this->baseConfig(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('object_key_invalid', $result['data']['code']);
        $this->assertStringStartsWith('FTP 存储', $result['message']);
    }

    /** logic execute 前置拦截：非法 local_path 时直接拒绝 */
    public function test_logic_execute_rejects_invalid_local_path(): void
    {
        $logic = new Ftp;

        $result = $logic->execute([
            'action' => 'storage.put',
            'payload' => ['object_key' => 'media/a.jpg', 'local_path' => 'E:/caiwu/composer.json'],
            'config' => $this->baseConfig(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('local_path_invalid', $result['data']['code']);
    }

    /** logic execute 前置拦截：非法 target_dir 时直接拒绝 */
    public function test_logic_execute_rejects_invalid_target_dir(): void
    {
        $logic = new Ftp;

        $result = $logic->execute([
            'action' => 'storage.get',
            'payload' => ['object_key' => 'media/a.jpg', 'target_dir' => 'E:/caiwu'],
            'config' => $this->baseConfig(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('local_path_invalid', $result['data']['code']);
    }

    /** logic execute 未知动作：归一 unknown_action（八类错误码之一） */
    public function test_logic_execute_unknown_action(): void
    {
        $logic = new Ftp;

        $result = $logic->execute([
            'action' => 'storage.copy',
            'payload' => [],
            'config' => $this->baseConfig(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('unknown_action', $result['data']['code']);
        $this->assertStringStartsWith('FTP 存储', $result['message']);
    }

    /** storage.url：完整对象键按 public_domain 现算外链（与 put 同一 buildUrl） */
    public function test_storage_url_full_key(): void
    {
        $logic = new Ftp;

        $result = $logic->execute([
            'action' => 'storage.url',
            'payload' => ['object_key' => 'media/img_20300101_abc.jpg'],
            'config' => $this->baseConfig(),
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('media/img_20300101_abc.jpg', $result['data']['object_key']);
        $this->assertSame('https://cdn.example.com/media/img_20300101_abc.jpg', $result['data']['url']);
        // 与 put 走同一个 buildUrl，二者必须全等
        $this->assertSame(
            FtpSupport::buildUrl('https://cdn.example.com', '', 'media/img_20300101_abc.jpg'),
            $result['data']['url']
        );
    }

    /** storage.url：裸文件名按 object_prefix 补前缀后再拼外链 */
    public function test_storage_url_bare_filename_gets_object_prefix(): void
    {
        $logic = new Ftp;

        $result = $logic->execute([
            'action' => 'storage.url',
            'payload' => ['object_key' => 'a.jpg'],
            'config' => $this->baseConfig(),
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('media/a.jpg', $result['data']['object_key']);
        $this->assertSame('https://cdn.example.com/media/a.jpg', $result['data']['url']);
    }

    /** storage.url：public_domain 为空无法生成可访问外链，返回 public_domain_missing */
    public function test_storage_url_without_public_domain_returns_code(): void
    {
        $config = $this->baseConfig();
        $config['public_domain'] = '';

        $logic = new Ftp;

        $result = $logic->execute([
            'action' => 'storage.url',
            'payload' => ['object_key' => 'media/a.jpg'],
            'config' => $config,
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('public_domain_missing', $result['data']['code']);
        $this->assertStringContainsString('外链域名', $result['message']);
    }

    /** storage.url：非法对象键按 object_key_invalid 拒绝 */
    public function test_storage_url_rejects_invalid_object_key(): void
    {
        $logic = new Ftp;

        $result = $logic->execute([
            'action' => 'storage.url',
            'payload' => ['object_key' => '/media/a.jpg'],
            'config' => $this->baseConfig(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('object_key_invalid', $result['data']['code']);
    }

    // ---------- 辅助 ----------

    /** 在 storage/app/tmp 下造一个本地临时文件 */
    private function makeLocalFile(string $content, string $ext): array
    {
        $dir = storage_path('app/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $name = 'ftp_test_'.bin2hex(random_bytes(6)).'.'.$ext;
        $path = $dir.DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, $content);

        return ['path' => $path, 'name' => $name];
    }
}
