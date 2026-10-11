<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $category_id 分类 ID，NULL=未分类（分类删除后置 NULL）
 * @property int|null $department_id 限定部门 ID，NULL=通用
 * @property string $title 模板名
 * @property string $content 内容，支持 {{user_email}} 等占位符
 * @property int $is_enabled 0=禁用 1=启用
 * @property int $sort_order 排序值，越小越靠前
 * @property int $usage_count 使用次数（原子自增）
 * @property int|null $created_by 创建人 admin_users.id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property TicketPresetReplyCategory|null $category
 * @property TicketDepartment|null $department
 * @property AdminUser|null $creator
 */
class TicketPresetReply extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'department_id', 'title', 'content',
        'is_enabled', 'sort_order', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'department_id' => 'integer',
            'is_enabled' => 'integer',
            'sort_order' => 'integer',
            'usage_count' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TicketPresetReplyCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketPresetReplyCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<TicketDepartment, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(TicketDepartment::class, 'department_id');
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }

    /**
     * 原子自增使用计数（usage_count），用于冷热统计与排序参考。
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}
