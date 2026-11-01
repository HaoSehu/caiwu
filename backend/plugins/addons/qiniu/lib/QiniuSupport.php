<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Addons\Qiniu\Lib;

/**
 * 七牛插件支撑函数（全部纯函数，无任何 IO）。
 */
final class QiniuSupport
{
    /** 中文消息前缀：插件中文名 */
    public const MESSAGE_PREFIX = '七牛云存储';

    /**
     * 校验对象键：非空、≤500、不以 / 开头、段内无 ..、仅 [A-Za-z0-9._/-]。
     */
    public static function isValidObjectKey(string $key): bool
    {
        if ($key === '' || strlen($key) > 500) {
            return false;
        }
        if (str_starts_with($key, '/')) {
            return false;
        }
        if (! preg_match('/^[A-Za-z0-9._\/\-]+$/', $key)) {
            return false;
        }
        foreach (explode('/', $key) as $segment) {
            if ($segment === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * 解析对象键：含斜杠原样使用（完整对象键）；裸文件名才拼 object_prefix。
     */
    public static function resolveObjectKey(string $key, string $objectPrefix): string
    {
        if (str_contains($key, '/')) {
            return $key;
        }
        $prefix = trim($objectPrefix);
        if ($prefix === '' || $prefix === '/') {
            return $key;
        }

        return trim($prefix, '/').'/'.$key;
    }

    /**
     * 校验本地文件路径：realpath 必须位于 public_path('media') 或 storage_path('app/tmp') 之下且为普通文件。
     */
    public static function isValidLocalPath(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        $real = realpath($path);
        if ($real === false || ! is_file($real)) {
            return false;
        }
        $mediaReal = realpath(public_path('media'));
        $tmpReal = realpath(storage_path('app/tmp'));
        if ($mediaReal === false || $tmpReal === false) {
            return false;
        }
        $mediaRoot = rtrim($mediaReal, '/\\');
        $tmpRoot = rtrim($tmpReal, '/\\');
        if ($mediaRoot === '' || $tmpRoot === '') {
            return false;
        }

        return str_starts_with($real, $mediaRoot.DIRECTORY_SEPARATOR)
            || str_starts_with($real, $tmpRoot.DIRECTORY_SEPARATOR)
            || $real === $mediaRoot
            || $real === $tmpRoot;
    }

    /**
     * 校验下载目标目录：realpath 必须位于 storage_path('app/tmp') 之下。
     */
    public static function isValidTargetDir(string $dir): bool
    {
        if ($dir === '') {
            return false;
        }
        $real = realpath($dir);
        if ($real === false || ! is_dir($real)) {
            return false;
        }
        $tmpReal = realpath(storage_path('app/tmp'));
        if ($tmpReal === false) {
            return false;
        }
        $tmpRoot = rtrim($tmpReal, '/\\');
        if ($tmpRoot === '') {
            return false;
        }

        return $real === $tmpRoot || str_starts_with($real, $tmpRoot.DIRECTORY_SEPARATOR);
    }

    /**
     * 拼接公开外链：rtrim(public_domain,'/') . '/' . object_key，路径段单独编码（rawurlencode 保留斜杠）。
     * 无协议自动补 https://。
     */
    public static function buildUrl(string $publicDomain, string $objectKey): string
    {
        $domain = trim($publicDomain);
        if ($domain !== '' && ! preg_match('#^https?://#i', $domain)) {
            $domain = 'https://'.$domain;
        }
        $base = rtrim($domain, '/');
        $encodedKey = implode('/', array_map(
            static fn (string $segment): string => rawurlencode($segment),
            explode('/', $objectKey)
        ));

        return $base.'/'.$encodedKey;
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
     * 错误归一：异常信息归一为中文可读消息，疑似密钥内容替换为通用文案。
     */
    public static function normalizeError(\Throwable $e, string $defaultCode = 'upstream_rejected'): array
    {
        $message = $e->getMessage();
        if (preg_match('/(password|passwd|secret|token|access[_-]?key)/i', $message) === 1 && strlen($message) > 4) {
            $message = '上游返回了不可展示的错误信息';
        }

        return self::failure($defaultCode, $message);
    }

    /**
     * 统一失败结构。
     *
     * @return array{success: bool, message: string, data: array{code: string}}
     */
    public static function failure(string $code, string $message): array
    {
        return [
            'success' => false,
            'message' => self::MESSAGE_PREFIX.'：'.$message,
            'data' => ['code' => $code],
        ];
    }

    /**
     * 统一成功结构。
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public static function success(string $message, array $data): array
    {
        return [
            'success' => true,
            'message' => self::MESSAGE_PREFIX.'：'.$message,
            'data' => $data,
        ];
    }
}
