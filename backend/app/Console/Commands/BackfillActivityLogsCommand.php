<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ActivityLogStream;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;

/**
 * 把双写生效前的 operation_logs 历史回填为 activity_logs 镜像。
 *
 * 幂等键：回填行 event_id 固定为 "oplog:{operation_logs.id}"，
 * 依赖 activity_logs.event_id 唯一索引，重复执行/中断重跑自动跳过。
 *
 * 窗口规则：只回填早于双写生效时刻的行（默认取现存最早 activity_logs 行，
 * 可用 --dual-write-start 显式指定）；双写期内的行由运行时镜像负责，
 * 回填会造成同一事件两条记录，因此只统计不补写。
 */
class BackfillActivityLogsCommand extends Command
{
    protected $signature = 'log:backfill-activity
        {--execute : 实际写入；缺省为 dry-run，仅输出对账报告}
        {--dual-write-start= : 双写生效时刻（Y-m-d H:i:s），默认取现存最早的 activity_logs 行}
        {--chunk=1000 : 每批处理的 operation_logs 行数}';

    protected $description = '把双写生效前的 operation_logs 历史回填为 activity_logs 镜像（幂等，可重复执行）';

    public function handle(): int
    {
        if (! Schema::hasTable('operation_logs') || ! Schema::hasTable('activity_logs')) {
            $this->error('[log:backfill-activity] operation_logs 或 activity_logs 表不存在，终止。');

            return self::FAILURE;
        }

        $dualWriteStart = $this->resolveDualWriteStart();
        if ($dualWriteStart === null) {
            return self::FAILURE;
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $execute = (bool) $this->option('execute');

        $totalOperation = DB::table('operation_logs')->count();
        $beforeWindow = DB::table('operation_logs')->where('created_at', '<', $dualWriteStart)->count();
        $inWindow = $totalOperation - $beforeWindow;

        $this->info("双写生效时刻: {$dualWriteStart}");
        $this->info("operation_logs 总行数: {$totalOperation}（窗口前待回填 {$beforeWindow}，双写期内由镜像覆盖 {$inWindow}）");

        if ($beforeWindow === 0) {
            $this->info('没有早于双写生效时刻的 operation_logs 行，无需回填。');

            return self::SUCCESS;
        }

        $inserted = 0;
        $bar = $this->output->createProgressBar($beforeWindow);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');

        DB::table('operation_logs')
            ->where('created_at', '<', $dualWriteStart)
            ->orderBy('id')
            ->chunkById($chunkSize, function (iterable $rows) use ($execute, &$inserted, $bar): void {
                $payload = [];
                foreach ($rows as $row) {
                    $payload[] = $this->buildActivityRow($row);
                }

                if ($payload === []) {
                    return;
                }

                $inserted += $execute
                    ? (int) DB::table('activity_logs')->insertOrIgnore($payload)
                    : count($payload);

                $bar->advance(count($payload));
            }, 'id');

        $bar->finish();
        $this->newLine(2);

        if ($execute) {
            $this->info("回填完成：本次新插入 {$inserted} 行（已存在的 oplog: 镜像被唯一索引自动跳过）。");
            $mirrored = DB::table('activity_logs')->where('event_id', 'like', 'oplog:%')->count();
            $this->info("activity_logs 现有 oplog: 回填镜像共 {$mirrored} 行。");
        } else {
            $this->info("dry-run 完成：待回填 {$inserted} 行。确认后加 --execute 执行。");
        }

        return self::SUCCESS;
    }

    private function resolveDualWriteStart(): ?string
    {
        $explicit = trim((string) $this->option('dual-write-start'));
        if ($explicit !== '') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $explicit)) {
                $this->error('[log:backfill-activity] --dual-write-start 格式应为 "Y-m-d H:i:s"。');

                return null;
            }

            return $explicit;
        }

        // 排除 oplog: 回填行：回填镜像沿用源行 created_at，若计入会把锚点拉低，
        // 中断恢复时窗口漂移导致漏回填
        $earliest = DB::table('activity_logs')
            ->where(fn ($query) => $query->whereNull('event_id')->orWhere('event_id', 'not like', 'oplog:%'))
            ->min('created_at');
        if ($earliest === null) {
            $this->warn('activity_logs 为空，无法推导双写生效时刻；将回填全部 operation_logs 行。');

            return '9999-12-31 23:59:59';
        }

        return (string) $earliest;
    }

    /**
     * 映射规则与 OperationLogService::writeActivityLog 保持一致。
     *
     * @return array<string, mixed>
     */
    private function buildActivityRow(stdClass $row): array
    {
        $context = json_decode((string) $row->context, true);
        $context = is_array($context) ? $context : [];

        $module = trim((string) ($row->module ?? ''));
        $action = trim((string) ($row->action ?? ''));
        $targetId = $row->subject_id !== null ? (int) $row->subject_id : null;

        $description = $action;
        if ($module !== '' && $module !== $action) {
            $description = "[{$module}] {$action}";
        }
        if ($targetId !== null) {
            $description .= " #{$targetId}";
        }

        $actorName = trim((string) ($context['actor_name'] ?? ''));
        if ($actorName === '') {
            $actorName = match ((string) ($row->user_type ?? '')) {
                'admin' => 'admin',
                'client' => 'client',
                default => 'system',
            };
        }

        $traceId = '';
        foreach (['request_id', 'trace_id'] as $key) {
            $candidate = trim((string) ($context[$key] ?? ''));
            if ($candidate !== '') {
                $traceId = substr($candidate, 0, 64);

                break;
            }
        }

        return [
            'event_id' => 'oplog:'.(int) $row->id,
            'stream' => ActivityLogStream::resolve($module, $action),
            'actor_type' => (string) ($row->user_type ?? 'system'),
            'actor_id' => $row->user_id !== null ? (int) $row->user_id : null,
            'actor_name' => mb_substr($actorName, 0, 100),
            'module' => $module,
            'action' => mb_substr($action, 0, 100),
            'description' => mb_substr($description, 0, 5000),
            'subject_type' => $module !== '' ? $module : null,
            'subject_id' => $targetId,
            'context' => $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $row->ip_address,
            'trace_id' => $traceId !== '' ? $traceId : null,
            'created_at' => (string) $row->created_at,
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
