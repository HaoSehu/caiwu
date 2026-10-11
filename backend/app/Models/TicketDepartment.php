<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketDepartment extends Model
{
    use SoftDeletes;

    public const AUTO_ASSIGN_MANUAL = 0;

    public const AUTO_ASSIGN_ROUND_ROBIN = 1;

    public const AUTO_ASSIGN_LEAST_LOAD = 2;

    public const AUTO_ASSIGN_MODE_LABELS = [
        self::AUTO_ASSIGN_MANUAL => '手动分派',
        self::AUTO_ASSIGN_ROUND_ROBIN => '轮询分派',
        self::AUTO_ASSIGN_LEAST_LOAD => '最少负载',
    ];

    protected $fillable = [
        'name', 'description', 'auto_assign_mode', 'is_enabled', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'auto_assign_mode' => 'integer',
            'is_enabled' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<TicketDepartmentStaff, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(TicketDepartmentStaff::class, 'department_id');
    }

    /**
     * @return BelongsToMany<AdminUser, $this>
     */
    public function adminUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminUser::class,
            'ticket_department_staff',
            'department_id',
            'admin_user_id'
        )->withPivot('is_leader');
    }

    public function autoAssignModeLabel(): string
    {
        return self::AUTO_ASSIGN_MODE_LABELS[(int) $this->auto_assign_mode] ?? (string) $this->auto_assign_mode;
    }
}
