<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\System\LogArchiveService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

class DatabaseArchiveLogsCommand extends Command
{
    protected $signature = 'db:archive-logs
        {--dry-run : 仅生成归档预检报告；未指定 --execute/--restore 时默认 dry-run}
        {--execute : 先导出 JSONL 归档文件再删除符合策略的普通日志}
        {--restore= : 从 archive manifest 恢复已归档日志}
        {--table=* : 只处理指定普通日志表，可重复；禁止财务/审计表}
        {--retain-days= : 临时覆盖所有表保留天数}
        {--chunk=1000 : 每批导出、删除或恢复数量}
        {--path= : 归档/报告输出根目录，默认 storage/app/private/log-archives}
        {--json : 以 JSON 输出结果}';

    protected $description = '按策略归档普通日志，支持 dry-run、JSONL 归档、manifest 恢复';

    public function handle(LogArchiveService $service): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $restorePath = trim((string) $this->option('restore'));

        try {
            if ($restorePath !== '') {
                $result = $service->restore($restorePath, $chunkSize);

                return $this->outputResult($result, 'restore');
            }

            $options = [
                'tables' => array_values((array) $this->option('table')),
                'chunk' => $chunkSize,
            ];

            $basePath = trim((string) $this->option('path'));
            if ($basePath !== '') {
                $options['base_path'] = $basePath;
            }

            $retainDays = $this->option('retain-days');
            if ($retainDays !== null && $retainDays !== '') {
                $options['retain_days'] = max(1, (int) $retainDays);
            }

            $execute = (bool) $this->option('execute') && ! (bool) $this->option('dry-run');
            $result = $execute ? $service->archive($options) : $service->dryRun($options);

            return $this->outputResult($result, $execute ? 'archive' : 'dry_run');
        } catch (InvalidArgumentException|RuntimeException $exception) {
            if ((bool) $this->option('json')) {
                $this->line(json_encode([
                    'error' => $exception->getMessage(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            } else {
                $this->error($exception->getMessage());
            }

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function outputResult(array $result, string $mode): int
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if ($mode === 'restore') {
            $this->info('日志归档恢复完成');
            $this->line('Manifest: '.($result['manifest_path'] ?? ''));

            foreach ((array) ($result['tables'] ?? []) as $table => $tableReport) {
                $this->line(sprintf(
                    '- %s: restored=%d status=%s',
                    $table,
                    (int) ($tableReport['restored_rows'] ?? 0),
                    (string) ($tableReport['status'] ?? '')
                ));
            }

            return self::SUCCESS;
        }

        $this->info($mode === 'archive' ? '日志归档执行完成' : '日志归档 dry-run 完成');
        $this->line('数据库: '.($result['database'] ?? ''));
        $this->line('批大小: '.($result['chunk'] ?? ''));
        $this->line('报告/Manifest: '.($result['manifest_path'] ?? $result['report_path'] ?? ''));
        $this->line('排除财务/审计表: '.implode(', ', (array) ($result['excluded_audit_tables'] ?? [])));
        $this->line(sprintf(
            '合计: eligible=%d exported=%d deleted=%d',
            (int) ($result['totals']['eligible_rows'] ?? 0),
            (int) ($result['totals']['exported_rows'] ?? 0),
            (int) ($result['totals']['deleted_rows'] ?? 0)
        ));

        foreach ((array) ($result['tables'] ?? []) as $table => $tableReport) {
            $this->line(sprintf(
                '- %s: retain=%sd cutoff=%s total=%d eligible=%d exported=%d deleted=%d',
                $table,
                (string) ($tableReport['retain_days'] ?? ''),
                (string) ($tableReport['cutoff'] ?? ''),
                (int) ($tableReport['total_rows'] ?? 0),
                (int) ($tableReport['eligible_rows'] ?? 0),
                (int) ($tableReport['exported_rows'] ?? 0),
                (int) ($tableReport['deleted_rows'] ?? 0)
            ));
        }

        if ($mode === 'archive') {
            $this->warn('已先写入 JSONL 归档文件，再删除同一批 id；需要恢复时执行 --restore=manifest.json。');
        } else {
            $this->warn('当前为 dry-run，未删除任何日志；确认报告和备份策略后再显式追加 --execute。');
        }

        return self::SUCCESS;
    }
}
