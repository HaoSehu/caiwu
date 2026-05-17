<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\IndexNowKeyFileSyncer;
use Illuminate\Console\Command;

class SyncIndexNowKeyFileCommand extends Command
{
    protected $signature = 'site:indexnow-sync-key-file';

    protected $description = '根据当前 SEO 设置，向前端 dist 目录同步 IndexNow 密钥验证文件（{key}.txt）。';

    public function handle(IndexNowKeyFileSyncer $syncer): int
    {
        $result = $syncer->sync();

        if (($result['skipped'] ?? false) === true) {
            $reason = (string) ($result['reason'] ?? 'unknown');
            $path = (string) ($result['path'] ?? '');
            $this->warn($path !== '' ? $reason.': '.$path : $reason);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'written=%s removed=%d path=%s',
            ($result['written'] ?? false) ? '1' : '0',
            (int) ($result['removed'] ?? 0),
            (string) ($result['path'] ?? '')
        ));

        return self::SUCCESS;
    }
}
