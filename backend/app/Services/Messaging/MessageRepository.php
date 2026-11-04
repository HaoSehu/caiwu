<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\MessageDeliveryAttemptLog;
use App\Models\MessageRecipient;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * 消息运行时仓储：消息流水、接收对象、投递任务的唯一写口。
 *
 * 语义与 howstack DatabaseMessageRepository 对齐：
 * - record() 必须在业务事务内调用（投递任务即事务 Outbox）；
 * - claim() 用行锁 + 租约，防重复领取；
 * - finish() 只有 failed 且 retry 才回到 pending，unknown 一律终态。
 */
final class MessageRepository
{
    private const MESSAGE_STATE_STALE_MESSAGE_ID = 'delivery 记录引用了不存在的 message';

    private const MESSAGE_STATE_STALE_RECIPIENT_ID = 'delivery 记录引用了不存在的 recipient';

    private const MAX_ATTEMPTS = 3;

    private const LEASE_SECONDS = 120;

    /**
     * 记录一条业务消息与其全部投递任务。
     *
     * @param  list<array{audience: string, account_id: int, target_key: string, group_key?: string, group_name?: string, channel: string, channel_name?: string, payload: array<string, mixed>}>  $targets
     * @return int 消息主键；同一 event_key 重复调用返回首次记录的主键
     */
    public function record(
        string $eventKey,
        string $scene,
        string $purpose,
        string $businessType,
        int $businessId,
        array $targets,
    ): int {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('消息任务必须与业务事务一起记录');
        }

        $now = now();
        $inserted = DB::table('messages')->insertOrIgnore([
            'event_key' => $eventKey,
            'scene' => $scene,
            'purpose' => $purpose,
            'business_type' => $businessType,
            'business_id' => $businessId,
            'created_at' => $now,
        ]);

        $message = Message::query()->where('event_key', $eventKey)->first();
        if (! $message instanceof Message) {
            throw new LogicException(self::MESSAGE_STATE_STALE_MESSAGE_ID);
        }

        if ($inserted === 0) {
            return (int) $message->id;
        }

        foreach ($targets as $target) {
            $recipient = MessageRecipient::query()->firstOrCreate(
                [
                    'message_id' => $message->id,
                    'audience' => $target['audience'],
                    'target_key' => $target['target_key'],
                ],
                [
                    'account_id' => $target['account_id'],
                    'group_key' => $target['group_key'] ?? '',
                    'group_name' => $target['group_name'] ?? '',
                    'created_at' => $now,
                ]
            );

            $hex = hash('sha256', $eventKey.':'.$recipient->id.':'.$target['channel']);
            $requestKey = substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4)
                .'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);

