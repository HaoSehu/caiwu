<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Automation\Heartbeat\Providers\CoreScheduledTaskProvider;
use App\Services\Automation\Heartbeat\ScheduledTaskValidator;
use App\Services\System\SettingService;
use Tests\TestCase;
use Throwable;

/**
 * 核心定时任务运行契约回归。
 *
 * 心跳槽位固定 15 分钟粒度（TickSlot::SECONDS=900），cron 的分钟字段必须是
 * 00/15/30/45，否则注册期会被 ScheduledTaskValidator 抛异常，再由
 * HeartbeatTaskRegistry 静默跳过：任务既不执行，管理端也无法手动重跑。
 * 2026-11-06 前的 finance-* 三个任务就写成 20/40/50 分钟，长期未执行。
 */
class CoreScheduledTaskContractTest extends TestCase
{
    public function test_every_core_task_satisfies_runtime_contract(): void
    {
        $validator = new ScheduledTaskValidator;
        $provider = new CoreScheduledTaskProvider($this->stubSettingService());

        $checked = [];

        foreach ($provider->tasks() as $task) {
            try {
                $validator->validate($task, CoreScheduledTaskProvider::class);
            } catch (Throwable $exception) {
                $this->fail("核心任务 {$task->key()} 不符合运行契约，注册时会被静默跳过：".$exception->getMessage());
            }

            $checked[] = $task->key();
        }

        $this->assertNotEmpty($checked, '核心任务清单为空，契约校验无意义。');

        foreach ([
            'finance-ledger-audit',
            'finance-operations-prune',
            'finance-account-balance-reconcile',
        ] as $key) {
            $this->assertContains($key, $checked, "财务任务 {$key} 未通过契约校验，会被注册表跳过而永不执行。");
        }
    }

    private function stubSettingService(): SettingService
    {
        $service = $this->createMock(SettingService::class);
        $service->method('getAutomationConfig')->willReturn([]);

        return $service;
    }
}
