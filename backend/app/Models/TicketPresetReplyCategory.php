<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name 分类名称
 * @property int $sort_order 排序值，越小越靠前
 * @property int $is_enabled 0=禁用 1=启用
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
class TicketPresetReplyCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'sort_order', 'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_enabled' => 'integer',
        ];
    }

    /**
     * @return HasMany<TicketPresetReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketPresetReply::class, 'category_id');
    }
}
