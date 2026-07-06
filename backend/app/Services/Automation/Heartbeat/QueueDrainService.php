<?php

declare(strict_types=1);

namespace App\Services\Automation\Heartbeat;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class QueueDrainService
{
    /**
     * @return array<string, mixed>
     */
    public function drainOnceIfDatabaseQueue(): array
    {
        if (app()->runningUnitTests()) {
            return [
                'status' => 'skipped',
                'reason' => 'running_unit_tests',
            ];
        }

        if ((string) config('queue.default', 'sync') !== 'database') {
            return [
                'status' => 'skipped',
                'reason' => 'queue_driver_not_database',
                'queue_driver' => (string) config('queue.default', 'sync'),
            ];
        }

        $table = (string) config('queue.connections.database.table', 'jobs');
        if ($table === '' || ! Schema::hasTable($table)) {
            return [
                'status' => 'skipped',
                'reason' => 'jobs_table_missing',
            ];
        }

        $parameters = [
            '--queue' => (string) config('queue.caiwu_worker_queues', 'provision,referral,notification,coupon,default'),
            '--sleep' => 1,
            '--tries' => (int) config('queue.caiwu_worker_tries', 3),
            '--timeout' => (int) config('queue.caiwu_worker_timeout', 1200),
            '--stop-when-empty' => true,
            '--max-time' => 50,
        ];

        $exitCode = Artisan::call('queue:work', $parameters);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            throw new RuntimeException('队列积压消费执行失败，退出码：'.$exitCode.($output !== '' ? '，输出：'.mb_substr($output, 0, 1000) : ''));
        }

        return [
            'status' => 'drained',
            'exit_code' => $exitCode,
            'parameters' => $parameters,
            'output' => $output !== '' ? mb_substr($output, 0, 2000) : null,
        ];
    }
}
