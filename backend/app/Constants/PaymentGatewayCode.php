<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * 支付网关编码集中定义，区分业务入账值和第三方插件 key。
 */
final class PaymentGatewayCode
{
    public const ALIPAY = 'alipay';

    public const YIPAY = 'yipay';

    public const BALANCE = 'balance';

    public const WECHAT = 'wechat';

    public const STRIPE = 'stripe';

    public const MANUAL = 'manual';

    public const FREE = 'free';

    public const ALIPAY_F2F_PLUGIN = 'alipay_f2f';

    public const THIRD_PARTY_GATEWAYS = [
        self::ALIPAY,
        self::YIPAY,
        self::WECHAT,
        self::STRIPE,
    ];

    public const LABELS = [
        self::ALIPAY => '支付宝支付',
        self::YIPAY => '易支付',
        self::BALANCE => '余额支付',
        self::WECHAT => '微信支付',
        self::STRIPE => 'Stripe 支付',
        self::MANUAL => '管理员手动',
        self::FREE => '免费开通',
    ];

    public static function label(string $gateway): string
    {
        return self::LABELS[$gateway] ?? $gateway;
    }

    /**
     * 面向用户的网关展示名：在集中 LABELS 之上叠加业务侧历史词条
     * （支付宝 / 手动入账 / 银行转账为历史展示口径），供邮件等通知文案统一使用。
     */
    public static function displayLabel(string $gateway): string
    {
        return match ($gateway) {
            'alipay' => '支付宝',
            self::YIPAY,
            self::BALANCE,
            self::WECHAT => self::label($gateway),
            'manual' => '手动入账',
            'bank_transfer' => '银行转账',
            default => $gateway !== '' ? $gateway : '未知方式',
        };
    }

    public static function normalize(string $gateway): string
    {
        $gateway = trim($gateway);

        return match ($gateway) {
            self::ALIPAY_F2F_PLUGIN, 'ali_pay' => self::ALIPAY,
            'yi_pay' => self::YIPAY,
            default => $gateway,
        };
    }

    public static function thirdPartyGateways(): array
    {
        return self::THIRD_PARTY_GATEWAYS;
    }

    public static function isThirdParty(string $gateway): bool
    {
        return in_array($gateway, self::THIRD_PARTY_GATEWAYS, true);
    }
}
