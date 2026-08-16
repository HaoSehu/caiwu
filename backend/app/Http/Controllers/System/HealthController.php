<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\System\ProductionReadinessService;
use App\Support\ApiResponseBuilder;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return ApiResponseBuilder::success(['status' => 'alive'], '服务正常');
    }

    public function ready(ProductionReadinessService $readiness): JsonResponse
    {
        $report = $readiness->check();
        $ready = $report['ready'];

        if ($ready) {
            return ApiResponseBuilder::success([
                'status' => 'ready',
                'checks' => $report['checks'],
            ], '服务已就绪');
        }

        return ApiResponseBuilder::error(50300, '服务未就绪', [
            'status' => 'not_ready',
            'checks' => $report['checks'],
        ], 503);
    }
}
