<?php

namespace App\Services\Automation;

use App\Jobs\RunScheduleTaskJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Predis\Client;

class ScheduleTaskTriggerService
{
    private ?bool $databaseQueueReady = null;

    private const INLINE_ONLY_TASKS = [
        'queue-backlog-drain',
    ];

    private const SUPPORTED_TASKS = [
        'refresh-hosting-panel-auth' => '接口认证刷新',
        'service-auto-renew' => '服务自动续费',
        'referral-release-rewards' => '推荐奖励释放',
        'billing-maintenance' => '账单自动化维护',
        'coupon-campaign-dispatch' => '优惠券活动发放',
        'product-upstream-config-sync' => '上游产品配置同步',
        'service-lifecycle-maintenance' => '服务生命周期维护',
        'service-status-sync' => '用户产品状态同步',
        'ticket-auto-close' => '工单自动关闭',
        'order-cleanup' => '账单与充值清理',
        'sync-processing-order-status' => '账单状态同步（兼容）',
        'queue-backlog-drain' => '队列积压消费',
    ];

    public function supports(string $taskKey): bool
    {
        return isset(self::SUPPORTED_TASKS[trim($taskKey)]);
    }

    public function dispatch(string $taskKey, ?int $adminUserId = null): array
    {
        $taskKey = trim($taskKey);

        if (! $this->supports($taskKey)) {
            throw new InvalidArgumentException('不支持的任务：'.$taskKey);
        }

        if (app()->runningInConsole()) {
            RunScheduleTaskJob::dispatchSync($taskKey, $adminUserId);

            return [
                'task' => $taskKey,
                'title' => self::SUPPORTED_TASKS[$taskKey],
                'execution_mode' => 'sync',
            ];
        }

        if ($this->shouldDispatchThroughQueue($taskKey)) {
            RunScheduleTaskJob::dispatch($taskKey, $adminUserId);

            return [
                'task' => $taskKey,
                'title' => self::SUPPORTED_TASKS[$taskKey],
                'execution_mode' => 'queue',
            ];
        }

        $this->logFallbackDispatch($taskKey, $adminUserId);
        RunScheduleTaskJob::dispatchAfterResponse($taskKey, $adminUserId);

        return [
            'task' => $taskKey,
            'title' => self::SUPPORTED_TASKS[$taskKey],
            'execution_mode' => 'after_response',
        ];
    }

    private function shouldDispatchThroughQueue(string $taskKey): bool
    {
        if (in_array($taskKey, self::INLINE_ONLY_TASKS, true)) {
            return false;
        }

        return $this->shouldUseQueue();
    }

    private function shouldUseQueue(): bool
    {
        $driver = (string) config('queue.default', 'sync');

        if ($driver === '' || $driver === 'sync') {
            return false;
        }

        if ($driver === 'database') {
            return $this->databaseQueueIsReady();
        }

        if ($driver === 'redis') {
            return extension_loaded('redis') || class_exists(Client::class);
        }

        return true;
    }

    private function databaseQueueIsReady(): bool
    {
        if ($this->databaseQueueReady !== null) {
            return $this->databaseQueueReady;
        }

        $table = (string) config('queue.connections.database.table', 'jobs');

        try {
            $this->databaseQueueReady = $table !== '' && Schema::hasTable($table);
        } catch (\Throwable $exception) {
            Log::warning('[手动任务触发] 检查队列表失败，回退为 afterResponse', [
                'table' => $table,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            $this->databaseQueueReady = false;
        }

        return $this->databaseQueueReady;
    }

    private function logFallbackDispatch(string $taskKey, ?int $adminUserId): void
    {
        Log::info('[手动任务触发] 管理端立即执行采用 afterResponse/同步执行', [
            'task' => $taskKey,
            'admin_user_id' => $adminUserId,
            'queue_driver' => (string) config('queue.default', 'sync'),
            'running_in_console' => app()->runningInConsole(),
        ]);
    }
}
