<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Service\BatchUpdateServiceCustomHostnameRequest;
use App\Services\Provisioning\AdminServiceHostnameService;
use App\Services\Provisioning\AdminServiceListService;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(
        private AdminServiceListService $adminServiceListService,
        private AdminServiceHostnameService $adminServiceHostnameService,
    ) {}

    /**
     * 全量服务列表
     */
    public function index(Request $request)
    {
        $filters = $request->only(['keyword', 'status', 'page', 'page_size']);
        $result = $this->adminServiceListService->paginate($filters);

        return $this->success($result);
    }

    public function batchUpdateCustomHostnames(BatchUpdateServiceCustomHostnameRequest $request)
    {
        return $this->success(
            $this->adminServiceHostnameService->batchUpdateCustomHostnames($request->payload(), [
                'operator_id' => (int) ($request->user()?->id ?? 0),
                'operator_name' => (string) ($request->user()?->username ?? $request->user()?->name ?? $request->user()?->email ?? 'admin'),
                'trace_id' => (string) $request->header('X-Request-Id', ''),
                'ip_address' => (string) $request->ip(),
            ]),
            '自定义主机名已更新'
        );
    }
}
