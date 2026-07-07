<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Logging\ZjmfBridgeLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogZjmfBridgeRequest
{
    public function __construct(
        private readonly ZjmfBridgeLogger $logger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $traceId = (string) ($request->headers->get('X-Request-Id') ?: 'zjmf_'.Str::ulid());
        $startedAt = microtime(true);
        $request->attributes->set('trace_id', $traceId);

        /** @var Response $response */
        $response = $next($request);

        $this->logger->record($request, $response, (int) round((microtime(true) - $startedAt) * 1000));

        return $response;
    }
}
