<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 新投递任务的逐次尝试：以 delivery_id 关联 message_deliveries。
 *
 * 与既有 message_delivery_attempts（message_log_id 维度，服务于 message_logs 审计）
 * 并存：本表用于新链路的重试判定，不保存地址、正文或凭据。
 */
class MessageDeliveryAttemptLog extends Model
{
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNKNOWN = 'unknown';

    public $timestamps = false;

    protected $fillable = [
        'delivery_id',
        'attempt_no',
        'status',
        'error_code',
        'duration_ms',
        'created_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_id' => 'integer',
            'attempt_no' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
