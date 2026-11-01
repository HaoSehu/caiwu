<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Addons\TencentCos;

use Caiwu\Plugins\Addons\TencentCos\Lib\TencentCosClient;
use Caiwu\Plugins\Addons\TencentCos\Lib\TencentCosSupport;
use RuntimeException;

/**
 * 腾讯云 COS 存储插件业务逻辑：storage.test / put / get / delete / exists 分发。
 */
class TencentCos
{
    /**
     * 插件入口：按 action 分发五个存储动作。
     *
     * @param  array<string,mixed>  $request  平台组装的请求（domain/slug/key/action/payload/config/context）
     * @return array{success:bool, message:string, data:array<string,mixed>}
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
                default => TencentCosSupport::failure('unknown_action', '不支持的动作：'.$action),
            };
        } catch (RuntimeException $e) {
            // 客户端用 RuntimeException + code 携带归一错误码
            $code = $e->getMessage();
            $known = ['auth_failed', 'bucket_missing', 'object_key_invalid', 'local_path_invalid', 'upstream_timeout', 'upstream_rejected', 'missing'];
            if (in_array($code, $known, true)) {
                return TencentCosSupport::failure($code, self::messages()[$code] ?? $code);
            }

            // 未知 code：消息先脱敏再透出，避免携带密钥素材
            return TencentCosSupport::failure('upstream_rejected', TencentCosSupport::sanitizeMessage($e->getMessage()));
        } catch (\Throwable $e) {
            // 兜底：任何未预期异常都不外抛，统一归一为失败结构
            return TencentCosSupport::failure('upstream_rejected', '插件内部错误：'.TencentCosSupport::sanitizeMessage($e->getMessage()));
        }
    }

    /**
     * 健康检查：静态自检（平台以无参调用，不发网络请求；连通性检查走 storage.test）。
     *
     * @return array{healthy:bool, message:string}
     */
    public function healthCheck(): array
    {
        return ['healthy' => true, 'message' => TencentCosSupport::PLUGIN_NAME.'：就绪'];
    }

    /**
     * 各错误码的中文文案。
     *
     * @return array<string,string>
     */
    private static function messages(): array
    {
        return [
            'auth_failed' => '认证失败，请检查 SecretId/SecretKey 与桶访问权限',
            'bucket_missing' => '存储桶不存在或未授权访问',
            'object_key_invalid' => '对象键不合法（非空、不超过500字符、不以/开头、不含..、仅限字母数字点下划线连字符斜杠）',
            'local_path_invalid' => '本地文件路径不合法（必须位于 public/media 或 storage/app/tmp 之内且为普通文件）',
            'upstream_timeout' => '请求腾讯云 COS 超时，请稍后重试或调大超时配置',
            'upstream_rejected' => '腾讯云 COS 返回错误，请检查配置与桶状态',
            'missing' => '对象不存在',
        ];
    }

    /**
     * storage.test：连通性测试，返回 ok/bucket/region/writable/latency_ms（按设计文档）。
     *
     * @param  array<string,mixed>  $config
     */
    private function actionTest(array $config): array
    {
        $bucket = trim((string) ($config['bucket'] ?? ''));
        $region = trim((string) ($config['region'] ?? ''));
        $startedAt = microtime(true);
        $client = new TencentCosClient($config);
        $result = $client->testBucket();
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $status = $result['status'];

        // 200/204/403 都说明域名与签名链路可达（403 仅 ListBucket 权限差异），视为链路通
        if ($status === 200 || $status === 204 || $status === 403) {
            return TencentCosSupport::success('连通性正常', [
                'ok' => true,
                'bucket' => $bucket,
                'region' => $region,
                'writable' => $status !== 403,
                'latency_ms' => $latencyMs,
            ]);
        }
        if ($status === 401) {
            return TencentCosSupport::failure('auth_failed', self::messages()['auth_failed'].'（HTTP 401）');
        }
        if ($status === 404) {
            return TencentCosSupport::failure('bucket_missing', self::messages()['bucket_missing']);
        }

        return TencentCosSupport::failure('upstream_rejected', '连通性测试失败（HTTP '.$status.'）');
    }

