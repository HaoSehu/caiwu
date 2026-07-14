<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ZjmfBridge\ZjmfResponseFactory;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ZjmfBridgeEnabled
{
    public function __construct(
        private readonly ZjmfResponseFactory $responses,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('zjmf_bridge.enabled', false) !== true) {
            return $this->responses->error(503, 'ZJMF Bridge 未启用', [
                'trace_id' => $request->attributes->get('trace_id'),
            ], 404);
        }

        return $next($request);
    }
}
