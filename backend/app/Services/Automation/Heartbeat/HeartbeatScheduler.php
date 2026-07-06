<?php

declare(strict_types=1);

namespace App\Services\Automation\Heartbeat;

use App\Jobs\RunHeartbeatTaskJob;
use App\Services\Automation\Heartbeat\Data\TickSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class HeartbeatScheduler
{
    public function __construct(
        private ScheduleTickRepository $ticks,
        private ScheduleTaskRunRepository $taskRuns,
        private HeartbeatTaskRegistry $registry,
        private TriggerRuleMatcher $matcher,
        private QueueDrainService $queueDrain,
    ) {}

    public function tick(CarbonImmutable $now): TickSummary
    {
        $slot = TickSlot::floorToFifteenMinutes($now);
        $lock = Cache::lock('scheduler:heartbeat:'.$slot->format('YmdHi'), 840);

        return $lock->block(1, function () use ($slot): TickSummary {
            $tickModel = $this->ticks->firstOrCreateSlot($slot);
            $tick = $this->ticks->toContext($tickModel);
            $queued = [];
            $skipped = [];
            $duplicates = [];

            foreach ($this->registry->enabledTasks() as $task) {
                $matched = $this->matcher->firstMatchedRule($task->triggers(), $tick);
                if ($matched === null) {
                    $skipped[] = $task->key();

                    continue;
                }

                $run = $this->taskRuns->markQueued($tickModel, $task, $matched);
                if ($run === null) {
                    $duplicates[] = $task->key();

                    continue;
                }

                RunHeartbeatTaskJob::dispatch($task->key(), (int) $tickModel->id, (int) $run->id, null, 'heartbeat')
                    ->onQueue($task->queue());
                $queued[] = $task->key();
            }

            $queueDrain = $this->queueDrain->drainOnceIfDatabaseQueue();

            return new TickSummary($tick, $queued, $skipped, $duplicates, $queueDrain);
        });
    }
}
