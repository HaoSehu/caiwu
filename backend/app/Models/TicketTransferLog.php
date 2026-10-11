<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketTransferLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ticket_id', 'from_department_id', 'to_department_id',
        'from_assignee_id', 'to_assignee_id', 'reason', 'operator_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'from_department_id' => 'integer',
            'to_department_id' => 'integer',
            'from_assignee_id' => 'integer',
            'to_assignee_id' => 'integer',
            'operator_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(TicketDepartment::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(TicketDepartment::class, 'to_department_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'operator_id');
    }
}