            MessageDelivery::query()->firstOrCreate(
                ['recipient_id' => $recipient->id, 'channel' => $target['channel']],
                [
                    'request_key' => $requestKey,
                    'channel_name' => $target['channel_name'] ?? '',
                    'payload_encrypted' => Crypt::encryptString(json_encode($target['payload'], JSON_THROW_ON_ERROR)),
                    'status' => MessageDelivery::STATUS_PENDING,
                    'next_attempt_at' => $now,
                    'queue_after' => $now,
                ]
            );
        }

        return (int) $message->id;
    }

    /**
     * 领取一条待投递任务；并发或状态不符时返回 null。
     *
     * @return array{id: int, audience: string, account_id: int, channel: string, request_key: string, lease_key: string, payload: array<string, mixed>}|null
     */
    public function claim(int $deliveryId): ?array
    {
        return DB::transaction(function () use ($deliveryId): ?array {
            $row = MessageDelivery::query()->whereKey($deliveryId)->lockForUpdate()->first();
            $now = now();

            if (! $row instanceof MessageDelivery
                || $row->status !== MessageDelivery::STATUS_PENDING
                || $row->next_attempt_at->greaterThan($now)) {
                return null;
            }

            $lease = bin2hex(random_bytes(24));
            $row->status = MessageDelivery::STATUS_PROCESSING;
            $row->lease_key = $lease;
            $row->lease_until = $now->copy()->addSeconds(self::LEASE_SECONDS);
            $row->attempts = $row->attempts + 1;
            $row->save();

            MessageDeliveryAttemptLog::query()->create([
                'delivery_id' => $deliveryId,
                'attempt_no' => $row->attempts,
                'status' => MessageDeliveryAttemptLog::STATUS_PROCESSING,
                'created_at' => $now,
            ]);

            $recipient = MessageRecipient::query()->find($row->recipient_id);
            if (! $recipient instanceof MessageRecipient) {
                throw new LogicException(self::MESSAGE_STATE_STALE_RECIPIENT_ID);
            }

            $payload = json_decode(Crypt::decryptString($row->payload_encrypted), true, 32, JSON_THROW_ON_ERROR);

            return [
                'id' => $deliveryId,
                'audience' => (string) $recipient->audience,
                'account_id' => (int) $recipient->account_id,
                'channel' => (string) $row->channel,
                'request_key' => (string) $row->request_key,
                'lease_key' => $lease,
                'payload' => is_array($payload) ? $payload : [],
            ];
        });
    }

    /**
     * 提交一次投递结果。
     *
     * @param  string  $status  accepted|delivered|failed|unknown
     */
    public function finish(
        int $deliveryId,
        string $leaseKey,
        string $status,
        string $errorCode,
        int $durationMs,
        bool $retry,
    ): void {
        if (! in_array($status, ['accepted', 'delivered', 'failed', 'unknown'], true)
            || preg_match('/^[a-z0-9_]{0,64}$/D', $errorCode) !== 1) {
            throw new LogicException('消息传输结果无效');
        }

        DB::transaction(function () use ($deliveryId, $leaseKey, $status, $errorCode, $durationMs, $retry): void {
            $row = MessageDelivery::query()->whereKey($deliveryId)->lockForUpdate()->first();
            if (! $row instanceof MessageDelivery
                || $row->status !== MessageDelivery::STATUS_PROCESSING
                || ! hash_equals((string) $row->lease_key, $leaseKey)) {
                return;
            }

            $now = now();
            MessageDeliveryAttemptLog::query()
                ->where('delivery_id', $deliveryId)
                ->where('attempt_no', $row->attempts)
                ->update([
                    'status' => $status === 'accepted' || $status === 'delivered' ? 'success' : $status,
                    'error_code' => $errorCode,
                    'duration_ms' => $durationMs,
                    'finished_at' => $now,
                ]);

            $retryable = $status === 'failed' && $retry && $row->attempts < self::MAX_ATTEMPTS;

            $row->status = $retryable ? MessageDelivery::STATUS_PENDING : $status;
            $row->error_code = $errorCode;
            $row->lease_key = '';
            $row->lease_until = null;
            $row->next_attempt_at = $now->copy()->addSeconds(30 * (2 ** min($row->attempts, 6)));
            $row->queue_after = $now->copy();
            $row->save();
        });
    }

    /** @return list<int> 当前可领取的任务主键 */
    public function due(int $limit): array
    {
        $now = now();

        return MessageDelivery::query()
            ->where('status', MessageDelivery::STATUS_PENDING)
            ->where('next_attempt_at', '<=', $now)
            ->where('queue_after', '<=', $now)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /** 标记任务已被推送队列，延后补投窗口避免重复推送 */
    public function queued(int $deliveryId): void
    {
        MessageDelivery::query()
            ->whereKey($deliveryId)
            ->where('status', MessageDelivery::STATUS_PENDING)
            ->update(['queue_after' => now()->addSeconds(60)]);
    }

    /**
     * 收回过期租约。执行中断的站点消息记 failed，外部渠道记 unknown，
     * 两者都不自动重发（可能已产生副作用）。
     */
    public function recoverExpired(): int
    {
        $ids = MessageDelivery::query()
            ->where('status', MessageDelivery::STATUS_PROCESSING)
            ->where('lease_until', '<', now())
            ->orderBy('id')
            ->limit(100)
            ->pluck('id');

        $recovered = 0;
        foreach ($ids as $id) {
            $recovered += DB::transaction(function () use ($id): int {
                $row = MessageDelivery::query()->whereKey($id)->lockForUpdate()->first();
                if (! $row instanceof MessageDelivery
                    || $row->status !== MessageDelivery::STATUS_PROCESSING
                    || $row->lease_until?->greaterThanOrEqualTo(now()) !== false) {
                    return 0;
                }

                $status = $row->channel === 'message' ? 'failed' : 'unknown';
                MessageDeliveryAttemptLog::query()
                    ->where('delivery_id', $id)
                    ->where('attempt_no', $row->attempts)
                    ->update([
                        'status' => $status,
                        'error_code' => 'worker_lease_expired',
                        'finished_at' => now(),
                    ]);

                $row->status = $status;
                $row->error_code = 'worker_lease_expired';
                $row->lease_key = '';
                $row->lease_until = null;
                $row->save();

                return 1;
            });
        }

        return $recovered;
    }
}
