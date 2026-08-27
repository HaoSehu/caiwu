<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 会员等级 × 营销产品组 折扣矩阵。
 *
 * 注意：discount_type=1 时 discount_value 是「折后保留比例」（bates 语义，90=九折），
 * 与优惠券 percentage 的「减免比例」语义相反。
 */
class MemberLevelGroupDiscount extends Model
{
    public const TYPE_PERCENT = 1;

    public const TYPE_FIXED = 2;

    protected $fillable = [
        'member_level_id',
        'marketing_product_group_id',
        'discount_type',
        'discount_value',
    ];

    protected function casts(): array
    {
        return [
            'member_level_id' => 'integer',
            'marketing_product_group_id' => 'integer',
            'discount_type' => 'integer',
            'discount_value' => 'decimal:2',
        ];
    }
}
