<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    protected $fillable = [
        'legacy_reply_id',
        'ticket_id',
        'sender_type',
        'sender_id',
        'content',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'legacy_reply_id' => 'integer',
            'ticket_id' => 'integer',
            'sender_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
