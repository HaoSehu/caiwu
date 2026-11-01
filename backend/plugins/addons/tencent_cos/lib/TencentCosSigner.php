<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Addons\TencentCos\Lib;

/**
 * 腾讯云 COS 请求签名（纯函数，无任何 IO）。
 *
 * 按官方六步算法实现（对照官方 SDK Qcloud\Cos\Signature::createAuthorization）：
 *  1. KeyTime = start;end（秒级时间戳，间隔 3600 秒，起点前移 60 秒容差）
 *  2. SignKey = HMAC-SHA1(SecretKey, KeyTime) 的小写十六进制
 *  3. HttpString = method\nUriPathname\nHttpParameters\nHttpHeaders\n（method 小写，空项保留空行）
 *  4. StringToSign = "sha1\n" + KeyTime + "\n" + SHA1(HttpString)
 *  5. Signature = HMAC-SHA1(SignKey, StringToSign)
 *  6. Authorization 头按固定顺序拼接 q-sign-* 参数
 *
 * 参与签名的头：host、content-type、content-length（至少）+ 全部 x-cos-* 头；
 * 头名与头值均需 rawurlencode（官方 SDK 对 value 用 rawurlencode，头名本身是安全字符）。
 */
final class TencentCosSigner
{
    /**
     * 生成 Authorization 头的完整值。
     *
     * @param  string  $secretId  云 API 密钥 SecretId
     * @param  string  $secretKey  云 API 密钥 SecretKey
     * @param  string  $method  HTTP 方法（大写或小写均可，内部统一小写）
     * @param  string  $uriPath  URL 路径（已编码，如 /media/a.jpg）
     * @param  array<string,string>  $headers  参与签名的头（头名小写 => 头值）
     * @param  array<string,string>  $params  参与签名的 URL 查询参数（参数名小写 => 值，可为空数组）
     * @param  int|null  $now  当前时间戳（测试注入用，null 取 time()）
     */
    public static function authorization(
        string $secretId,
        string $secretKey,
        string $method,
        string $uriPath,
        array $headers,
        array $params = [],
        ?int $now = null,
    ): string {
        $timestamp = $now ?? time();
        $keyTime = ($timestamp - 60).';'.($timestamp + 3600);

        // 第 2 步：SignKey
        $signKey = hash_hmac('sha1', $keyTime, $secretKey);

        // 第 3 步：HttpString 的参数与头部分（均按头名/参数名小写字典序）
        $paramPairs = [];
        foreach ($params as $name => $value) {
            $name = strtolower((string) $name);
            $paramPairs[$name] = $name.'='.rawurlencode((string) $value);
        }
        ksort($paramPairs);
        $urlParamList = implode(';', array_keys($paramPairs));
        $httpParameters = implode('&', $paramPairs);

        $headerPairs = [];
        foreach ($headers as $name => $value) {
            $name = strtolower((string) $name);
            $headerPairs[$name] = $name.'='.rawurlencode((string) $value);
        }
        ksort($headerPairs);
        $headerList = implode(';', array_keys($headerPairs));
        $httpHeaders = implode('&', $headerPairs);

        $httpString = strtolower($method)."\n".$uriPath."\n".$httpParameters."\n".$httpHeaders."\n";

        // 第 4、5 步
        $stringToSign = 'sha1'."\n".$keyTime."\n".sha1($httpString)."\n";
        $signature = hash_hmac('sha1', $stringToSign, $signKey);

        // 第 6 步
        return 'q-sign-algorithm=sha1'
            .'&q-ak='.$secretId
            .'&q-sign-time='.$keyTime
            .'&q-key-time='.$keyTime
            .'&q-header-list='.$headerList
            .'&q-url-param-list='.$urlParamList
            .'&q-signature='.$signature;
    }
}
