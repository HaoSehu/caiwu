<?php

declare(strict_types=1);

namespace App\Services\Upstream\Integration;

use App\Services\Automation\Heartbeat\Contracts\ScheduledTask;
use App\Services\Automation\Heartbeat\Data\TaskContext;
use App\Services\Automation\Heartbeat\ScheduleRule;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * 上游集成任务：上游开通孤儿单补偿告警。
 *
 * 从系统核心任务清单（CoreScheduledTaskProvider）迁入上游集成模块，
 * 默认 dry-run 扫描上游开通失败孤儿单并写日志告警。
 */
final class ProvisionRetryFailedTask implements ScheduledTask
{
    public const KEY = 'provision-retry-failed';

    private const COMMAND = 'provision:retry-failed';

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return '上游开通孤儿单补偿告警';
    }

    public function description(): string
    {
        return '默认 dry-run 扫描上游开通失败孤儿单并写日志告警。归属上游集成模块。';
    }

    public function category(): string
    {
        return '上游开通';
    }

    public function triggers(): array
    {
        return [ScheduleRule::everyTicks(1)];
    }

    public function handle(TaskContext $context): array
    {
        $exitCode = Artisan::call(self::COMMAND);
        $output = trim(Artisan::output());

        $summary = [
            'command' => self::COMMAND,
            'exit_code' => $exitCode,
        ];

        if ($output !== '') {
            $summary['output'] = mb_substr($output, 0, 2000);
        }

        if ($exitCode !== 0) {
            $message = 'Artisan command ['.self::COMMAND.'] exited with code '.$exitCode.'.';
            if ($output !== '') {
                $message .= ' Output: '.mb_substr($output, 0, 1000);
            }

            throw new RuntimeException($message);
        }

        return $summary;
    }

    public function queue(): string
    {
        return (string) config('queue.caiwu_schedule_queue', 'automation');
    }

    public function timeout(): int
    {
        return 900;
    }

    public function lockTtlSeconds(): int
    {
        return 960;
    }

    public function manualTriggerable(): bool
    {
        return true;
    }
}
