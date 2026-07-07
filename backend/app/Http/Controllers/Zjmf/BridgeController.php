<?php

declare(strict_types=1);

namespace App\Http\Controllers\Zjmf;

use App\Http\Controllers\Controller;
use App\Services\ZjmfBridge\ZjmfResponseFactory;
use Illuminate\Http\JsonResponse;

class BridgeController extends Controller
{
    public function __construct(
        private readonly ZjmfResponseFactory $responses,
    ) {}

    public function health(): JsonResponse
    {
        return $this->responses->success([
            'service' => 'zjmf_bridge',
            'enabled' => true,
            'mode' => (string) config('zjmf_bridge.mode', 'strict'),
        ]);
    }

    public function systemHealth(): JsonResponse
    {
        return $this->responses->success([
            'service' => 'zjmf_bridge',
            'enabled' => true,
            'mode' => (string) config('zjmf_bridge.mode', 'strict'),
            'scope' => 'system.health',
        ]);
    }
}
