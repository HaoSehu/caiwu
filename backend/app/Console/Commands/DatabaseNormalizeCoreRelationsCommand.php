<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\System\DatabaseEngineeringService;
use Illuminate\Console\Command;

class DatabaseNormalizeCoreRelationsCommand extends Command
{
    protected $signature = 'db:normalize-core-relations
        {--dry-run : 仅统计将影响的行数，不写入、不改结构}
        {--json : 以 JSON 输出结果}';

    protected $description = '清理核心关系伪引用、孤儿记录，并回填核心表 trace_id';

    public function handle(DatabaseEngineeringService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $summary = $service->normalizeCoreRelations($dryRun);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info($dryRun ? '核心关系规范化预检完成（dry-run，未写入）' : '核心关系规范化完成');
        if ($dryRun) {
            // dry-run 计数在归一化前独立统计，个别项相对真实执行偏大（安全方向）。
            $this->line('注意：以下计数为上界估算，实际执行影响行数可能略小。');
        }
        foreach ($summary as $key => $value) {
            $this->line("- {$key}: {$value}");
        }

        return self::SUCCESS;
    }
}
