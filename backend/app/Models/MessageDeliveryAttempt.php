<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 一次消息发送尝试（对齐 howstack-bos message_delivery_attempts）。
 *
 * 同一逻辑投递（message_logs）可有多次尝试（队列重试），
 * (message_log_id, attempt_no) 唯一；表内不保存收件地址、正文或凭据。
 */
class MessageDeliveryAttempt extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public $timestamps = false;

    protected $fillable = [
        'message_log_id',
        'attempt_no',
        'status',
        'error_code',
        'error_msg',
        'duration_ms',
        'created_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'message_log_id' => 'integer',
            'attempt_no' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
