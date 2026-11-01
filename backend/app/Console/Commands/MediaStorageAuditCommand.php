<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MediaFile;
use App\Support\UploadUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 媒体库存储一致性只读核对。
 *
 * 不变式（violation，存在即判定命令失败）：
 * - 远程行（disk != local）：path === url 且 object_key 非空。
 *
 * 漂移统计（informational，不判定失败）：
 * - 本地行 url 与 UploadUrl::resolve(path) 不一致（历史域名变更等造成的存量差异）。
 */
class MediaStorageAuditCommand extends Command
{
    protected $signature = 'media:storage-audit';

    protected $description = '只读核对媒体库存储记录一致性（远程行 path===url 与 object_key 非空，附本地 url 漂移统计）';

    private const MAX_VIOLATION_SAMPLES = 50;

    public function handle(): int
    {
        $diskSummary = DB::table('media_files')
            ->groupBy('disk')
            ->selectRaw('disk, count(*) as total')
            ->get();

        $remoteRows = MediaFile::query()
            ->where('disk', '!=', 'local')
            ->orderBy('id')
            ->get(['id', 'disk', 'path', 'url', 'object_key']);

        $remoteTotal = $remoteRows->count();
        $violationCount = 0;
        $violationSamples = [];

        foreach ($remoteRows as $row) {
            if ((string) $row->path === (string) $row->url
                && trim((string) ($row->object_key ?? '')) !== '') {
                continue;
            }

            $violationCount++;
            if (count($violationSamples) < self::MAX_VIOLATION_SAMPLES) {
                $violationSamples[] = sprintf(
                    '#%d disk=%s path=%s url=%s object_key=%s',
                    (int) $row->id,
                    (string) $row->disk,
                    (string) $row->path,
                    (string) $row->url,
                    (string) ($row->object_key ?? '')
                );
            }
        }

        $localRows = MediaFile::query()
            ->where('disk', 'local')
            ->orderBy('id')
            ->get(['id', 'path', 'url']);

        $localTotal = $localRows->count();
        $urlDrift = 0;
        $driftSamples = [];

        foreach ($localRows as $row) {
            $expected = (string) UploadUrl::resolve((string) $row->path);
            if ((string) $row->url === $expected) {
                continue;
            }

            $urlDrift++;
            if (count($driftSamples) < 10) {
                $driftSamples[] = sprintf('#%d path=%s url=%s expected=%s', (int) $row->id, (string) $row->path, (string) $row->url, $expected);
            }
        }

        $this->info('按存储驱动分布：');
        $this->table(
            ['disk', 'total'],
            $diskSummary->map(fn ($row): array => [(string) $row->disk, (int) $row->total])->all()
        );
        $this->line(sprintf('远程行 %d 条，本地行 %d 条。', $remoteTotal, $localTotal));
        $this->line(sprintf('本地行 url 漂移（informational）：%d 条。', $urlDrift));
        foreach ($driftSamples as $sample) {
            $this->line('  '.$sample);
        }

        if ($violationCount > 0) {
            $this->error(sprintf('发现 %d 条远程行不变式违规：', $violationCount));
            foreach ($violationSamples as $sample) {
                $this->line('  '.$sample);
            }

            return self::FAILURE;
        }

        $this->info('0 条不变式违规。');

        return self::SUCCESS;
    }
}
