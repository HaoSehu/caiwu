<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponseBuilder;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class IntegrationException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $action,
        string $message = '第三方服务暂时不可用，请稍后重试',
        public readonly array $context = [],
        int $code = 42200,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function render(): JsonResponse
    {
        // context 含上游原始报错，仅在日志中记录，不随响应下发给客户端。
        return ApiResponseBuilder::error($this->code, $this->getMessage());
    }
}
