<?php

declare(strict_types=1);

namespace App\Support;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

class SiteVerificationHtmlSyncer
{
    public const MANAGED_START = '<!-- site-verification:managed:start -->';

    public const MANAGED_END = '<!-- site-verification:managed:end -->';

    private const FIELDS = [
        'verify_google' => 'google-site-verification',
        'verify_baidu' => 'baidu-site-verification',
        'verify_bing' => 'msvalidate.01',
        'verify_360' => '360-site-verification',
        'verify_sogou' => 'sogou_site_verification',
    ];

    private const SKIP_DIRECTORIES = ['assets', 'novnc', 'vnc'];

    private const TARGET_FILE_NAME = 'index.html';

    public function sync(?array $settings = null): array
    {
        $distPath = $this->resolveDistPath();
        if ($distPath === null) {
            return [
                'skipped' => true,
                'reason' => 'dist_not_configured',
                'scanned' => 0,
                'updated' => 0,
                'removed' => 0,
                'files' => [],
            ];
        }

        if (! is_dir($distPath)) {
            return [
                'skipped' => true,
                'reason' => 'dist_not_found',
                'path' => $distPath,
                'scanned' => 0,
                'updated' => 0,
                'removed' => 0,
                'files' => [],
            ];
        }

        $config = $this->loadVerificationContent($settings);
        $files = $this->collectIndexHtmlFiles($distPath);

        $updated = 0;
        $removed = 0;
        $details = [];
        foreach ($files as $file) {
            try {
                $status = $this->syncFile($file, $config);
            } catch (Throwable $exception) {
                $status = 'error';
            }

            if ($status === 'updated') {
                $updated++;
            } elseif ($status === 'removed') {
                $removed++;
            }

            $details[] = ['path' => $file, 'status' => $status];
        }

        return [
            'skipped' => false,
            'path' => $distPath,
            'scanned' => count($files),
            'updated' => $updated,
            'removed' => $removed,
            'files' => $details,
        ];
    }

    private function loadVerificationContent(?array $settings = null): array
    {
        $payload = is_array($settings) ? $settings : SiteSeoConfig::seoGroupValues();
        $config = [];
        foreach (self::FIELDS as $settingKey => $metaName) {
            $value = trim((string) ($payload[$settingKey] ?? ''));
            if ($value !== '') {
                $config[$metaName] = $value;
            }
        }

        return $config;
    }

    private function resolveDistPath(): ?string
    {
        $configured = (string) config('idc.frontend.dist_path', '');
        $configured = trim($configured);
        if ($configured === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', $configured);
        $realpath = realpath($normalized);

        return $realpath !== false ? $realpath : $normalized;
    }

    private function collectIndexHtmlFiles(string $distPath): array
    {
        $base = new RecursiveDirectoryIterator(
            $distPath,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
        );

        $filtered = new RecursiveCallbackFilterIterator(
            $base,
            static function (SplFileInfo $current): bool {
                if ($current->isDir()) {
                    return ! in_array(
                        strtolower($current->getFilename()),
                        self::SKIP_DIRECTORIES,
                        true
                    );
                }

                return strtolower($current->getFilename()) === self::TARGET_FILE_NAME;
            }
        );

        $iterator = new RecursiveIteratorIterator($filtered, RecursiveIteratorIterator::LEAVES_ONLY);

        $files = [];
        foreach ($iterator as $entry) {
            if ($entry instanceof SplFileInfo && $entry->isFile()) {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function syncFile(string $filePath, array $config): string
    {
        $source = (string) @file_get_contents($filePath);
        if ($source === '') {
            return 'unchanged';
        }

        if (! preg_match('/<\/head>/i', $source)) {
            return 'missing_head';
        }

        $stripped = $this->removeVerificationTags($source);
        $next = $this->injectBlock($stripped, $config);

        if ($next === $source) {
            return 'unchanged';
        }

        if (@file_put_contents($filePath, $next) === false) {
            return 'error';
        }

        if ($config === []) {
            return $next !== $source ? 'removed' : 'unchanged';
        }

        return 'updated';
    }

    private function removeVerificationTags(string $html): string
    {
        $managedPattern = sprintf(
            '/\s*%s[\s\S]*?%s\s*/i',
            preg_quote(self::MANAGED_START, '/'),
            preg_quote(self::MANAGED_END, '/')
        );
        $html = (string) preg_replace($managedPattern, '', $html);

        foreach (self::FIELDS as $metaName) {
            $metaPattern = sprintf(
                '/\s*<meta\b[^>]*\bname=["\']%s["\'][^>]*>\s*/i',
                preg_quote($metaName, '/')
            );
            $html = (string) preg_replace($metaPattern, '', $html);
        }

        return $html;
    }

    private function injectBlock(string $html, array $config): string
    {
        if ($config === []) {
            return $html;
        }

        $newline = str_contains($html, "\r\n") ? "\r\n" : "\n";
        $tags = [];
        foreach (self::FIELDS as $metaName) {
            if (! isset($config[$metaName])) {
                continue;
            }
            $content = htmlspecialchars($config[$metaName], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $tags[] = '  <meta name="'.$metaName.'" content="'.$content.'" />';
        }

        if ($tags === []) {
            return $html;
        }

        $block = '  '.self::MANAGED_START.$newline
            .implode($newline, $tags).$newline
            .'  '.self::MANAGED_END.$newline;

        return (string) preg_replace('/<\/head>/i', $block.'</head>', $html, 1);
    }
}
