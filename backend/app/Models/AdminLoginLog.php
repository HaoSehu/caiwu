<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 管理员登录尝试（成功与失败）审计记录。
 *
 * 与 howstack-bos 的 admin_login_logs 对齐：独立于 activity_logs 存储，
 * 保留昵称/角色快照、失败原因与归纳后的客户端环境。
 */
class AdminLoginLog extends Model
{
    /** 登录成功 */
    public const STATUS_SUCCESS = 1;

    /** 登录失败 */
    public const STATUS_FAILED = 0;

    /** 账号或密码错误 */
    public const REASON_INVALID_CREDENTIALS = 'invalid_credentials';

    /** 账号已停用 */
    public const REASON_ACCOUNT_DISABLED = 'account_disabled';

    /** 双因素验证码错误或挑战凭证失效 */
    public const REASON_MFA_INVALID = 'mfa_invalid';

    protected $fillable = [
        'admin_user_id',
        'username',
        'nickname',
        'role_name',
        'status',
        'failure_reason',
        'client_ip',
        'browser',
        'operating_system',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'admin_user_id' => 'integer',
            'status' => 'integer',
            'logged_at' => 'datetime',
        ];
    }
}
