<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ScheduleTaskService;
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
}
