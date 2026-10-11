<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarePlan extends Model
{
    use SoftDeletes;

    public const TYPE_BIRTHDAY = 1;

    public const TYPE_EXPIRE = 2;

    public const TYPE_RECALL = 3;

    public const TYPE_CUSTOM = 4;

    /** @var array<int, string> */
    public const TYPE_LABELS = [
        self::TYPE_BIRTHDAY => '生日祝福',
        self::TYPE_EXPIRE => '到期提醒',
        self::TYPE_RECALL => '流失召回',
        self::TYPE_CUSTOM => '自定义',
    ];

    public const CHANNEL_EMAIL = 1;

    public const CHANNEL_INBOX = 2;

    public const CHANNEL_BOTH = 3;

    protected $fillable = [
        'name', 'type', 'offset_days', 'channel', 'template_id', 'subject', 'content',
        'filter_json', 'is_enabled', 'last_planned_date',
    ];

    protected $casts = [
        'type' => 'integer',
        'offset_days' => 'integer',
        'channel' => 'integer',
        'filter_json' => 'array',
        'is_enabled' => 'integer',
        'last_planned_date' => 'date:Y-m-d',
    ];

    /**
     * @return BelongsTo<NotificationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(CareTaskRun::class);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? (string) $this->type;
    }
}
