<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Addons\Ftp\Logic;

use Caiwu\Plugins\Addons\Ftp\Lib\FtpClient;
use Caiwu\Plugins\Addons\Ftp\Lib\FtpSupport;

/**
 * FTP 插件业务逻辑：execute 动作分发 + healthCheck。
 */
final class Ftp
{
    /**
     * 动作统一入口（match 分发）。
     *
     * @param  array<string, mixed>  $request
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function execute(array $request): array
    {
        // 入口自检：ext-ftp 扩展缺失时不可用
        if (! function_exists('ftp_connect')) {
            return $this->failure('dependency_missing', 'PHP 扩展 ext-ftp 未安装，插件不可用');
        }

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
                default => $this->failure('unknown_action', '不支持的动作：'.$action),
            };
        } catch (\Throwable $e) {
            // 异常消息可能夹带密钥，统一经 normalizeError 脱敏（与七牛插件一致）
            return FtpSupport::normalizeError($e, 'upstream_rejected');
        }
    }

    /**
     * 健康检查：配置齐全即可用（不发真实连接）。
     *
     * @return array{healthy: bool, message: string, details: array<string, mixed>}
     */
    public function healthCheck(): array
    {
        return [
            'healthy' => function_exists('ftp_connect'),
            'message' => function_exists('ftp_connect') ? 'FTP 扩展可用' : 'PHP 扩展 ext-ftp 未安装',
            'details' => [
                'extension' => 'ftp',
                'available' => function_exists('ftp_connect'),
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
        $client = new FtpClient($config);

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
        $localPath = (string) ($payload['local_path'] ?? '');
        $objectKey = FtpSupport::resolveObjectKey(
            (string) ($payload['object_key'] ?? ''),
            (string) ($config['object_prefix'] ?? 'media')
        );

        if (! FtpSupport::isValidObjectKey($objectKey)) {
            return $this->failure('object_key_invalid', '对象键非法（需非空、≤500、不以 / 开头、段内无 ..、仅字母数字._/-）');
        }
        if (! FtpSupport::isValidLocalPath($localPath)) {
            return $this->failure('local_path_invalid', '本地文件路径必须位于 public/media 或 storage/app/tmp 之下');
        }

        $client = new FtpClient($config);

        return $client->put($localPath, $objectKey, (string) ($config['public_domain'] ?? ''), (string) ($config['url_prefix'] ?? ''));
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
        $objectKey = FtpSupport::resolveObjectKey(
            (string) ($payload['object_key'] ?? ''),
            (string) ($config['object_prefix'] ?? 'media')
        );
        if (! FtpSupport::isValidObjectKey($objectKey)) {
            return $this->failure('object_key_invalid', '对象键非法');
        }
        $targetDir = (string) ($payload['target_dir'] ?? '');
        if (! FtpSupport::isValidTargetDir($targetDir)) {
            return $this->failure('local_path_invalid', '下载目标目录必须位于 storage/app/tmp 之下');
        }

        $client = new FtpClient($config);

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
        $objectKey = FtpSupport::resolveObjectKey(
            (string) ($payload['object_key'] ?? ''),
            (string) ($config['object_prefix'] ?? 'media')
        );
        if (! FtpSupport::isValidObjectKey($objectKey)) {
            return $this->failure('object_key_invalid', '对象键非法');
        }

        $client = new FtpClient($config);

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
        $objectKey = FtpSupport::resolveObjectKey(
            (string) ($payload['object_key'] ?? ''),
            (string) ($config['object_prefix'] ?? 'media')
        );
        if (! FtpSupport::isValidObjectKey($objectKey)) {
            return $this->failure('object_key_invalid', '对象键非法');
        }

        $client = new FtpClient($config);

        return $client->exists($objectKey);
    }

    /**
     * storage.url：按对象键现算外链（换域名/协议后订正存量记录用，不发网络请求、不重传文件）。
     *
     * 与 storage.put 共用 FtpSupport::buildUrl 与同一 object_key 归一规则，
     * 因此同一配置与对象键下，本动作的 url 必须与 put 返回的 url 全等。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function actionUrl(array $payload, array $config): array
    {
        $objectKey = FtpSupport::resolveObjectKey(
            (string) ($payload['object_key'] ?? ''),
            (string) ($config['object_prefix'] ?? 'media')
        );
        if (! FtpSupport::isValidObjectKey($objectKey)) {
            return $this->failure('object_key_invalid', '对象键非法（需非空、≤500、不以 / 开头、段内无 ..、仅字母数字._/-）');
        }

        $url = FtpSupport::buildUrl(
            (string) ($config['public_domain'] ?? ''),
            (string) ($config['url_prefix'] ?? ''),
            $objectKey
        );
        if (! FtpSupport::isUsablePublicUrl($url)) {
            return $this->failure('public_domain_missing', '请在插件配置中填写可公开访问的外链域名（需含 http:// 或 https://）');
        }

        return FtpSupport::success('外链生成成功', ['object_key' => $objectKey, 'url' => $url]);
    }

    /**
     * 统一失败结构（与 FtpSupport::failure 一致的前缀拼接）。
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function failure(string $code, string $message): array
    {
        return FtpSupport::failure($code, $message);
    }
}
