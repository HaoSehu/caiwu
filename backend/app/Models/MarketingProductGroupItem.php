<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingProductGroupItem extends Model
{
    protected $fillable = [
        'marketing_product_group_id',
        'product_id',
    ];

    protected function casts(): array
    {
        return [
            'marketing_product_group_id' => 'integer',
            'product_id' => 'integer',
        ];
    }
}
