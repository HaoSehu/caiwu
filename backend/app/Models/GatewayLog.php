<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatewayLog extends Model
{
    protected $fillable = [
        'gateway',
        'action',
        'out_trade_no',
        'trade_no',
        'invoice_id',
        'request_data',
        'response_data',
        'result_status',
        'error_msg',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'request_data' => 'array',
            'response_data' => 'array',
            'invoice_id' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
