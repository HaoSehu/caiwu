<?php

declare(strict_types=1);

namespace App\Http\Controllers\Zjmf;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ZjmfBridge\ZjmfErrorMapper;
use App\Services\ZjmfBridge\ZjmfFinanceService;
use App\Services\ZjmfBridge\ZjmfResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function __construct(
        private readonly ZjmfFinanceService $finance,
        private readonly ZjmfResponseFactory $responses,
        private readonly ZjmfErrorMapper $errors,
    ) {}

    public function invoices(Request $request): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->finance->invoices($user, $request->query()));
    }

    public function invoice(Request $request, int $id): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->finance->invoice($user, $id));
    }

    public function invoiceStatus(Request $request, int $id): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->finance->invoiceStatus($user, $id));
    }

    public function payInvoiceByBalance(Request $request, int $id): JsonResponse
    {
        return $this->respond(
            $request,
            fn (User $user): array => $this->finance->payInvoiceByBalance($user, $id, $this->operationContext($request, $user)),
            '支付成功'
        );
    }

    public function fundTransactions(Request $request): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->finance->fundTransactions($user, $request->query()));
    }

    public function funds(Request $request): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->finance->funds($user, $request->query()));
    }

    public function recharge(Request $request): JsonResponse
    {
        return $this->respond(
            $request,
            fn (User $user): array => $this->finance->recharge($user, $request->all(), $this->operationContext($request, $user)),
            '充值二维码已生成'
        );
    }

    public function payments(Request $request): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->finance->payments($user, $request->query()));
    }

    public function payment(Request $request, int $id): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->finance->payment($user, $id));
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

    /**
     * @return array<string, mixed>
     */
    private function operationContext(Request $request, User $user): array
    {
        return [
            'actor_type' => 'client',
            'actor_user_id' => (int) $user->id,
            'actor_name' => (string) ($user->display_name ?? $user->nickname ?? $user->email ?? ''),
            'operator' => 'zjmf_bridge',
            'ip_address' => (string) $request->ip(),
            'trace_id' => (string) $request->header('X-Request-Id', ''),
        ];
    }
}
