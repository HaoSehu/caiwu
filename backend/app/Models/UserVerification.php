<?php

namespace App\Models;

use App\Casts\LegacyEncrypted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVerification extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'verification_type',
        'verification_status',
        'real_name',
        'id_card_encrypted',
        'certify_id',
        'verification_message',
        'last_submitted_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'id_card_encrypted' => LegacyEncrypted::class,
            'verification_status' => 'integer',
            'last_submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
