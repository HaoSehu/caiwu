<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\BusinessException;
use App\Models\MediaFile;
use App\Services\Content\Storage\MediaReferenceRewriter;
use App\Services\Content\Storage\MediaStorageResolver;
use App\Services\Content\Storage\RemoteMediaStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 媒体库远程外链订正：按 object_key 与插件当前配置重算外链，不重传文件。
 *
 * 使用场景：远程存储站点换了域名或从 http 升到 https（前端 CSP 只放行 https 图片），
 * 存量 media_files 的 path/url 还是旧地址，浏览器预览会被拦。本命令用 storage.url
 * 动作现算新地址并回写，同时把 settings 与文章封面里的旧地址一并替换。
 *
 * 默认 dry-run，只打印将变更的行；必须显式 --execute 才写库。可重复执行：
 * 已与插件现算结果一致的行标记 unchanged 并跳过。
 */
class MediaStorageRewriteUrlsCommand extends Command
{
    protected $signature = 'media:storage-rewrite-urls
        {--driver= : 只处理指定存储驱动（插件 slug），留空处理全部远程行}
        {--dry-run : 只比对并输出差异，不写库（默认行为）}
        {--execute : 回写 media_files.path/url 并替换引用}
        {--json : 以 JSON 输出结果}';

    protected $description = '按对象键与插件当前配置重算远程媒体外链，默认 dry-run';

    public function handle(
        MediaStorageResolver $storageResolver,
        RemoteMediaStorage $remoteStorage,
        MediaReferenceRewriter $references,
    ): int {
        if ((bool) $this->option('dry-run') && (bool) $this->option('execute')) {
            $this->error('--dry-run 与 --execute 不能同时使用。');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $json = (bool) $this->option('json');
        $driverFilter = trim((string) $this->option('driver'));

        if ($driverFilter !== '') {
            try {
                $storageResolver->assertDriverAvailable($driverFilter);
            } catch (BusinessException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        $query = MediaFile::query()
            ->where('disk', '!=', MediaStorageResolver::LOCAL_DRIVER)
            ->whereNotNull('object_key')
            ->where('object_key', '!=', '')
            ->orderBy('id');

        if ($driverFilter !== '') {
            $query->where('disk', $driverFilter);
        }

        $rows = [];
        $failed = 0;

        foreach ($query->get() as $mediaFile) {
            $disk = trim((string) $mediaFile->disk);
            $objectKey = trim((string) $mediaFile->object_key);
            $oldPath = (string) $mediaFile->path;
            $oldUrl = (string) $mediaFile->url;

            if ($objectKey === '') {
                $failed++;
                $rows[] = $this->row($mediaFile, $oldUrl, null, 'missing_object_key');

                continue;
            }

            try {
                $newUrl = $remoteStorage->url($disk, $objectKey);
            } catch (Throwable $exception) {
                Log::warning('[media] 远程外链重算失败', [
                    'media_file_id' => (int) $mediaFile->id,
                    'disk' => $disk,
                    'object_key' => $objectKey,
                    'message' => $exception->getMessage(),
                ]);
                $failed++;
                $rows[] = $this->row($mediaFile, $oldUrl, null, 'url_failed: '.$exception->getMessage());

                continue;
            }

            if ($newUrl === $oldPath && $newUrl === $oldUrl) {
                $rows[] = $this->row($mediaFile, $oldUrl, $newUrl, 'unchanged');

                continue;
            }

            if (! $execute) {
                $rows[] = $this->row($mediaFile, $oldUrl, $newUrl, 'pending_updated');

                continue;
            }

            try {
                $pathMap = $references->buildPathMap($oldPath, $oldUrl, $newUrl);
                $mediaFile->forceFill(['path' => $newUrl, 'url' => $newUrl])->save();
                $references->replaceReferences($pathMap);
            } catch (Throwable $exception) {
                Log::error('[media] 远程外链回写失败', [
                    'media_file_id' => (int) $mediaFile->id,
                    'disk' => $disk,
                    'object_key' => $objectKey,
                    'message' => $exception->getMessage(),
                ]);
                $failed++;
                $rows[] = $this->row($mediaFile, $oldUrl, $newUrl, 'write_failed: '.$exception->getMessage());

                continue;
            }

            $rows[] = $this->row($mediaFile, $oldUrl, $newUrl, 'updated');
        }

        $summary = [
            'mode' => $execute ? 'execute' : 'dry-run',
            'driver' => $driverFilter !== '' ? $driverFilter : null,
            'total' => count($rows),
            'written' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'updated')),
            'pending' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'pending_updated')),
            'unchanged' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'unchanged')),
            'failed' => $failed,
            'rows' => $rows,
        ];

        if ($json) {
            $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return $failed === 0 ? self::SUCCESS : self::FAILURE;
        }

        foreach ($rows as $row) {
            $this->line(sprintf(
                '- #%d %s %s: %s -> %s',
                $row['media_file_id'],
                $row['disk'],
                $row['status'],
                $row['old_url'],
                $row['new_url'] ?? '(未算出)',
            ));
        }

        $this->info(sprintf(
            '远程外链订正（%s）完成：共 %d 条，写入 %d 条，待写入 %d 条，未变化 %d 条，失败 %d 条。',
            $summary['mode'],
            $summary['total'],
            $summary['written'],
            $summary['pending'],
            $summary['unchanged'],
            $summary['failed'],
        ));

        if (! $execute && $summary['pending'] > 0) {
            $this->warn('当前为 dry-run，未写库；确认无误后执行 php artisan media:storage-rewrite-urls --execute，并重跑 php artisan media:storage-audit。');
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{media_file_id: int, disk: string, object_key: string, old_url: string, new_url: ?string, status: string}
     */
    private function row(MediaFile $mediaFile, string $oldUrl, ?string $newUrl, string $status): array
    {
        return [
            'media_file_id' => (int) $mediaFile->id,
            'disk' => (string) $mediaFile->disk,
            'object_key' => (string) ($mediaFile->object_key ?? ''),
            'old_url' => $oldUrl,
            'new_url' => $newUrl,
            'status' => $status,
        ];
    }
}
