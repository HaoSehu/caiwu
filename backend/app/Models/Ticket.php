<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'user_id', 'department', 'department_id', 'subject', 'priority',
        'status', 'service_id', 'assignee_id', 'close_reason',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return HasMany<TicketReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    /**
     * 转派流水，最新在前。注意：tickets 表已有字符串冗余列 department，
     * 部门关联因此命名为 departmentRelation()，避免与列访问语义冲突。
     *
     * @return HasMany<TicketTransferLog, $this>
     */
    public function transferLogs(): HasMany
    {
        return $this->hasMany(TicketTransferLog::class, 'ticket_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'assignee_id');
    }

    public function departmentRelation(): BelongsTo
    {
        return $this->belongsTo(TicketDepartment::class, 'department_id');
    }
}
