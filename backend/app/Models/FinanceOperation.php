<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 财务写操作幂等记录。同 scope+actor+key 只允许一行，同键异 hash 由服务层拒绝。
 *
 * ack_pending：资金已提交但「succeeded」终态写入重试耗尽，需人工核对。
 * 该状态既不可重放（会二次执行资金回调）也不可重试，与 failed / 僵死 processing 区别对待；
 * 人工核对后按需改回 succeeded 或清理（清理命令按 TTL 过期同样会删除它）。
 */
class FinanceOperation extends Model
{
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ACK_PENDING = 'ack_pending';

    protected $fillable = [
        'scope',
        'request_key',
        'request_hash',
        'actor_type',
        'actor_id',
        'resource_type',
        'resource_id',
        'status',
        'result_payload',
        'error_code',
        'error_msg',
        'trace_id',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'resource_id' => 'integer',
            'result_payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
