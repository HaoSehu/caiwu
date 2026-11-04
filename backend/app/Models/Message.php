<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 消息流水：一条业务事件对应一行，event_key 唯一保证重复发布不重复投递。
 */
class Message extends Model
{
    public const PURPOSE_NOTIFICATION = 'notification';

    public const PURPOSE_SYSTEM = 'system';

    public const PURPOSE_VERIFICATION = 'verification';

    public $timestamps = false;

    protected $fillable = [
        'event_key',
        'scene',
        'purpose',
        'business_type',
        'business_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
