<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentCallback extends Model
{
    protected $fillable = [
        'payment_id',
        'callback_type',
        'gateway_trade_no',
        'payload_json',
        'is_verified',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
            'payload_json' => 'array',
            'is_verified' => 'integer',
            'received_at' => 'datetime',
        ];
    }
}
