<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceTrackingRecord extends Model
{
    protected $fillable = [
        'service_id',
        'user_id',
        'tracked_at',
        'content',
        'images',
        'operator_id',
        'operator_name',
    ];

    protected function casts(): array
    {
        return [
            'service_id' => 'integer',
            'user_id' => 'integer',
            'tracked_at' => 'datetime',
            'images' => 'array',
            'operator_id' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
