<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunHeartbeatTaskJob;
use App\Services\Automation\Heartbeat\Contracts\ScheduledTask;
use App\Services\Automation\Heartbeat\HeartbeatTaskRegistry;
use App\Services\Automation\Heartbeat\HeartbeatTaskRunner;
use App\Services\Automation\Heartbeat\ScheduleTaskRunRepository;
use App\Services\Automation\Heartbeat\ScheduleTickRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * 关键 Job 失败不吞异常（B5：V7-06 零覆盖缺口，BACKEND §9「关键 Job 失败不吞异常，
 * 能够进入重试、failed_jobs 或明确补偿状态」）：
 * 1. RunHeartbeatTaskJob::handle 内任务抛异常必须向上冒泡（交由队列重试与 failed_jobs 记录），
 *    且 Job 保留 3 次重试 / 60 秒退避的队列配置；
 * 2. failed() 必须把心跳运行台账收敛为终态失败（明确补偿状态，不静默丢失）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class JobFailureHandlingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_heartbeat_job_propagates_failure_and_finalizes_run_state(): void
    {
        $registry = Mockery::mock(HeartbeatTaskRegistry::class);
        $registry->shouldReceive('get')->with('convergence-task')->andReturn(Mockery::mock(ScheduledTask::class));

        $ticks = Mockery::mock(ScheduleTickRepository::class);
        $ticks->shouldReceive('findContext')->andReturn(null);

        $runner = Mockery::mock(HeartbeatTaskRunner::class);
        $runner->shouldReceive('run')->once()->andThrow(new RuntimeException('心跳任务失败'));

        $job = new RunHeartbeatTaskJob('convergence-task', null, 12345);

        try {
            $job->handle($registry, $runner, $ticks);
            $this->fail('任务异常必须向上抛出，不得被 Job 吞掉');
        } catch (RuntimeException $exception) {
            $this->assertSame('心跳任务失败', $exception->getMessage());
        }

        // 重试配置：失败后进入队列重试/最终 failed_jobs 的依据
        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->backoff);

        // failed()：把运行台账（task_run_id=12345）收敛为终态失败
        $runs = Mockery::mock(ScheduleTaskRunRepository::class);
        $runs->shouldReceive('markTerminalFailed')
            ->once()
            ->with(12345, '心跳任务失败', null)
            ->andReturn(true);
        $this->app->instance(ScheduleTaskRunRepository::class, $runs);

        $job->failed(new RuntimeException('心跳任务失败'));
    }
}
