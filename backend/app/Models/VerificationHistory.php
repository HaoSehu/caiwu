<?php

namespace App\Models;

use App\Casts\LegacyEncrypted;
use Illuminate\Database\Eloquent\Model;

class VerificationHistory extends Model
{
    protected $fillable = [
        'user_id',
        'real_name',
        'id_card',
        'verification_status',
        'verification_message',
        'verification_certify_id',
        'verification_biz_code',
        'verification_type',
        // 本次认证所用实名认证插件与驱动的快照（V-01），提交时写入、回调与审核沿用。
        'plugin_id', 'plugin_key', 'plugin_name', 'driver_key',
        'submitted_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'id_card' => LegacyEncrypted::class,
            'plugin_id' => 'integer',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
