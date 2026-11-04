<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 验证码挑战：只存 HMAC-SHA256 摘要，明文验证码不落库。
 *
 * @property string $challenge_id
 * @property string $purpose
 * @property string $method
 * @property string $target_hash
 * @property string $binding_hash
 * @property string $code_hash
 * @property string $status
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon $created_at
 */
class VerificationChallenge extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_EXPIRED = 'expired';

    public $timestamps = false;

    protected $fillable = [
        'challenge_id',
        'purpose',
        'method',
        'target_hash',
        'binding_hash',
        'code_hash',
        'status',
        'attempts',
        'expires_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
