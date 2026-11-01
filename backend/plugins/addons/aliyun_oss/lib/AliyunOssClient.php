<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Addons\AliyunOss\Lib;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * 阿里云 OSS HTTP 客户端（插件内唯一外部 IO 类）。
 *
 * 传输层通过 $transport 闭包注入，默认走 Laravel Http facade；
 * 测试可注入桩闭包断言请求形状而不发真实网络请求。
 *
 * @phpstan-type Transport callable(string $method, string $url, array<string,mixed>): array{status:int, headers:array<string,string>, body:string}
 */
final class AliyunOssClient
{
    /**
     * @param  array<string,mixed>  $config  已解密配置（access_key_id/access_key_secret/bucket/endpoint/scheme/acl/public_domain/object_prefix/timeout）
     * @param  callable|null  $transport  传输桩（测试注入用）
     */
    public function __construct(
        private readonly array $config,
        private $transport = null,
    ) {}

    /**
     * 解析配置中的存储空间名。
     */
    private function bucket(): string
    {
        return trim((string) ($this->config['bucket'] ?? ''));
    }

    /**
     * 请求超时秒数（5-120，默认 30）。
     */
    private function timeout(): int
    {
        $timeout = (int) ($this->config['timeout'] ?? 30);
        if ($timeout < 5 || $timeout > 120) {
            $timeout = 30;
        }

        return $timeout;
    }

    /**
     * 外链协议（https 默认，http 显式配置才用）。
     */
    private function scheme(): string
    {
        return strtolower(trim((string) ($this->config['scheme'] ?? 'https'))) === 'http' ? 'http' : 'https';
    }

    /**
     * 发起一次签名请求（put/get/delete/head 共用）。
     *
     * @param  string  $method  HTTP 方法
     * @param  string  $objectKey  已归一的对象键（不含前导 /）
     * @param  array<string,string>  $extraHeaders  额外头（如 content-type/x-oss-object-acl）
     * @param  array<string,mixed>  $options  传输选项（如 body 字符串、sink 文件路径）
     * @return array{status:int, headers:array<string,string>, body:string}
     */
    public function request(string $method, string $objectKey, array $extraHeaders = [], array $options = []): array
    {
        $host = sprintf('%s.%s', $this->bucket(), trim((string) $this->config['endpoint']));
        $uriPath = '/'.$objectKey;
        $url = $this->scheme().'://'.$host.$uriPath;

        // 头统一小写化；host 头由 HTTP 层自动携带，V1 签名串不含 host
        $headers = array_change_key_case($extraHeaders, CASE_LOWER);
        $ossHeaders = array_filter($headers, static fn (string $name): bool => str_starts_with($name, 'x-oss-'), ARRAY_FILTER_USE_KEY);

        $date = AliyunOssSigner::gmtDate();
        $authorization = AliyunOssSigner::authorization(
            trim((string) $this->config['access_key_id']),
            trim((string) $this->config['access_key_secret']),
            $method,
            '/'.$this->bucket().$uriPath,
            (string) ($headers['content-md5'] ?? ''),
            (string) ($headers['content-type'] ?? ''),
            $date,
            $ossHeaders,
        );

        $sendHeaders = array_merge($extraHeaders, [
            'Date' => $date,
            'Authorization' => $authorization,
        ]);

        if ($this->transport !== null) {
            $transport = $this->transport;

            return $transport($method, $url, [
                'headers' => $sendHeaders,
                'timeout' => $this->timeout(),
            ] + $options);
        }

        try {
            $response = Http::timeout($this->timeout())
                ->connectTimeout(min($this->timeout(), 10))
                ->withHeaders($sendHeaders)
                ->send($method, $url, $options);

            $headers = [];
            foreach ($response->headers() as $name => $values) {
                $headers[strtolower($name)] = (string) $values[0];
            }

            return [
                'status' => $response->status(),
                'headers' => $headers,
                'body' => (string) $response->body(),
            ];
        } catch (ConnectionException) {
            throw new RuntimeException('upstream_timeout', 0);
        }
    }

    /**
     * 上传文件：PUT /{objectKey}，body 为文件内容。
     * acl=public-read 时带 x-oss-object-acl 头（已参与 CanonicalizedOSSHeaders 签名）。
     *
     * @return array{status:int, headers:array<string,string>, body:string}
     */
    public function putObject(string $objectKey, string $localPath, string $mimeType): array
    {
        // OSS V1 签名不含 Content-Length 头，大小校验由 logic 层实测比对承担
        $headers = [
            'Content-Type' => $mimeType,
        ];
        if (self::isPublicRead($this->config)) {
            $headers['x-oss-object-acl'] = 'public-read';
        }

        return $this->request('PUT', $objectKey, $headers, [
            'body' => (string) file_get_contents($localPath),
        ]);
    }

    /**
     * 下载对象到目标目录下的临时文件：GET /{objectKey}，sink 落盘。
     *
     * @return array{status:int, headers:array<string,string>, body:string, temp_path?:string}
     */
    public function getObject(string $objectKey, string $targetDir): array
    {
        $tempPath = $targetDir.DIRECTORY_SEPARATOR.'oss_'.bin2hex(random_bytes(12)).'.tmp';

        $result = $this->request('GET', $objectKey, [], [
            'sink' => $tempPath,
        ]);
        if ($result['status'] === 200) {
            $result['temp_path'] = $tempPath;
        }

        return $result;
    }

    /**
     * 删除对象：DELETE /{objectKey}。
     *
     * @return array{status:int, headers:array<string,string>, body:string}
     */
    public function deleteObject(string $objectKey): array
    {
        return $this->request('DELETE', $objectKey);
    }

    /**
     * 探测对象是否存在：HEAD /{objectKey}（轻量，不拉内容）。
     *
     * @return array{status:int, headers:array<string,string>, body:string}
     */
    public function headObject(string $objectKey): array
    {
        return $this->request('HEAD', $objectKey);
    }

    /**
     * 桶连通性测试：GET 桶根（GetBucket(ListObject) 权限可能缺失，
     * 200/403 都说明域名与签名链路可达，仅权限差异，由调用方归一）。
     *
     * @return array{status:int, headers:array<string,string>, body:string}
     */
    public function testBucket(): array
    {
        return $this->request('GET', '');
    }

    /**
     * 是否声明 public-read ACL。
     *
     * @param  array<string,mixed>  $config
     */
    public static function isPublicRead(array $config): bool
    {
        return strtolower(trim((string) ($config['acl'] ?? ''))) === 'public-read';
    }

    /**
     * 计算（并归一）object_prefix：仅当 object_key 为裸文件名时拼接（按 lead 统一裁决）。
     */
    public static function applyPrefix(string $objectKey, array $config): string
    {
        $prefix = trim((string) ($config['object_prefix'] ?? 'media'));
        if ($prefix === '' || str_contains($objectKey, '/')) {
            return $objectKey;
        }

        return trim($prefix, '/').'/'.$objectKey;
    }
}
