<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Addons\Qiniu\Lib;

/**
 * 七牛签名/编码纯函数类（无任何 IO）。
 *
 * 实现 URL 安全 Base64、上传凭证（上传策略签名）、
 * 管理凭证（Qiniu 签名）、私有空间下载凭证。
 */
final class QiniuSigner
{
    /**
     * URL 安全 Base64 编码：+ 换 -、/ 换 _、去掉 = 填充。
     */
    public static function urlsafeBase64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), ['+' => '-', '/' => '_']), '=');
    }

    /**
     * 生成上传凭证：token = ak:urlsafeBase64(hmacSha1(encodedPolicy, sk)):encodedPolicy。
     *
     * @param  int  $deadline  凭证有效期 Unix 时间戳
     */
    public static function uploadToken(string $accessKey, string $secretKey, string $bucket, string $key, int $deadline): string
    {
        // 上传策略：scope = bucket:key，insertOnly=0 允许覆盖同名对象（迁移场景需要）
        $policy = json_encode([
            'scope' => $bucket.':'.$key,
            'deadline' => $deadline,
            'insertOnly' => 0,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($policy === false) {
            $policy = '';
        }
        $encodedPolicy = self::urlsafeBase64($policy);
        $encodedSign = self::urlsafeBase64(hash_hmac('sha1', $encodedPolicy, $secretKey, true));

        return $accessKey.':'.$encodedSign.':'.$encodedPolicy;
    }

    /**
     * 生成管理凭证：Authorization: Qiniu <ak>:<encodedSign>。
     * 签名数据 = Method + ' ' + Path + '?' + Query + 换行 + 'Host: ' + Host + 换行（七牛管理凭证规范）。
     *
     * @param  string  $method  HTTP 方法（大写）
     * @param  string  $path  请求路径（含开头 /）
     * @param  string  $query  查询串（可为空，不含 ?）
     * @param  string  $host  请求 Host（含端口，不含协议）
     */
    public static function accessToken(string $accessKey, string $secretKey, string $method, string $path, string $query, string $host): string
    {
        // 七牛管理凭证签名数据：Method 与 Path?Query 后接换行，再接 Host 头与换行（无 body 场景省略 Content-Type 与 body 部分）
        $data = $method.' '.$path.($query !== '' ? '?'.$query : '')."\n".'Host: '.$host."\n";
        $encodedSign = self::urlsafeBase64(hash_hmac('sha1', $data, $secretKey, true));

        return 'Qiniu '.$accessKey.':'.$encodedSign;
    }

    /**
     * 生成私有空间下载凭证：e=过期时间戳，token = ak:urlsafeBase64(hmacSha1(url?e=xx, sk))。
     */
    public static function privateDownloadToken(string $accessKey, string $secretKey, string $url, int $expires): string
    {
        $urlWithE = $url.(str_contains($url, '?') ? '&' : '?').'e='.$expires;
        $encodedSign = self::urlsafeBase64(hash_hmac('sha1', $urlWithE, $secretKey, true));

        return $urlWithE.'&token='.$accessKey.':'.$encodedSign;
    }

    /**
     * 生成 EncodedEntryURI：urlsafe_base64(bucket ':' objectKey)。
     */
    public static function encodedEntry(string $bucket, string $key): string
    {
        return self::urlsafeBase64($bucket.':'.$key);
    }
}
