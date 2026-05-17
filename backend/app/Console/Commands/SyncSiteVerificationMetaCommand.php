<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\SiteVerificationHtmlSyncer;
use Illuminate\Console\Command;

class SyncSiteVerificationMetaCommand extends Command
{
    protected $signature = 'site:sync-verification-meta';

    protected $description = '同步站点搜索引擎验证 meta 标签到前端 dist/index.html';

    public function handle(SiteVerificationHtmlSyncer $syncer): int
    {
        $result = $syncer->sync();

        if (($result['skipped'] ?? false) === true) {
            $reason = (string) ($result['reason'] ?? 'unknown');
            $path = (string) ($result['path'] ?? '');
            $message = $path !== '' ? $reason.': '.$path : $reason;
            $this->warn($message);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'scanned=%d updated=%d removed=%d path=%s',
            (int) ($result['scanned'] ?? 0),
            (int) ($result['updated'] ?? 0),
            (int) ($result['removed'] ?? 0),
            (string) ($result['path'] ?? '')
        ));

        return self::SUCCESS;
    }
}
