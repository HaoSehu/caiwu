<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MemberLevel extends Model
{
    protected $fillable = [
        'name',
        'status',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class, 'member_level_id');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
