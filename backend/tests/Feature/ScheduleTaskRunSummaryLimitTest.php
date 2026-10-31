<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ScheduleTaskRun;
use App\Services\Automation\Heartbeat\ScheduleTaskRunRepository;
use App\Services\Automation\Heartbeat\ScheduleTickRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 运行台账 summary 字节上限回归。
 *
 * 背景：同一次执行的同一份结果，审计镜像侧受
 * ScheduleRunLogService::CRON_MIRROR_SUMMARY_MAX_BYTES(4096) 约束，
 * 台账侧 markSucceeded 却把 handler 返回的完整数组直写 summary，没有上限，
 * 是 schedule_task_runs 单表体量失控的直接原因之一。
 * 本测试锁定两侧同口径：超过 4096 字节的结果必须被收敛为摘要结构。
 *
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class ScheduleTaskRunSummaryLimitTest extends TestCase
{
    use DatabaseTransactions;

    public function test_oversized_summary_is_limited_before_persist(): void
    {
        $now = CarbonImmutable::now();
        $tick = app(ScheduleTickRepository::class)->firstOrCreateSlot($now);

        $run = ScheduleTaskRun::query()->create([
            'schedule_tick_id' => (int) $tick->id,
            'task_key' => 'summary-limit-probe',
            'task_name' => '台账摘要上限探针',
            'source' => 'heartbeat',
            'queue' => 'caiwu_schedule',
            'status' => ScheduleTaskRun::STATUS_RUNNING,
            'started_at' => $now,
        ]);

        // 构造明显超过 4096 字节的结果（单字段 12KB 随机内容）。
        $huge = ['output' => str_repeat('x', 12288)];
        $ok = app(ScheduleTaskRunRepository::class)->markSucceeded((int) $run->id, $huge, 12);
        $this->assertTrue($ok, '台账状态迁移应成功');

        $stored = ScheduleTaskRun::query()->findOrFail((int) $run->id);
        $encoded = (string) json_encode($stored->summary, JSON_UNESCAPED_UNICODE);

        $this->assertLessThanOrEqual(
            4096,
            strlen($encoded),
            '台账 summary 落库后不得超过 4096 字节',
        );
    }

    public function test_small_summary_preserves_original_content(): void
    {
        $now = CarbonImmutable::now();
        $tick = app(ScheduleTickRepository::class)->firstOrCreateSlot($now);

        $run = ScheduleTaskRun::query()->create([
            'schedule_tick_id' => (int) $tick->id,
            'task_key' => 'summary-keep-probe',
            'task_name' => '台账摘要保持探针',
            'source' => 'heartbeat',
            'queue' => 'caiwu_schedule',
            'status' => ScheduleTaskRun::STATUS_RUNNING,
            'started_at' => $now,
        ]);

        $summary = ['processed' => 3, 'output' => '普通结果'];
        app(ScheduleTaskRunRepository::class)->markSucceeded((int) $run->id, $summary, 7);

        $stored = ScheduleTaskRun::query()->findOrFail((int) $run->id);

        // MySQL json 列不保留顶层键的书写顺序，因此比对内容而非顺序。
        $this->assertEquals($summary, $stored->summary, '未超限的结果必须原样保留，不得被改写');
        $this->assertArrayNotHasKey('__truncated', (array) $stored->summary, '未超限结果不应被降级为摘要结构');
    }
}
