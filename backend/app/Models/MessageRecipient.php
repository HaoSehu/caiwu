<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 消息接收对象：每个收件人一行，站内已读状态也挂在它上面（read_at）。
 */
class MessageRecipient extends Model
{
    public const AUDIENCE_CUSTOMER = 'customer';

    public const AUDIENCE_ADMIN = 'admin';

    public const AUDIENCE_ROBOT = 'robot';

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'audience',
        'account_id',
        'target_key',
        'group_key',
        'group_name',
        'read_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'message_id' => 'integer',
            'account_id' => 'integer',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
