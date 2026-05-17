<?php

declare(strict_types=1);

namespace App\Support;

use FilesystemIterator;
use SplFileInfo;
use Throwable;

/**
 * IndexNow 要求密钥文件放在站点根目录，文件名为 {key}.txt，内容只包含 key 自身。
 * 这里以静态站点 dist 目录为根（同 SiteVerificationHtmlSyncer 逻辑）。保存 SEO 设置时调用
 * sync() 自动写入/清理，确保官网 / 后端重启后仍能通过 Bing IndexNow 的校验。
 */
class IndexNowKeyFileSyncer
{
    private const MANAGED_MARKER = "IndexNow:managed\n";

    /**
     * 执行同步：以 settings 中当前的 indexnow_key 为准，
     *  - 清理所有旧的 managed key 文件
     *  - 若 key 非空，写入新的 {key}.txt
     *
     * 返回值包含最终文件路径、是否跳过、删除了几个旧文件。
     *
     * @param  array<string, string>|null  $settings  可传入管理端即将保存的原始值
     * @return array<string, mixed>
     */
    public function sync(?array $settings = null): array
    {
        $distPath = $this->resolveDistPath();
        if ($distPath === null) {
            return [
                'skipped' => true,
                'reason' => 'dist_not_configured',
                'removed' => 0,
                'written' => false,
            ];
        }

        if (! is_dir($distPath)) {
            return [
                'skipped' => true,
                'reason' => 'dist_not_found',
                'path' => $distPath,
                'removed' => 0,
                'written' => false,
            ];
        }

        $rawKey = is_array($settings)
            ? (string) ($settings['indexnow_key'] ?? '')
            : (SiteSeoConfig::seoGroupValues()['indexnow_key'] ?? '');
        $key = SiteSeoConfig::normalizeIndexNowKey($rawKey);

        $removed = $this->removeStaleKeyFiles($distPath, $key);

        if ($key === '') {
            return [
                'skipped' => false,
                'path' => $distPath,
                'written' => false,
                'removed' => $removed,
            ];
        }

        $filePath = $distPath.DIRECTORY_SEPARATOR.$key.'.txt';
        $payload = $key."\n".self::MANAGED_MARKER;

        $written = @file_put_contents($filePath, $payload) !== false;

        return [
            'skipped' => false,
            'path' => $filePath,
            'written' => $written,
            'removed' => $removed,
        ];
    }

    private function resolveDistPath(): ?string
    {
        $configured = trim((string) config('idc.frontend.dist_path', ''));
        if ($configured === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', $configured);
        $realpath = realpath($normalized);

        return $realpath !== false ? $realpath : $normalized;
    }

    private function removeStaleKeyFiles(string $distPath, string $keepKey): int
    {
        $iterator = new FilesystemIterator($distPath, FilesystemIterator::SKIP_DOTS);
        $removed = 0;

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if (! $entry->isFile()) {
                continue;
            }

            $filename = $entry->getFilename();
            if (! preg_match('/^[A-Za-z0-9\-]{8,128}\.txt$/', $filename)) {
                continue;
            }

            // 只清理本类写过的文件，避免误删 robots.txt / sitemap.xml 等其他内容
            try {
                $contents = (string) @file_get_contents($entry->getPathname());
            } catch (Throwable $e) {
                continue;
            }

            if (! str_contains($contents, self::MANAGED_MARKER)) {
                continue;
            }

            $baseName = substr($filename, 0, -4);
            if ($keepKey !== '' && strcasecmp($baseName, $keepKey) === 0) {
                continue;
            }

            if (@unlink($entry->getPathname())) {
                $removed++;
            }
        }

        return $removed;
    }
}
