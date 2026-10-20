<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 切换窗口期支付回调收件箱：只存原始报文，不校验不入账，窗口后重放。
 */
class FinanceCutoverInbox extends Model
{
    /** 表名刻意为单数（finance_cutover_inbox），与迁移一致，屏蔽 Eloquent 复数猜测。 */
    protected $table = 'finance_cutover_inbox';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REPLAYED = 'replayed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'channel',
        'notification_id',
        'request_uri',
        'payload',
        'headers',
        'status',
        'received_at',
        'replayed_at',
        'error_msg',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'received_at' => 'datetime',
            'replayed_at' => 'datetime',
        ];
    }
}