    /**
     * storage.put：上传本地文件。
     *
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $config
     */
    private function actionPut(array $payload, array $config): array
    {
        // 前置校验：object_key 与 local_path
        $normalizedKey = TencentCosSupport::normalizeObjectKey((string) ($payload['object_key'] ?? ''));
        if ($normalizedKey === null) {
            return TencentCosSupport::failure('object_key_invalid', self::messages()['object_key_invalid']);
        }
        $localPath = TencentCosSupport::resolveLocalFile(isset($payload['local_path']) ? (string) $payload['local_path'] : null);
        if ($localPath === null) {
            return TencentCosSupport::failure('local_path_invalid', self::messages()['local_path_invalid']);
        }

        $objectKey = TencentCosClient::applyPrefix($normalizedKey, $config);
        $mimeType = trim((string) ($payload['mime_type'] ?? 'application/octet-stream'));
        // 大小以本地实测为准，payload content_length 仅做一致性比对
        $actualSize = (int) filesize($localPath);
        $declaredLength = isset($payload['content_length']) ? (int) $payload['content_length'] : null;
        if ($declaredLength !== null && $declaredLength !== $actualSize) {
            return TencentCosSupport::failure('local_path_invalid', '本地文件大小与 content_length 不一致（实测 '.$actualSize.' 字节），请重新发起');
        }

        $client = new TencentCosClient($config);
        $result = $client->putObject($objectKey, $localPath, $mimeType);

        if ($result['status'] === 401 || $result['status'] === 403) {
            return TencentCosSupport::failure('auth_failed', self::messages()['auth_failed'].'（HTTP '.$result['status'].'）');
        }
        if ($result['status'] === 404) {
            return TencentCosSupport::failure('bucket_missing', self::messages()['bucket_missing']);
        }
        if ($result['status'] !== 200 && $result['status'] !== 204) {
            return TencentCosSupport::failure('upstream_rejected', '上传失败（HTTP '.$result['status'].'）');
        }

        $data = [
            'object_key' => $objectKey,
            'url' => TencentCosSupport::buildPublicUrl(
                $objectKey,
                trim((string) ($config['bucket'] ?? '')),
                trim((string) ($config['region'] ?? '')),
                isset($config['public_domain']) ? (string) $config['public_domain'] : null,
            ),
            'size' => $actualSize,
        ];
        $etag = $result['headers']['etag'] ?? null;
        if (is_string($etag) && $etag !== '') {
            $data['etag'] = trim($etag, '"');
        }

        return TencentCosSupport::success('上传成功', $data);
    }

    /**
     * storage.get：下载对象到临时目录。
     *
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $config
     */
    private function actionGet(array $payload, array $config): array
    {
        $normalizedKey = TencentCosSupport::normalizeObjectKey((string) ($payload['object_key'] ?? ''));
        if ($normalizedKey === null) {
            return TencentCosSupport::failure('object_key_invalid', self::messages()['object_key_invalid']);
        }
        $targetDir = TencentCosSupport::resolveTargetDir(isset($payload['target_dir']) ? (string) $payload['target_dir'] : null);
        if ($targetDir === null) {
            return TencentCosSupport::failure('local_path_invalid', '目标目录不合法（必须位于 storage/app/tmp 之内）');
        }

        $objectKey = TencentCosClient::applyPrefix($normalizedKey, $config);
        $client = new TencentCosClient($config);
        $result = $client->getObject($objectKey, $targetDir);

        if ($result['status'] === 404) {
            return TencentCosSupport::failure('missing', '对象不存在：'.$objectKey);
        }
        if ($result['status'] === 401 || $result['status'] === 403) {
            return TencentCosSupport::failure('auth_failed', self::messages()['auth_failed'].'（HTTP '.$result['status'].'）');
        }
        if ($result['status'] !== 200) {
            return TencentCosSupport::failure('upstream_rejected', '下载失败（HTTP '.$result['status'].'）');
        }

        $tempPath = (string) ($result['temp_path'] ?? '');
        if ($tempPath === '' || ! is_file($tempPath)) {
            return TencentCosSupport::failure('upstream_rejected', '下载未落盘，请重试');
        }

        return TencentCosSupport::success('下载成功', [
            'temp_path' => $tempPath,
            'size' => (int) filesize($tempPath),
        ]);
    }

