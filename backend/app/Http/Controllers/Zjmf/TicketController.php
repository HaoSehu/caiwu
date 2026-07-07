<?php

declare(strict_types=1);

namespace App\Http\Controllers\Zjmf;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ZjmfBridge\ZjmfErrorMapper;
use App\Services\ZjmfBridge\ZjmfResponseFactory;
use App\Services\ZjmfBridge\ZjmfTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(
        private readonly ZjmfTicketService $tickets,
        private readonly ZjmfResponseFactory $responses,
        private readonly ZjmfErrorMapper $errors,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->tickets->tickets($user, $request->query()));
    }

    public function page(Request $request): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->tickets->page($user, $request->query()));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->respond(
            $request,
            fn (User $user): array => $this->tickets->create($user, $request->all()),
            '工单提交成功'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->tickets->ticket($user, $id));
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        return $this->respond(
            $request,
            fn (User $user): array => $this->tickets->reply($user, $id, $request->all()),
            '回复成功'
        );
    }

    public function close(Request $request, int $id): JsonResponse
    {
        return $this->respond(
            $request,
            fn (User $user): array => $this->tickets->close($user, $id),
            '工单已关闭'
        );
    }

    private function respond(Request $request, callable $callback, string $message = 'success'): JsonResponse
    {
        $user = $request->attributes->get('zjmf_user');
        if (! $user instanceof User) {
            return $this->responses->error(401, '未登录或登录已过期');
        }

        try {
            return $this->responses->success($callback($user), $message);
        } catch (BusinessException $exception) {
            return $this->responses->error(
                $this->errors->fromCaiwuCode($exception->getErrorCode()),
                $exception->getMessage()
            );
        }
    }
}
