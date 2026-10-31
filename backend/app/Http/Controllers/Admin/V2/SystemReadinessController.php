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

        // reasons 只带未通过项的中文原因：运维在 503 页面要能直接看出是哪一层挂了，
        // 不能只给一个 checks 布尔表让人再去服务器上翻日志。
        return $this->success(
            [
                'status' => $report['ready'] ? 'ready' : 'not_ready',
                'checks' => $report['checks'],
                'reasons' => $report['reasons'],
            ],
            $report['ready'] ? '服务已就绪' : '服务未就绪'
        );
    }
}
