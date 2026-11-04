<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 渠道投递任务（事务 Outbox）。
 *
 * 状态机：pending → processing → accepted / delivered / failed / unknown。
 * 只有 failed 且传输层标记可重试才回到 pending；unknown 一律终态。
 *
 * @property int $id
 * @property int $recipient_id
 * @property string $request_key
 * @property string $channel
 * @property string $channel_name
 * @property string $payload_encrypted
 * @property string $status
 * @property int $attempts
 * @property Carbon $next_attempt_at
 * @property Carbon $queue_after
 * @property string $lease_key
 * @property Carbon|null $lease_until
 * @property string $error_code
 */
class MessageDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNKNOWN = 'unknown';

    protected $fillable = [
        'recipient_id',
        'request_key',
        'channel',
        'channel_name',
        'payload_encrypted',
        'status',
        'attempts',
        'next_attempt_at',
        'queue_after',
        'lease_key',
        'lease_until',
        'error_code',
    ];

    protected function casts(): array
    {
        return [
            'recipient_id' => 'integer',
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'queue_after' => 'datetime',
            'lease_until' => 'datetime',
        ];
    }
}
