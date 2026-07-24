<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunHeartbeatTaskJob;
use App\Models\ScheduleTaskRun;
use App\Models\ScheduleTick;
use App\Services\Automation\Heartbeat\CallbackScheduledTask;
use App\Services\Automation\Heartbeat\Contracts\ScheduledTask;
use App\Services\Automation\Heartbeat\HeartbeatScheduler;
use App\Services\Automation\Heartbeat\HeartbeatTaskRegistry;
use App\Services\Automation\Heartbeat\QueueDrainService;
use App\Services\Automation\Heartbeat\ScheduleRule;
use App\Services\Automation\Heartbeat\ScheduleTaskRunRepository;
use App\Services\Automation\Heartbeat\ScheduleTickRepository;
use App\Services\Automation\Heartbeat\TriggerRuleMatcher;
use App\Services\Automation\ScheduleTaskTriggerService;
use App\Services\System\ProductionReadinessService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HeartbeatSchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureHeartbeatTables();

        ScheduleTaskRun::query()
            ->whereIn('task_key', ['heartbeat-test-due', 'heartbeat-test-skip', 'product-upstream-config-sync'])
            ->delete();
        ScheduleTick::query()
            ->whereBetween('slot_started_at', ['2026-07-05 12:00:00', '2026-07-05 12:15:00'])
            ->delete();
    }

    public function test_laravel_schedule_only_registers_single_heartbeat_event(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertSame(1, substr_count($output, 'scheduler:heartbeat'));
        $this->assertStringContainsString('* * * * *', $output);
        $this->assertStringNotContainsString('接口认证刷新', $output);
        $this->assertStringNotContainsString('队列积压消费', $output);
    }

    public function test_heartbeat_dispatches_due_tasks_once_per_tick(): void
    {
        Queue::fake();

        $registry = new class(app()) extends HeartbeatTaskRegistry
        {
            /** @var list<ScheduledTask> */
            public array $tasks = [];

            public function enabledTasks(): array
            {
                return $this->tasks;
            }

            public function get(string $taskKey): ScheduledTask
            {
                foreach ($this->tasks as $task) {
                    if ($task->key() === $taskKey) {
                        return $task;
                    }
                }

                return parent::get($taskKey);
            }
        };

        $registry->tasks = [
            new CallbackScheduledTask(
                key: 'heartbeat-test-due',
                title: 'Heartbeat Test Due',
                description: 'Test due task',
                category: 'Test',
                triggers: [ScheduleRule::everyTicks(1)],
                handler: fn (): array => ['ok' => true],
            ),
            new CallbackScheduledTask(
                key: 'heartbeat-test-skip',
                title: 'Heartbeat Test Skip',
                description: 'Test skipped task',
                category: 'Test',
                triggers: [ScheduleRule::cron('5 5 5 5 *')],
                handler: fn (): array => ['ok' => true],
            ),
        ];

        $scheduler = new HeartbeatScheduler(
            app(ScheduleTickRepository::class),
            app(ScheduleTaskRunRepository::class),
            $registry,
            app(TriggerRuleMatcher::class),
            app(QueueDrainService::class),
        );

        $summary = $scheduler->tick(CarbonImmutable::parse('2026-07-05 12:00:00', 'Asia/Shanghai'));

        $this->assertSame(['heartbeat-test-due'], $summary->queuedTasks);
        Queue::assertPushed(RunHeartbeatTaskJob::class, 1);
        Queue::assertPushed(RunHeartbeatTaskJob::class, fn (RunHeartbeatTaskJob $job): bool => $job->taskKey === 'heartbeat-test-due' && $job->timeout === 900);
        Queue::assertNotPushed(RunHeartbeatTaskJob::class, fn (RunHeartbeatTaskJob $job): bool => $job->taskKey === 'heartbeat-test-skip');
        $this->assertDatabaseHas('schedule_task_runs', [
            'task_key' => 'heartbeat-test-due',
            'status' => 'queued',
            'source' => 'heartbeat',
        ]);

        $secondSummary = $scheduler->tick(CarbonImmutable::parse('2026-07-05 12:01:00', 'Asia/Shanghai'));

        $this->assertSame([], $secondSummary->queuedTasks);
        $this->assertSame(['heartbeat-test-due'], $secondSummary->duplicateTasks);
        Queue::assertPushed(RunHeartbeatTaskJob::class, 1);
    }

    public function test_repeated_heartbeat_updates_trigger_time_and_keeps_readiness_fresh_within_same_slot(): void
    {
        Queue::fake();

        $registry = new class(app()) extends HeartbeatTaskRegistry
        {
            public function enabledTasks(): array
            {
                return [];
            }
        };

        $scheduler = new HeartbeatScheduler(
            app(ScheduleTickRepository::class),
            app(ScheduleTaskRunRepository::class),
            $registry,
            app(TriggerRuleMatcher::class),
            app(QueueDrainService::class),
        );

        $scheduler->tick(CarbonImmutable::parse('2026-07-05 12:00:00', 'Asia/Shanghai'));
        $scheduler->tick(CarbonImmutable::parse('2026-07-05 12:04:00', 'Asia/Shanghai'));

        $tick = ScheduleTick::query()
            ->where('slot_started_at', '2026-07-05 12:00:00')
            ->sole();

        $this->assertSame('2026-07-05 12:04:00', $tick->triggered_at?->format('Y-m-d H:i:s'));
        $this->assertSame(1, ScheduleTick::query()
            ->where('slot_started_at', '2026-07-05 12:00:00')
            ->count());

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-05 12:04:30', 'Asia/Shanghai'));

        try {
            config(['health.scheduler_max_age_seconds' => 180]);

            $this->assertTrue(app(ProductionReadinessService::class)->check()['checks']['scheduler']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_manual_trigger_uses_heartbeat_job_and_registry(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);
        $this->ensureJobsTable();

        $result = app(ScheduleTaskTriggerService::class)->dispatch('product-upstream-config-sync', 123);

        $this->assertSame('product-upstream-config-sync', $result['task']);
        $this->assertSame('queue', $result['execution_mode']);
        Queue::assertPushed(RunHeartbeatTaskJob::class, function (RunHeartbeatTaskJob $job): bool {
            return $job->taskKey === 'product-upstream-config-sync'
                && $job->source === 'manual_trigger'
                && $job->adminUserId === 123
                && $job->taskRunId !== null
                && $job->timeout === 3600;
        });
        $this->assertDatabaseHas('schedule_task_runs', [
            'task_key' => 'product-upstream-config-sync',
            'source' => 'manual_trigger',
            'status' => 'queued',
        ]);
    }

    public function test_heartbeat_coalesces_a_due_task_while_its_previous_run_is_active(): void
    {
        Queue::fake();

        $registry = new class(app()) extends HeartbeatTaskRegistry
        {
            /** @var list<ScheduledTask> */
            public array $tasks = [];

            public function enabledTasks(): array
            {
                return $this->tasks;
            }
        };

        $registry->tasks = [
            new CallbackScheduledTask(
                key: 'heartbeat-test-due',
                title: 'Heartbeat Test Due',
                description: 'Test due task',
                category: 'Test',
                triggers: [ScheduleRule::everyTicks(1)],
                handler: fn (): array => ['ok' => true],
            ),
        ];

        ScheduleTaskRun::query()->create([
            'task_key' => 'heartbeat-test-due',
            'task_name' => 'Heartbeat Test Due',
            'rule_description' => '每次心跳',
            'source' => 'heartbeat',
            'queue' => 'default',
            'status' => ScheduleTaskRun::STATUS_QUEUED,
            'queued_at' => now()->subMinute(),
        ]);

        $scheduler = new HeartbeatScheduler(
            app(ScheduleTickRepository::class),
            app(ScheduleTaskRunRepository::class),
            $registry,
            app(TriggerRuleMatcher::class),
            app(QueueDrainService::class),
        );

        $summary = $scheduler->tick(CarbonImmutable::parse('2026-07-05 12:15:00', 'Asia/Shanghai'));

        $this->assertSame([], $summary->queuedTasks);
        $this->assertSame(['heartbeat-test-due'], $summary->duplicateTasks);
        Queue::assertNothingPushed();
        $this->assertSame(1, ScheduleTaskRun::query()->where('task_key', 'heartbeat-test-due')->count());
    }

    public function test_manual_trigger_reuses_an_active_run_instead_of_enqueueing_a_duplicate(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);
        $this->ensureJobsTable();

        $first = app(ScheduleTaskTriggerService::class)->dispatch('product-upstream-config-sync', 123);
        $second = app(ScheduleTaskTriggerService::class)->dispatch('product-upstream-config-sync', 456);

        $this->assertSame('queue', $first['execution_mode']);
        $this->assertSame('already_queued', $second['execution_mode']);
        $this->assertArrayHasKey('task_run_id', $second);
        Queue::assertPushed(RunHeartbeatTaskJob::class, 1);
        $this->assertSame(1, ScheduleTaskRun::query()
            ->where('task_key', 'product-upstream-config-sync')
            ->where('status', ScheduleTaskRun::STATUS_QUEUED)
            ->count());
    }

    public function test_failed_job_marks_its_schedule_run_as_failed(): void
    {
        $run = ScheduleTaskRun::query()->create([
            'task_key' => 'heartbeat-test-due',
            'task_name' => 'Heartbeat Test Due',
            'rule_description' => '每次心跳',
            'source' => 'manual_trigger',
            'queue' => 'default',
            'status' => ScheduleTaskRun::STATUS_RUNNING,
            'queued_at' => now()->subMinute(),
            'started_at' => now()->subSeconds(30),
        ]);

        (new RunHeartbeatTaskJob('heartbeat-test-due', null, (int) $run->id, null, 'manual_trigger', 900))
            ->failed(new \RuntimeException('simulated queue failure'));

        $this->assertDatabaseHas('schedule_task_runs', [
            'id' => $run->id,
            'status' => ScheduleTaskRun::STATUS_FAILED,
            'error_msg' => 'simulated queue failure',
        ]);
    }

    public function test_retries_keep_the_original_schedule_run_start_time(): void
    {
        $run = ScheduleTaskRun::query()->create([
            'task_key' => 'heartbeat-test-due',
            'task_name' => 'Heartbeat Test Due',
            'rule_description' => '每次心跳',
            'source' => 'manual_trigger',
            'queue' => 'default',
            'status' => ScheduleTaskRun::STATUS_QUEUED,
            'queued_at' => now()->subMinute(),
        ]);
        $repository = app(ScheduleTaskRunRepository::class);

        $repository->markRunning((int) $run->id);
        $firstStartedAt = $run->fresh()->started_at?->toDateTimeString();
        $repository->markFailed((int) $run->id, 'retry me', 10);
        $repository->markRunning((int) $run->id);

        $this->assertSame($firstStartedAt, $run->fresh()->started_at?->toDateTimeString());
        $this->assertSame(ScheduleTaskRun::STATUS_RUNNING, $run->fresh()->status);
    }

    public function test_queue_visibility_timeout_covers_the_longest_registered_task(): void
    {
        $longestTaskTimeout = collect(app(HeartbeatTaskRegistry::class)->enabledTasks())
            ->map(fn (ScheduledTask $task): int => $task->timeout())
            ->max();

        $this->assertGreaterThan((int) $longestTaskTimeout, (int) config('queue.connections.database.retry_after'));
        $this->assertGreaterThanOrEqual(
            (int) config('queue.connections.database.retry_after') + 60,
            (int) config('queue.caiwu_worker_drain_lock_ttl'),
        );
    }

    public function test_app_serve_with_schedule_uses_the_heartbeat_queue_drain_instead_of_a_second_worker(): void
    {
        $source = file_get_contents(app_path('Console/Commands/ServeBackendStackCommand.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("&& ! (bool) \$this->option('with-schedule')", $source);
        $this->assertStringContainsString('队列由每分钟心跳消费', $source);
    }

    private function ensureHeartbeatTables(): void
    {
        if (! Schema::hasTable('schedule_ticks')) {
            Schema::create('schedule_ticks', function (Blueprint $table) {
                $table->id();
                $table->timestamp('slot_started_at')->unique();
                $table->unsignedBigInteger('global_number')->unique();
                $table->unsignedTinyInteger('daily_index')->index();
                $table->timestamp('triggered_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('schedule_task_runs')) {
            Schema::create('schedule_task_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('schedule_tick_id')->nullable()->index();
                $table->string('task_key', 120);
                $table->string('task_name', 160);
                $table->string('rule_description', 160)->nullable();
                $table->string('source', 40)->default('heartbeat');
                $table->string('queue', 80)->nullable();
                $table->string('status', 30)->default('queued');
                $table->unsignedInteger('duration_ms')->nullable();
                $table->json('summary')->nullable();
                $table->text('error_msg')->nullable();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->unique(['schedule_tick_id', 'task_key', 'source'], 'schedule_task_runs_tick_task_source_unique');
                $table->index(['task_key', 'created_at']);
                $table->index(['status', 'created_at']);
                $table->index(['source', 'created_at']);
            });
        }
    }

    private function ensureJobsTable(): void
    {
        if (Schema::hasTable('jobs')) {
            return;
        }

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }
}
