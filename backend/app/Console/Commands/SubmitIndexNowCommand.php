<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Site\IndexNowService;
use App\Support\SiteSeoConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class SubmitIndexNowCommand extends Command
{
    protected $signature = 'site:indexnow-submit
        {urls?* : 绝对 URL 列表；留空则会从 dist/sitemap.xml 读取全部 URL}
        {--sitemap= : 指定 sitemap.xml 文件的绝对路径，覆盖默认的 dist/sitemap.xml}
        {--host= : 覆盖 host，默认从 canonical_base 派生}
        {--limit=0 : 最多提交多少条 URL，0 表示不限}';

    protected $description = '向 IndexNow(Bing/Yandex 等) 推送指定 URL；未传入 URL 时自动读取 sitemap.xml。';

    public function handle(IndexNowService $service): int
    {
        $urls = (array) $this->argument('urls');
        if ($urls === []) {
            $urls = $this->loadUrlsFromSitemap((string) $this->option('sitemap'));
            if ($urls === []) {
                $this->warn('未找到任何 URL，可用 php artisan site:indexnow-submit URL1 URL2 ... 手动提交。');

                return self::FAILURE;
            }
            $this->line(sprintf('[sitemap] 读取到 %d 条 URL', count($urls)));
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0 && count($urls) > $limit) {
            $urls = array_slice($urls, 0, $limit);
        }

        $host = (string) $this->option('host');

        $result = $service->submit($urls, $host !== '' ? $host : null);

        if (($result['skipped'] ?? false) === true) {
            $this->warn('推送跳过：'.(string) ($result['reason'] ?? 'unknown'));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'submitted=%d total=%d host=%s key_location=%s',
            (int) ($result['submitted'] ?? 0),
            (int) ($result['total'] ?? 0),
            (string) ($result['host'] ?? ''),
            (string) ($result['key_location'] ?? '')
        ));

        $failures = is_array($result['failures'] ?? null) ? $result['failures'] : [];
        foreach ($failures as $failure) {
            $this->warn('  - 失败：'.json_encode($failure, JSON_UNESCAPED_UNICODE));
        }

        return count($failures) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function loadUrlsFromSitemap(string $explicitPath): array
    {
        $candidates = [];
        if ($explicitPath !== '') {
            $candidates[] = $explicitPath;
        }

        $distPath = trim((string) config('idc.frontend.dist_path', ''));
        if ($distPath !== '') {
            $candidates[] = rtrim($distPath, DIRECTORY_SEPARATOR.'/').DIRECTORY_SEPARATOR.'sitemap.xml';
        }

        foreach ($candidates as $candidate) {
            if ($candidate === '' || ! File::exists($candidate)) {
                continue;
            }

            try {
                $xml = File::get($candidate);
            } catch (Throwable $exception) {
                $this->warn('读取 sitemap 失败：'.$exception->getMessage());

                continue;
            }

            $urls = $this->extractUrlsFromXml($xml);
            if ($urls !== []) {
                // 用 canonical_base 补齐相对 URL（防御生成工具忘了注入 SITE_URL）
                $urls = $this->resolveAbsoluteUrls($urls);

                return $urls;
            }
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function extractUrlsFromXml(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();

        if ($document === false) {
            return [];
        }

        $urls = [];
        foreach ($document->url as $entry) {
            $loc = trim((string) $entry->loc);
            if ($loc !== '') {
                $urls[] = $loc;
            }
        }

        return $urls;
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    private function resolveAbsoluteUrls(array $urls): array
    {
        $payload = SiteSeoConfig::payload();
        $base = rtrim((string) ($payload['canonical_base'] ?? ''), '/');
        $result = [];

        foreach ($urls as $url) {
            if (preg_match('#^https?://#i', $url)) {
                $result[] = $url;

                continue;
            }

            if ($base === '') {
                continue;
            }

            $result[] = $base.(str_starts_with($url, '/') ? $url : '/'.$url);
        }

        return $result;
    }
}
