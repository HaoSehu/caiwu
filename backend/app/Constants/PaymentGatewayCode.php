<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * 支付网关编码集中定义，区分业务入账值和第三方插件 key。
 */
final class PaymentGatewayCode
{
    public const ALIPAY = 'alipay';

    public const BALANCE = 'balance';

    public const WECHAT = 'wechat';

    public const MANUAL = 'manual';

    public const FREE = 'free';

    public const ALIPAY_F2F_PLUGIN = 'alipay_f2f';

    public const LABELS = [
        self::ALIPAY => '支付宝支付',
        self::BALANCE => '余额支付',
        self::WECHAT => '微信支付',
        self::MANUAL => '管理员手动',
        self::FREE => '免费开通',
    ];

    public static function label(string $gateway): string
    {
        return self::LABELS[$gateway] ?? $gateway;
    }
}
