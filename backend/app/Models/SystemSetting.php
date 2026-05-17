<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $table = 'settings';

    public $timestamps = false;

    protected $fillable = [
        'legacy_setting_id',
        'group_key',
        'item_key',
        'item_value',
        'value_type',
        'is_encrypted',
        'updated_by_admin_id',
    ];
}
