<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\BusinessException;
use App\Services\Messaging\DispatchDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 单条投递任务（对齐 howstack MessageDeliveryWorker 的队列分支）。
 *
 * 队列载荷只有 delivery_id：地址与正文在 message_deliveries 加密存放，
 * 不进入 Redis、不进入 Laravel failed_jobs 的异常上下文。
 *
 * 失败语义完全由 DispatchDelivery + MessageRepository::finish 决定，
 * 本任务不设 Laravel 级重试（tries=1）：重试必须经过状态机与租约，
 * 而不是队列的盲目重投。
 */
class DeliverMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('notification');
    }

    public function handle(DispatchDelivery $dispatcher): void
    {
        $dispatcher->consume($this->deliveryId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[消息投递] 任务执行失败', [
            'delivery_id' => $this->deliveryId,
            'exception' => $exception::class,
            'message' => $exception instanceof BusinessException ? $exception->getMessage() : '见异常类型',
        ]);
    }
}
