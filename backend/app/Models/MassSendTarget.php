<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MassSendTarget extends Model
{
    public const STATUS_PENDING = 0;

    public const STATUS_SENT = 1;

    public const STATUS_FAILED = 2;

    /** 发送中（分片 Job 原子抢占后的中间态，防双派发重发） */
    public const STATUS_SENDING = 3;

    protected $fillable = [
        'mass_send_id', 'user_id', 'email', 'status', 'error', 'sent_at',
    ];

    protected $casts = [
        'status' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function massSend(): BelongsTo
    {
        return $this->belongsTo(MassSend::class);
    }
}
