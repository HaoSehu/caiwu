<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\NormalizesTraceId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 服务转移（用户间过户）记录。
 *
 * @property int $id
 * @property int $service_id
 * @property int $from_user_id
 * @property int $to_user_id
 * @property int $status
 * @property int $initiated_by
 * @property Carbon|null $from_confirmed_at
 * @property Carbon|null $to_confirmed_at
 * @property Carbon $expires_at
 * @property array|null $risk_flags
 * @property string|null $reject_reason
 * @property int|null $operator_id
 * @property string|null $trace_id
 */
class ServiceTransfer extends Model
{
    use NormalizesTraceId;

    protected $fillable = [
        'service_id', 'from_user_id', 'to_user_id', 'status', 'initiated_by',
        'from_confirmed_at', 'to_confirmed_at', 'expires_at', 'risk_flags',
        'reject_reason', 'operator_id', 'trace_id',
    ];

    /** 状态：待对方确认 */
    public const STATUS_PENDING = 0;

    /** 状态：双方已确认待生效 */
    public const STATUS_CONFIRMED = 1;

    /** 状态：已完成 */
    public const STATUS_COMPLETED = 2;

    /** 状态：已取消 */
    public const STATUS_CANCELLED = 3;

    /** 状态：已拒绝 */
    public const STATUS_REJECTED = 4;

    /** 状态：已过期 */
    public const STATUS_EXPIRED = 5;

    /** 进行中的状态集合（互斥判定用） */
    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_CONFIRMED];

    /** 发起方：转出方 */
    public const INITIATED_BY_FROM = 1;

    /** 发起方：接收方 */
    public const INITIATED_BY_TO = 2;

    /** 发起方：管理员 */
    public const INITIATED_BY_ADMIN = 3;

    public const STATUS_LABELS = [
        self::STATUS_PENDING => '待对方确认',
        self::STATUS_CONFIRMED => '双方已确认待生效',
        self::STATUS_COMPLETED => '已完成',
        self::STATUS_CANCELLED => '已取消',
        self::STATUS_REJECTED => '已拒绝',
        self::STATUS_EXPIRED => '已过期',
    ];

    public const INITIATED_BY_LABELS = [
        self::INITIATED_BY_FROM => '转出方',
        self::INITIATED_BY_TO => '接收方',
        self::INITIATED_BY_ADMIN => '管理员',
    ];

    protected function casts(): array
    {
        return [
            'service_id' => 'integer',
            'from_user_id' => 'integer',
            'to_user_id' => 'integer',
            'status' => 'integer',
            'initiated_by' => 'integer',
            'from_confirmed_at' => 'datetime',
            'to_confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
            'risk_flags' => 'array',
            'operator_id' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }

    /**
     * 是否涉及指定用户（转出方或接收方）。
     */
    public function involvesUser(int $userId): bool
    {
        return $this->from_user_id === $userId || $this->to_user_id === $userId;
    }
}
