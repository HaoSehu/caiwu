<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use App\Models\User;
use App\Services\System\OperationLogService;
use App\Support\AuditParamRedactor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class LogOperation
{
    public function __construct(
        private OperationLogService $operationLogService,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $startedAt = microtime(true);
        $requestTime = now()->format('Y-m-d H:i:s.u');
        $requestId = $this->resolveRequestId($request);

        // 结构化日志上下文：同一请求内所有 Log 调用自动携带 request_id 等链路字段。
        try {
            Log::shareContext([
                'request_id' => $requestId,
            ]);
        } catch (\Throwable) {
            // 日志上下文初始化失败不应阻断 API 主流程。
        }

        try {
            $response = $next($request);
            $response->headers->set('X-Request-Id', $requestId);
            $this->writeApiAccessLog($request, $response, null, $startedAt, $requestTime, $requestId);

            return $response;
        } catch (\Throwable $exception) {
            $this->writeApiAccessLog($request, null, $exception, $startedAt, $requestTime, $requestId);

            throw $exception;
        } finally {
            // flushSharedContext() 只清理 manager 级 sharedContext；已经解析的
            // channel 仍可能保留 request_id，长驻 worker 下一请求会继承旧链路。
            try {
                Log::withoutContext(['request_id']);
            } catch (\Throwable) {
                // 清理失败不应覆盖业务异常。
            }

            try {
                Log::flushSharedContext();
            } catch (\Throwable) {
                // 清理失败不应覆盖业务异常。
            }
        }
    }

    private function writeApiAccessLog(
        Request $request,
        ?Response $response = null,
        ?\Throwable $exception = null,
        ?float $startedAt = null,
        ?string $requestTime = null,
        ?string $requestId = null,
    ): void {
        if (! $this->shouldLog($request)) {
            return;
        }

        try {
            $user = $this->resolveUserForLog($request);
            $requestId = trim((string) ($requestId ?? $request->attributes->get('request_id', $request->header('X-Request-Id', ''))));
            $userAgent = trim((string) $request->userAgent());
            $statusCode = $response?->getStatusCode();

            if ($statusCode === null) {
                $statusCode = $exception instanceof HttpExceptionInterface
                    ? $exception->getStatusCode()
                    : (is_int($exception?->getCode()) && $exception->getCode() >= 100 && $exception->getCode() <= 599
                        ? (int) $exception->getCode()
                        : 500);
            }

            if ($exception === null && $this->shouldSkipSuccessfulPollingRequest($request, $statusCode)) {
                return;
            }

            $detail = [
                'request_time' => $requestTime ?? now()->format('Y-m-d H:i:s.u'),
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $statusCode,
                'request_id' => $requestId,
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'user_agent' => $userAgent,
                'service' => (string) config('app.name', 'caiwu-backend'),
                // 文件日志与审计库共用的链路字段：普通请求写入 api-json 文件时
                // 也保留 actor/module/IP，保证管理端合并视图可还原来源。
                'user_id' => $user?->id ? (int) $user->id : null,
                'user_type' => $this->resolveUserType($user),
                'actor_name' => $this->resolveActorName($user),
                'module' => $this->resolveModule($request),
                'ip_address' => $request->ip(),
            ];

            $shouldPersistAudit = $this->shouldPersistAccessAudit($request, $statusCode, (string) $detail['module']);
            if ($shouldPersistAudit) {
                // 密码/验证码/令牌不是应进审计的展示要素，落库前剔除（含失败尝试）。
                $detail['params'] = AuditParamRedactor::redact($request->all(), (string) $detail['module']);
            }

            if ($exception !== null) {
                $detail['exception'] = class_basename($exception);
                $detail['exception_message'] = $exception->getMessage();
            }

            if ($shouldPersistAudit) {
                // 审计落库延迟到响应发送之后执行：所有写请求此前都在响应返回前
                // 串行等待一次 activity_logs INSERT（含参数序列化与递归截断），
                // 高峰期直接计入接口 RT 并拖累审计表写入吞吐。defer 由框架保证
                // 在响应发送后、进程结束前执行，失败仅记日志不影响主流程。
                $operationLogService = $this->operationLogService;
                defer(function () use ($operationLogService, $user, $request, $detail): void {
                    $operationLogService->write(
                        userId: $user?->id ? (int) $user->id : null,
                        userType: $this->resolveUserType($user),
                        action: $request->method().' '.$request->path(),
                        module: $this->resolveModule($request),
                        targetId: null,
                        detail: $detail,
                        ipAddress: $request->ip(),
                    );
                });

                return;
            }

            // 普通成功 GET 等非审计请求：只写入按日轮转的结构化文件，不落 activity_logs。
            Log::channel('api-json')->info('api.access', $detail);
        } catch (\Throwable) {
            // 日志记录失败不影响主流程
        }
    }

    private function shouldLog(Request $request): bool
    {
        if (! $request->is('api/*')) {
            return false;
        }

        if (in_array($request->method(), ['OPTIONS', 'HEAD'], true)) {
            return false;
        }

        if ($request->is('api/v2/admin/logs')
            || $request->is('api/v2/admin/logs/*')
            || $request->is('api/v2/admin/log-summaries')
            || $request->is('api/v2/admin/log-summaries/*')
            || $request->is('api/v2/admin/log-cleanups/overview')) {
            return false;
        }

        if ($request->is('api/health', 'api/ready')) {
            return false;
        }

        return true;
    }

    private function shouldSkipSuccessfulPollingRequest(Request $request, int $statusCode): bool
    {
        if ($statusCode >= 400 || $request->method() !== 'GET') {
            return false;
        }

        return $request->is('api/v2/client/verification/status')
            || $request->is('api/v2/client/recharge/*/status')
            || $request->is('api/v2/client/invoices/*/pay/alipay/status');
    }

    /**
     * 判定该请求是否属于需要落 activity_logs 的审计事件：
     * 错误/异常请求、认证模块、非 GET 写请求保留审计；其余普通成功 GET 只写文件。
     */
    private function shouldPersistAccessAudit(Request $request, int $statusCode, string $module): bool
    {
        if ($statusCode >= 400) {
            return true;
        }

        if ($module === 'auth') {
            return true;
        }

        return $request->method() !== 'GET';
    }

    private function resolveUserType(mixed $user): string
    {
        if ($user instanceof AdminUser) {
            return 'admin';
        }

        if ($user instanceof User) {
            return 'client';
        }

        return 'guest';
    }

    /**
     * 解析日志身份：site 等公开路由不挂 auth:sanctum，登录用户的 Bearer token
     * 无法经 $request->user() 获得。此处仅在带 token 时用 sanctum guard 兜底，
     * 只用于日志身份识别，不改变路由鉴权；无 token 仍为 guest。
     */
    private function resolveUserForLog(Request $request): mixed
    {
        $user = $request->user();

        if ($user !== null || $request->bearerToken() === null) {
            return $user;
        }

        try {
            return Auth::guard('sanctum')->user();
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveActorName(mixed $user): string
    {
        if ($user === null) {
            return '';
        }

        return trim((string) ($user->display_name ?? $user->nickname ?? $user->email ?? $user->username ?? ''));
    }

    private function resolveModule(Request $request): string
    {
        $segments = array_values(array_filter(explode('/', trim($request->path(), '/'))));

        if (($segments[0] ?? '') === 'api') {
            array_shift($segments);
        }

        if (($segments[0] ?? '') === 'v2') {
            array_shift($segments);
        }

        if (in_array($segments[0] ?? '', ['admin', 'client', 'site'], true)) {
            array_shift($segments);
        }

        return trim((string) ($segments[0] ?? 'unknown')) ?: 'unknown';
    }

    private function resolveRequestId(Request $request): string
    {
        $requestId = trim((string) ($request->headers->get('X-Request-Id') ?: $request->attributes->get('request_id', '')));

        if ($requestId === '') {
            $requestId = (string) Str::ulid();
        }

        $requestId = mb_substr($requestId, 0, 64);

        $request->headers->set('X-Request-Id', $requestId);
        $request->attributes->set('request_id', $requestId);

        return $requestId;
    }

    private function elapsedMilliseconds(?float $startedAt): int
    {
        if ($startedAt === null) {
            return 0;
        }

        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
    }
}
