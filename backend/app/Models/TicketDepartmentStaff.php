<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketDepartmentStaff extends Model
{
    protected $table = 'ticket_department_staff';

    protected $fillable = [
        'department_id', 'admin_user_id', 'is_leader',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'admin_user_id' => 'integer',
            'is_leader' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(TicketDepartment::class, 'department_id');
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }

    /**
     * 部门内启用状态的成员（admin_users.status=1），按 admin_user_id 升序，供轮询/最少负载取人。
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeEnabledStaff($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId)
            ->whereHas('adminUser', fn ($q) => $q->where('status', 1))
            ->orderBy('admin_user_id');
    }
}
