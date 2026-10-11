<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\TicketDepartment\SaveDepartmentRequest;
use App\Http\Requests\Admin\V2\TicketDepartment\SetDepartmentStaffRequest;
use App\Http\Resources\Admin\V2\AdminTicketDepartmentResource;
use App\Models\TicketDepartment;
use App\Services\Ticket\TicketDepartmentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class TicketDepartmentController extends Controller
{
    use ApiResponse;

    public function __construct(private TicketDepartmentService $departments) {}

    public function index(): JsonResponse
    {
        return $this->success([
            'list' => AdminTicketDepartmentResource::collection(
                $this->departments->listDepartments()
            )->resolve(),
        ]);
    }

    public function store(SaveDepartmentRequest $request): JsonResponse
    {
        $department = $this->departments->createDepartment($request->payload());

        return $this->success([
            'department' => AdminTicketDepartmentResource::make($department)->resolve(),
        ], '部门创建成功');
    }

    public function update(SaveDepartmentRequest $request, TicketDepartment $department): JsonResponse
    {
        $department = $this->departments->updateDepartment($department, $request->payload());

        return $this->success([
            'department' => AdminTicketDepartmentResource::make($department)->resolve(),
        ], '部门更新成功');
    }

    public function destroy(TicketDepartment $department): JsonResponse
    {
        $this->departments->deleteDepartment($department);

        return $this->success(null, '部门已删除');
    }

    /**
     * 全量设置部门成员：body staff_ids[] + leader_ids[]（leader_ids 为 staff_ids 子集）。
     */
    public function updateStaff(SetDepartmentStaffRequest $request, TicketDepartment $department): JsonResponse
    {
        $this->departments->syncStaff($department, $request->staffIds(), $request->leaderIds());

        $department->loadCount('staff');

        return $this->success([
            'department' => AdminTicketDepartmentResource::make($department)->resolve(),
        ], '部门成员已更新');
    }
}
