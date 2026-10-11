<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Verification\VerificationGateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * M11 未实名自动限制：路由层拦截中间件。
 *
 * 以别名 + 参数方式注册（bootstrap/app.php）：
 *   'verified.purchase' => EnsureVerifiedForPurchase::class,
 * 路由用法：->middleware('verified.purchase:recharge')（参数为 Gate scope，缺省 purchase）。
 * 需挂在 auth:sanctum + ensure.client 之后；服务层 ensureCan() 兜底防绕过 API 层直调。
 */
class EnsureVerifiedForPurchase
{
    public function __construct(
        private VerificationGateService $verificationGate,
    ) {}

    public function handle(Request $request, Closure $next, string $scope = VerificationGateService::SCOPE_PURCHASE): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->verificationGate->ensureCan($user, $scope);
        }

        return $next($request);
    }
}
