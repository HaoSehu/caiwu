<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ArchiveItem;
use App\Services\System\LogArchiveV2Service;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class DatabaseArchiveLogsV2ListCommand extends Command
{
    protected $signature = 'db:archive-logs-v2:list
        {--table= : 只列出指定白名单表}
        {--status= : 只列出指定状态}
        {--batch-id= : 只列出指定批次}
        {--limit= : 最大条数，默认 100，最大 500}
        {--json : 以 JSON 输出}';

    protected $description = '只读列出 V2 归档批次物与可恢复性（冷检索打底，不导入不删除）';

    public function handle(LogArchiveV2Service $service): int
    {
        try {
            $limit = $this->optionalInteger('limit', 100);
            if ($limit < 1 || $limit > 500) {
                throw new InvalidArgumentException('--limit 必须在 1 到 500 之间。');
            }
            $status = trim((string) $this->option('status'));
            if ($status !== '' && ! in_array($status, [
                ArchiveItem::STATUS_PLANNED,
                ArchiveItem::STATUS_STAGING,
                ArchiveItem::STATUS_VERIFIED,
                ArchiveItem::STATUS_PUBLISHED,
                ArchiveItem::STATUS_PURGING,
                ArchiveItem::STATUS_PURGED,
                ArchiveItem::STATUS_FAILED,
                ArchiveItem::STATUS_NEEDS_RECOVERY,
            ], true)) {
                throw new InvalidArgumentException('--status 不是有效归档状态。');
            }

            $result = $service->list([
                'table' => $this->option('table'),
                'status' => $status !== '' ? $status : null,
                'batch_id' => $this->option('batch-id'),
                'limit' => $limit,
            ]);

            if ((bool) $this->option('json')) {
                $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            } else {
                $this->info('归档批次物: '.count($result['items']));

                foreach ((array) ($result['items'] ?? []) as $item) {
                    $this->line(sprintf(
                        '- %s batch=%s status=%s rows=%d/%d restorable=%s',
                        (string) ($item['table'] ?? ''),
                        (string) ($item['batch_id'] ?? ''),
                        (string) ($item['status'] ?? ''),
                        (int) ($item['exported_rows'] ?? 0),
                        (int) ($item['expected_rows'] ?? 0),
                        (bool) ($item['restorable'] ?? false) ? 'yes' : 'no',
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
