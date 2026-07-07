<?php

declare(strict_types=1);

namespace App\Http\Controllers\Zjmf;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\ZjmfBridge\ZjmfErrorMapper;
use App\Services\ZjmfBridge\ZjmfResponseFactory;
use App\Services\ZjmfBridge\ZjmfTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly ZjmfTokenService $tokens,
        private readonly ZjmfResponseFactory $responses,
        private readonly ZjmfErrorMapper $errors,
    ) {}

    public function login(Request $request): JsonResponse
    {
        try {
            $account = trim((string) ($request->input('account') ?: $request->input('email') ?: $request->input('phone')));
            $password = (string) $request->input('password', '');
            if ($account === '' || $password === '') {
                throw new BusinessException('账号和密码不能为空', 42200, 422);
            }

            $result = $this->auth->clientLogin($account, $password, (string) $request->ip(), $request->userAgent());
            $this->deleteInternalToken((string) ($result['token'] ?? ''));
            $user = (array) ($result['user'] ?? []);
            $userId = (int) ($user['id'] ?? 0);
            $ttl = (int) config('zjmf_bridge.token_ttl', 7200);
            $jwt = $this->tokens->issue([
                'sub' => 'client:'.$userId,
                'uid' => $userId,
                'scope' => $this->clientScopes(),
            ], $ttl);

            return $this->responses->success([
                'jwt' => $jwt,
                'token' => $jwt,
                'token_type' => 'JWT',
                'expires_in' => $ttl,
                'user' => $this->userPayload(User::query()->with(['account', 'memberLevel'])->findOrFail($userId)),
            ], '登录成功');
        } catch (BusinessException $exception) {
            return $this->responses->error(
                $this->errors->fromCaiwuCode($exception->getErrorCode()),
                $exception->getMessage()
            );
        }
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->attributes->get('zjmf_user');
        if (! $user instanceof User) {
            return $this->responses->error(401, '未登录或登录已过期');
        }

        return $this->responses->success($this->userPayload($user));
    }

    /**
     * @return list<string>
     */
    private function clientScopes(): array
    {
        return [
            'client.read',
            'finance.read',
            'finance.write',
            'payment.read',
            'payment.write',
            'service.read',
            'service.write',
            'ticket.read',
            'ticket.write',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $memberLevel = $user->relationLoaded('memberLevel') ? $user->memberLevel : null;

        return [
            'id' => (int) $user->id,
            'email' => (string) ($user->email ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            'nickname' => (string) ($user->nickname ?? ''),
            'display_name' => (string) ($user->display_name ?? ''),
            'cash_balance' => (string) $user->balance,
            'credit_limit' => (string) $user->credit_limit,
            'referral_code' => (string) ($user->referral_code ?? ''),
            'member_level_id' => $user->member_level_id,
            'member_level' => $memberLevel ? [
                'id' => (int) $memberLevel->id,
                'name' => (string) $memberLevel->name,
                'code' => (string) $memberLevel->code,
            ] : null,
            'status' => (int) $user->status,
            'is_verified' => (int) $user->is_verified,
            'real_name' => (string) $user->real_name,
            'verification_status' => (int) $user->verification_status,
            'last_login_at' => $user->last_login_at?->format('Y-m-d H:i:s'),
            'last_login_ip' => (string) ($user->last_login_ip ?? ''),
            'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function deleteInternalToken(string $plainTextToken): void
    {
        $tokenId = (int) strtok($plainTextToken, '|');
        if ($tokenId <= 0) {
            return;
        }

        PersonalAccessToken::query()->whereKey($tokenId)->delete();
    }
}
