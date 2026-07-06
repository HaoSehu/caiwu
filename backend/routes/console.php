<?php

use Illuminate\Support\Facades\Schedule;

/**
 * 唯一时钟源：Laravel Schedule 只负责每 15 分钟发出一次全局心跳。
 * 具体业务任务由 HeartbeatTaskRegistry 注册，Scheduler 核心不承载业务逻辑。
 */
$shouldUseScheduleMutex = ! (
    PHP_OS_FAMILY === 'Windows'
    && (string) config('cache.default', 'file') === 'file'
);

config([
    'idc.schedule_runtime' => array_replace_recursive(
        (array) config('idc.schedule_runtime', []),
        [
            'mutex' => [
                'enabled' => $shouldUseScheduleMutex,
                'degraded' => ! $shouldUseScheduleMutex,
                'mode' => $shouldUseScheduleMutex ? 'without_overlapping' : 'degraded',
                'reason' => $shouldUseScheduleMutex ? '' : 'windows_file_cache_lock_unreliable',
                'cache_store' => (string) config('cache.default', 'file'),
                'os_family' => PHP_OS_FAMILY,
            ],
            'automation_config' => [
                'status' => 'loaded',
                'fallback_reason' => '',
            ],
        ],
    ),
]);

$heartbeat = Schedule::command('scheduler:heartbeat')
    ->everyFifteenMinutes()
    ->name('scheduler-heartbeat');

if ($shouldUseScheduleMutex) {
    $heartbeat->withoutOverlapping(14);
}
