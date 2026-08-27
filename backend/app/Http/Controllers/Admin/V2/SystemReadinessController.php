<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Services\System\ProductionReadinessService;
use Illuminate\Http\JsonResponse;

class SystemReadinessController extends Controller
{
    public function show(ProductionReadinessService $readiness): JsonResponse
    {
        $report = $readiness->check();

        return $this->success(
            [
                'status' => $report['ready'] ? 'ready' : 'not_ready',
                'checks' => $report['checks'],
            ],
            $report['ready'] ? '服务已就绪' : '服务未就绪'
        );
    }
}
