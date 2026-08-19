<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Installer\InstallerStateService;
use Closure;
use Illuminate\Http\Request;

class EnsureInstallerAvailable
{
    public function __construct(private readonly InstallerStateService $state) {}

    public function handle(Request $request, Closure $next): mixed
    {
        abort_if($this->state->isInstalled(), 404);
        config(['cache.default' => 'array', 'session.driver' => 'array']);

        return $next($request);
    }
}
