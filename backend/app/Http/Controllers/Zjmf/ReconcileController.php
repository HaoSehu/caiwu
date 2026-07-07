<?php

declare(strict_types=1);

namespace App\Http\Controllers\Zjmf;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Services\ZjmfBridge\ZjmfErrorMapper;
use App\Services\ZjmfBridge\ZjmfReconcileService;
use App\Services\ZjmfBridge\ZjmfResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReconcileController extends Controller
{
    public function __construct(
        private readonly ZjmfReconcileService $reconcile,
        private readonly ZjmfResponseFactory $responses,
        private readonly ZjmfErrorMapper $errors,
    ) {}

    public function payments(Request $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->reconcile->payments($request->query()));
    }

    public function invoices(Request $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->reconcile->invoices($request->query()));
    }

    private function respond(callable $callback): JsonResponse
    {
        try {
            return $this->responses->success($callback());
        } catch (BusinessException $exception) {
            return $this->responses->error(
                $this->errors->fromCaiwuCode($exception->getErrorCode()),
                $exception->getMessage()
            );
        }
    }
}
