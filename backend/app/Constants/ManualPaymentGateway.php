<?php

namespace App\Constants;

/**
 * 补录线下收款方式（管理员补录账单/订单时登记系统外收款渠道）。
 * 仅作审计记录：Payment.gateway 固定 manual，该值写入 Payment.callback_raw.payment_gateway，
 * 不参与网关路由与对账口径；与前端 shared/statusConfig.js 的 MANUAL_PAYMENT_METHOD_MAP 保持一致。
 */
class ManualPaymentGateway
{
    public const BANK_TRANSFER = 'bank_transfer';

    public const CASH = 'cash';

    public const ALIPAY = 'alipay';

    public const WECHAT = 'wechat';

    public const OTHER = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::BANK_TRANSFER,
            self::CASH,
            self::ALIPAY,
            self::WECHAT,
            self::OTHER,
        ];
    }

    /**
     * 供 FormRequest in: 规则使用
     */
    public static function rule(): string
    {
        return implode(',', self::values());
    }

    public static function label(string $gateway): string
    {
        return [
            self::BANK_TRANSFER => '银行转账',
            self::CASH => '现金',
            self::ALIPAY => '支付宝',
            self::WECHAT => '微信',
            self::OTHER => '其他',
        ][$gateway] ?? $gateway;
    }
}
