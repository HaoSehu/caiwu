<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ScheduleTaskRun;
use App\Services\Automation\Heartbeat\Contracts\ScheduledTask;
use App\Services\Automation\Heartbeat\Data\TaskContext;
use App\Services\Automation\Heartbeat\HeartbeatScheduler;
use App\Services\Automation\Heartbeat\HeartbeatTaskRegistry;
use App\Services\Automation\Heartbeat\ScheduleTaskRunRepository;
use App\Services\Automation\Heartbeat\ScheduleTickRepository;
use App\Services\Automation\Heartbeat\TriggerRuleMatcher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 心跳槽位级自愈回归（TuraIDC 245af97 同源）。
 *
 * 背景：超租约的卡死运行记录原先只在「规则命中+拿锁后」回收，
 * 任务卡死时 task_readiness 会持续 503 直到下一次触发槽位（如每日 00:00）。
 * 修复：每个槽位无论规则是否命中，都先回收所有启用任务的超租约运行。
 *
 * 使用 DatabaseTransactions：ScheduleTick/ScheduleTaskRun 写入在测试结束后回滚。
 */
class HeartbeatSlotReclaimTest extends TestCase
{
    use DatabaseTransactions;

    public const TASK_KEY = 'stub-reclaim-task';

    #[Test]
    public function tick_reclaims_stale_running_run_even_when_no_rule_matches(): void
    {
        $now = CarbonImmutable::now();
        $tick = app(ScheduleTickRepository::class)->firstOrCreateSlot($now);

        $run = ScheduleTaskRun::query()->create([
            'schedule_tick_id' => (int) $tick->id,
            'task_key' => self::TASK_KEY,
            'task_name' => '卡死任务',
            'source' => 'heartbeat',
            'queue' => 'caiwu_schedule',
            'status' => ScheduleTaskRun::STATUS_RUNNING,
            'started_at' => $now->subHours(3),
        ]);
        // 模拟卡死：updated_at 回拨到 3 小时前（远超 360s 租约）。
        ScheduleTaskRun::query()->whereKey((int) $run->id)->update([
            'updated_at' => $now->subHours(3),
        ]);

        $this->runSchedulerTick($now);

        $run->refresh();
        $this->assertSame(ScheduleTaskRun::STATUS_FAILED, $run->status, '规则未命中的槽位也必须回收超租约运行');
        $this->assertStringContainsString('租约', (string) $run->error_msg);
    }

    #[Test]
    public function tick_keeps_run_within_lease(): void
    {
        $now = CarbonImmutable::now();
        $tick = app(ScheduleTickRepository::class)->firstOrCreateSlot($now);

        $run = ScheduleTaskRun::query()->create([
            'schedule_tick_id' => (int) $tick->id,
            'task_key' => self::TASK_KEY,
            'task_name' => '运行中任务',
            'source' => 'heartbeat',
            'queue' => 'caiwu_schedule',
            'status' => ScheduleTaskRun::STATUS_RUNNING,
            'started_at' => $now->subMinute(),
        ]);
        ScheduleTaskRun::query()->whereKey((int) $run->id)->update([
            'updated_at' => $now->subMinute(),
        ]);

        $this->runSchedulerTick($now);

        $run->refresh();
        $this->assertSame(ScheduleTaskRun::STATUS_RUNNING, $run->status, '租约内的运行记录不得被误杀');
    }

    private function runSchedulerTick(CarbonImmutable $now): void
    {
        // enabledTasks 在自愈循环与派发循环各调用一次；触发规则恒不命中，
        // 保证派发分支不进入队列（sync 队列下会同步执行任务）。
        $registry = Mockery::mock(HeartbeatTaskRegistry::class);
        $registry->shouldReceive('enabledTasks')->twice()->andReturn([$this->stubTask()]);

        $matcher = Mockery::mock(TriggerRuleMatcher::class);
        $matcher->shouldReceive('firstMatchedRule')->once()->andReturnNull();

        $scheduler = new HeartbeatScheduler(
            app(ScheduleTickRepository::class),
            app(ScheduleTaskRunRepository::class),
            $registry,
            $matcher,
        );

        $scheduler->tick($now);
    }

    private function stubTask(): ScheduledTask
    {
        return new class implements ScheduledTask
        {
            public function key(): string
            {
                return HeartbeatSlotReclaimTest::TASK_KEY;
            }

            public function title(): string
            {
                return '回归桩任务';
            }

            public function description(): string
            {
                return '心跳槽位自愈回归桩';
            }

            public function category(): string
            {
                return 'test';
            }

            public function triggers(): array
            {
                return [];
            }

            public function handle(TaskContext $context): array
            {
                return [];
            }

            public function queue(): string
            {
                return 'caiwu_schedule';
            }

            public function timeout(): int
            {
                return 300;
            }

            public function lockTtlSeconds(): int
            {
                return 60;
            }

            public function manualTriggerable(): bool
            {
                return false;
            }
        };
    }
}
