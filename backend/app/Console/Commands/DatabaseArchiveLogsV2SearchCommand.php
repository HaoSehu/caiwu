<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\System\LogArchiveV2Service;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class DatabaseArchiveLogsV2SearchCommand extends Command
{
    protected $signature = 'db:archive-logs-v2:search
        {--table= : 必填，归档白名单表名}
        {--start-date= : 必填，起始时间（Y-m-d 或 Y-m-d H:i:s），含}
        {--end-date= : 必填，结束时间（Y-m-d 或 Y-m-d H:i:s），含}
        {--limit= : 最大命中条数，默认 100，最大 500}
        {--json : 以 JSON 输出}';

    protected $description = '只读冷检索：在已发布归档 CSV 中按表与时间范围匹配记录，不导入不删除';

    public function handle(LogArchiveV2Service $service): int
    {
        try {
            $table = (string) $this->option('table');
            if ($table === '') {
                throw new InvalidArgumentException('--table 必填。');
            }

            $limit = $this->optionalInteger('limit', 100);
            if ($limit < 1 || $limit > 500) {
                throw new InvalidArgumentException('--limit 必须在 1 到 500 之间。');
            }

            $result = $service->search([
                'table' => $table,
                'start_date' => $this->option('start-date'),
                'end_date' => $this->option('end-date'),
                'limit' => $limit,
            ]);

            if ((bool) $this->option('json')) {
                $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            } else {
                $this->info("归档命中: {$result['count']} 条");

                foreach ((array) ($result['items'] ?? []) as $row) {
                    $this->line(sprintf(
                        '- id=%d created_at=%s batch=%s file=%s restorable=%s',
                        (int) ($row['id'] ?? 0),
                        (string) ($row['created_at'] ?? ''),
                        (string) ($row['batch_id'] ?? ''),
                        (string) ($row['file'] ?? ''),
                        (bool) ($row['restorable'] ?? false) ? 'yes' : 'no',
                    ));
                }
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ((bool) $this->option('json')) {
                $this->line(json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } else {
                $this->error($exception->getMessage());
            }

            return self::FAILURE;
        }
    }

    private function optionalInteger(string $option, int $default): int
    {
        $value = $this->option($option);
        if ($value === null || $value === '') {
            return $default;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException("{$option} must be an integer.");
        }

        return (int) $value;
    }
}
