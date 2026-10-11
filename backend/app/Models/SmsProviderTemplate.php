<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 短信运营商模板映射（业务码 ↔ 运营商模板 ID + 审核状态）。
 *
 * 写入口唯一：SmsProviderTemplateService（pull upsert / link / unlink）。
 * 读入口：SmsTemplateResolver（发送解析）与管理端同步状态查询。
 */
class SmsProviderTemplate extends Model
{
    /** 本地草稿（未提交运营商），对齐 ZJMF message_template status=0 */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_DELETED = 'deleted';

    protected $fillable = [
        'plugin_key',
        'title',
        'business_code',
        'provider_template_id',
        'provider_sign',
        'content',
        'remark',
        'variables_json',
        'status',
        'reject_reason',
        'raw_json',
        'synced_at',
    ];

    protected $casts = [
        'variables_json' => 'array',
        'raw_json' => 'array',
        'synced_at' => 'datetime',
    ];
}
