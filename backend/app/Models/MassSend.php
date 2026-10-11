<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MassSend extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 0;

    public const STATUS_PENDING = 1;

    public const STATUS_SENDING = 2;

    public const STATUS_FINISHED = 3;

    public const STATUS_CANCELLED = 4;

    /** @var array<int, string> */
    public const STATUS_LABELS = [
        self::STATUS_DRAFT => '草稿',
        self::STATUS_PENDING => '待发送',
        self::STATUS_SENDING => '发送中',
        self::STATUS_FINISHED => '已完成',
        self::STATUS_CANCELLED => '已取消',
    ];

    public const CHANNEL_EMAIL = 1;

    public const CHANNEL_INBOX = 2;

    public const CHANNEL_BOTH = 3;

    protected $fillable = [
        'name', 'channel', 'is_marketing', 'template_id', 'subject', 'content',
        'filter_json', 'total_count', 'sent_count', 'failed_count', 'status',
        'scheduled_at', 'started_at', 'finished_at', 'created_by',
    ];

    protected $casts = [
        'filter_json' => 'array',
        'is_marketing' => 'integer',
        'channel' => 'integer',
        'total_count' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'status' => 'integer',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<NotificationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(MassSendTarget::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }

    /**
     * 是否仍允许编辑/删除（仅草稿）。
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
