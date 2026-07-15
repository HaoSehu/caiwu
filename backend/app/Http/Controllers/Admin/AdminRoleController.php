<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rbac\ListAdminRolesRequest;
use App\Http\Requests\Admin\Rbac\StoreAdminRoleRequest;
use App\Http\Requests\Admin\Rbac\UpdateAdminRoleRequest;
use App\Http\Resources\Admin\AdminRoleResource;
use App\Models\Role;
use App\Services\Admin\Rbac\AdminRoleService;

class AdminRoleController extends Controller
{
    public function __construct(private readonly AdminRoleService $adminRoleService) {}

    public function index(ListAdminRolesRequest $request)
    {
        return $this->success([
            'list' => AdminRoleResource::collection($this->adminRoleService->list($request->filters()))->resolve(),
        ]);
    }

    public function show(Role $role)
    {
        return $this->success(new AdminRoleResource($this->adminRoleService->detail($role)));
    }

    public function store(StoreAdminRoleRequest $request)
    {
        $role = $this->adminRoleService->create($request->payload());

        return $this->success(new AdminRoleResource($role), '角色创建成功');
    }

    public function update(UpdateAdminRoleRequest $request, Role $role)
    {
        $role = $this->adminRoleService->update($role, $request->payload());

        return $this->success(new AdminRoleResource($role), '角色更新成功');
    }

    public function destroy(Role $role)
    {
        $this->adminRoleService->delete($role);

        return $this->success(null, '角色删除成功');
    }

    public function copy(Role $role)
    {
        $newRole = $this->adminRoleService->copy($role);

        return $this->success(new AdminRoleResource($newRole), '角色复制成功');
    }
}
