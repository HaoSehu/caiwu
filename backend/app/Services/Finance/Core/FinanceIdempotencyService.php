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
 * - failed 或超时的 processing → 允许同键重试。
 *
 * 约束：必须在业务事务之外调用。幂等记录先落库再执行回调，回调内部自行开事务；
 * 若把本方法包在业务事务里，业务回滚会把幂等事实一起带走，就退化成没有幂等。
 */
final class FinanceIdempotencyService
{
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

        // 资金已经动了：finish 失败只告警，绝不能让幂等行停留在 processing。
        // 否则超过 finance.idempotency.stale_processing_seconds 的僵死窗口后
        // 会被判定僵死并允许同键重放，等于二次执行资金回调。
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
     */
    private function staleSeconds(): int
    {
        return max((int) config('finance.idempotency.stale_processing_seconds', 120), 60);
    }

    /**
     * finish 的失败必须被吞掉并告警：调用点要么是「业务已失败正在抛错」（再抛会掩盖原始异常），
     * 要么是「资金已提交成功」（抛错会让调用方误以为整笔失败而重试）。
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
        try {
            $this->finish($operation, $status, $result, $error, $meta);
        } catch (Throwable $finishError) {
            Log::error('[财务幂等] 幂等记录终态写入失败，需人工核对', [
                'operation_id' => (int) $operation->id,
                'scope' => (string) $operation->scope,
                'request_key' => (string) $operation->request_key,
                'expected_status' => $status,
                'error' => $finishError->getMessage(),
            ]);
        }
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
