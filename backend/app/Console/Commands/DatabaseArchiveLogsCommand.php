<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\System\DatabaseEngineeringService;
use Illuminate\Console\Command;

class DatabaseArchiveLogsCommand extends Command
{
    protected $signature = 'db:archive-logs
        {--retain-days=180 : 主库日志保留天数}
        {--chunk=1000 : 每批删除数量}
        {--dry-run : 仅输出将处理的记录数，不实际删除}
        {--json : 以 JSON 输出结果}';

    protected $description = '分批清理 operation_logs、notification_logs、email_logs、sms_logs、automation_logs 历史数据';

    public function handle(DatabaseEngineeringService $service): int
    {
        $retainDays = max(1, (int) $this->option('retain-days'));
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $summary = $service->archiveLogs($retainDays, $chunkSize, $dryRun);

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'dry_run' => $dryRun,
                'retain_days' => $retainDays,
                'chunk' => $chunkSize,
                'summary' => $summary,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info($dryRun ? '日志归档预检完成' : '日志归档完成');
        $this->line('保留天数: '.$retainDays);
        $this->line('批大小: '.$chunkSize);
        foreach ($summary as $table => $affected) {
            $this->line("- {$table}: {$affected}");
        }

        return self::SUCCESS;
    }
}
