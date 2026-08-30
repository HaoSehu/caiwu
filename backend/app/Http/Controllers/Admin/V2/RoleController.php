<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\Role\CopyRoleRequest;
use App\Http\Requests\Admin\V2\Role\CreateRoleRequest;
use App\Http\Requests\Admin\V2\Role\DeleteRoleRequest;
use App\Http\Requests\Admin\V2\Role\ListPermissionsRequest;
use App\Http\Requests\Admin\V2\Role\ListRolesRequest;
use App\Http\Requests\Admin\V2\Role\ShowRoleRequest;
use App\Http\Requests\Admin\V2\Role\UpdateRoleRequest;
use App\Http\Resources\Admin\V2\AdminActionResultResource;
use App\Http\Resources\Admin\V2\AdminPermissionCatalogResource;
use App\Http\Resources\Admin\V2\AdminRoleDetailResource;
use App\Http\Resources\Admin\V2\AdminRoleListItemResource;
use App\Models\Role;
use App\Services\Admin\Rbac\AdminRoleService;
use App\Services\Admin\Rbac\PermissionCatalogService;
use App\Services\Admin\V2\AdminRbacActionV2Service;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleController extends Controller
{
    public function __construct(
        private readonly AdminRbacActionV2Service $actions,
        private readonly AdminRoleService $roles,
        private readonly PermissionCatalogService $permissions,
    ) {}

    public function permissions(ListPermissionsRequest $request)
    {
        $list = AdminPermissionCatalogResource::collection($this->permissions->list())->resolve();

        // 全量权限目录无真实分页，统一经标准分页器出信封（page=1、page_size=条目数）。
        $total = count($list);

        return $this->paginate(new LengthAwarePaginator($list, $total, max($total, 1), 1));
    }

    public function index(ListRolesRequest $request)
    {
        $list = AdminRoleListItemResource::collection($this->roles->list($request->filters()))->resolve();

        // 全量角色列表无真实分页，统一经标准分页器出信封（page=1、page_size=条目数）。
        $total = count($list);

        return $this->paginate(new LengthAwarePaginator($list, $total, max($total, 1), 1));
    }

    public function show(ShowRoleRequest $request, Role $role)
    {
        return $this->success(AdminRoleDetailResource::make($this->roles->detail($role))->resolve());
    }

    public function store(CreateRoleRequest $request)
    {
        $role = $this->roles->create($request->payload(), $request->user());

        return $this->success(AdminRoleDetailResource::make($role)->resolve(), '角色创建成功');
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $role = $this->roles->update($role, $request->payload(), $request->user());

        return $this->success(AdminRoleDetailResource::make($role)->resolve(), '角色更新成功');
    }

    public function destroy(DeleteRoleRequest $request, Role $role)
    {
        $this->roles->delete($role);

        return $this->success(null, '角色删除成功');
    }

    public function copy(CopyRoleRequest $request, Role $role)
    {
        $result = $this->actions->copyRole($role, $request->user());

        return $this->success(AdminActionResultResource::make($result)->resolve(), (string) $result['message']);
    }
}
