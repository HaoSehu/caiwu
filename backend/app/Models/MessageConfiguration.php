<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 消息场景配置：按 (受众, 场景) 保存整条接收组与渠道策略。
 *
 * 写入口唯一：MessageConfigurationRepository（乐观锁 + 加密存储）。
 * 读入口：MessageConfigurationRepository 与管理端场景接口。
 */
class MessageConfiguration extends Model
{
    public const AUDIENCE_CUSTOMER = 'customer';

    public const AUDIENCE_ADMIN = 'admin';

    protected $fillable = [
        'audience',
        'message_type',
        'configuration_encrypted',
        'revision',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
        ];
    }
}
