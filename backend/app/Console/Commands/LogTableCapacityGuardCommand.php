<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 日志/审计表容量护栏（数据库结构分析报告 P1-14）。
 *
 * 报告建议对 service_provision_attempts / payment_callbacks / gateway_logs /
 * integration_plugin_runtime_logs 做按月分区。2026-10-21 实库复核：四表合计
 * 不足 9MB、最大表 schedule_task_runs 21.6MB，分区（需停机重建表）收益远低于成本。
 *
 * 替代方案：所有高增长表已纳入 log_archive（90 天在线 + 180 天文件）。本命令提供
 * 「提前告警」——任一张表超过阈值时以非零退出码通知运维，届时再评估分区/归档提速。
 * 默认阈值 2GB，可用 --threshold-mb 覆盖；只读，不改任何数据。
 */
class LogTableCapacityGuardCommand extends Command
{
    protected $signature = 'db:log-capacity-guard
        {--threshold-mb=2048 : 单表告警阈值（MB）}
        {--json : 以 JSON 输出}';

    protected $description = '只读检查高增长日志/审计表容量，超过阈值时以非零退出码告警';

    /** 报告点名的高增长表 + 已接入归档的其它大表。 */
    private const TABLES = [
        'activity_logs',
        'admin_login_logs',
        'gateway_logs',
        'integration_plugin_runtime_logs',
        'message_delivery_attempts',
        'message_logs',
        'payment_callbacks',
        'schedule_task_runs',
        'schedule_ticks',
        'service_provision_attempts',
    ];

    public function handle(): int
    {
        $thresholdMb = max((int) $this->option('threshold-mb'), 1);
        $rows = [];

        foreach (self::TABLES as $table) {
            try {
                $stat = DB::selectOne(
                    'select DATA_LENGTH as d, INDEX_LENGTH as i from information_schema.tables
                     where table_schema = database() and table_name = ?',
                    [$table],
                );
            } catch (Throwable) {
                continue;
            }

            if ($stat === null) {
                continue;
            }

            $mb = round((((int) $stat->d) + ((int) $stat->i)) / 1048576, 2);
            $rows[$table] = [
                'size_mb' => $mb,
                'over_threshold' => $mb >= $thresholdMb,
            ];
        }

        $over = array_keys(array_filter($rows, static fn (array $row): bool => $row['over_threshold']));

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'threshold_mb' => $thresholdMb,
                'tables' => $rows,
                'over_threshold' => $over,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->table(
                ['表', '大小(MB)', '超过阈值'],
                array_map(static fn (string $table): array => [
                    $table,
                    number_format($rows[$table]['size_mb'], 2),
                    $rows[$table]['over_threshold'] ? 'YES' : '',
                ], array_keys($rows)),
            );
        }

        if ($over !== []) {
            $this->warn(sprintf(
                '%d 张表超过 %d MB：%s。归档已接入，请评估分区或缩短在线窗口。',
                count($over),
                $thresholdMb,
                implode('、', $over),
            ));

            return self::FAILURE;
        }

        if (! (bool) $this->option('json')) {
            $this->info(sprintf('全部 %d 张表均低于 %d MB 阈值。', count($rows), $thresholdMb));
        }

        return self::SUCCESS;
    }
}
