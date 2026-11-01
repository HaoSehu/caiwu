<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Services\Content\Storage\RemoteMediaStorage;
use App\Services\Integrations\Plugins\PluginRuntimeRegistry;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RemoteMediaStorageTest extends TestCase
{
    protected function tearDown(): void
    {
        $directory = storage_path('app/tmp');
        if (is_dir($directory)) {
            foreach (File::files($directory) as $file) {
                @unlink($file->getPathname());
            }
        }

        parent::tearDown();
    }

    public function test_put_rejects_object_key_with_leading_slash(): void
    {
        $storage = new RemoteMediaStorage($this->createMock(PluginRuntimeRegistry::class));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('远程对象键不合法');

        $storage->put('ftp', 'irrelevant', '/media/a.jpg', 'image/jpeg', 1);
    }

    public function test_put_rejects_object_key_with_traversal(): void
    {
        $storage = new RemoteMediaStorage($this->createMock(PluginRuntimeRegistry::class));

        $this->expectException(BusinessException::class);

        $storage->put('ftp', 'irrelevant', 'media/../secret.jpg', 'image/jpeg', 1);
    }

    public function test_put_passes_path_and_key_to_plugin_and_normalizes_response(): void
    {
        $directory = storage_path('app/tmp');
        File::ensureDirectoryExists($directory);
        $localPath = $directory.DIRECTORY_SEPARATOR.'put-sample.jpg';
        file_put_contents($localPath, 'sample');

        $registry = $this->createMock(PluginRuntimeRegistry::class);
        $registry->expects($this->once())
            ->method('execute')
            ->with('addons', 'qiniu', 'storage.put', [
                'local_path' => $localPath,
                'object_key' => 'media/put-sample.jpg',
                'mime_type' => 'image/jpeg',
                'content_length' => 6,
            ])
            ->willReturn([
                'success' => true,
                'message' => '上传成功',
                'data' => [
                    'object_key' => 'media/put-sample.jpg',
                    'url' => 'https://cdn.example.com/media/put-sample.jpg',
                    'size' => 6,
                    'etag' => 'etag-value',
                ],
            ]);

        $storage = new RemoteMediaStorage($registry);
        $result = $storage->put('qiniu', $localPath, 'media/put-sample.jpg', 'image/jpeg', 6);

        $this->assertSame('media/put-sample.jpg', $result['object_key']);
        $this->assertSame('https://cdn.example.com/media/put-sample.jpg', $result['url']);
        $this->assertSame(6, $result['size']);
        $this->assertSame('etag-value', $result['etag']);
    }

    public function test_put_throws_plugin_message_on_failure(): void
    {
        $directory = storage_path('app/tmp');
        File::ensureDirectoryExists($directory);
        $localPath = $directory.DIRECTORY_SEPARATOR.'put-fail.jpg';
        file_put_contents($localPath, 'sample');

        $registry = $this->createMock(PluginRuntimeRegistry::class);
        $registry->method('execute')->willReturn([
            'success' => false,
            'message' => '媒体库七牛云存储：鉴权失败',
            'data' => ['code' => 'auth_failed'],
        ]);

        $storage = new RemoteMediaStorage($registry);

        try {
            $storage->put('qiniu', $localPath, 'media/put-fail.jpg', 'image/jpeg', 6);
            $this->fail('预期抛出 BusinessException');
        } catch (BusinessException $exception) {
            $this->assertSame('媒体库七牛云存储：鉴权失败', $exception->getMessage());
        }
    }

    public function test_delete_maps_missing_object_as_success(): void
    {
        $registry = $this->createMock(PluginRuntimeRegistry::class);
        $registry->method('execute')->willReturn([
            'success' => true,
            'data' => ['deleted' => true, 'missing' => true],
        ]);

        $storage = new RemoteMediaStorage($registry);
        $result = $storage->delete('ftp', 'media/gone.jpg');

        $this->assertTrue($result['deleted']);
        $this->assertTrue($result['missing']);
    }

    public function test_get_rejects_temp_path_outside_target_dir(): void
    {
        $outside = (string) tempnam(sys_get_temp_dir(), 'media-outside-');
        file_put_contents($outside, 'x');

        $registry = $this->createMock(PluginRuntimeRegistry::class);
        $registry->method('execute')->willReturn([
            'success' => true,
            'data' => ['temp_path' => $outside, 'size' => 1],
        ]);

        $storage = new RemoteMediaStorage($registry);

        try {
            $storage->get('ftp', 'media/a.jpg', storage_path('app/tmp'));
            $this->fail('预期抛出 BusinessException');
        } catch (BusinessException $exception) {
            $this->assertSame('远程存储返回的临时文件路径不合法', $exception->getMessage());
        } finally {
            @unlink($outside);
        }
    }

    public function test_url_passes_object_key_to_plugin_and_returns_external_link(): void
    {
        $registry = $this->createMock(PluginRuntimeRegistry::class);
        $registry->expects($this->once())
            ->method('execute')
            ->with('addons', 'ftp', 'storage.url', ['object_key' => 'media/a.jpg'])
            ->willReturn([
                'success' => true,
                'message' => 'FTP 存储：外链生成成功',
                'data' => ['object_key' => 'media/a.jpg', 'url' => 'https://cdn.example.com/media/a.jpg'],
            ]);

        $storage = new RemoteMediaStorage($registry);

        $this->assertSame('https://cdn.example.com/media/a.jpg', $storage->url('ftp', 'media/a.jpg'));
    }

    public function test_url_rejects_invalid_object_key_before_calling_plugin(): void
    {
        $registry = $this->createMock(PluginRuntimeRegistry::class);
        $registry->expects($this->never())->method('execute');

        $storage = new RemoteMediaStorage($registry);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('远程对象键不合法');

        $storage->url('ftp', '/media/a.jpg');
    }

    public function test_url_throws_plugin_message_when_domain_missing(): void
    {
        $registry = $this->createMock(PluginRuntimeRegistry::class);
        $registry->method('execute')->willReturn([
            'success' => false,
            'message' => 'FTP 存储：请在插件配置中填写可公开访问的外链域名（需含 http:// 或 https://）',
            'data' => ['code' => 'public_domain_missing'],
        ]);

        $storage = new RemoteMediaStorage($registry);

        try {
            $storage->url('ftp', 'media/a.jpg');
            $this->fail('预期抛出 BusinessException');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('外链域名', $exception->getMessage());
        }
    }

    public function test_url_rejects_empty_url_from_plugin(): void
    {
        $registry = $this->createMock(PluginRuntimeRegistry::class);
        $registry->method('execute')->willReturn(['success' => true, 'data' => ['url' => '   ']]);

        $storage = new RemoteMediaStorage($registry);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('远程存储未返回外链地址');

        $storage->url('ftp', 'media/a.jpg');
    }
}
