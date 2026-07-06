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
        $this->assertStringContainsString('*/15 * * * *', $output);
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
        Queue::assertPushed(RunHeartbeatTaskJob::class, fn (RunHeartbeatTaskJob $job): bool => $job->taskKey === 'heartbeat-test-due');
        Queue::assertNotPushed(RunHeartbeatTaskJob::class, fn (RunHeartbeatTaskJob $job): bool => $job->taskKey === 'heartbeat-test-skip');
        $this->assertDatabaseHas('schedule_task_runs', [
            'task_key' => 'heartbeat-test-due',
            'status' => 'queued',
            'source' => 'heartbeat',
        ]);

        $secondSummary = $scheduler->tick(CarbonImmutable::parse('2026-07-05 12:00:00', 'Asia/Shanghai'));

        $this->assertSame([], $secondSummary->queuedTasks);
        $this->assertSame(['heartbeat-test-due'], $secondSummary->duplicateTasks);
        Queue::assertPushed(RunHeartbeatTaskJob::class, 1);
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
                && $job->taskRunId !== null;
        });
        $this->assertDatabaseHas('schedule_task_runs', [
            'task_key' => 'product-upstream-config-sync',
            'source' => 'manual_trigger',
            'status' => 'queued',
        ]);
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
