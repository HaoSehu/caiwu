<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 账单合并子账单关联快照（M1 账单合并）。
 *
 * 一行 = 一张子账单挂载到一张总账单的事实 + 合并时刻的金额快照。
 * child_invoice_id 唯一约束保证一张子账单只能属于一张总账单；
 * 写入仅发生在 InvoiceMergeService::merge，删除仅发生在 unmerge / FK 级联。
 *
 * @property int $id
 * @property int $master_invoice_id 总账单ID
 * @property int $child_invoice_id 子账单ID（唯一）
 * @property string $child_invoice_no 子账单号快照
 * @property string $amount_snapshot 合并时子账单金额快照
 * @property CarbonInterface|null $created_at
 */
class InvoiceMergeItem extends Model
{
    /**
     * 快照行只插不改：表仅含 created_at，关闭 updated_at 自动时间戳。
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'master_invoice_id',
        'child_invoice_id',
        'child_invoice_no',
        'amount_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'master_invoice_id' => 'integer',
            'child_invoice_id' => 'integer',
            'amount_snapshot' => 'decimal:2',
        ];
    }

    public function masterInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'master_invoice_id');
    }

    public function childInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'child_invoice_id');
    }
}
