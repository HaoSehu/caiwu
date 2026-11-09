<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Automation\Heartbeat\Contracts\ScheduledTask;
use App\Services\Automation\Heartbeat\Data\TaskContext;
use App\Services\Automation\Heartbeat\Providers\CoreScheduledTaskProvider;
use App\Services\Automation\Heartbeat\ScheduledTaskValidator;
use App\Services\System\SettingService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * M-92 / M-67：财务审计与热备漂移任务的调度参数回归。
 *
 * M-92：finance-ledger-audit 必须带 --strict，否则金额级账实不符永远退出 0，
 *       心跳任务恒成功，告警通道形同虚设。
 * M-67：finance:hot-standby-drift 此前没有任何调度；现按每日 05:15 注册，
 *       且 finance.hot_standby=false 时调度层直接跳过，避免热备列停更产生全量假漂移。
 */
class Fix2FinanceScheduledTasksTest extends TestCase
{
    public function test_finance_ledger_audit_passes_strict_flag(): void
    {
        $task = $this->taskByKey('finance-ledger-audit');

        Artisan::shouldReceive('call')
            ->once()
            ->withArgs(function (string $command, array $parameters): bool {
                $this->assertSame('finance:audit-ledger-consistency', $command);
                $this->assertSame(true, $parameters['--strict'] ?? null);

                return true;
            })
            ->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('');

        $summary = $task->handle(new TaskContext($task->key(), 'test'));

        $this->assertSame(0, $summary['exit_code'] ?? null);
        $this->assertSame(true, $summary['parameters']['--strict'] ?? null);
    }

    public function test_hot_standby_drift_task_is_registered_daily(): void
    {
        $task = $this->taskByKey('finance-hot-standby-drift');

        $this->assertSame('财务', $task->category());
        $this->assertSame('15 5 * * *', $task->triggers()[0]->describe());

        (new ScheduledTaskValidator)->validate($task);
    }

    public function test_hot_standby_drift_task_skips_when_hot_standby_disabled(): void
    {
        config(['finance.hot_standby' => false]);

        $task = $this->taskByKey('finance-hot-standby-drift');

        Artisan::shouldReceive('call')->never();

        $summary = $task->handle(new TaskContext($task->key(), 'test'));

        $this->assertSame(true, $summary['skipped'] ?? null);
        $this->assertSame('finance.hot_standby=false', $summary['reason'] ?? null);
    }

    private function taskByKey(string $key): ScheduledTask
    {
        $provider = new CoreScheduledTaskProvider($this->stubSettingService());

        foreach ($provider->tasks() as $task) {
            if ($task->key() === $key) {
                return $task;
            }
        }

        $this->fail("核心任务 {$key} 未注册。");
    }

    private function stubSettingService(): SettingService
    {
        $service = $this->createMock(SettingService::class);
        $service->method('getAutomationConfig')->willReturn([]);

        return $service;
    }
}
