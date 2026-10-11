<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M5 客户归属变更日志：内部销售归属每次变更留痕（防抢客纠纷）。
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $from_admin_id
 * @property int|null $to_admin_id
 * @property string|null $reason
 * @property int|null $operator_id
 */
class SalesAttributionLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'from_admin_id',
        'to_admin_id',
        'reason',
        'operator_id',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'from_admin_id' => 'integer',
            'to_admin_id' => 'integer',
            'operator_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'from_admin_id');
    }

    public function toAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'to_admin_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'operator_id');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
