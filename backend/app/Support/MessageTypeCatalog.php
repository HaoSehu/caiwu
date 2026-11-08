<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\BusinessException;

/**
 * 固定消息场景目录（对齐 howstack-bos 的 MessageTypeCatalog）。
 *
 * 场景由代码声明而不是后台自由新增：每个场景都必须有真实发送方，
 * 否则管理端会出现永不发送的空配置项。这里只收录 caiwu 现有模板能映射到的场景，
 * 未映射的模板进入 __unmapped 保留可见（见模板映射迁移）。
 *
 * 受众分两类：customer 面向客户，admin 面向内部人员。同一场景可同时有两种受众，
 * 各自独立维护接收组与渠道策略。
 *
 * 新增场景时的硬要求（约定全文见 docs/执行计划/进行中/消息发送体系按howstack重构-2026-11-03.md）：
 * 1. 场景在 customer/admin 两侧都要有对应模板，邮件与短信同编号；
 * 2. 声明哪些渠道，就要有对应渠道的模板载体，否则管理端会出现永远为空的列；
 * 3. 变量必须登记在 VARIABLES 白名单里，模板不允许出现未声明的占位符；
 * 4. 同时更新 SmsTemplateCatalog 常量、notification_templates 与 message_scene_templates。
 */
final class MessageTypeCatalog
{
    /** 各受众允许的渠道白名单；robot 的钉钉/企微/飞书位保留但不实现投递 */
    public const CHANNELS = [
        'customer' => ['email', 'sms', 'message', 'wechat'],
        'admin' => ['email', 'message', 'dingtalk', 'wecom', 'feishu'],
    ];

    /** 场景可用变量；未登记的自定义变量不允许出现在模板里 */
    private const VARIABLES = [
        'site_name' => '站点名称',
        'customer_name' => '客户名称',
        'code' => '验证码',
        'expires_in' => '有效分钟数',
        'display_name' => '显示名称',
        'username' => '用户名',
        'email' => '邮箱',
        'account' => '登录账号',
        'login_at' => '登录时间',
        'attempt_at' => '尝试时间',
        'ip' => 'IP 地址',
        'previous_ip' => '上次 IP',
        'device' => '设备',
        'changed_at' => '变更时间',
        'bind_type' => '绑定类型',
        'bind_account' => '绑定账号',
        'old_phone' => '原手机号',
        'new_phone' => '新手机号',
        'old_email' => '原邮箱',
        'new_email' => '新邮箱',
        'registered_at' => '注册时间',
        'product_name' => '产品名称',
        'service_name' => '服务名称',
        'hostname' => '主机名',
        'product_main_ip' => '主 IP',
        'product_username' => '产品用户名',
        'operating_system' => '操作系统',
        'activated_at' => '开通时间',
        'expires_at' => '到期时间',
        'billing_cycle_label' => '付款周期',
        'reason' => '原因',
        'suspended_at' => '停用时间',
        'resumed_at' => '恢复时间',
        'terminated_at' => '删除时间',
        'completed_at' => '完成时间',
        'notice_message' => '说明',
        'order_no' => '订单号',
        'invoice_no' => '账单号',
        'amount' => '金额（元）',
        'order_amount' => '订单金额',
        'paid_amount' => '支付金额',
        'refund_amount' => '退款金额',
        'refund_method_label' => '退款方式',
        'refund_reason' => '退款说明',
        'refunded_at' => '退款时间',
        'due_date' => '到期日期',
        'due_at' => '到期时间',
        'payment_method' => '支付方式',
        'trade_no' => '交易号',
        'paid_at' => '支付时间',
        'renewed_at' => '续费时间',
        'auto_renew_date' => '自动续费日期',
        'days_left' => '剩余天数',
        'urgency_message' => '催办提示',
        'user_name' => '客户名称',
        'user_email' => '客户邮箱',
        'order_type_label' => '订单类型',
        'order_status_label' => '订单状态',
        'created_at' => '创建时间',
        'ticket_id' => '工单编号',
        'ticket_subject' => '工单标题',
        'ticket_created_at' => '工单创建时间',
        'ticket_closed_at' => '工单关闭时间',
        'department' => '部门',
        'priority' => '优先级',
        'status' => '状态',
        'staff_name' => '处理人',
        'message_preview' => '消息摘要',
        'tickets_url' => '工单链接',
        'login_tip' => '登录提示',
        'client_name' => '客户名称',
        'client_email' => '客户邮箱',
        'recipient_name' => '收件人名称',
        'close_reason' => '关闭原因',
        'failure_reason' => '失败原因',
        'failed_at' => '失败时间',
        'direction_label' => '变更方向',
        'counterparty_email' => '对方账号',
        'transferred_at' => '转移时间',
        'remark' => '备注',
        'status_label' => '状态文案',
        'notice_title' => '通知标题',
        'auto_renew' => '是否自动续费',
        'server_status' => '实例状态',
        'hours_left' => '剩余小时数',
    ];

