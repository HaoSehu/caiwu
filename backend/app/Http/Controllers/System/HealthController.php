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
        // 公开探针只暴露结论，避免 checks 明细被用于基础设施侦察；明细由管理端 /system/readiness 输出。
        if ($readiness->check()['ready']) {
            return ApiResponseBuilder::success(['status' => 'ready'], '服务已就绪');
        }

        return ApiResponseBuilder::error(50300, '服务未就绪', [
            'status' => 'not_ready',
        ], 503);
    }
}
