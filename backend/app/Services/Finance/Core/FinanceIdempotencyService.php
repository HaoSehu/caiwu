<?php

declare(strict_types=1);

namespace App\Services\Finance\Core;

use App\Exceptions\BusinessException;
use App\Models\FinanceOperation;
use App\Services\Finance\Core\Data\FinanceOperationResult;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 财务写操作幂等。
 *
 * 语义：
 * - 同 (scope, actor_type, actor_id, request_key) 且同 request_hash → 重放，直接返回首次结果；
 * - 同键不同 hash → 409，既不执行也不覆盖（避免「换金额复用旧单」）；
 * - failed 或超时的 processing → 允许同键重试；
 * - ack_pending（资金已提交但成功终态记录失败）→ 409 拒绝，不重放也不重试，等人工核对。
 *
 * 约束：必须在业务事务之外调用。幂等记录先落库再执行回调，回调内部自行开事务；
 * 若把本方法包在业务事务里，业务回滚会把幂等事实一起带走，就退化成没有幂等。
 */
final class FinanceIdempotencyService
{
    /**
     * 成功待确认：资金已提交但成功终态记录重试耗尽，需人工核对。
     * 该状态既不可重放也不可重试（区别于 failed / 僵死 processing）。
     * 值域真源在模型常量 FinanceOperation::STATUS_ACK_PENDING（运维/清理命令按模型常量识别）。
     */
    private const STATUS_ACK_PENDING = FinanceOperation::STATUS_ACK_PENDING;

    /**
     * @param  array<string, mixed>  $payload  参与摘要的规范化请求体
     * @param  callable(): array<string, mixed>  $callback
     * @param  array{resource_type?: string, resource_id?: int, trace_id?: string}  $meta
     */
    public function execute(
        string $scope,
        string $actorType,
        int $actorId,
        string $requestKey,
        array $payload,
        callable $callback,
        array $meta = [],
    ): FinanceOperationResult {
        if (! (bool) config('finance.idempotency.enabled', true)) {
            return new FinanceOperationResult(false, (array) $callback(), 0, FinanceOperation::STATUS_SUCCEEDED);
        }

        $this->assertRequestKey($requestKey);
        $hash = $this->hash($scope, $payload);
        $operation = $this->claim($scope, $actorType, $actorId, $requestKey, $hash);

        if ($operation instanceof FinanceOperationResult) {
            return $operation;
        }

        try {
            $result = (array) $callback();
        } catch (Throwable $error) {
            // 业务失败必须落 failed 才能允许同键重试；记录失败本身不再二次抛出，
            // 否则会把原始业务异常掩盖成「幂等记录写入失败」。
            $this->safeFinish($operation, FinanceOperation::STATUS_FAILED, null, $error);

            throw $error;
        }

        // 资金已经动了：finish 失败绝不能留下 processing 行。
        // 否则超过 finance.idempotency.stale_processing_seconds 的僵死窗口后
        // 会被判定僵死并允许同键重放，等于二次执行资金回调。
        // 因此成功态的终态写入做有限重试（指数退避）；仍失败时不再假装成功——
        // 把行标记为「成功待确认」（仍需重试的同键请求被 409 挡住，不会重放资金回调），
        // 并写 result_payload 的 ack 失败标记与错误日志，交人工核对。
        $this->safeFinish($operation, FinanceOperation::STATUS_SUCCEEDED, $result, null, $meta);

        return new FinanceOperationResult(false, $result, (int) $operation->id, FinanceOperation::STATUS_SUCCEEDED);
    }