    /**
     * 各场景默认兜底变量：模板实际引用了它、但调用方不一定显式传入时，
     * 从收件人上下文自动填充（display_name / recipient_name 取收件人名称）。
     *
     * @var list<string>
     */
    public const RECIPIENT_NAME_ALIASES = ['display_name', 'recipient_name'];

    /**
     * 客户侧场景：键为场景编码，值为 [名称, 描述, 渠道, 变量]。
     *
     * @return array<string, array{code: string, name: string, description: string, channels: list<string>, variables: list<string>}>
     */
    public static function customer(): array
    {
        return self::build([
            'register_verification' => ['注册验证码', '客户注册时验证手机号或邮箱', ['sms', 'email'], ['code', 'expires_in']],
            'login_verification' => ['登录验证码', '客户登录时使用手机号验证码验证身份', ['email', 'sms'], ['code', 'expires_in']],
            'password_reset' => ['找回密码', '客户找回密码时验证身份', ['sms', 'email'], ['code', 'expires_in']],
            'security_changed' => ['账号安全变更', '密码、邮箱或手机号等安全资料变更后通知客户', ['email', 'sms', 'message'], ['changed_at', 'bind_type', 'bind_account', 'old_phone', 'new_phone', 'old_email', 'new_email', 'ip', 'device']],
            'login_alert' => ['登录提醒', '客户登录成功后发送安全提醒', ['email', 'sms', 'message'], ['display_name', 'email', 'account', 'login_at', 'attempt_at', 'ip', 'previous_ip', 'device']],
            'registration_success' => ['注册成功', '客户注册成功后发送欢迎通知', ['email', 'sms', 'message'], ['display_name', 'email', 'registered_at']],
            'service_activated' => ['服务开通成功', '服务交付完成后通知客户，不包含实例密码', ['email', 'sms', 'message'], ['service_name', 'product_name', 'hostname', 'product_main_ip', 'product_username', 'operating_system', 'activated_at', 'expires_at', 'billing_cycle_label']],
            'service_reinstalled' => ['系统重装成功', '产品重装系统成功后通知客户', ['message'], ['service_name', 'product_name', 'operating_system', 'product_main_ip', 'product_username', 'completed_at']],
            'service_suspended' => ['服务暂停', '服务暂停后通知客户处理', ['email', 'sms', 'message'], ['service_name', 'product_name', 'reason', 'expires_at', 'suspended_at']],
            'service_resumed' => ['服务恢复', '服务续费成功恢复后通知客户', ['email', 'sms', 'message'], ['service_name', 'product_name', 'resumed_at', 'expires_at']],
            'service_suspend_failed' => ['解除停用失败', '产品解除停用失败后通知客户', ['email', 'sms', 'message'], ['service_name', 'product_name', 'failure_reason', 'failed_at']],
            'service_terminated' => ['服务删除', '产品或服务删除后通知客户', ['email', 'message'], ['service_name', 'product_name', 'terminated_at', 'notice_message']],
            'service_transferred' => ['实例归属变更', '管理员转移实例归属后通知原持有者与接收方', ['email', 'sms', 'message'], ['service_name', 'direction_label', 'counterparty_email', 'transferred_at', 'remark', 'notice_message']],
            'order_created' => ['订单创建', '订单创建后提醒客户确认和付款', ['email', 'sms', 'message'], ['order_no', 'invoice_no', 'product_name', 'billing_cycle_label', 'amount', 'order_amount', 'due_date']],
            'invoice_payment_reminder' => ['账单支付提醒', '账单待支付或临近逾期时提醒客户', ['email', 'sms', 'message'], ['invoice_no', 'order_no', 'product_name', 'amount', 'due_date', 'notice_message']],
            'order_paid' => ['订单支付成功', '订单收款确认后通知客户', ['email', 'sms', 'message'], ['invoice_no', 'order_no', 'product_name', 'paid_amount', 'amount', 'payment_method', 'trade_no', 'paid_at', 'notice_message', 'remark']],
            'order_refunded' => ['账单退款', '账单或订单退款后通知客户', ['email', 'message'], ['invoice_no', 'order_no', 'product_name', 'refund_amount', 'refund_method_label', 'refunded_at', 'refund_reason']],
            'auto_renew_upcoming' => ['自动续费预告', '自动续费执行前发送提醒', ['email', 'sms', 'message'], ['service_name', 'product_name', 'expires_at', 'billing_cycle_label', 'auto_renew_date', 'urgency_message']],
            'auto_renew_notice' => ['自动续费通知', '自动续费订单生成或扣款时发送通知', ['email', 'message'], ['service_name', 'product_name', 'order_no', 'invoice_no', 'amount', 'order_amount', 'renewed_at', 'notice_message']],
            'service_due' => ['服务即将到期', '服务到期前提醒客户续费', ['email', 'sms', 'message'], ['service_name', 'product_name', 'days_left', 'expires_at', 'billing_cycle_label', 'urgency_message']],
            'service_expired' => ['服务已到期', '服务到期后通知客户，区别于到期前的续费提醒', ['email', 'sms', 'message'], ['service_name', 'product_name', 'expires_at']],
            'credit_invoice_created' => ['信用额账单已生成', '信用额账单生成后发送通知', ['message'], ['invoice_no', 'product_name', 'amount', 'due_at', 'due_date', 'notice_message']],
            'ticket_opened' => ['工单开通', '客户工单创建成功后发送通知', ['email', 'sms', 'message'], ['ticket_id', 'ticket_subject', 'department', 'priority', 'ticket_created_at', 'message_preview', 'tickets_url', 'login_tip']],
            'ticket_replied' => ['工单回复', '工作人员回复工单后通知客户', ['email', 'sms', 'message'], ['ticket_id', 'ticket_subject', 'status', 'staff_name', 'message_preview', 'login_tip']],
            'ticket_auto_closed' => ['工单自动关闭', '工单自动关闭后发送通知', ['email', 'sms', 'message'], ['ticket_id', 'ticket_subject', 'ticket_closed_at', 'close_reason']],
            'ticket_pending_reminder' => ['工单待处理提醒', '客服已回复、超时未跟进将自动关闭时提醒客户', ['email', 'sms', 'message'], ['ticket_subject', 'hours_left', 'display_name']],
            'ticket_reopened' => ['工单重新开启', '工单被重新开启后通知客户继续跟进', ['message'], ['ticket_subject', 'display_name']],
            'account_bound' => ['账号绑定', '客户账号绑定成功后发送提醒', ['email', 'sms', 'message'], []],
            'real_name_approved' => ['实名认证通过', '实名认证审核通过后通知客户', ['email', 'sms', 'message'], ['username', 'display_name']],
            'real_name_rejected' => ['实名认证未通过', '实名认证审核未通过后通知客户补充资料', ['message'], ['reason']],
        ]);
    }

