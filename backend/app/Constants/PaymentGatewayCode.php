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

    // 预留：暂无对应插件与受理链路。保留常量是为了让历史数据查询
    // （第三方网关集合过滤、日志筛选）口径稳定，不要据此以为已支持 Stripe。
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
     * 支付渠道（支付方式）展示名：区分同一插件下的不同渠道，用户端只读这一层。
     * 传入 payment_type 时优先按渠道类型精确命中（如易支付 + wxpay → 微信支付），
     * 未传或未收录时回落到网关级渠道名（如支付宝当面付 → 支付宝）。
     */
    public const CHANNEL_LABELS = [
        self::ALIPAY => '支付宝',
        'wxpay' => '微信支付',
        self::WECHAT => '微信支付',
    ];

    public static function channelLabel(string $gateway, string $paymentType = ''): string
    {
        $gateway = self::normalize($gateway);
        $paymentType = trim($paymentType);

        if ($paymentType !== '') {
            return self::CHANNEL_LABELS[$paymentType] ?? $paymentType;
        }

        return match ($gateway) {
            self::ALIPAY => '支付宝',
            self::WECHAT => '微信支付',
            self::BALANCE => '余额支付',
            self::FREE => '免费',
            self::MANUAL => '手动入账',
            self::YIPAY => '易支付',
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
