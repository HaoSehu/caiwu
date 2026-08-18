<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\V2;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\V2\Service\IndexRequest;
use App\Http\Requests\Client\V2\Service\ListServiceOperationLogsRequest;
use App\Http\Requests\Client\V2\Service\ModuleStatusRequest;
use App\Http\Requests\Client\V2\Service\MonitorBatchRequest;
use App\Http\Requests\Client\V2\Service\MonitorRequest;
use App\Http\Requests\Client\V2\Service\UpdateNameRequest;
use App\Http\Requests\Client\V2\Service\UpdateRemarkRequest;
use App\Services\ClientServiceConsole\ClientServiceConsoleService;
use App\Support\PublicUrl;
use App\Support\RequestContext;
use Illuminate\Http\Request;

class ServiceConsoleController extends Controller
{
    public function __construct(
        private ClientServiceConsoleService $clientServiceConsoleService,
    ) {}

    public function index(IndexRequest $request)
    {
        $filters = $request->validated();

        return $this->success(
            $this->clientServiceConsoleService->paginateForUser($request->user(), $filters)
        );
    }

    public function groupedOverview(Request $request)
    {
        return $this->success(
            $this->clientServiceConsoleService->groupedOverviewForUser($request->user())
        );
    }

    public function config(Request $request, int $id)
    {
        return $this->success(
            $this->clientServiceConsoleService->getServiceConfigForUser(
                $request->user(),
                $id
            )
        );
    }

    public function updateRemark(UpdateRemarkRequest $request, int $id)
    {
        $data = $request->validated();

        return $this->success(
            $this->clientServiceConsoleService->updateRemarkForUser(
                $request->user(),
                $id,
                $data['remark'] ?? null,
                RequestContext::forClient($request)
            ),
            '备注已更新'
        );
    }

    public function updateName(UpdateNameRequest $request, int $id)
    {
        $data = $request->validated();

        return $this->success(
            $this->clientServiceConsoleService->updateServiceNameForUser(
                $request->user(),
                $id,
                $data['name'] ?? null,
                RequestContext::forClient($request)
            ),
            '实例名称已更新'
        );
    }

    public function moduleStatus(ModuleStatusRequest $request, int $id)
    {
        $filters = $request->validated();

        return $this->success(
            $this->clientServiceConsoleService->getModuleStatusForUser($request->user(), $id, $filters['type'])
        );
    }

    public function reinstallOptions(Request $request, int $id)
    {
        return $this->success(
            $this->clientServiceConsoleService->getReinstallOptionsForUser(
                $request->user(),
                $id,
                $this->booleanQuery($request, 'refresh')
            )
        );
    }

    public function operationLogs(ListServiceOperationLogsRequest $request, int $id)
    {
        return $this->success(
            $this->clientServiceConsoleService->getOperationLogsForUser(
                $request->user(),
                $id,
                $request->filters(),
                $request->perPage(10, 50)
            )
        );
    }

    public function monitor(MonitorRequest $request, int $id)
    {
        $filters = $request->validated();

        if ($request->has('fresh')) {
            $filters['fresh'] = $this->booleanQuery($request, 'fresh');
        }

        return $this->success(
            $this->clientServiceConsoleService->getMonitorForUser($request->user(), $id, $filters)
        );
    }

    public function monitorBatch(MonitorBatchRequest $request, int $id)
    {
        $filters = $request->validated();

        if ($request->has('fresh')) {
            $filters['fresh'] = $this->booleanQuery($request, 'fresh');
        }

        return $this->success(
            $this->clientServiceConsoleService->getMonitorBatchForUser($request->user(), $id, $filters)
        );
    }

    public function vnc(Request $request, int $id)
    {
        $result = $this->clientServiceConsoleService->getVncUrlForUser(
            $request->user(),
            $id,
            RequestContext::forClient($request)
        );
        $result['detail'] = $this->clientServiceConsoleService->sanitizeClientDetail(
            (array) ($result['detail'] ?? [])
        );

        return $this->success($result, '获取VNC链接成功');
    }

    public function vncToken(Request $request, string $token)
    {
        $this->assertVncTokenOriginAllowed($request);

        return $this->success(
            $this->clientServiceConsoleService->resolvePublicVncTokenPayload($token)
        );
    }

    /**
     * VNC 兑换端点 Origin 纵深校验：请求带 Origin 且不在本站白名单（console/api 域）时拒绝。
     * 同源请求或非浏览器客户端不带 Origin 时放行，避免误伤。
     */
    private function assertVncTokenOriginAllowed(Request $request): void
    {
        $origin = trim((string) $request->headers->get('Origin', ''));
        if ($origin === '') {
            return;
        }

        $allowed = array_values(array_filter([
            PublicUrl::console(),
            PublicUrl::api(),
        ]));

        foreach ($allowed as $allowedOrigin) {
            if (strcasecmp(rtrim((string) $allowedOrigin, '/'), rtrim($origin, '/')) === 0) {
                return;
            }
        }

        throw new BusinessException('VNC 兑换来源未获授权', 40300, 403);
    }
}