    /**
     * 管理员侧场景。
     *
     * @return array<string, array{code: string, name: string, description: string, channels: list<string>, variables: list<string>}>
     */
    public static function admin(): array
    {
        return self::build([
            'admin_order_created' => ['新订单', '客户创建订单后通知管理员', ['email', 'sms'], ['user_name', 'user_email', 'order_no', 'invoice_no', 'order_type_label', 'product_name', 'billing_cycle_label', 'order_amount', 'order_status_label', 'created_at']],
            'admin_order_paid' => ['订单已付款', '客户订单支付完成后通知管理员', ['email', 'sms'], ['user_name', 'user_email', 'order_no', 'invoice_no', 'product_name', 'billing_cycle_label', 'paid_amount', 'payment_method', 'trade_no', 'paid_at']],
            'admin_ticket_created' => ['新工单', '客户提交新工单后通知管理员', ['email', 'sms'], ['ticket_id', 'ticket_subject', 'department', 'priority', 'status', 'client_name', 'client_email', 'message_preview']],
            'admin_ticket_replied' => ['客户回复工单', '客户补充工单回复后通知管理员', ['email', 'sms'], ['ticket_id', 'ticket_subject', 'department', 'priority', 'status', 'client_name', 'client_email', 'message_preview']],
            'admin_login_alert' => ['管理员登录提示', '管理员登录后台后发送安全提醒', ['email'], ['recipient_name', 'account', 'login_at', 'ip', 'device']],
        ]);
    }

    /** @return array<string, array<string, mixed>> 全部场景（客户在前，按编码排序稳定） */
    public static function all(): array
    {
        return self::customer() + self::admin();
    }

    /** @return array<string, mixed> 单个场景；不存在时抛业务异常 */
    public static function get(string $audience, string $code): array
    {
        $scenes = $audience === 'admin' ? self::admin() : self::customer();

        return $scenes[$code] ?? throw new BusinessException('消息场景不存在', 40400, 404);
    }

    /** @return list<string> 场景声明的变量键 */
    public static function variableKeys(string $audience, string $code): array
    {
        return self::get($audience, $code)['variables'];
    }

    /** @return array<string, string> 变量键 => 中文标签 */
    public static function variableLabels(): array
    {
        return self::VARIABLES;
    }

    /**
     * @param  array<string, array{0: string, 1: string, 2: list<string>, 3: list<string>}>  $definitions
     * @return array<string, array{code: string, name: string, description: string, channels: list<string>, variables: list<string>}>
     */
    private static function build(array $definitions): array
    {
        $result = [];

        foreach ($definitions as $code => [$name, $description, $channels, $variables]) {
            // 收件人名称是通用变量：历史邮件模板大量使用 {{display_name}}（管理员侧为
            // {{recipient_name}}），若未登记会被渲染成空白。统一并入每个场景，
            // 由发送侧在调用方未显式传值时用收件人名称兜底。
            $keys = array_values(array_unique(array_merge(
                ['site_name', 'customer_name'],
                self::RECIPIENT_NAME_ALIASES,
                $variables
            )));

            $result[$code] = [
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'channels' => array_values(array_intersect($channels, self::CHANNELS['customer'] + self::CHANNELS['admin'])),
                'variables' => array_values(array_filter(
                    $keys,
                    static fn (string $key): bool => array_key_exists($key, self::VARIABLES)
                )),
            ];
        }

        return $result;
    }
}
