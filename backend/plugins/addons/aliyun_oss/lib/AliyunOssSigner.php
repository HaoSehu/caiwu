<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Addons\AliyunOss\Lib;

/**
 * 阿里云 OSS 请求签名 V1（纯函数，无任何 IO）。
 *
 * 按官方 SDK Aliyun\OSS\Signer\SignerV1 的 stringToSign 规则实现：
 *  stringToSign = VERB + "\n" + Content-MD5 + "\n" + Content-Type + "\n" + Date
 *              + "\n" + CanonicalizedOSSHeaders + CanonicalizedResource
 *  （CanonicalizedOSSHeaders 末尾自带 \n，无 x-oss-* 头时为空串）
 *  Authorization = "OSS " + AccessKeyId + ":" + base64(hmac-sha1(AccessKeySecret, stringToSign))
 *
 * 注意（对照 SDK 源码）：
 * - CanonicalizedOSSHeaders 只收 x-oss- 前缀头，名称小写按字典序，每行 "key:value\n"（值不 encode）；
 * - Content-MD5 / Content-Type 未设置时以空串参与；
 * - 上传/下载/删除普通对象不签名 URL query 子资源（signKeyList 仅 acl/uploadId 等特殊操作）。
 */
final class AliyunOssSigner
{
    /**
     * 生成 Authorization 头的完整值。
     *
     * @param  string  $accessKeyId  AccessKeyId
     * @param  string  $accessKeySecret  AccessKeySecret
     * @param  string  $method  HTTP 方法（大写，如 PUT/GET/DELETE/HEAD）
     * @param  string  $canonicalizedResource  规范化资源串（/{bucket}/{objectKey}）
     * @param  string  $contentMd5  Content-MD5 头值（未设为空串）
     * @param  string  $contentType  Content-Type 头值（未设为空串）
     * @param  string  $date  Date 头值（GMT 格式）
     * @param  array<string,string>  $ossHeaders  x-oss- 前缀头（头名小写 => 值）
     * @param  int|null  $now  当前时间戳（测试注入用，null 取 time()）
     */
    public static function authorization(
        string $accessKeyId,
        string $accessKeySecret,
        string $method,
        string $canonicalizedResource,
        string $contentMd5 = '',
        string $contentType = '',
        ?string $date = null,
        array $ossHeaders = [],
        ?int $now = null,
    ): string {
        // Date 头：GMT 格式（与 SDK 一致用 gmdate）
        $date ??= gmdate('D, d M Y H:i:s \G\M\T', $now ?? time());

        // CanonicalizedOSSHeaders：x-oss- 前缀头名称小写字典序，每行 key:value\n
        $normalized = [];
        foreach ($ossHeaders as $name => $value) {
            $normalized[strtolower((string) $name)] = (string) $value;
        }
        ksort($normalized);
        $canonicalizedOssHeaders = '';
        foreach ($normalized as $name => $value) {
            $canonicalizedOssHeaders .= $name.':'.$value."\n";
        }

        $stringToSign = strtoupper($method)
            ."\n".$contentMd5
            ."\n".$contentType
            ."\n".$date
            ."\n".$canonicalizedOssHeaders
            .$canonicalizedResource;

        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret, true));

        return 'OSS '.$accessKeyId.':'.$signature;
    }

    /**
     * 生成 Date 头值（GMT 格式，供调用方填头与签名共用同一值）。
     */
    public static function gmtDate(?int $now = null): string
    {
        return gmdate('D, d M Y H:i:s \G\M\T', $now ?? time());
    }
}
