<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Addons\Qiniu\Logic;

use Caiwu\Plugins\Addons\Qiniu\Lib\QiniuClient;
use Caiwu\Plugins\Addons\Qiniu\Lib\QiniuSupport;

/**
 * 七牛插件业务逻辑：execute 动作分发 + healthCheck。
 */
final class Qiniu
{
    /**
     * 动作统一入口（match 分发）。
     *
     * @param  array<string, mixed>  $request
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function execute(array $request): array
    {
        $action = (string) ($request['action'] ?? '');
        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];
        $config = is_array($request['config'] ?? null) ? $request['config'] : [];

        try {
            return match ($action) {
                'storage.test' => $this->actionTest($config),
                'storage.put' => $this->actionPut($payload, $config),
                'storage.get' => $this->actionGet($payload, $config),
                'storage.delete' => $this->actionDelete($payload, $config),
                'storage.exists' => $this->actionExists($payload, $config),
                'storage.url' => $this->actionUrl($payload, $config),
                default => QiniuSupport::failure('upstream_rejected', '不支持的动作：'.$action),
            };
        } catch (\Throwable $e) {
            return QiniuSupport::normalizeError($e, 'upstream_rejected');
        }
    }

    /**
     * 健康检查：扩展依赖即可（curl/hash/json 均为 PHP 标配）。
     *
     * @return array{healthy: bool, message: string, details: array<string, mixed>}
     */
    public function healthCheck(): array
    {
        $available = extension_loaded('curl') && function_exists('hash_hmac');
        $missing = [];
        if (! extension_loaded('curl')) {
            $missing[] = 'curl';
        }
        if (! function_exists('hash_hmac')) {
            $missing[] = 'hash';
        }

        return [
            'healthy' => $available,
            'message' => $available ? '依赖可用' : 'PHP 扩展缺失：'.implode(',', $missing),
            'details' => [
                'extensions' => ['curl', 'hash'],
                'available' => $available,
            ],
        ];
    }

    /**
     * storage.test：连通性测试。
     *
     * @param  array<string, mixed>  $config
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function actionTest(array $config): array
    {
        $client = new QiniuClient($config);

        return $client->test();
    }

    /**
     * storage.put：上传本地文件。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function actionPut(array $payload, array $config): array
    {
        $objectKey = QiniuSupport::resolveObjectKey(
            (string) ($payload['object_key'] ?? ''),
            (string) ($config['object_prefix'] ?? 'media')
        );
        if (! QiniuSupport::isValidObjectKey($objectKey)) {
            return QiniuSupport::failure('object_key_invalid', '对象键非法（需非空、≤500、不以 / 开头、段内无 ..、仅字母数字._/-）');
        }

        $localPath = (string) ($payload['local_path'] ?? '');
        if (! QiniuSupport::isValidLocalPath($localPath)) {
            return QiniuSupport::failure('local_path_invalid', '本地文件路径必须位于 public/media 或 storage/app/tmp 之下');
        }

        // 大小以本地实测为准，payload content_length 仅做一致性比对（与 cos/oss 契约一致）
        $actualSize = (int) filesize($localPath);
        $declaredLength = isset($payload['content_length']) ? (int) $payload['content_length'] : null;
        if ($declaredLength !== null && $declaredLength !== $actualSize) {
            return QiniuSupport::failure('local_path_invalid', '本地文件大小与 content_length 不一致（实测 '.$actualSize.' 字节），请重新发起');
        }

        $mimeType = trim((string) ($payload['mime_type'] ?? ''));
        if ($mimeType === '') {
            $mimeType = 'application/octet-stream';
        }

        $client = new QiniuClient($config);

        return $client->put($localPath, $objectKey, $mimeType);
    }

    /**
     * storage.get：下载到临时目录。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function actionGet(array $payload, array $config): array
    {
        $objectKey = QiniuSupport::resolveObjectKey(
            (string) ($payload['object_key'] ?? ''),
            (string) ($config['object_prefix'] ?? 'media')
        );
        if (! QiniuSupport::isValidObjectKey($objectKey)) {
            return QiniuSupport::failure('object_key_invalid', '对象键非法');
        }
        $targetDir = (string) ($payload['target_dir'] ?? '');
        if (! QiniuSupport::isValidTargetDir($targetDir)) {
            return QiniuSupport::failure('local_path_invalid', '下载目标目录必须位于 storage/app/tmp 之下');
        }

        $client = new QiniuClient($config);

        return $client->get($objectKey, $targetDir);
    }

    /**
     * storage.delete：删除远端对象。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function actionDelete(array $payload, array $config): array
    {
        $objectKey = QiniuSupport::resolveObjectKey(
            (string) ($payload['object_key'] ?? ''),
            (string) ($config['object_prefix'] ?? 'media')
        );
        if (! QiniuSupport::isValidObjectKey($objectKey)) {
            return QiniuSupport::failure('object_key_invalid', '对象键非法');
        }

        $client = new QiniuClient($config);

        return $client->delete($objectKey);
    }

    /**
     * storage.exists：判断远端对象是否存在。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function actionExists(array $payload, array $config): array
    {
        $objectKey = QiniuSupport::resolveObjectKey(
            (string) ($payload['object_key'] ?? ''),
            (string) ($config['object_prefix'] ?? 'media')
        );
        if (! QiniuSupport::isValidObjectKey($objectKey)) {
            return QiniuSupport::failure('object_key_invalid', '对象键非法');
        }

        $client = new QiniuClient($config);

        return $client->exists($objectKey);
    }

    /**
     * storage.url：按对象键现算外链（换域名/协议后订正存量记录用，不发网络请求、不重传文件）。
     *
     * 与 storage.put 共用 QiniuSupport::buildUrl 与同一 object_key 归一规则，
     * 因此同一配置与对象键下，本动作的 url 必须与 put 返回的 url 全等。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function actionUrl(array $payload, array $config): array
    {
        $objectKey = QiniuSupport::resolveObjectKey(
            (string) ($payload['object_key'] ?? ''),
            (string) ($config['object_prefix'] ?? 'media')
        );
        if (! QiniuSupport::isValidObjectKey($objectKey)) {
            return QiniuSupport::failure('object_key_invalid', '对象键非法（需非空、≤500、不以 / 开头、段内无 ..、仅字母数字._/-）');
        }

        $url = QiniuSupport::buildUrl((string) ($config['public_domain'] ?? ''), $objectKey);
        if (! QiniuSupport::isUsablePublicUrl($url)) {
            return QiniuSupport::failure('public_domain_missing', '请在插件配置中填写可公开访问的外链域名（需含 http:// 或 https://）');
        }

        return QiniuSupport::success('外链生成成功', ['object_key' => $objectKey, 'url' => $url]);
    }
}