    /**
     * storage.delete：删除对象（不存在视为成功 missing=true）。
     *
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $config
     */
    private function actionDelete(array $payload, array $config): array
    {
        $normalizedKey = TencentCosSupport::normalizeObjectKey((string) ($payload['object_key'] ?? ''));
        if ($normalizedKey === null) {
            return TencentCosSupport::failure('object_key_invalid', self::messages()['object_key_invalid']);
        }

        $objectKey = TencentCosClient::applyPrefix($normalizedKey, $config);
        $client = new TencentCosClient($config);
        $result = $client->deleteObject($objectKey);

        if ($result['status'] === 404) {
            return TencentCosSupport::success('对象不存在，视为已删除', [
                'deleted' => true,
                'missing' => true,
            ]);
        }
        if ($result['status'] === 401 || $result['status'] === 403) {
            return TencentCosSupport::failure('auth_failed', self::messages()['auth_failed'].'（HTTP '.$result['status'].'）');
        }
        if ($result['status'] !== 200 && $result['status'] !== 204) {
            return TencentCosSupport::failure('upstream_rejected', '删除失败（HTTP '.$result['status'].'）');
        }

        return TencentCosSupport::success('删除成功', [
            'deleted' => true,
            'missing' => false,
        ]);
    }

    /**
     * storage.exists：探测对象是否存在。
     *
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $config
     */
    private function actionExists(array $payload, array $config): array
    {
        $normalizedKey = TencentCosSupport::normalizeObjectKey((string) ($payload['object_key'] ?? ''));
        if ($normalizedKey === null) {
            return TencentCosSupport::failure('object_key_invalid', self::messages()['object_key_invalid']);
        }

        $objectKey = TencentCosClient::applyPrefix($normalizedKey, $config);
        $client = new TencentCosClient($config);
        $result = $client->headObject($objectKey);

        if ($result['status'] === 200) {
            return TencentCosSupport::success('对象存在', ['exists' => true]);
        }
        if ($result['status'] === 404) {
            return TencentCosSupport::success('对象不存在', ['exists' => false]);
        }
        if ($result['status'] === 401 || $result['status'] === 403) {
            return TencentCosSupport::failure('auth_failed', self::messages()['auth_failed'].'（HTTP '.$result['status'].'）');
        }

        return TencentCosSupport::failure('upstream_rejected', '探测失败（HTTP '.$result['status'].'）');
    }

    /**
     * storage.url：按对象键现算外链（换域名/协议后订正存量记录用，不发网络请求、不重传文件）。
     *
     * 对象键归一与 storage.put 完全一致（normalizeObjectKey + Client::applyPrefix），
     * 外链拼接复用 TencentCosSupport::buildPublicUrl，因此同一配置与对象键下
     * 本动作的 url 必须与 put 返回的 url 全等。public_domain 为空时回落桶默认域名。
     *
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $config
     */
    private function actionUrl(array $payload, array $config): array
    {
        $normalizedKey = TencentCosSupport::normalizeObjectKey((string) ($payload['object_key'] ?? ''));
        if ($normalizedKey === null) {
            return TencentCosSupport::failure('object_key_invalid', self::messages()['object_key_invalid']);
        }

        $objectKey = TencentCosClient::applyPrefix($normalizedKey, $config);
        $url = TencentCosSupport::buildPublicUrl(
            $objectKey,
            trim((string) ($config['bucket'] ?? '')),
            trim((string) ($config['region'] ?? '')),
            isset($config['public_domain']) ? (string) $config['public_domain'] : null,
        );

        if (! TencentCosSupport::isUsablePublicUrl($url)) {
            return TencentCosSupport::failure('public_domain_missing', '请在插件配置中填写可公开访问的外链域名（需含 http:// 或 https://）');
        }

        return TencentCosSupport::success('外链生成成功', ['object_key' => $objectKey, 'url' => $url]);
    }
}
