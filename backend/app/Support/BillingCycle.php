<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;

/**
 * 计费周期月数与到期推进集中工具。
 *
 * 到期推进统一走 advance()：Carbon 的 addMonth()/addYear() 默认允许溢出，
 * 实测 2026-01-31 addMonth() 得到 2026-03-03、addMonths(3) 得到 2026-05-01，
 * 2024-02-29 addYear() 得到 2025-03-01。用在到期日上，「月付」会直接跳过 2 月，
 * 到期日每期向后漂移，账期与月份对不上。NoOverflow 变体夹到当月最后一天（2026-02-28）。
 *
 * 已知取舍：NoOverflow 不记忆原始锚定日，1 月 31 日续期为 2 月 28 日后，
 * 下一期从 2 月 28 日推进得到 3 月 28 日，锚定日会逐期回退。
 * 相比「跳过整月」，逐期回退是更小且更可解释的偏差。
 */
final class BillingCycle
{
    /**
     * 周期对应的自然月数；一次性/免费周期为 0（不产生续期），未知周期不存在键。
     */
    private const CYCLE_MONTHS = [
        'monthly' => 1,
        'quarterly' => 3,
        'semiannually' => 6,
        'annually' => 12,
        'biennially' => 24,
        'triennially' => 36,
        'one_time' => 0,
        'onetime' => 0,
    ];

    /**
     * 读取周期对应的自然月数：未知周期返回 null，一次性/免费周期返回 0。
     */
    public static function months(?string $cycle): ?int
    {
        $normalized = strtolower(trim((string) $cycle));

        return self::CYCLE_MONTHS[$normalized] ?? null;
    }

    /**
     * 按周期推进到期时间。未知周期与不产生续期的周期（一次性/免费）返回 null，
     * 由调用方决定兜底；推进一律夹月末，不产生溢出。
     */
    public static function advance(Carbon $base, ?string $cycle): ?Carbon
    {
        $months = self::months($cycle);
        if ($months === null || $months <= 0) {
            return null;
        }

        return $base->copy()->addMonthsNoOverflow($months);
    }
}
