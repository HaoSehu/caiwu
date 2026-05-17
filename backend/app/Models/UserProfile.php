<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'nickname',
        'company',
        'qq',
        'admin_note',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
