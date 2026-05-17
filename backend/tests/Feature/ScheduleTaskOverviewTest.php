<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Automation\ScheduleTaskService;
use Tests\TestCase;

class ScheduleTaskOverviewTest extends TestCase
{
    public function test_schedule_task_overview_exposes_mutex_and_automation_config_state(): void
    {
        $service = app(ScheduleTaskService::class);

        $overview = $service->overview();
        $environment = (array) ($overview['environment'] ?? []);
        $scheduleMutex = (array) ($environment['schedule_mutex'] ?? []);
        $automationConfig = (array) ($environment['automation_config'] ?? []);

        $this->assertArrayHasKey('enabled', $scheduleMutex);
        $this->assertArrayHasKey('degraded', $scheduleMutex);
        $this->assertArrayHasKey('mode', $scheduleMutex);
        $this->assertArrayHasKey('reason', $scheduleMutex);
        $this->assertArrayHasKey('cache_store', $scheduleMutex);
        $this->assertArrayHasKey('os_family', $scheduleMutex);
        $this->assertArrayHasKey('status', $automationConfig);
        $this->assertArrayHasKey('fallback_reason', $automationConfig);

        $this->assertContains(
            $automationConfig['status'],
            ['loaded', 'fallback_default']
        );
        $this->assertIsBool($scheduleMutex['enabled']);
        $this->assertIsBool($scheduleMutex['degraded']);
        $this->assertIsString((string) $scheduleMutex['mode']);
        $this->assertIsString((string) $automationConfig['fallback_reason']);
    }

    public function test_schedule_task_overview_includes_product_upstream_config_sync_task(): void
    {
        $service = app(ScheduleTaskService::class);

        $overview = $service->overview();
        $tasks = collect($overview['tasks'] ?? []);
        $task = $tasks->firstWhere('key', 'product-upstream-config-sync');

        $this->assertIsArray($task);
        $this->assertSame('上游产品配置同步', $task['title'] ?? null);
        $this->assertTrue((bool) ($task['manual_triggerable'] ?? false));
    }

    public function test_schedule_task_overview_marks_queue_backlog_drain_as_manual_triggerable(): void
    {
        $service = app(ScheduleTaskService::class);

        $overview = $service->overview();
        $tasks = collect($overview['tasks'] ?? []);
        $task = $tasks->firstWhere('key', 'queue-backlog-drain');

        $this->assertIsArray($task);
        $this->assertSame('队列积压消费', $task['title'] ?? null);
        $this->assertTrue((bool) ($task['manual_triggerable'] ?? false));
    }
}
