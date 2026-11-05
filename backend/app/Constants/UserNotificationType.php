<?php

namespace App\Constants;

/**
 * 站内信（个性化通知）类型常量
 *
 * 公告类（notice / notice_update）不在此表存储，由 content_articles + notice_reads 聚合。
 */
class UserNotificationType
{
    /** 订购/开通成功 */
    public const ORDER_PAID = 'order_paid';

    /** 服务续费提醒（到期前） */
    public const SERVICE_RENEW_REMINDER = 'service_renew_reminder';

    /** 即将自动续费提醒 */
    public const SERVICE_AUTO_RENEW_UPCOMING = 'service_auto_renew_upcoming';

    /** 服务到期/暂停提醒 */
    public const SERVICE_EXPIRE_REMINDER = 'service_expire_reminder';

    /** 账单待支付提醒 */
    public const INVOICE_PAYMENT_REMINDER = 'invoice_payment_reminder';

    /** 账单逾期提醒 */
    public const INVOICE_OVERDUE_REMINDER = 'invoice_overdue_reminder';

    /** 支付款项转入余额（账单已取消/已支付/金额不符等异常捕获款） */
    public const PAYMENT_CREDITED_TO_BALANCE = 'payment_credited_to_balance';

    /** 工单提交成功（客户创建工单） */
    public const TICKET_OPENED = 'ticket_opened';

    /** 工单收到客服回复 */
    public const TICKET_STAFF_REPLY = 'ticket_staff_reply';

    /** 工单待客户处理（超时未跟进，即将自动关闭） */
    public const TICKET_PENDING_REMINDER = 'ticket_pending_reminder';

    /** 工单已自动关闭 */
    public const TICKET_AUTO_CLOSED = 'ticket_auto_closed';

    /** 工单已重新开启 */
    public const TICKET_REOPENED = 'ticket_reopened';

    // ─── 消息场景重构（2026-11-04）后 createForScene 使用场景编码作为 type ───

    /** 服务即将到期（场景编码 service_due） */
    public const SERVICE_DUE = 'service_due';

    /** 即将自动续费（场景编码 auto_renew_upcoming） */
    public const AUTO_RENEW_UPCOMING = 'auto_renew_upcoming';

    /** 自动续费账单已生成（场景编码 auto_renew_notice） */
    public const AUTO_RENEW_NOTICE = 'auto_renew_notice';

    /** 服务到期暂停（场景编码 service_expired） */
    public const SERVICE_EXPIRED = 'service_expired';

    /** 服务开通成功（场景编码 service_activated） */
    public const SERVICE_ACTIVATED = 'service_activated';

    /** 服务恢复（场景编码 service_resumed） */
    public const SERVICE_RESUMED = 'service_resumed';

    /** 服务暂停（场景编码 service_suspended） */
    public const SERVICE_SUSPENDED = 'service_suspended';

    /** 服务删除（场景编码 service_terminated） */
    public const SERVICE_TERMINATED = 'service_terminated';

    /** 账单退款（场景编码 order_refunded） */
    public const ORDER_REFUNDED = 'order_refunded';

    /** 账号绑定（场景编码 account_bound） */
    public const ACCOUNT_BOUND = 'account_bound';

    /** 实名认证通过（场景编码 real_name_approved） */
    public const REAL_NAME_APPROVED = 'real_name_approved';

    /** 实名认证未通过（场景编码 real_name_rejected） */
    public const REAL_NAME_REJECTED = 'real_name_rejected';

    /**
     * 类型对应的中文标签，用于前端展示分组/图标。
     */
    public const LABELS = [
        self::ORDER_PAID => '订购提醒',
        self::SERVICE_RENEW_REMINDER => '续费提醒',
        self::SERVICE_AUTO_RENEW_UPCOMING => '自动续费提醒',
        self::SERVICE_EXPIRE_REMINDER => '到期提醒',
        self::INVOICE_PAYMENT_REMINDER => '账单提醒',
        self::INVOICE_OVERDUE_REMINDER => '逾期提醒',
        self::PAYMENT_CREDITED_TO_BALANCE => '余额到账',
        self::TICKET_OPENED => '工单提交',
        self::TICKET_STAFF_REPLY => '工单回复',
        self::TICKET_PENDING_REMINDER => '工单提醒',
        self::TICKET_AUTO_CLOSED => '工单自动关闭',
        self::TICKET_REOPENED => '工单重新开启',
        // 场景编码（与 type 同值）
        self::SERVICE_DUE => '续费提醒',
        self::AUTO_RENEW_UPCOMING => '自动续费提醒',
        self::AUTO_RENEW_NOTICE => '自动续费提醒',
        self::SERVICE_EXPIRED => '到期提醒',
        self::SERVICE_ACTIVATED => '开通提醒',
        self::SERVICE_RESUMED => '服务恢复',
        self::SERVICE_SUSPENDED => '服务暂停',
        self::SERVICE_TERMINATED => '服务删除',
        self::ORDER_REFUNDED => '退款提醒',
        self::ACCOUNT_BOUND => '账号绑定',
        self::REAL_NAME_APPROVED => '实名认证',
        self::REAL_NAME_REJECTED => '实名认证',
    ];

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? '系统通知';
    }
}
