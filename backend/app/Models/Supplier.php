<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $hidden = [
        'api_key',
    ];

    protected $fillable = [
        'name',
        'code',
        'interface_type',
        'api_url',
        'api_username',
        'api_key',
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
}
