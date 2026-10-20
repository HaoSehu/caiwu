<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 财务写操作幂等记录。同 scope+actor+key 只允许一行，同键异 hash 由服务层拒绝。
 */
class FinanceOperation extends Model
{
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

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
