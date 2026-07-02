<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IntegrationPlugin extends Model
{
    public const STATUS_DISABLED = 0;

    public const STATUS_ENABLED = 1;

    protected $fillable = [
        'domain',
        'slug',
        'plugin_key',
        'name',
        'version',
        'provider_class',
        'entry_class',
        'capabilities_json',
        'config_schema_json',
        'status',
        'installed_at',
    ];

    protected $casts = [
        'capabilities_json' => 'array',
        'config_schema_json' => 'array',
        'installed_at' => 'datetime',
    ];

    public function config(): HasOne
    {
        return $this->hasOne(IntegrationPluginConfig::class, 'plugin_id');
    }

    public function isEnabled(): bool
    {
        return (int) $this->status === self::STATUS_ENABLED;
    }
}
