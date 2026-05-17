<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponseBuilder;
use Exception;
use Illuminate\Http\JsonResponse;

class BusinessException extends Exception
{
    protected int $errorCode;

    public function __construct(string $message = '业务异常', int $errorCode = 42200, int $httpStatus = 422)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message, $httpStatus);
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function render(): JsonResponse
    {
        return ApiResponseBuilder::error($this->errorCode, $this->getMessage());
    }
}
