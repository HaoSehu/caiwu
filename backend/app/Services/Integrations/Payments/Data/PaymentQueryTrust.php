<?php

declare(strict_types=1);

namespace App\Services\Integrations\Payments\Data;

/**
 * 主动查询结果可信度判定：只有网关客户端完成响应验签并显式透传
 * verified=true（顶层或 raw 内）才允许作为入账依据（M-39）。
 *
 * 账单与充值两条轮询链路共用同一口径，避免只修一条造成绕过。
 */
final class PaymentQueryTrust
{
    /**
     * @param  array<string, mixed>  $queryResult
     */
    public static function isVerified(array $queryResult): bool
    {
        if (($queryResult['verified'] ?? null) === true) {
            return true;
        }

        $raw = $queryResult['raw'] ?? null;

        return is_array($raw) && ($raw['verified'] ?? null) === true;
    }
}
