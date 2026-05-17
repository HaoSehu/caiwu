<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use App\Models\User;
use App\Services\System\OperationLogService;
use App\Support\SensitiveDataSanitizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class LogOperation
{
    public function __construct(
        private OperationLogService $operationLogService,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            $this->writeApiAccessLog($request, null, $exception);

            throw $exception;
        }

        $this->writeApiAccessLog($request, $response);

        return $response;
    }

    private function writeApiAccessLog(Request $request, ?Response $response = null, ?\Throwable $exception = null): void
    {
        if (! $this->shouldLog($request)) {
            return;
        }

        try {
            $user = $request->user();
            $requestId = trim((string) $request->header('X-Request-Id', ''));
            $userAgent = trim((string) $request->userAgent());
            $statusCode = $response?->getStatusCode();

            if ($statusCode === null) {
                $statusCode = $exception instanceof HttpExceptionInterface
                    ? $exception->getStatusCode()
                    : (is_int($exception?->getCode()) && $exception->getCode() >= 100 && $exception->getCode() <= 599
                        ? (int) $exception->getCode()
                        : 500);
            }

            $detail = [
                'params' => SensitiveDataSanitizer::sanitize($request->all()),
                'status' => $statusCode,
                'request_id' => $requestId,
                'user_agent' => $userAgent,
            ];

            if ($exception !== null) {
                $detail['exception'] = class_basename($exception);
                $detail['exception_message'] = SensitiveDataSanitizer::sanitizeText($exception->getMessage());
            }

            $this->operationLogService->write(
                userId: $user?->id ? (int) $user->id : null,
                userType: $this->resolveUserType($user),
                action: $request->method().' '.$request->path(),
                module: $this->resolveModule($request),
                targetId: null,
                detail: $detail,
                ipAddress: $request->ip(),
            );
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

        if ($request->is('api/admin/logs') || $request->is('api/admin/logs/*')) {
            return false;
        }

        if ($request->is('api/health')) {
            return false;
        }

        return true;
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

    private function resolveModule(Request $request): string
    {
        $segments = array_values(array_filter(explode('/', trim($request->path(), '/'))));

        if (($segments[0] ?? '') === 'api') {
            array_shift($segments);
        }

        if (in_array($segments[0] ?? '', ['admin', 'client', 'site'], true)) {
            array_shift($segments);
        }

        return trim((string) ($segments[0] ?? 'unknown')) ?: 'unknown';
    }
}
