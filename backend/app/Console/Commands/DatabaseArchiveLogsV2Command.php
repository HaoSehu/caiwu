<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\System\LogArchiveV2Service;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class DatabaseArchiveLogsV2Command extends Command
{
    protected $signature = 'db:archive-logs-v2
        {--execute : 暂存导出 .part 并校验发布 manifest}
        {--purge : 对已发布批次执行源数据分块删除（危险操作；可与 --execute 组合成同一批次闭环，或配合 --batch-id 单独执行）}
        {--restore-dry-run : 按 manifest 只读校验归档物可恢复性，不导入数据}
        {--cleanup : 清理超过归档文件保留期且已完成 purge 的 V2 文件}
        {--table=* : 只处理指定白名单表，可重复}
        {--retain-days= : 日志保留天数，覆盖后台设置}
        {--file-retain-days= : V2 归档文件保留天数，覆盖后台设置}
        {--batch-id= : 指定批次（单独 --purge / --restore-dry-run 必填）}
        {--json : 以 JSON 输出结果}';

    protected $description = 'V2 两阶段归档：暂存导出 -> 校验 -> 发布 manifest -> 显式清除';

    public function handle(LogArchiveV2Service $service): int
    {
        try {
            $tables = array_values((array) $this->option('table'));
            $retentionDays = $this->optionalInteger('retain-days');
            $fileRetentionDays = $this->optionalInteger('file-retain-days');
            $batchId = trim((string) $this->option('batch-id'));
            $execute = (bool) $this->option('execute');
            $purge = (bool) $this->option('purge');
            $restore = (bool) $this->option('restore-dry-run');
            $cleanup = (bool) $this->option('cleanup');

            if ($restore && ($execute || $purge || $cleanup)) {
                throw new InvalidArgumentException('--restore-dry-run 不能与其他执行模式组合。');
            }
            if ($cleanup && ($execute || $purge)) {
                throw new InvalidArgumentException('--cleanup 不能与归档或清除模式组合。');
            }
            if ($purge && ! $execute && $batchId === '') {
                throw new InvalidArgumentException('单独 --purge 必须配合 --batch-id 指定批次。');
            }
            if ($purge && ! $execute && $tables !== []) {
                throw new InvalidArgumentException('单独 --purge 不接受 --table；请通过 --batch-id 指定已发布批次。');
            }
            if ($restore && $batchId === '') {
                throw new InvalidArgumentException('--restore-dry-run 必须配合 --batch-id 指定批次。');
            }
            if ($restore && $tables !== []) {
                throw new InvalidArgumentException('--restore-dry-run 不接受 --table。');
            }
            if ($cleanup && ($tables !== [] || $batchId !== '' || $retentionDays !== null)) {
                throw new InvalidArgumentException('--cleanup 不能与 --table、--batch-id 或 --retain-days 组合。');
            }
            if ($fileRetentionDays !== null && ! $cleanup) {
                throw new InvalidArgumentException('--file-retain-days 只能与 --cleanup 组合。');
            }

            $result = match (true) {
                $execute && $purge => $this->archiveAndPurge($service, $tables, $retentionDays),
                $purge => $service->purge($batchId),
                $restore => $service->restoreDryRun($batchId),
                $cleanup => $service->cleanup($fileRetentionDays),
                $execute => $service->archive($tables, $retentionDays, $batchId !== '' ? $batchId : null),
                default => $service->overview($tables, $retentionDays),
            };

            if ((bool) $this->option('json')) {
                $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            } else {
                $this->render($result);
            }

            return (string) ($result['status'] ?? 'completed') === 'completed' ? self::SUCCESS : self::FAILURE;
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

    /**
     * 同一批次闭环：先暂存/校验/发布，全部成功后清除源数据；任一表失败则跳过清除，
     * 源数据保持完整，符合“归档物可靠发布前不删除”的约束。
     *
     * @param  list<string>  $tables
     * @return array<string, mixed>
     */
    private function archiveAndPurge(LogArchiveV2Service $service, array $tables, ?int $retentionDays): array
    {
        $batchId = trim((string) $this->option('batch-id'));

        return $service->archiveAndPurge($tables, $retentionDays, $batchId !== '' ? $batchId : null);
    }

    /** @param array<string, mixed> $result */
    private function render(array $result): void
    {
        $this->info('模式: '.($result['mode'] ?? 'overview'));
        $this->line('批次: '.($result['batch_id'] ?? '-'));

        if (isset($result['tables'])) {
            foreach ((array) $result['tables'] as $table => $stats) {
                $this->line(sprintf(
                    '- %s: total=%d eligible=%d (id %s~%s)',
                    $table,
                    (int) ($stats['total'] ?? 0),
                    (int) ($stats['eligible'] ?? 0),
                    (string) ($stats['id_min'] ?? '-'),
                    (string) ($stats['id_max'] ?? '-'),
                ));
            }
        }

        foreach ((array) ($result['items'] ?? []) as $item) {
            $this->line(sprintf(
                '- %s: status=%s rows=%d/%d deleted=%d',
                (string) ($item['table'] ?? ''),
                (string) ($item['status'] ?? ''),
                (int) ($item['exported_rows'] ?? 0),
                (int) ($item['expected_rows'] ?? 0),
                (int) ($item['deleted_rows'] ?? 0),
            ));
            if (! empty($item['error_message'])) {
                $this->warn('  error: '.(string) $item['error_message']);
            }
        }
    }

    private function optionalInteger(string $option): ?int
    {
        $value = $this->option($option);
        if ($value === null || $value === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException("{$option} must be an integer.");
        }

        return (int) $value;
    }
}
