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
        $this->assertSame('system', $task['source_type'] ?? null);
        $this->assertSame('系统任务', $task['source_label'] ?? null);
        $this->assertTrue((bool) ($task['manual_triggerable'] ?? false));
    }

    public function test_schedule_task_overview_does_not_expose_internal_or_deprecated_tasks_as_business_tasks(): void
    {
        $service = app(ScheduleTaskService::class);

        $overview = $service->overview();
        $tasks = collect($overview['tasks'] ?? []);

        $this->assertNull($tasks->firstWhere('key', 'queue-backlog-drain'));
        $this->assertNull($tasks->firstWhere('key', 'sync-processing-order-status'));
    }

    public function test_high_frequency_schedule_tasks_run_every_fifteen_minutes(): void
    {
        $service = app(ScheduleTaskService::class);

        $overview = $service->overview();
        $tasks = collect($overview['tasks'] ?? []);

        foreach ([
            'referral-release-rewards',
            'coupon-campaign-dispatch',
            'provision-retry-failed',
            'vnc-ensure-relay',
            'site-cache-warmup',
        ] as $taskKey) {
            $task = $tasks->firstWhere('key', $taskKey);

            $this->assertIsArray($task, 'Missing schedule task key: '.$taskKey);
            $this->assertSame('每次心跳', $task['expression'] ?? null, 'Unexpected schedule expression for '.$taskKey);
        }
    }
}
