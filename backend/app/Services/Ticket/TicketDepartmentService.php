<?php

declare(strict_types=1);

namespace App\Services\Ticket;

use App\Exceptions\BusinessException;
use App\Models\AdminUser;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketDepartmentStaff;
use App\Support\AdminPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * M3 工单部门体系：部门 CRUD、成员全量设置、存量字符串部门回填、客户端部门选项。
 */
class TicketDepartmentService
{
    /**
     * 存量回填：按 tickets.department 字符串列 distinct 非空值各建一条部门（已存在跳过），
     * 再按字符串回填 department_id，并把冗余列 department 同步为部门名。
     * 幂等可重入：重复执行不重复建部门、不重复回填。
     *
     * @return array{created_departments: int, backfilled_tickets: int}
     */
    public static function backfillLegacyDepartments(): array
    {
        $created = 0;
        $backfilled = 0;

        $legacyNames = Ticket::query()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        foreach ($legacyNames as $legacyName) {
            $name = trim((string) $legacyName);
            if ($name === '') {
                continue;
            }

            $department = TicketDepartment::withTrashed()->firstOrCreate(['name' => $name]);
            if ($department->wasRecentlyCreated) {
                $created++;
            }

            $backfilled += Ticket::query()
                ->where('department', $name)
                ->whereNull('department_id')
                ->update([
                    'department_id' => $department->id,
                    'department' => $name,
                ]);
        }

        return [
            'created_departments' => $created,
            'backfilled_tickets' => $backfilled,
        ];
    }

    /**
     * 部门列表（管理端），附带成员数。
     */
    public function listDepartments(): array
    {
        return TicketDepartment::query()
            ->withCount('staff')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * 客户端提单可选部门：仅启用中，排序输出。
     */
    public function enabledDepartmentsForClient(): array
    {
        return TicketDepartment::query()
            ->where('is_enabled', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'description', 'sort_order'])
            ->map(fn (TicketDepartment $department) => [
                'id' => (int) $department->id,
                'name' => (string) $department->name,
                'description' => (string) ($department->description ?? ''),
                'sort_order' => (int) $department->sort_order,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDepartment(array $data): TicketDepartment
    {
        $name = trim((string) ($data['name'] ?? ''));
        throw_if($name === '', new BusinessException('部门名称不能为空'));
        throw_if(
            TicketDepartment::withTrashed()->where('name', $name)->exists(),
            new BusinessException('部门名称已存在')
        );

        return TicketDepartment::create([
            'name' => $name,
            'description' => $data['description'] ?? null,
            'auto_assign_mode' => (int) ($data['auto_assign_mode'] ?? TicketDepartment::AUTO_ASSIGN_MANUAL),
            'is_enabled' => (int) ($data['is_enabled'] ?? 1),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDepartment(TicketDepartment $department, array $data): TicketDepartment
    {
        $name = trim((string) ($data['name'] ?? $department->name));
        throw_if($name === '', new BusinessException('部门名称不能为空'));
        throw_if(
            TicketDepartment::withTrashed()
                ->where('name', $name)
                ->where('id', '!=', (int) $department->id)
                ->exists(),
            new BusinessException('部门名称已存在')
        );

        $department->update([
            'name' => $name,
            'description' => $data['description'] ?? $department->description,
            'auto_assign_mode' => (int) ($data['auto_assign_mode'] ?? $department->auto_assign_mode),
            'is_enabled' => (int) ($data['is_enabled'] ?? $department->is_enabled),
            'sort_order' => (int) ($data['sort_order'] ?? $department->sort_order),
        ]);

        return $department->refresh();
    }

    /**
     * 删除部门（软删）：不级联改工单，工单 department_id 保留由 FK ON DELETE SET NULL 兜底。
     */
    public function deleteDepartment(TicketDepartment $department): void
    {
        $department->delete();
    }

    /**
     * 全量设置部门成员：staff_ids 为最终成员集合，leader_ids 为其中的负责人子集。
     * 不在集合内的既有成员被移除；重复执行幂等。
     *
     * @param  list<int>  $staffIds
     * @param  list<int>  $leaderIds
     */
    public function syncStaff(TicketDepartment $department, array $staffIds, array $leaderIds = []): void
    {
        $staffIds = collect($staffIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $leaderIds = collect($leaderIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => in_array($id, $staffIds, true))
            ->unique()
            ->values()
            ->all();

        $validCount = AdminUser::query()->whereIn('id', $staffIds)->count();
        throw_if($validCount !== count($staffIds), new BusinessException('存在无效的客服账号'));

        DB::transaction(function () use ($department, $staffIds, $leaderIds): void {
            TicketDepartmentStaff::query()
                ->where('department_id', (int) $department->id)
                ->whereNotIn('admin_user_id', $staffIds === [] ? [0] : $staffIds)
                ->delete();

            foreach ($staffIds as $adminUserId) {
                TicketDepartmentStaff::query()->updateOrCreate(
                    [
                        'department_id' => (int) $department->id,
                        'admin_user_id' => $adminUserId,
                    ],
                    ['is_leader' => in_array($adminUserId, $leaderIds, true) ? 1 : 0]
                );
            }
        });
    }

    /**
     * 操作者的部门归属 ID 集合（部门级数据范围依据）。
     *
     * @return list<int>
     */
    public static function operatorDepartmentIds(AdminUser $operator): array
    {
        return TicketDepartmentStaff::query()
            ->where('admin_user_id', (int) $operator->id)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 单工单访问校验（操作级越权防护，与 applyDataScope 的列表口径对齐）：
     * - 管理岗（ALL 或 ticket.manage）全量放行；
     * - 无部门归属的客服放行（裁决的向后兼容语义）；
     * - 有归属的客服仅可访问归属部门内的工单，否则拒绝（防 render 等入口越权读 PII）。
     */
    public static function assertTicketAccessible(AdminUser $operator, Ticket $ticket): void
    {
        $permissions = $operator->resolvedPermissions();
        if (in_array(AdminPermissions::ALL, $permissions, true)
            || in_array(AdminPermissions::TICKET_MANAGE, $permissions, true)) {
            return;
        }

        $departmentIds = self::operatorDepartmentIds($operator);
        if ($departmentIds === []) {
            return;
        }

        if ($ticket->department_id === null || ! in_array((int) $ticket->department_id, $departmentIds, true)) {
            throw new BusinessException('无权访问该工单');
        }
    }

    /**
     * 部门级数据范围：有归属且非 super_admin 的客服限定在归属部门内；
     * 无归属（向后兼容）与 super_admin 恒全量。
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyDataScope($query, AdminUser $operator)
    {
        $permissions = $operator->resolvedPermissions();
        if (in_array(AdminPermissions::ALL, $permissions, true)) {
            return $query;
        }

        $departmentIds = self::operatorDepartmentIds($operator);
        if ($departmentIds === []) {
            return $query;
        }

        return $query->whereIn('department_id', $departmentIds);
    }
}
