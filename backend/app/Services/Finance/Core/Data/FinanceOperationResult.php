<?php

declare(strict_types=1);

namespace App\Services\Finance\Core\Data;

/**
 * 幂等操作的返回结果。
 *
 * replayed=true 表示本次未执行业务逻辑，直接返回此前成功结果。
 */
final readonly class FinanceOperationResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public bool $replayed,
        public array $payload,
        public int $operationId,
        public string $status,
    ) {}
}
