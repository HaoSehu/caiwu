<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Automation\Contracts\ScheduleHook;
use App\Services\Automation\ScheduleHookService;
use App\Services\System\ScheduleRunLogService;
use RuntimeException;
use Tests\TestCase;

class ScheduleHookServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ScheduleHookServiceTestListener::$received = [];
    }

    public function test_it_runs_configured_hook_listeners(): void
    {
        config()->set('schedule_hooks.listeners.'.ScheduleHookService::HOOK_TASK_BEFORE, [
            ScheduleHookServiceTestListener::class,
        ]);

        $results = app(ScheduleHookService::class)->run(ScheduleHookService::HOOK_TASK_BEFORE, [
            'task_key' => 'service-auto-renew',
        ]);

        $this->assertTrue(app(ScheduleHookService::class)->hasListeners(ScheduleHookService::HOOK_TASK_BEFORE));
        $this->assertSame('success', $results[0]['status']);
        $this->assertSame('service-auto-renew', ScheduleHookServiceTestListener::$received[0]['context']['task_key']);
    }

    public function test_listener_failures_are_reported_without_interrupting_scheduler(): void
    {
        config()->set('schedule_hooks.listeners.'.ScheduleHookService::HOOK_TASK_AFTER, [
            FailingScheduleHookServiceTestListener::class,
            ScheduleHookServiceTestListener::class,
        ]);

        $results = app(ScheduleHookService::class)->run(ScheduleHookService::HOOK_TASK_AFTER, [
            'task_name' => '账单自动化维护',
        ]);

        $this->assertSame('failed', $results[0]['status']);
        $this->assertSame('success', $results[1]['status']);
        $this->assertSame('账单自动化维护', ScheduleHookServiceTestListener::$received[0]['context']['task_name']);
    }

    public function test_schedule_run_log_record_fires_before_and_after_hooks(): void
    {
        config()->set('schedule_hooks.listeners.'.ScheduleHookService::HOOK_TASK_BEFORE, [
            ScheduleHookServiceTestListener::class,
        ]);
        config()->set('schedule_hooks.listeners.'.ScheduleHookService::HOOK_TASK_AFTER, [
            ScheduleHookServiceTestListener::class,
        ]);

        $result = app(ScheduleRunLogService::class)->record('hook integration task', fn (): array => [
            'handled' => true,
        ], [
            'task_key' => 'hook-integration-task',
            'source' => 'unit-test',
        ]);

        $this->assertSame(['handled' => true], $result);
        $this->assertCount(2, ScheduleHookServiceTestListener::$received);
        $this->assertSame(ScheduleHookService::HOOK_TASK_BEFORE, ScheduleHookServiceTestListener::$received[0]['hook']);
        $this->assertSame(ScheduleHookService::HOOK_TASK_AFTER, ScheduleHookServiceTestListener::$received[1]['hook']);
        $this->assertSame('hook-integration-task', ScheduleHookServiceTestListener::$received[0]['context']['task_key']);
        $this->assertSame(['handled' => true], ScheduleHookServiceTestListener::$received[1]['context']['summary']);
    }

    public function test_schedule_run_log_record_fires_failed_hook_and_rethrows(): void
    {
        config()->set('schedule_hooks.listeners.'.ScheduleHookService::HOOK_TASK_FAILED, [
            ScheduleHookServiceTestListener::class,
        ]);

        try {
            app(ScheduleRunLogService::class)->record('hook failure task', function (): void {
                throw new RuntimeException('schedule task failed');
            }, [
                'task_key' => 'hook-failure-task',
                'source' => 'unit-test',
            ]);

            $this->fail('The schedule task exception was not rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('schedule task failed', $exception->getMessage());
        }

        $this->assertCount(1, ScheduleHookServiceTestListener::$received);
        $this->assertSame(ScheduleHookService::HOOK_TASK_FAILED, ScheduleHookServiceTestListener::$received[0]['hook']);
        $this->assertSame('hook-failure-task', ScheduleHookServiceTestListener::$received[0]['context']['task_key']);
        $this->assertSame(RuntimeException::class, ScheduleHookServiceTestListener::$received[0]['context']['exception_class']);
    }
}

final class ScheduleHookServiceTestListener implements ScheduleHook
{
    public static array $received = [];

    public function handle(string $hook, array $context = []): array
    {
        self::$received[] = [
            'hook' => $hook,
            'context' => $context,
        ];

        return ['handled' => true];
    }
}

final class FailingScheduleHookServiceTestListener implements ScheduleHook
{
    public function handle(string $hook, array $context = []): mixed
    {
        throw new RuntimeException('hook failed for test');
    }
}
