<?php

namespace App\Constants;

class ServiceStatus
{
    const PENDING = 0; // 开通中

    const ACTIVE = 1; // 运行中

    const SUSPENDED = 2; // 已暂停

    const TERMINATED = 3; // 已删除

    const ABNORMAL = 4; // 异常

    public static array $labels = [
        self::PENDING => '开通中',
        self::ACTIVE => '运行中',
        self::SUSPENDED => '已暂停',
        self::TERMINATED => '已删除',
        self::ABNORMAL => '异常',
    ];

    /**
     * 上游魔方/WHMCS 系 domainstatus → 本地服务状态。
     * 空值保守归开通中；fraud 及其他非空未知值归异常。
     */
    public static function fromUpstreamDomainStatus(mixed $domainstatus): int
    {
        return match (strtolower(trim((string) $domainstatus))) {
            'active' => self::ACTIVE,
            'suspended' => self::SUSPENDED,
            'deleted', 'cancelled', 'terminated' => self::TERMINATED,
            '', 'pending' => self::PENDING,
            default => self::ABNORMAL,
        };
    }
}
