<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 财务重建运行记录：每个阶段一行，保存闸门结果与统计摘要。
 */
class FinanceMigrationRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_PASSED = 'passed';

    /** 阶段按配置跳过（如热备关闭时的 hot_standby_sync）；语义等同 passed。 */
    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ABORTED = 'aborted';

    protected $fillable = [
        'run_no',
        'phase',
        'status',
        'stats',
        'message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'stats' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
