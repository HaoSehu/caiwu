<?php

declare(strict_types=1);

namespace App\Support;

final class UploadUrl
{
    /**
     * 把上传文件的相对路径（例如 /uploads/content/20260418/cover_xxx.jpg）拼成完整 URL。
     *
     * 统一走 FRONTEND_URL（用户端站点域名）而不是 APP_URL：
     * - 生产环境 frontend-client 站点的 Nginx 已把 /uploads/* 反代到后端 PHP-FPM
     *   （见 文档/运维/宝塔Nginx配置/frontend-client.conf），所以从客户端域名访问 /uploads/
     *   天然可达，后端域名反而可能是内网地址（如 127.0.0.1:8000）不对外。
     * - 用 FRONTEND_URL 能同时兼容"只暴露前端站点、后端内网部署"的典型部署形态。
     *
     * 存量数据兼容：历史上曾把当时的 APP_URL / FRONTEND_URL（包括本地 127.0.0.1:8000、
     * 旧域名等）直接烧进 cover_image。这里对任意绝对 URL，只要 path 以 /uploads/ 开头，
     * 都按当前 FRONTEND_URL 重新拼一次，避免跨环境链接失效；其它外链（如手动粘贴的 CDN 图）
     * 保持原样。
     */
    public static function resolve(?string $path): ?string
    {
        $value = trim((string) $path);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $parsedPath = parse_url($value, PHP_URL_PATH);
            if (is_string($parsedPath) && self::isManagedUploadPath($parsedPath)) {
                if (! self::uploadedFileExists($parsedPath)) {
                    return $value;
                }

                return self::buildFrontendUrl($parsedPath);
            }

            return $value;
        }

        return self::buildFrontendUrl($value);
    }

    private static function uploadedFileExists(string $relativePath): bool
    {
        $normalized = '/'.ltrim($relativePath, '/');

        return is_file(public_path(ltrim($normalized, '/')));
    }

    private static function isManagedUploadPath(string $path): bool
    {
        $normalized = '/'.ltrim($path, '/');

        return str_starts_with($normalized, '/media/') || str_starts_with($normalized, '/uploads/');
    }

    private static function buildFrontendUrl(string $relativePath): string
    {
        $base = rtrim((string) config('app.frontend_url', ''), '/');

        // FRONTEND_URL 为空时兜底走 APP_URL，保证至少能拼出一个完整 URL
        if ($base === '') {
            return asset($relativePath);
        }

        return $base.'/'.ltrim($relativePath, '/');
    }
}
