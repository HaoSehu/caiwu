<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Staff\DeleteAdminStaffRequest;
use App\Http\Requests\Admin\Staff\ListAdminStaffRequest;
use App\Http\Requests\Admin\Staff\ResetAdminStaffPasswordRequest;
use App\Http\Requests\Admin\Staff\StoreAdminStaffRequest;
use App\Http\Requests\Admin\Staff\UpdateAdminStaffRequest;
use App\Http\Resources\Admin\AdminStaffResource;
use App\Models\AdminUser;
use App\Services\Admin\Rbac\AdminStaffService;
use Illuminate\Http\Request;

class AdminStaffController extends Controller
{
    public function __construct(private readonly AdminStaffService $adminStaffService) {}

    public function index(ListAdminStaffRequest $request)
    {
        return $this->paginate(
            $this->adminStaffService->list($request->filters(), $request->perPage()),
            AdminStaffResource::class,
        );
    }

    public function show(AdminUser $staff)
    {
        $staff->load('role');

        return $this->success(new AdminStaffResource($staff));
    }

    public function store(StoreAdminStaffRequest $request)
    {
        $staff = $this->adminStaffService->create(
            data: $request->payload(),
            operator: $request->user(),
            ipAddress: $request->ip(),
        );

        return $this->success(new AdminStaffResource($staff), '员工创建成功');
    }

    public function update(UpdateAdminStaffRequest $request, AdminUser $staff)
    {
        $staff = $this->adminStaffService->update(
            staff: $staff,
            data: $request->payload(),
            operator: $request->user(),
            ipAddress: $request->ip(),
        );

        return $this->success(new AdminStaffResource($staff), '员工更新成功');
    }

    public function toggleStatus(Request $request, AdminUser $staff)
    {
        $staff = $this->adminStaffService->toggleStatus(
            staff: $staff,
            operator: $request->user(),
            ipAddress: $request->ip(),
        );

        return $this->success(new AdminStaffResource($staff), '员工状态已更新');
    }

    public function resetPassword(ResetAdminStaffPasswordRequest $request, AdminUser $staff)
    {
        $this->adminStaffService->resetPassword(
            staff: $staff,
            password: (string) $request->payload()['password'],
            operator: $request->user(),
            ipAddress: $request->ip(),
        );

        return $this->success(null, '员工密码已重置');
    }

    public function destroy(DeleteAdminStaffRequest $request, AdminUser $staff)
    {
        $this->adminStaffService->deleteDisabled(
            staff: $staff,
            operator: $request->user(),
            ipAddress: $request->ip(),
        );

        return $this->success(null, '员工已删除');
    }

    public function roles()
    {
        return $this->success([
            'list' => $this->adminStaffService->roleOptions()
                ->map(fn ($role) => [
                    'id' => (int) $role->id,
                    'name' => (string) $role->name,
                    'label' => (string) ($role->label ?? $role->name),
                ])
                ->values()
                ->all(),
        ]);
    }
}
