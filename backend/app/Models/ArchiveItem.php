<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * V2 归档协议元数据。状态机：
 * planned -> staging -> verified -> published -> purging -> purged
 *   \-> failed / needs_recovery
 * 源数据只在 published 后允许按边界分块删除。
 */
class ArchiveItem extends Model
{
    public const STATUS_PLANNED = 'planned';

    public const STATUS_STAGING = 'staging';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_PURGING = 'purging';

    public const STATUS_PURGED = 'purged';

    public const STATUS_FAILED = 'failed';

    public const STATUS_NEEDS_RECOVERY = 'needs_recovery';

    protected $fillable = [
        'batch_id',
        'table_name',
        'status',
        'cutoff_at',
        'id_min',
        'id_max',
        'expected_rows',
        'exported_rows',
        'deleted_rows',
        'part_path',
        'published_path',
        'manifest_path',
        'checksum_sha256',
        'file_size',
        'error_message',
        'started_at',
        'verified_at',
        'published_at',
        'purged_at',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_at' => 'datetime',
            'id_min' => 'integer',
            'id_max' => 'integer',
            'expected_rows' => 'integer',
            'exported_rows' => 'integer',
            'deleted_rows' => 'integer',
            'file_size' => 'integer',
            'started_at' => 'datetime',
            'verified_at' => 'datetime',
            'published_at' => 'datetime',
            'purged_at' => 'datetime',
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_PURGED,
            self::STATUS_FAILED,
            self::STATUS_NEEDS_RECOVERY,
        ], true);
    }
}
