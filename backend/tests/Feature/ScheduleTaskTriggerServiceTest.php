<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Automation\ScheduleTaskTriggerService;
use App\Services\Automation\ServiceStatusSyncService;
use Tests\TestCase;

class ScheduleTaskTriggerServiceTest extends TestCase
{
    public function test_dispatch_prefers_sync_mode_when_running_in_console(): void
    {
        config()->set('queue.default', 'database');

        $serviceStatusSyncService = $this->createMock(ServiceStatusSyncService::class);
        $serviceStatusSyncService->expects($this->once())
            ->method('handle')
            ->with()
            ->willReturn([
                'scanned' => 0,
                'synced' => 0,
                'failed' => 0,
                'skipped' => 0,
            ]);
        app()->instance(ServiceStatusSyncService::class, $serviceStatusSyncService);

        $service = app(ScheduleTaskTriggerService::class);

        $result = $service->dispatch('service-status-sync', 1);

        $this->assertSame('service-status-sync', $result['task'] ?? null);
        $this->assertSame('sync', $result['execution_mode'] ?? null);
        $this->assertIsString((string) ($result['title'] ?? ''));
        $this->assertNotSame('', trim((string) ($result['title'] ?? '')));
    }

    public function test_supports_queue_backlog_drain_manual_trigger(): void
    {
        $service = app(ScheduleTaskTriggerService::class);

        $this->assertTrue($service->supports('queue-backlog-drain'));
    }
}
