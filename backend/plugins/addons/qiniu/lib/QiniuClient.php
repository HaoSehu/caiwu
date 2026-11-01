<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Addons\Qiniu\Lib;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * 七牛客户端（插件内唯一外部 IO 类）。
 *
 * 网络访问统一走 Laravel Http facade，测试中可用 Http::fake() 桩替。
 * 上传 = 表单直传（multipart）；删除/存在性 = RS 管理接口；
 * 下载 = 外链 GET（私有空间带过期下载凭证）。
 */
final class QiniuClient
{
    /** @var array<string, mixed> 插件配置（已解密） */
    private readonly array $config;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * 读取字符串配置。
     */
    private function configString(string $key, string $default = ''): string
    {
        $value = $this->config[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * 读取整数配置（钳制在范围内）。
     */
    private function configInt(string $key, int $default, int $min, int $max): int
    {
        $value = $this->config[$key] ?? $default;
        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', $value) === 1)) {
            $int = (int) $value;

            return max($min, min($max, $int));
        }

        return $default;
    }

    /**
     * 读取布尔配置（switch 字段）。
     */
    private function configBool(string $key, bool $default): bool
    {
        $value = $this->config[$key] ?? $default;
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }

        return $default;
    }

    /**
     * 上传域名：upload_host 优先，否则按 region 推导。
     */
    public function uploadHost(): string
    {
        $configured = trim($this->configString('upload_host'));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }
        $region = $this->configString('region', 'z0');
        $host = match ($region) {
            'z0' => 'https://up-z0.qiniup.com',
            'cn-east-2' => 'https://up-cn-east-2.qiniup.com',
            'z1' => 'https://up-z1.qiniup.com',
            'z2' => 'https://up-z2.qiniup.com',
            'na0' => 'https://up-na0.qiniup.com',
            'as0' => 'https://up-as0.qiniup.com',
            'fog-cn-east' => 'https://up-fog-cn-east.qiniup.com',
            default => 'https://up.qiniup.com',
        };

