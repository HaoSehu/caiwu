<?php

declare(strict_types=1);

namespace App\Services\Content\Storage;

use App\Exceptions\BusinessException;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\Integrations\Plugins\PluginRuntimeRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/**
 * 远程媒体存储访问门面。
 *
 * 宿主侧唯一调用 PluginRuntimeRegistry::execute('addons', ...) 的地方：
 * 负责对象键/本地路径校验、payload 组装、插件返回的归一化与错误翻译。
 * 文件内容永不进入 payload（运行日志会截断落库），只传本地绝对路径与对象键。
 */
class RemoteMediaStorage
{
    private const ACTION_TEST = 'storage.test';

    private const ACTION_PUT = 'storage.put';

    private const ACTION_GET = 'storage.get';

    private const ACTION_DELETE = 'storage.delete';

    private const ACTION_EXISTS = 'storage.exists';

    private const ACTION_URL = 'storage.url';

    public function __construct(private readonly PluginRuntimeRegistry $registry) {}

    /**
     * 把上传文件落到 storage/app/tmp 暂存区，返回绝对路径。
     *
     * 插件侧只允许读取 public/media 与 storage/app/tmp 下的文件，
     * 上传直连远程前必须先暂存到允许范围内。
     */
    public function stageUploadedFile(UploadedFile $file, string $filename): string
    {
        $directory = storage_path('app/tmp');
        File::ensureDirectoryExists($directory);
        $file->move($directory, $filename);

        return $directory.DIRECTORY_SEPARATOR.$filename;
    }

    /**
     * 上传本地文件到远程存储，返回对象键与外链。
     *
     * @return array{object_key: string, url: string, size: int, etag: string|null}
     */
    public function put(string $driver, string $localPath, string $objectKey, string $mimeType, int $contentLength): array
    {
        $this->assertObjectKey($objectKey);
        $this->assertLocalPath($localPath);

        $response = $this->execute($driver, self::ACTION_PUT, [
            'local_path' => $localPath,
            'object_key' => $objectKey,
            'mime_type' => $mimeType,
            'content_length' => $contentLength,
        ]);

        $url = trim((string) ($response['url'] ?? ''));
        if ($url === '') {
            throw new BusinessException('远程存储未返回外链地址', 42200);
        }

        $size = (int) ($response['size'] ?? 0);

        return [
            'object_key' => (string) ($response['object_key'] ?? $objectKey),
            'url' => $url,
            'size' => $size > 0 ? $size : $contentLength,
            'etag' => isset($response['etag']) && $response['etag'] !== '' ? (string) $response['etag'] : null,
        ];
    }

    /**
     * 从远程存储下载对象到临时目录（迁回本地用）。
     *
     * @return array{temp_path: string, size: int}
     */
    public function get(string $driver, string $objectKey, ?string $targetDir = null): array
    {
        $this->assertObjectKey($objectKey);

        $directory = $targetDir !== null && trim($targetDir) !== '' ? trim($targetDir) : storage_path('app/tmp');
        File::ensureDirectoryExists($directory);

        $response = $this->execute($driver, self::ACTION_GET, [
            'object_key' => $objectKey,
            'target_dir' => $directory,
        ]);

        $tempPath = (string) ($response['temp_path'] ?? '');
        if ($tempPath === '' || ! is_file($tempPath)) {
            throw new BusinessException('远程存储未返回有效的临时文件', 42200);
        }

        $realPath = str_replace('\\', '/', (string) realpath($tempPath));
        $realDir = rtrim(str_replace('\\', '/', (string) realpath($directory)), '/');
        if ($realDir === '' || ! str_starts_with($realPath, $realDir.'/')) {
            @unlink($tempPath);

            throw new BusinessException('远程存储返回的临时文件路径不合法', 42200);
        }

        $size = (int) ($response['size'] ?? 0);

        return [
            'temp_path' => $realPath,
            'size' => $size > 0 ? $size : (int) (@filesize($realPath) ?: 0),
        ];
    }

    /**
     * 删除远程对象；对象不存在按成功处理（missing=true）。
     *
     * @return array{deleted: bool, missing: bool}
     */
    public function delete(string $driver, string $objectKey): array
    {
        $this->assertObjectKey($objectKey);

        $response = $this->execute($driver, self::ACTION_DELETE, ['object_key' => $objectKey]);

        return [
            'deleted' => (bool) ($response['deleted'] ?? true),
            'missing' => (bool) ($response['missing'] ?? false),
        ];
    }

    /**
     * 探测远程对象是否存在；插件执行失败时抛业务异常，由调用方决定如何归集。
     */
    public function exists(string $driver, string $objectKey): bool
    {
        $this->assertObjectKey($objectKey);

        $response = $this->execute($driver, self::ACTION_EXISTS, ['object_key' => $objectKey]);

        return ($response['exists'] ?? false) === true;
    }

    /**
     * 按对象键现算外链（存储站点换域名/协议后订正存量记录用）。
     *
     * 纯本地计算，不发网络请求、不重传文件：插件用当前配置把 object_key 拼成访问地址。
     * 因此同一 object_key 与同一配置下，结果必须与 put() 返回的 url 全等。
     */
    public function url(string $driver, string $objectKey): string
    {
        $this->assertObjectKey($objectKey);

        $response = $this->execute($driver, self::ACTION_URL, ['object_key' => $objectKey]);

        $url = trim((string) ($response['url'] ?? ''));
        if ($url === '') {
            throw new BusinessException('远程存储未返回外链地址', 42200);
        }

        return $url;
    }

    /**
     * 连接测试（安装配置后的「测试连接」与健康检查）。
     *
     * @return array<string, mixed>
     */
    public function test(string $driver): array
    {
        return $this->execute($driver, self::ACTION_TEST, []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function execute(string $driver, string $action, array $payload): array
    {
        $slug = trim($driver);
        if ($slug === '') {
            throw new BusinessException('远程存储驱动不能为空', 42200);
        }

        $response = $this->registry->execute(PluginDomain::ADDONS, $slug, $action, $payload);

        if (($response['success'] ?? null) !== true) {
            $message = trim((string) ($response['message'] ?? ''));

            throw new BusinessException($message !== '' ? $message : '远程存储操作失败（'.$action.'）', 42200);
        }

        $data = $response['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    private function assertObjectKey(string $objectKey): void
    {
        if ($objectKey === ''
            || strlen($objectKey) > 500
            || str_starts_with($objectKey, '/')
            || str_contains($objectKey, '..')
            || preg_match('/^[A-Za-z0-9._\/-]+$/', $objectKey) !== 1) {
            throw new BusinessException('远程对象键不合法', 42200);
        }
    }

    private function assertLocalPath(string $localPath): void
    {
        $realPath = realpath($localPath);
        if ($realPath === false || ! is_file($realPath)) {
            throw new BusinessException('待上传的本地文件不存在', 42200);
        }

        $normalized = str_replace('\\', '/', $realPath);
        foreach ([public_path('media'), storage_path('app/tmp')] as $root) {
            $realRoot = realpath($root);
            if ($realRoot === false) {
                continue;
            }

            $normalizedRoot = rtrim(str_replace('\\', '/', $realRoot), '/');
            if ($normalized === $normalizedRoot || str_starts_with($normalized, $normalizedRoot.'/')) {
                return;
            }
        }

        throw new BusinessException('本地文件路径不在允许的目录范围内', 42200);
    }
}
