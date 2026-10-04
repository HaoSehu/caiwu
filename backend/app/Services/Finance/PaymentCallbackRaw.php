<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Payment;
use App\Support\VersionedJson;

/**
 * 支付单 callback_raw 内嵌资金幂等标志的唯一读写口径。
 *
 * mix_payment / balance_amount / balance_restored / balance_restored_amount /
 * credited_to_balance / credited_amount / credit_reason / duplicate_paid
 * 八个键是资金判定依据（混合支付预扣、余额回补、异常款转余额、双重退款拦截），
 * 读写方分散在混合支付、入账引擎、退款与取消链路，任何一处口径漂移都会
 * 直接造成资金重复退付，因此键名常量与读取全部收敛到本类。
 *
 * closed_reason / closed_by / source / trace_id 等纯审计键不参与资金判定，
 * 不在本类收口范围内。
 */
final class PaymentCallbackRaw
{
    public const KEY_MIX_PAYMENT = 'mix_payment';

    public const KEY_BALANCE_AMOUNT = 'balance_amount';

    public const KEY_BALANCE_RESTORED = 'balance_restored';

    public const KEY_BALANCE_RESTORED_AMOUNT = 'balance_restored_amount';

    public const KEY_CREDITED_TO_BALANCE = 'credited_to_balance';

    public const KEY_CREDITED_AMOUNT = 'credited_amount';

    public const KEY_CREDIT_REASON = 'credit_reason';

    public const KEY_DUPLICATE_PAID = 'duplicate_paid';

    /**
     * 读取 callback_raw 原始值；allowAuditMirror 时透传 VersionedJson 审计镜像解码。
     *
     * @return array<string, mixed>
     */
    public static function rawDecode(Payment $payment, bool $allowAuditMirror = false): array
    {
        if ($allowAuditMirror) {
            $raw = $payment->getRawOriginal('callback_raw');
            $decoded = VersionedJson::decodeToArray($raw);
            if ($decoded !== null) {
                return VersionedJson::paymentCallback($decoded, 'payment');
            }
        }

        return (array) ($payment->callback_raw ?? []);
    }

    /**
     * @param  array<string, mixed>|Payment  $rawOrPayment
     */
    public static function isMixPayment(array|Payment $rawOrPayment): bool
    {
        return (bool) data_get(self::rawOf($rawOrPayment), self::KEY_MIX_PAYMENT, false);
    }

    /**
     * @param  array<string, mixed>|Payment  $rawOrPayment
     */
    public static function mixBalanceAmount(array|Payment $rawOrPayment): float
    {
        return round(max((float) data_get(self::rawOf($rawOrPayment), self::KEY_BALANCE_AMOUNT, 0), 0), 2);
    }

    /**
     * @param  array<string, mixed>|Payment  $rawOrPayment
     */
    public static function isBalanceRestored(array|Payment $rawOrPayment): bool
    {
        return (bool) data_get(self::rawOf($rawOrPayment), self::KEY_BALANCE_RESTORED, false);
    }

    /**
     * @param  array<string, mixed>|Payment  $rawOrPayment
     */
    public static function isCreditedToBalance(array|Payment $rawOrPayment): bool
    {
        return (bool) data_get(self::rawOf($rawOrPayment), self::KEY_CREDITED_TO_BALANCE, false);
    }

    /**
     * @param  array<string, mixed>|Payment  $rawOrPayment
     */
    public static function creditedAmount(array|Payment $rawOrPayment): float
    {
        $raw = self::rawOf($rawOrPayment);
        $amount = (float) (data_get($raw, self::KEY_CREDITED_AMOUNT, 0) > 0
            ? data_get($raw, self::KEY_CREDITED_AMOUNT, 0)
            : 0);

        return round($amount, 2);
    }

    /**
     * @param  array<string, mixed>|Payment  $rawOrPayment
     */
    public static function isDuplicatePaid(array|Payment $rawOrPayment): bool
    {
        return (bool) data_get(self::rawOf($rawOrPayment), self::KEY_DUPLICATE_PAID, false);
    }

    /**
     * @param  array<string, mixed>|Payment  $rawOrPayment
     * @return array<string, mixed>
     */
    private static function rawOf(array|Payment $rawOrPayment): array
    {
        return is_array($rawOrPayment) ? $rawOrPayment : (array) ($rawOrPayment->callback_raw ?? []);
    }
}
