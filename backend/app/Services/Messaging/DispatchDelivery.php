<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Services\Messaging\Data\TransportResult;
use Throwable;

/**
 * 投递消费者：领取 → 校验收件人 → 发送 → 提交结果。
 *
 * 任何未预期异常都归为 unknown 且不重试：执行开始后异常不能证明厂商未受理。
 */
final class DispatchDelivery
{
    public function __construct(
        private readonly MessageRepository $repository,
        private readonly MessageTransport $transport,
        private readonly MessageRecipientGate $gate,
    ) {}

    public function consume(int $deliveryId): void
    {
        try {
            $task = $this->repository->claim($deliveryId);
        } catch (Throwable) {
            // 领取阶段异常（典型是 payload 解密失败，如 APP_KEY 轮换）：
            // 该任务永远不会成功，必须判死，否则 messages:dispatch 每分钟重投一次。
            $this->repository->markUndecryptable($deliveryId);

            return;
        }

        if ($task === null) {
            return;
        }

        $startedAt = hrtime(true);

        try {
            $result = $this->gate->active($task['audience'], $task['account_id'])
                ? $this->dispatch($task['channel'], $task['request_key'], $task['payload'])
                : TransportResult::failed('recipient_disabled');
        } catch (Throwable) {
            $result = TransportResult::unknown('transport_outcome_unknown');
        }

        $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        $this->repository->finish(
            $deliveryId,
            $task['lease_key'],
            $result->status,
            $result->error,
            $durationMs,
            $result->retry && $task['channel'] !== 'message',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatch(string $channel, string $requestKey, array $payload): TransportResult
    {
        return $channel === 'message'
            ? $this->transport->deliverInbox($payload)
            : $this->transport->send($channel, $requestKey, $payload);
    }
}
