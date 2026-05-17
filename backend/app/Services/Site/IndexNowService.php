<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Support\SiteSeoConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * IndexNow 推送服务。
 *
 * IndexNow 是 Bing / Yandex 等搜索引擎联合推出的「主动通知 URL 有更新」协议。
 * 使用方式：
 *  1. 在「站务 - SEO 设置」里填入 indexnow_key（推荐随机生成 16~32 位 ASCII）。
 *  2. 保存后后端会自动在官网 dist 根目录写入 {key}.txt 验证文件，
 *     对应 SiteVerificationHtmlSyncer + IndexNowKeyFileSyncer 的组合逻辑。
 *  3. 需要推送时，构造 URL 数组调用 submit($urls, $host)，或使用 artisan 命令批量提交。
 *
 * 约束：
 *  - 单次最多 10000 条 URL；本类按 1000 条分批。
 *  - 所有 URL 必须指向同一个 host，host 由站点 canonical_base 自动派生。
 *  - 网络失败只记日志，不抛异常，避免阻塞业务主流程。
 */
class IndexNowService
{
    private const ENDPOINT = 'https://api.indexnow.org/IndexNow';

    private const MAX_BATCH = 1000;

    private const HTTP_TIMEOUT = 8;

    /**
     * @param  array<int, string>  $urls  绝对 URL 数组，例如 ['https://www.example.com/help/1']
     * @return array<string, mixed>
     */
    public function submit(array $urls, ?string $host = null): array
    {
        $payload = SiteSeoConfig::payload();
        $key = SiteSeoConfig::normalizeIndexNowKey((string) ($payload['indexnow_key'] ?? ''));
        if ($key === '') {
            return [
                'skipped' => true,
                'reason' => 'indexnow_key_missing',
                'submitted' => 0,
            ];
        }

        $normalizedHost = $this->resolveHost($host, $payload['canonical_base'] ?? '');
        if ($normalizedHost === '') {
            return [
                'skipped' => true,
                'reason' => 'host_missing',
                'submitted' => 0,
            ];
        }

        $sanitized = $this->sanitizeUrls($urls, $normalizedHost);
        if ($sanitized === []) {
            return [
                'skipped' => true,
                'reason' => 'url_list_empty',
                'submitted' => 0,
            ];
        }

        $keyLocation = $this->resolveKeyLocation($payload['canonical_base'] ?? '', $key, $normalizedHost);

        $submitted = 0;
        $failures = [];
        foreach (array_chunk($sanitized, self::MAX_BATCH) as $chunk) {
            $result = $this->postChunk($normalizedHost, $key, $keyLocation, $chunk);
            if ($result['ok']) {
                $submitted += count($chunk);
            } else {
                $failures[] = $result;
            }
        }

        return [
            'skipped' => false,
            'submitted' => $submitted,
            'total' => count($sanitized),
            'failures' => $failures,
            'host' => $normalizedHost,
            'key_location' => $keyLocation,
        ];
    }

    private function resolveHost(?string $host, string $canonicalBase): string
    {
        $explicit = trim((string) $host);
        if ($explicit !== '') {
            return $this->extractHost($explicit);
        }

        return $this->extractHost(trim($canonicalBase));
    }

    private function extractHost(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $parsed = parse_url($value);
        if ($parsed === false) {
            return '';
        }

        if (isset($parsed['host'])) {
            return strtolower($parsed['host']);
        }

        // 允许用户直接填写纯域名（不带协议）
        $candidate = strtolower((string) ($parsed['path'] ?? ''));
        $candidate = trim($candidate, '/');

        return preg_match('/^[a-z0-9\.-]+$/', $candidate) ? $candidate : '';
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    private function sanitizeUrls(array $urls, string $host): array
    {
        $result = [];
        foreach ($urls as $url) {
            $normalized = trim((string) $url);
            if ($normalized === '') {
                continue;
            }

            $parsed = parse_url($normalized);
            if (! is_array($parsed) || ! isset($parsed['scheme'], $parsed['host'])) {
                continue;
            }

            if (! in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
                continue;
            }

            if (strtolower($parsed['host']) !== $host) {
                continue;
            }

            $result[] = $normalized;
        }

        return array_values(array_unique($result));
    }

    private function resolveKeyLocation(string $canonicalBase, string $key, string $host): string
    {
        $base = rtrim(trim($canonicalBase), '/');
        if ($base !== '' && preg_match('#^https?://#i', $base)) {
            return $base.'/'.$key.'.txt';
        }

        return 'https://'.$host.'/'.$key.'.txt';
    }

    /**
     * @param  array<int, string>  $chunk
     * @return array<string, mixed>
     */
    private function postChunk(string $host, string $key, string $keyLocation, array $chunk): array
    {
        $body = [
            'host' => $host,
            'key' => $key,
            'keyLocation' => $keyLocation,
            'urlList' => array_values($chunk),
        ];

        try {
            $response = Http::acceptJson()
                ->timeout(self::HTTP_TIMEOUT)
                ->connectTimeout(self::HTTP_TIMEOUT)
                ->withHeaders(['Content-Type' => 'application/json; charset=utf-8'])
                ->post(self::ENDPOINT, $body);

            return [
                'ok' => $response->successful() || $response->status() === 202,
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        } catch (Throwable $exception) {
            Log::warning('IndexNow submit failed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'url_count' => count($chunk),
            ]);

            return [
                'ok' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }
}
