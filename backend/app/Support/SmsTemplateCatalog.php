<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 短信业务模板编码。
 *
 * 2026-11-04 起与邮件模板统一编号：同一消息场景下两种渠道使用同一编码，
 * 消除历史上「同号不同义」的混淆（例如 100021 邮件=管理员新订单、短信=订单退款）。
 *
 * 维护约定（改这里之前必读 docs/执行计划/进行中/消息发送体系按howstack重构-2026-11-03.md）：
 * 1. 同一场景的邮件与短信必须同号；
 * 2. 新增模板先复用同场景另一渠道的编号，不要新分配；
 * 3. 一个场景多档提醒时，首档对齐邮件号，补充档位进 100049+ 扩展段；
 * 4. 严禁同一编号在两种渠道指向不同消息；
 * 5. 改编号必须同步 notification_templates、message_scene_templates 与 settings 里的
 *    sms_template_provider_template_id_* 键，否则运营商模板 ID 会失联。
 */
final class SmsTemplateCatalog
{
    /** 验证码（与邮件 100001 同号） */
    public const TEMPLATE_VERIFY_CODE = '100001';

    /** 注册成功（与邮件 100002 同号） */
    public const TEMPLATE_REGISTRATION_SUCCESS = '100002';

    /** 登录提醒（与邮件 100003 同号） */
    public const TEMPLATE_LOGIN_ALERT = '100003';

    /** 账号安全变更（与邮件 100004 同号） */
    public const TEMPLATE_ACCOUNT_BINDING_ALERT = '100004';

    /** 服务开通成功（与邮件 100005 同号） */
    public const TEMPLATE_SERVICE_ACTIVATED = '100005';

    /** 服务暂停（与邮件 100006 同号） */
    public const TEMPLATE_SERVICE_SUSPENDED = '100006';

    /** 服务恢复（与邮件 100007 同号） */
    public const TEMPLATE_SERVICE_RESTORED = '100007';

    /** 服务删除（与邮件 100008 同号） */
    public const TEMPLATE_SERVICE_TERMINATED = '100008';

    /** 订单创建（与邮件 100010 同号） */
    public const TEMPLATE_CLIENT_ORDER_CREATED = '100010';

    /** 自动续费预告（与邮件 100014 同号） */
    public const TEMPLATE_AUTO_RENEW_UPCOMING = '100014';

    /** 自动续费通知（与邮件 100015 同号） */
    public const TEMPLATE_AUTO_RENEW_NOTICE = '100015';

    /** 付款成功（与邮件 100016 同号） */
    public const TEMPLATE_MANUAL_PAYMENT_CONFIRM = '100016';

    /** 订单退款（与邮件 100017 同号） */
    public const TEMPLATE_ORDER_REFUND = '100017';

    /** 续费/服务到期提醒（与邮件 100018 同号） */
    public const TEMPLATE_SERVICE_RENEW_REMINDER = '100018';

    /** 管理员新订单（与邮件 100021 同号） */
    public const TEMPLATE_ADMIN_ORDER_CREATED = '100021';

    /** 管理员订单支付（与邮件 100022 同号） */
    public const TEMPLATE_ADMIN_ORDER_PAID = '100022';

    /** 工单开通（与邮件 100023 同号） */
    public const TEMPLATE_TICKET_CREATED = '100023';

    /** 工单回复（与邮件 100024 同号） */
    public const TEMPLATE_TICKET_CLIENT_REPLY = '100024';

    public const TEMPLATE_TICKET_STAFF_REPLY = '100024';

    /** 工单自动关闭（与邮件 100025 同号） */
    public const TEMPLATE_TICKET_AUTO_CLOSED = '100025';

    /** 管理员新工单（与邮件 100026 同号） */
    public const TEMPLATE_ADMIN_TICKET_CREATED = '100026';

    /** 管理员工单回复（与邮件 100027 同号） */
    public const TEMPLATE_ADMIN_TICKET_REPLIED = '100027';

    /** 管理员登录提示（与邮件 100028 同号） */
    public const TEMPLATE_ADMIN_LOGIN_ALERT = '100028';

    /** 解除停用失败（与邮件 100029 同号） */
    public const TEMPLATE_SERVICE_UNSUSPEND_FAILED = '100029';

    /** 实例归属变更（与邮件 100030 同号） */
    public const TEMPLATE_SERVICE_TRANSFERRED = '100030';

    /** 实名认证通过（与邮件 100044 同号） */
    public const TEMPLATE_REALNAME_APPROVED = '100044';

    /** 服务已到期（与邮件 100046 同号） */
    public const TEMPLATE_SERVICE_EXPIRED = '100046';

    /** 账号绑定（与邮件 100048 同号） */
    public const TEMPLATE_ACCOUNT_BOUND = '100048';

    // ── 一对多场景的补充档位（扩展号段） ──
    /** 未实名暂停（服务暂停场景的第二档） */
    public const TEMPLATE_SERVICE_UNCERTIFIED_SUSPENDED = '100049';

    /** 订单支付第二档 */
    public const TEMPLATE_ORDER_PAID_SECOND = '100050';

    /** 自动续费预告第二档 */
    public const TEMPLATE_AUTO_RENEW_UPCOMING_SECOND = '100051';

    /** 工单开通第二档 */
    public const TEMPLATE_TICKET_OPENED_SECOND = '100062';

    public static function contentSettingKey(string $code): string
    {
        return 'sms_template_content_'.trim($code);
    }

    public static function providerTemplateIdSettingKey(string $code): string
    {
        return 'sms_template_provider_template_id_'.trim($code);
    }

    public static function enabledSettingKey(string $code): string
    {
        return 'sms_template_enabled_'.trim($code);
    }
}
