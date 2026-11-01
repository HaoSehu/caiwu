<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Addons\AliyunOss\Lib;

/**
 * 阿里云 OSS 插件的支撑函数：入参校验、外链拼接、错误归一。
 * 全部纯函数（realpath/is_file 依赖文件系统，但不做网络 IO）。
 */
final class AliyunOssSupport
{
    /** 插件中文名（错误消息前缀用） */
    public const PLUGIN_NAME = '阿里云OSS存储';

    /** object_key 最大长度 */
    private const OBJECT_KEY_MAX = 500;

    /**
     * 校验并归一 object_key。
     * 规则：非空、≤500、不以 / 开头、不含空段（即无 //）、段内无 ..、仅 [A-Za-z0-9._/-]。
     *
     * @return string|null 合法时返回归一后的键（首尾空白去除），非法返回 null
     */
    public static function normalizeObjectKey(string $key): ?string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > self::OBJECT_KEY_MAX) {
            return null;
        }
        if ($key[0] === '/' || str_starts_with($key, './')) {
            return null;
        }
        if (preg_match('/[^A-Za-z0-9._\/-]/', $key) === 1) {
            return null;
        }
        foreach (explode('/', $key) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $key;
    }

    /**
     * 校验 local_path：必须 realpath 后位于 public_path('media') 或 storage_path('app/tmp') 之下，且是普通文件。
     *
     * @return string|null 合法时返回 realpath 结果，非法返回 null
     */
    public static function resolveLocalFile(?string $localPath): ?string
    {
        if ($localPath === null || trim($localPath) === '') {
            return null;
        }
        $real = realpath(trim($localPath));
        if ($real === false || ! is_file($real)) {
            return null;
        }

        return self::isUnderAllowedRoot($real, [
            (string) realpath(public_path('media')),
            (string) realpath(storage_path('app/tmp')),
        ]) ? $real : null;
    }

    /**
     * 校验 get 动作的 target_dir：必须 realpath 后位于 storage_path('app/tmp') 之下（等于根也算）。
     *
     * @return string|null 合法时返回 realpath 结果，非法返回 null
     */
    public static function resolveTargetDir(?string $targetDir): ?string
    {
        if ($targetDir === null || trim($targetDir) === '') {
            return null;
        }
        $real = realpath(trim($targetDir));
        if ($real === false || ! is_dir($real)) {
            return null;
        }

        return self::isUnderAllowedRoot($real, [(string) realpath(storage_path('app/tmp'))]) ? $real : null;
    }

    /**
     * 判断真实路径是否位于任一允许的根目录之下（等于根也允许）。
     *
     * @param  string  $path  已 realpath 的绝对路径
     * @param  array<int,string>  $roots  已 realpath 的根目录列表（Windows 下统一小写比较）
     */
    private static function isUnderAllowedRoot(string $path, array $roots): bool
    {
        foreach ($roots as $root) {
            $root = rtrim($root, '/\\');
            if ($root === '') {
                continue;
            }
            $separator = DIRECTORY_SEPARATOR;
            if (self::sameDrive($path, $root) === false) {
                continue;
            }
            $needle = strtolower($path);
            if ($needle === strtolower(rtrim($root, $separator))) {
                return true;
            }
            if (str_starts_with($needle, strtolower(rtrim($root, $separator).$separator))) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断两个路径是否在同一 Windows 盘符（大小写不敏感）；非 Windows 恒真。
     */
    private static function sameDrive(string $a, string $b): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return true;
        }
        $driveA = strtoupper((string) preg_replace('/^([A-Za-z]:).*$/', '$1', $a));
        $driveB = strtoupper((string) preg_replace('/^([A-Za-z]:).*$/', '$1', $b));

        return $driveA === $driveB && $driveA !== '';
    }

    /**
     * 拼接外链地址。
     * public_domain 非空时 rtrim(public_domain,'/') . '/' . 路径段编码后的 object_key；
     * 否则用桶默认域名 {scheme}://{bucket}.{endpoint}/{object_key}。
     */
    public static function buildPublicUrl(
        string $objectKey,
        string $bucket,
        string $endpoint,
        string $scheme = 'https',
        ?string $publicDomain = null,
    ): string {
        $encodedKey = self::encodeKeyPath($objectKey);

        if ($publicDomain !== null && trim($publicDomain) !== '') {
            return rtrim(trim($publicDomain), '/').'/'.$encodedKey;
        }

        return sprintf(
            '%s://%s.%s/%s',
            $scheme === 'http' ? 'http' : 'https',
            $bucket,
            $endpoint,
            $encodedKey,
        );
    }

    /**
     * 外链是否可直接对外访问：非空、带 http/https 协议、能解析出 host。
     *
     * 四插件统一口径：storage.url 先用各自 buildUrl/buildPublicUrl 拼出地址，
     * 再用本方法判定配置是否足以生成可访问外链，不合格统一返回 public_domain_missing。
     */
    public static function isUsablePublicUrl(string $url): bool
    {
        $candidate = trim($url);
        if ($candidate === '' || preg_match('#^https?://#i', $candidate) !== 1) {
            return false;
        }

        $host = parse_url($candidate, PHP_URL_HOST);

        return is_string($host) && $host !== '';
    }

    /**
     * 对 object_key 做路径段编码：每段 rawurlencode，/ 保留。
     * object_key 已通过 normalizeObjectKey（字符集受限），此处仅防御性处理。
     */
    public static function encodeKeyPath(string $key): string
    {
        return implode('/', array_map(
            static fn (string $segment): string => rawurlencode($segment),
            explode('/', $key),
        ));
    }

    /**
     * 消息脱敏：疑似包含密钥/密码素材时替换为通用文案（与 ftp/qiniu 插件口径一致）。
     */
    public static function sanitizeMessage(string $message): string
    {
        if (preg_match('/(password|passwd|secret|token|access[_-]?key)/i', $message) === 1 && strlen($message) > 4) {
            return '上游返回了不可展示的错误信息';
        }

        return $message;
    }

    /**
     * 归一化错误返回结构。
     *
     * @param  string  $code  错误码（八类之一）
     * @param  string  $message  中文可读原因（自动加插件名前缀）
     * @return array{success:false, message:string, data:array{code:string}}
     */
    public static function failure(string $code, string $message): array
    {
        return [
            'success' => false,
            'message' => self::PLUGIN_NAME.'：'.$message,
            'data' => ['code' => $code],
        ];
    }

    /**
     * 标准成功返回结构。
     *
     * @param  string  $message  中文消息（自动加插件名前缀）
     */
    public static function success(string $message, array $data): array
    {
        return [
            'success' => true,
            'message' => self::PLUGIN_NAME.'：'.$message,
            'data' => $data,
        ];
    }
}
