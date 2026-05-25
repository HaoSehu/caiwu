<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProduct extends Model
{
    protected $table = 'supplier_products';

    protected $connection = 'idc';

    protected $fillable = [
        'supplier_id',
        'product_id',
        'upstream_product_code',
        'upstream_plan_code',
        'provision_module',
        'mapping_config_json',
        'is_default',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'supplier_id' => 'integer',
            'product_id' => 'integer',
            'mapping_config_json' => 'array',
            'is_default' => 'boolean',
            'status' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