        return $host;
    }

    /**
     * 管理域名（RS）。
     */
    public function apiHost(): string
    {
        $configured = trim($this->configString('api_host'));

        return $configured !== '' ? rtrim($configured, '/') : 'https://rs.qiniuapi.com';
    }

    /**
     * 凭据完整性检查：ak/sk/bucket 任一缺失即失败。
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function requireCredentials(): array
    {
        if ($this->configString('access_key') === '' || $this->configString('secret_key') === '') {
            return QiniuSupport::failure('auth_failed', 'AccessKey/SecretKey 未配置');
        }
        if ($this->configString('bucket') === '') {
            return QiniuSupport::failure('bucket_missing', '空间名未配置');
        }
        if (str_contains($this->configString('bucket'), ':')) {
            return QiniuSupport::failure('bucket_missing', '空间名不能包含冒号');
        }

        return QiniuSupport::success('凭据齐全', []);
    }

    /**
     * 上传文件（put）：表单直传 + key/hash 校验。
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function put(string $localPath, string $objectKey, string $mimeType = ''): array
    {
        $check = $this->requireCredentials();
        if ($check['success'] !== true) {
            return $check;
        }
        $accessKey = $this->configString('access_key');
        $secretKey = $this->configString('secret_key');
        $bucket = $this->configString('bucket');
        $timeout = $this->configInt('timeout', 30, 5, 120);

        $token = QiniuSigner::uploadToken($accessKey, $secretKey, $bucket, $objectKey, time() + 3600);
        $url = $this->uploadHost();

        // 与官方 PHP SDK FormUploader::createFile 同义：mime 作为文件 part 的 Content-Type，
        // 七牛按该值存储对象 Content-Type（不设则服务端按 key 后缀推断）。
        $partHeaders = [];
        if ($mimeType !== '') {
            $partHeaders['Content-Type'] = $mimeType;
        }

        try {
            $response = Http::timeout($timeout)
                ->attach('file', file_get_contents($localPath) ?: '', basename($localPath), $partHeaders)
                ->post($url, [
                    'key' => $objectKey,
                    'token' => $token,
                ]);
        } catch (ConnectionException $e) {
            return QiniuSupport::failure('upstream_timeout', '上传请求超时或连接失败');
        } catch (\Throwable $e) {
            return QiniuSupport::failure('upstream_rejected', '上传请求异常');
        }

        if ($response->status() === 200) {
            $body = $response->json();
            $key = is_array($body) && array_key_exists('key', $body) ? (string) $body['key'] : '';
            $hash = is_array($body) && array_key_exists('hash', $body) ? (string) $body['hash'] : '';
            if ($key === '') {
                return QiniuSupport::failure('upstream_rejected', '上传成功但响应缺少 key 字段');
            }

            return QiniuSupport::success('上传成功', [
                'object_key' => $key,
                'url' => $this->publicUrl($objectKey),
                'size' => filesize($localPath) ?: 0,
                'etag' => $hash !== '' ? $hash : null,
            ]);
        }
        if ($response->status() === 401) {
            return QiniuSupport::failure('auth_failed', '上传凭证无效（AccessKey/SecretKey 错误或权限不足）');
        }
        if ($response->status() === 614) {
            return QiniuSupport::failure('upstream_rejected', '目标对象已存在且不允许覆盖');
        }

        $error = '';
        $body = $response->json();
        if (is_array($body) && array_key_exists('error', $body)) {
            $error = (string) $body['error'];
        }

        return QiniuSupport::failure('upstream_rejected', $error !== '' ? '上传被拒绝：'.$error : '上传失败（HTTP '.$response->status().'）');
    }

    /**
     * 删除对象（delete）：RS /delete/<EncodedEntryURI>；612 按缺失成功。
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function delete(string $objectKey): array
    {
        $check = $this->requireCredentials();
        if ($check['success'] !== true) {
            return $check;
        }
        $encoded = QiniuSigner::encodedEntry($this->configString('bucket'), $objectKey);
        $result = $this->rsPost('/delete/'.$encoded);

        if ($result['success'] !== true) {
            return $result;
        }
        if ($result['data']['status'] === 612) {
            return QiniuSupport::success('对象不存在，按删除成功处理', [
                'deleted' => false,
                'missing' => true,
            ]);
        }
        if ($result['data']['status'] === 401) {
            return QiniuSupport::failure('auth_failed', '管理凭证无效（AccessKey/SecretKey 错误或权限不足）');
        }
        if ($result['data']['status'] !== 200) {
            return QiniuSupport::failure('upstream_rejected', '删除失败（HTTP '.$result['data']['status'].'）');
        }

        return QiniuSupport::success('删除成功', [
            'deleted' => true,
            'missing' => false,
        ]);
    }

    /**
     * 判断对象是否存在（exists）：RS /stat/<EncodedEntryURI>，200 存在、612 不存在。
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function exists(string $objectKey): array
    {
        $check = $this->requireCredentials();
        if ($check['success'] !== true) {
            return $check;
        }
        $encoded = QiniuSigner::encodedEntry($this->configString('bucket'), $objectKey);
        $result = $this->rsPost('/stat/'.$encoded);

        if ($result['success'] !== true) {
            return $result;
        }
        if ($result['data']['status'] === 401) {
            return QiniuSupport::failure('auth_failed', '管理凭证无效（AccessKey/SecretKey 错误或权限不足）');
        }
        if ($result['data']['status'] !== 200 && $result['data']['status'] !== 612) {
            return QiniuSupport::failure('upstream_rejected', '查询失败（HTTP '.$result['data']['status'].'）');
        }

        return QiniuSupport::success('查询成功', [
            'exists' => $result['data']['status'] === 200,
        ]);
    }

    /**
     * 下载对象（get）：公开空间直接 GET 外链；私有空间带过期下载凭证。
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function get(string $objectKey, string $targetDir): array
    {
        $url = $this->publicUrl($objectKey);
        if ($this->configBool('private_bucket', false)) {
            // 私有空间下载依赖 AccessKey/SecretKey 签发过期凭证，密钥缺失时前置拦截归一 auth_failed
            $check = $this->requireCredentials();
            if ($check['success'] !== true) {
                return $check;
            }
            $expires = time() + 3600;
            $url = QiniuSigner::privateDownloadToken(
                $this->configString('access_key'),
                $this->configString('secret_key'),
                $url,
                $expires
            );
        }
        $timeout = $this->configInt('timeout', 30, 5, 120);

        try {
            $response = Http::timeout($timeout)->get($url);
        } catch (ConnectionException $e) {
            return QiniuSupport::failure('upstream_timeout', '下载请求超时或连接失败');
        } catch (\Throwable $e) {
            return QiniuSupport::failure('upstream_rejected', '下载请求异常');
        }

        if ($response->status() !== 200) {
            return QiniuSupport::failure('upstream_rejected', '下载失败（HTTP '.$response->status().'）');
        }
        $content = $response->body();
        $tempName = 'qiniu_'.bin2hex(random_bytes(8));
        $tempPath = rtrim($targetDir, '/\\').DIRECTORY_SEPARATOR.$tempName;
        if (file_put_contents($tempPath, $content) === false) {
            return QiniuSupport::failure('upstream_rejected', '临时文件写入失败');
        }

        return QiniuSupport::success('下载成功', [
            'temp_path' => $tempPath,
            'size' => strlen($content),
        ]);
    }

    /**
     * 连通性测试（storage.test）：对 RS 管理接口做一次 stat 空间根探测（bucket 存在性）。
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function test(): array
    {
        $check = $this->requireCredentials();
        if ($check['success'] !== true) {
            return $check;
        }
        $start = microtime(true);
        // 用一个不可能存在的键探测空间可达性与凭证有效性（612 = 空间可达且凭证有效）
        $probeKey = '.plugin-health-probe-'.bin2hex(random_bytes(4));
        $encoded = QiniuSigner::encodedEntry($this->configString('bucket'), $probeKey);
        $result = $this->rsPost('/stat/'.$encoded);
        $latency = (int) round((microtime(true) - $start) * 1000);

        if ($result['success'] !== true) {
            return $result;
        }
        $status = $result['data']['status'];
        if ($status === 401) {
            return QiniuSupport::failure('auth_failed', '管理凭证无效（AccessKey/SecretKey 错误或权限不足）');
        }
        if ($status === 631) {
            return QiniuSupport::failure('bucket_missing', '空间不存在（bucket 名错误或区域不符）');
        }
        if ($status !== 200 && $status !== 612) {
            return QiniuSupport::failure('upstream_rejected', '连通性测试失败（HTTP '.$status.'）');
        }

        // 真实写探针：上传一个随机小对象再删除，writable 由实测得出而非硬编码。
        // 探针失败不影响「链路可达」结论（与 FTP 语义一致），只降级 writable=false。
        $writable = $this->probeWritable($probeKey);

        return QiniuSupport::success('连接成功', [
            'ok' => true,
            'endpoint' => $this->apiHost(),
            'bucket' => $this->configString('bucket'),
            'writable' => $writable,
            'latency_ms' => $latency,
        ]);
    }

    /**
     * 写探针：上传随机小对象成功即 writable=true，随后尽力删除清理。
     * 任何失败（上传被拒/超时/删除失败残留）都只返回 false，不抛错。
     */
    private function probeWritable(string $probeKey): bool
    {
        $tempFile = (string) tempnam(sys_get_temp_dir(), 'qiniu_probe_');
        if ($tempFile === '' || file_put_contents($tempFile, 'writable-probe') === false) {
            return false;
        }
        try {
            // 探针键无文件后缀，显式传 mime 避免七牛按后缀推断失败
            $uploaded = $this->put($tempFile, $probeKey, 'application/octet-stream');
        } catch (\Throwable $e) {
            @unlink($tempFile);

            return false;
        }
        @unlink($tempFile);
        if ($uploaded['success'] !== true) {
            return false;
        }

        // 清理探测对象；删除失败只留一个 <16B 的探测残留，不影响 writable 结论
        try {
            $this->delete($probeKey);
        } catch (\Throwable $e) {
            // 探测对象清理失败不改变写探针结论
        }

        return true;
    }

    /**
     * 公开外链。
     */
    public function publicUrl(string $objectKey): string
    {
        return QiniuSupport::buildUrl($this->configString('public_domain'), $objectKey);
    }

    /**
     * RS 管理接口 POST：带管理凭证。
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function rsPost(string $path): array
    {
        $host = $this->apiHost();
        $parsed = parse_url($host);
        if (! is_array($parsed) || ! isset($parsed['host'])) {
            return QiniuSupport::failure('upstream_rejected', '管理域名配置非法');
        }
        $hostHeader = $parsed['host'].(isset($parsed['port']) ? ':'.$parsed['port'] : '');
        // api_host 含路径前缀时（如反代部署 https://rs.example.com/qiniu），
        // 请求 URL 已带前缀（$host 原样拼接），签名路径也必须带同一前缀，
        // 否则七牛端按裸路径校验签名必然 401。
        $prefix = isset($parsed['path']) ? '/'.trim((string) $parsed['path'], '/') : '';
        if ($prefix === '/') {
            $prefix = '';
        }
        $token = QiniuSigner::accessToken(
            $this->configString('access_key'),
            $this->configString('secret_key'),
            'POST',
            $prefix.$path,
            '',
            $hostHeader
        );

        try {
            // 必须用 send()：post() 会强制带 Content-Type: application/json 与请求体，
            // 而管理凭证的签名数据不含 Content-Type 与 body，头多了会导致七牛端签名校验失败。
            $response = Http::timeout($this->configInt('timeout', 30, 5, 120))
                ->withHeaders(['Authorization' => $token])
                ->send('POST', $host.$path, []);
        } catch (ConnectionException $e) {
            return QiniuSupport::failure('upstream_timeout', '管理接口请求超时或连接失败');
        } catch (\Throwable $e) {
            return QiniuSupport::failure('upstream_rejected', '管理接口请求异常');
        }

        // 七牛 6xx 业务码（612/631 等）是真实 HTTP 状态码；为兼容测试桩与部分网关
        // 改写为 200 + body code 的场景，优先读 body 的 code 字段，HTTP status 兜底。
        $status = $response->status();
        $body = $response->json();
        if (is_array($body) && isset($body['code']) && is_int($body['code'])) {
            $status = $body['code'];
        }

        return QiniuSupport::success('请求完成', [
            'status' => $status,
        ]);
    }
}