    /**
     * 规范化请求摘要：递归按键排序、金额统一两位小数、JSON 不转义中文。
     *
     * @param  array<string, mixed>  $payload
     */
    public function hash(string $scope, array $payload): string
    {
        $normalized = $this->canonicalize($payload);

        return hash('sha256', json_encode([$scope, $normalized], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * 抢占幂等键：命中成功/处理中/异体时直接返回结果或抛错；否则返回可执行的记录。
     */
    private function claim(
        string $scope,
        string $actorType,
        int $actorId,
        string $requestKey,
        string $hash,
    ): FinanceOperation|FinanceOperationResult {
        $existing = $this->find($scope, $actorType, $actorId, $requestKey);

        if ($existing !== null) {
            if (! hash_equals((string) $existing->request_hash, $hash)) {
                throw new BusinessException('同一幂等键不能用于不同内容的请求', 40900, 409);
            }

            if ($existing->status === FinanceOperation::STATUS_SUCCEEDED) {
                return new FinanceOperationResult(
                    true,
                    (array) ($existing->result_payload ?? []),
                    (int) $existing->id,
                    FinanceOperation::STATUS_SUCCEEDED,
                );
            }

            // 成功待确认：资金已动、终态记录失败已交由人工核对。
            // 既不能重放（会二次执行资金回调），也不能当作可重试的 failed，只能拒绝。
            if ($existing->status === self::STATUS_ACK_PENDING) {
                throw new BusinessException('该请求的资金操作已完成但结果确认待人工核对，请勿重复提交', 40900, 409);
            }

            if ($existing->status === FinanceOperation::STATUS_PROCESSING && ! $this->isStale($existing)) {
                throw new BusinessException('相同请求正在处理中，请稍后查询结果', 40900, 409);
            }

            // 原子抢占：只有「failed」或「已判定僵死的 processing」才能被重新接管。
            // 先读后写会让并发重放在两步之间同时通过，两个进程都执行资金回调。
            $claimed = FinanceOperation::query()
                ->whereKey((int) $existing->id)
                ->where(function ($query): void {
                    $query->where('status', FinanceOperation::STATUS_FAILED)
                        ->orWhere(function ($query): void {
                            $query->where('status', FinanceOperation::STATUS_PROCESSING)
                                ->where('started_at', '<', $this->staleBoundary());
                        });
                })
                ->update([
                    'status' => FinanceOperation::STATUS_PROCESSING,
                    'error_code' => null,
                    'error_msg' => null,
                    'started_at' => date('Y-m-d H:i:s'),
                    'finished_at' => null,
                ]);

            if ($claimed !== 1) {
                throw new BusinessException('相同请求正在处理中，请稍后查询结果', 40900, 409);
            }

            return $existing->refresh();
        }

        try {
            return FinanceOperation::query()->create([
                'scope' => $scope,
                'request_key' => $requestKey,
                'request_hash' => $hash,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'status' => FinanceOperation::STATUS_PROCESSING,
                'started_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (QueryException $error) {
            // 并发抢占：另一进程已插入同键，按命中已有记录处理。
            if ((string) $error->getCode() === '23000' && ($row = $this->find($scope, $actorType, $actorId, $requestKey)) !== null) {
                if (! hash_equals((string) $row->request_hash, $hash)) {
                    throw new BusinessException('同一幂等键不能用于不同内容的请求', 40900, 409);
                }

                throw new BusinessException('相同请求正在处理中，请稍后查询结果', 40900, 409);
            }

            throw $error;
        }
    }

    private function find(string $scope, string $actorType, int $actorId, string $requestKey): ?FinanceOperation
    {
        return FinanceOperation::query()
            ->where('scope', $scope)
            ->where('actor_type', $actorType)
            ->where('actor_id', $actorId)
            ->where('request_key', $requestKey)
            ->first();
    }

    private function isStale(FinanceOperation $operation): bool
    {
        $startedAt = $operation->getAttribute('started_at');

        if ($startedAt === null) {
            return true;
        }

        $timestamp = $startedAt instanceof \DateTimeInterface
            ? $startedAt->getTimestamp()
            : (int) strtotime((string) $startedAt);

        return $timestamp < $this->staleBoundaryTimestamp();
    }

    private function staleBoundaryTimestamp(): int
    {
        return time() - $this->staleSeconds();
    }

    private function staleBoundary(): string
    {
        return date('Y-m-d H:i:s', $this->staleBoundaryTimestamp());
    }

    /**
     * 僵死判定窗口必须长于受保护业务的最长持锁时间（退款 Cache::lock 40s、结算 30s），
     * 否则回调还在跑就被判僵死，第二次重放会并发执行同一笔资金。
     * 下限固定 120s：config 默认值即为 120，clamp 不得低于它（历史下限 60s 是缺陷来源之一）。
     */
    private function staleSeconds(): int
    {
        return max((int) config('finance.idempotency.stale_processing_seconds', 120), 120);
    }

    /**
     * finish 的失败必须被吞掉：调用点要么是「业务已失败正在抛错」（再抛会掩盖原始异常），
     * 要么是「资金已提交成功」（抛错会让调用方误以为整笔失败而重试）。
     *
     * 但成功态的终态写入不能只告警：进程崩溃/DB 抖动后行停在 processing，
     * 僵死窗口一过同键重放就会二次执行资金回调。因此成功态做有限重试（3 次、指数退避），
     * 仍失败则标记为「成功待确认」（同键请求被 409 挡住而非重放），并留结果与日志供人工核对。
     *
     * @param  array<string, mixed>|null  $result
     * @param  array{resource_type?: string, resource_id?: int, trace_id?: string}  $meta
     */
    private function safeFinish(
        FinanceOperation $operation,
        string $status,
        ?array $result,
        ?Throwable $error = null,
        array $meta = [],
    ): void {
        if ($status !== FinanceOperation::STATUS_SUCCEEDED) {
            // 失败态不是资金事实，写入失败只需告警（业务异常会继续向上抛）。
            try {
                $this->finish($operation, $status, $result, $error, $meta);
            } catch (Throwable $finishError) {
                $this->logFinishFailure($operation, $status, $finishError);
            }

            return;
        }

        $attempts = 3;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $this->finish($operation, $status, $result, $error, $meta);

                return;
            } catch (Throwable $finishError) {
                if ($attempt >= $attempts) {
                    $this->markAckPending($operation, $result, $meta, $finishError);

                    return;
                }

                usleep(50_000 * (2 ** ($attempt - 1))); // 50ms、100ms 指数退避
            }
        }
    }

    /**
     * 成功态终态写入重试耗尽后的兜底：标记「成功待确认」并保留结果与失败原因。
     *
     * 该状态不会被 claim() 当作可重放/可重试行，因此不会触发资金回调二次执行；
     * 人工核对后按需把行改回 succeeded 或清理。
     *
     * @param  array<string, mixed>|null  $result
     * @param  array{resource_type?: string, resource_id?: int, trace_id?: string}  $meta
     */
    private function markAckPending(
        FinanceOperation $operation,
        ?array $result,
        array $meta,
        Throwable $finishError,
    ): void {
        try {
            $payload = $result ?? [];
            $payload['_ack'] = [
                'status' => 'failed',
                'error' => mb_substr($finishError->getMessage(), 0, 200),
                'at' => date('Y-m-d H:i:s'),
            ];

            $operation->forceFill([
                'status' => self::STATUS_ACK_PENDING,
                'result_payload' => $payload,
                'resource_type' => $meta['resource_type'] ?? null,
                'resource_id' => $meta['resource_id'] ?? null,
                'finished_at' => date('Y-m-d H:i:s'),
            ])->save();

            Log::error('[财务幂等] 成功态终态写入重试耗尽，已标记成功待确认，需人工核对', [
                'operation_id' => (int) $operation->id,
                'scope' => (string) $operation->scope,
                'request_key' => (string) $operation->request_key,
                'error' => $finishError->getMessage(),
            ]);
        } catch (Throwable $fallbackError) {
            Log::error('[财务幂等] 幂等记录终态写入失败且兜底标记失败，需人工核对', [
                'operation_id' => (int) $operation->id,
                'scope' => (string) $operation->scope,
                'request_key' => (string) $operation->request_key,
                'expected_status' => FinanceOperation::STATUS_SUCCEEDED,
                'error' => $finishError->getMessage(),
                'fallback_error' => $fallbackError->getMessage(),
            ]);
        }
    }

    private function logFinishFailure(FinanceOperation $operation, string $status, Throwable $finishError): void
    {
        Log::error('[财务幂等] 幂等记录终态写入失败，需人工核对', [
            'operation_id' => (int) $operation->id,
            'scope' => (string) $operation->scope,
            'request_key' => (string) $operation->request_key,
            'expected_status' => $status,
            'error' => $finishError->getMessage(),
        ]);
    }

    private function finish(
        FinanceOperation $operation,
        string $status,
        ?array $result,
        ?Throwable $error = null,
        array $meta = [],
    ): void {
        $payload = [
            'status' => $status,
            'finished_at' => date('Y-m-d H:i:s'),
        ];

        if ($status === FinanceOperation::STATUS_SUCCEEDED) {
            $payload['result_payload'] = $result ?? [];
            $payload['resource_type'] = $meta['resource_type'] ?? null;
            $payload['resource_id'] = $meta['resource_id'] ?? null;
        }

        if ($error !== null) {
            $payload['error_code'] = $error instanceof BusinessException ? (string) $error->getErrorCode() : $error::class;
            $payload['error_msg'] = mb_substr($error->getMessage(), 0, 500);
        }

        $operation->forceFill($payload)->save();
    }

    private function assertRequestKey(string $requestKey): void
    {
        if (! preg_match('/^[A-Za-z0-9._:-]{8,128}$/D', $requestKey)) {
            throw new BusinessException('幂等键格式无效', 42200, 422);
        }
    }

    private function canonicalize(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        // 资金相关浮点一律按两位小数归一，避免 10 与 10.00 生成不同摘要。
        if (is_float($value)) {
            return number_format($value, 2, '.', '');
        }

        return $value;
    }
}
