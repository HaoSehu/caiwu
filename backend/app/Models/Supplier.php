<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 供应商。
 *
 * api_url / api_username / api_key 不是 suppliers 表的列，而是插件运行时从
 * supplier_plugin_bindings 解密后由 PluginBindingResolver::supplierWithRuntimeCredentials()
 * 通过 setAttribute 注入的临时属性。这里显式声明，避免各处按属性访问时被静态分析
 * 判成未定义属性；未经过该解析器直接取 Supplier 时三者均为 null。
 *
 * @property string|null $api_url
 * @property string|null $api_username
 * @property string|null $api_key
 */
class Supplier extends Model
{
    protected $hidden = [];

    protected $fillable = [
        'name',
        'code',
        'contact_name',
        'contact_phone',
        'contact_email',
        'website',
        'status',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function scopeEnabled($query)
    {
        return $query->where('status', 1);
    }

    public function pluginBindings(): HasMany
    {
        return $this->hasMany(SupplierPluginBinding::class, 'supplier_id');
    }
}
