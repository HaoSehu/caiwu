<?php

declare(strict_types=1);

namespace App\Http\Controllers\Zjmf;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ZjmfBridge\ZjmfErrorMapper;
use App\Services\ZjmfBridge\ZjmfResponseFactory;
use App\Services\ZjmfBridge\ZjmfServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(
        private readonly ZjmfServiceService $services,
        private readonly ZjmfResponseFactory $responses,
        private readonly ZjmfErrorMapper $errors,
    ) {}

    public function hosts(Request $request): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->services->hosts($user, $request->query()));
    }

    public function host(Request $request, int $id): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->services->host($user, $id));
    }

    private function respond(Request $request, callable $callback): JsonResponse
    {
        $user = $request->attributes->get('zjmf_user');
        if (! $user instanceof User) {
            return $this->responses->error(401, '未登录或登录已过期');
        }

        try {
            return $this->responses->success($callback($user));
        } catch (BusinessException $exception) {
            return $this->responses->error(
                $this->errors->fromCaiwuCode($exception->getErrorCode()),
                $exception->getMessage()
            );
        }
    }
}
