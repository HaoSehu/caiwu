<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DeliverMessageJob;
use App\Services\Messaging\MessageRepository;
use Illuminate\Console\Command;

/**
 * 消息投递补投：把到期的 pending 任务推入队列，并回收过期租约。
 *
 * 与 howstack DispatchMessageService::tick 等价。数据库是待投递队列的真源，
 * Redis 只是加速通道：Redis 不可用时任务仍在 pending，恢复后下一轮补投。
 */
class DispatchMessagesCommand extends Command
{
    protected $signature = 'messages:dispatch {--limit=100 : 单轮最多补投条数}';

    protected $description = '补投到期消息任务并回收过期租约';

    public function handle(MessageRepository $repository): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $recovered = $repository->recoverExpired();
        if ($recovered > 0) {
            $this->warn("回收过期租约 {$recovered} 条（外部渠道记为 unknown，不自动重发）");
        }

        $dispatched = 0;
        foreach ($repository->due($limit) as $deliveryId) {
            DeliverMessageJob::dispatch($deliveryId);
            $repository->queued($deliveryId);
            $dispatched++;
        }

        $this->info("本轮补投 {$dispatched} 条，回收租约 {$recovered} 条");

        return self::SUCCESS;
    }
}
