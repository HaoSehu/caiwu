<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\System\StaleRecordPruneService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class DatabasePruneStaleRecordsCommand extends Command
{
    protected $signature = 'db:prune-stale-records
        {--dry-run : 仅统计将清理的行数；未指定 --execute 时默认 dry-run}
        {--execute : 物理删除过期的 API 令牌、密码重置令牌与超期失败任务}
        {--json : 以 JSON 输出结果}';

    protected $description = '清理过期即无价值的记录（API 令牌/密码重置令牌/失败任务），不归档直接删除';

    public function handle(StaleRecordPruneService $service): int
    {
        try {
            if ((bool) $this->option('execute') && (bool) $this->option('dry-run')) {
                throw new InvalidArgumentException('--execute and --dry-run cannot be used together.');
            }

            $dryRun = ! (bool) $this->option('execute');
            $result = $service->prune($dryRun);

            if ((bool) $this->option('json')) {
                $this->line(json_encode(
                    $result,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
                ));

                return self::SUCCESS;
            }

            $this->info($dryRun ? '过期记录清理预检完成（dry-run，未删除）' : '过期记录清理完成');
            foreach ($result['tables'] as $table => $report) {
                $this->line(sprintf(
                    '- %s: eligible=%d deleted=%d',
                    $table,
                    (int) $report['eligible'],
                    (int) $report['deleted'],
                ));
            }
            $this->line(sprintf(
                '合计: eligible=%d deleted=%d',
                (int) $result['totals']['eligible'],
                (int) $result['totals']['deleted'],
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ((bool) $this->option('json')) {
                $this->line(json_encode(
                    ['error' => $exception->getMessage()],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
                ));
            } else {
                $this->error($exception->getMessage());
            }

            return self::FAILURE;
        }
    }
}
