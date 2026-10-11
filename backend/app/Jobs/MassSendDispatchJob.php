<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MassSend;
use App\Models\MassSendTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * M9 批量群发调度 Job：把待发送任务置为发送中，并按 100/片派发分片 Job。
 *
 * 触达路径：MassSendService::start()（status=1）→ 本 Job（可由接口立即派发
 * 或每分钟调度扫描 scheduled_at 到点任务触发，注册方式见 INTEGRATION.md）→
 * MassSendChunkJob 逐用户发送。
 */
class MassSendDispatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    /** @var int 分片大小：每片目标数 */
    private const CHUNK_SIZE = 100;

    public function __construct(
        public int $massSendId,
    ) {
        $this->onQueue('notification');
        $this->afterCommit();
    }

    public function handle(): void
    {
        // 原子抢占：待发送（scheduled_at 到点或立即）正常领取；发送中超 1 小时
        // 视为卡死（调度 Job 丢失）允许续派——target 侧由 MassSendChunkJob 原子
        // 消费（status=0 抢占）保证幂等，不会重发已处理目标。
        $claimed = MassSend::query()
            ->whereKey($this->massSendId)
            ->where(function ($query): void {
                $query->where(function ($pending): void {
                    $pending->where('status', MassSend::STATUS_PENDING)
                        ->where(function ($due): void {
                            $due->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
                        });
                })->orWhere(function ($stuck): void {
                    $stuck->where('status', MassSend::STATUS_SENDING)
                        ->where('started_at', '<=', now()->subHour());
                });
            })
            ->update([
                'status' => MassSend::STATUS_SENDING,
                'started_at' => now(),
            ]);

        if ($claimed === 0) {
            return;
        }

        // 卡死分片回收：发送中超 10 分钟未回写结果的目标视为所属分片 Job 已丢失，
        // 重置回待发参与本轮重派（正常分片数十秒内完成，不会误回收）。
        MassSendTarget::query()
            ->where('mass_send_id', $this->massSendId)
            ->where('status', MassSendTarget::STATUS_SENDING)
            ->where('updated_at', '<=', now()->subMinutes(10))
            ->update(['status' => MassSendTarget::STATUS_PENDING]);

        $lastTargetId = 0;
        $chunkCount = 0;

        do {
            $targetIds = MassSendTarget::query()
                ->where('mass_send_id', $this->massSendId)
                ->where('status', MassSendTarget::STATUS_PENDING)
                ->where('id', '>', $lastTargetId)
                ->orderBy('id')
                ->limit(self::CHUNK_SIZE)
                ->pluck('id')
                ->all();

            if ($targetIds === []) {
                break;
            }

            $lastTargetId = (int) end($targetIds);
            $chunkCount++;

            MassSendChunkJob::dispatch($this->massSendId, array_map(intval(...), $targetIds));
        } while (count($targetIds) === self::CHUNK_SIZE);

        Log::info('[批量群发] 调度派发完成', [
            'mass_send_id' => $this->massSendId,
            'chunks' => $chunkCount,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[批量群发] 调度任务失败', [
            'mass_send_id' => $this->massSendId,
            'message' => $exception->getMessage(),
        ]);
    }
}
