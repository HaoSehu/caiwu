<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Automation\Heartbeat\CallbackScheduledTask;
use App\Services\Automation\Heartbeat\HeartbeatTaskRegistry;
use App\Services\System\AdminLogService;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * 管理日志任务标题与关键词反查的三级兜底回归：
 * 静态 TASK_META → 心跳注册表动态任务 → 原始 task key；
 * 注册表故障必须静默回退，不得打断日志页展示与文件日志反查。
 */
class AdminLogTaskTitleTest extends TestCase
{
    public function test_static_meta_takes_precedence(): void
    {
        $this->bindRegistry(new class($this->app) extends HeartbeatTaskRegistry
        {
            public function get(string $taskKey): CallbackScheduledTask
            {
                throw new RuntimeException('registry should not be consulted for static entries');
            }
        });

        $this->assertSame('接口认证刷新', $this->invokeTaskTitle('refresh-hosting-panel-auth'));
    }

    public function test_dynamic_registry_supplements_titles(): void
    {
        $this->bindRegistry(new class($this->app) extends HeartbeatTaskRegistry
        {
            public function get(string $taskKey): CallbackScheduledTask
            {
                return $this->makeTask($taskKey, 'ZJMF 财务认证刷新');
            }

            /** @return list<CallbackScheduledTask> */
            public function enabledTasks(): array
            {
                return [$this->makeTask('refresh-zjmf-finance-auth', 'ZJMF 财务认证刷新')];
            }

            private function makeTask(string $key, string $title): CallbackScheduledTask
            {
                return new CallbackScheduledTask(
                    key: $key,
                    title: $title,
                    description: '',
                    category: '测试',
                    triggers: [],
                    handler: fn (): array => [],
                );
            }
        });

        $this->assertSame('ZJMF 财务认证刷新', $this->invokeTaskTitle('refresh-zjmf-finance-auth'));
        $this->assertSame(
            ['refresh-zjmf-finance-auth' => ['ZJMF 财务认证刷新', 'refresh-zjmf-finance-auth']],
            array_intersect_key($this->invokeTaskKeywordIndex(), ['refresh-zjmf-finance-auth' => true]),
        );
    }

    public function test_registry_failure_falls_back_to_raw_task_key(): void
    {
        $this->bindRegistry(new class($this->app) extends HeartbeatTaskRegistry
        {
            public function get(string $taskKey): CallbackScheduledTask
            {
                throw new RuntimeException('registry unavailable');
            }

            /** @return list<CallbackScheduledTask> */
            public function enabledTasks(): array
            {
                throw new RuntimeException('registry unavailable');
            }
        });

        $this->assertSame('refresh-zjmf-finance-auth', $this->invokeTaskTitle('refresh-zjmf-finance-auth'));

        // 注册表故障时仅保留静态核心条目，反查不得崩溃
        $index = $this->invokeTaskKeywordIndex();
        $this->assertArrayHasKey('refresh-hosting-panel-auth', $index);
        $this->assertArrayNotHasKey('refresh-zjmf-finance-auth', $index);
    }

    private function bindRegistry(HeartbeatTaskRegistry $registry): void
    {
        $this->app->instance(HeartbeatTaskRegistry::class, $registry);
    }

    private function invokeTaskTitle(string $taskKey): string
    {
        $method = new ReflectionMethod(AdminLogService::class, 'taskTitle');
        $method->setAccessible(true);

        return $method->invoke(new AdminLogService, $taskKey);
    }

    /**
     * @return array<string, list<string>>
     */
    private function invokeTaskKeywordIndex(): array
    {
        $method = new ReflectionMethod(AdminLogService::class, 'taskKeywordIndex');
        $method->setAccessible(true);

        return $method->invoke(new AdminLogService);
    }
}
