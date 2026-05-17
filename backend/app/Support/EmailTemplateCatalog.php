<?php

declare(strict_types=1);

namespace App\Support;

final class EmailTemplateCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            '100001' => [
                'code' => '100001',
                'name' => '邮箱验证码',
                'description' => '发送邮箱验证码时使用。',
                'subject' => '邮箱验证码',
                'content' => <<<'TEXT'
您的邮箱验证码为：{{code}}，{{expire_minutes}}分钟内有效。
如非本人操作，请忽略此邮件。
TEXT,
                'variables' => ['code', 'expire_minutes'],
            ],
            '100002' => [
                'code' => '100002',
                'name' => '登录提醒',
                'description' => '客户登录成功后发送安全提醒。',
                'subject' => '{{site_name}} 登录提醒',
                'content' => <<<'TEXT'
您好，{{display_name}}：

检测到您的账户刚刚完成一次登录，详情如下：
登录邮箱：{{email}}
登录时间：{{login_at}}
登录 IP：{{ip}}
登录设备：{{device}}

如非本人操作，请立即修改密码，并检查账户安全设置。
TEXT,
                'variables' => ['site_name', 'display_name', 'email', 'login_at', 'ip', 'device'],
            ],
            '100003' => [
                'code' => '100003',
                'name' => '服务续费提醒',
                'description' => '服务到期前自动发送续费提醒。',
                'subject' => '【{{site_name}}】服务续费提醒（{{days_left}} 天后到期）',
                'content' => <<<'TEXT'
您好，{{display_name}}：

{{urgency_message}}

服务名称：{{service_name}}
到期时间：{{expires_at}}
计费周期：{{billing_cycle_label}}

请登录控制台及时完成续费，避免服务被暂停。到期后未续费，服务将自动暂停。

如有疑问，请联系 {{site_name}} 客服或提交工单。
TEXT,
                'variables' => ['site_name', 'display_name', 'service_name', 'days_left', 'expires_at', 'billing_cycle_label', 'urgency_message'],
            ],
            '100004' => [
                'code' => '100004',
                'name' => '账单付款提醒',
                'description' => '账单到期前发送付款提醒。',
                'subject' => '【{{site_name}}】账单付款提醒 #{{invoice_no}}',
                'content' => <<<'TEXT'
您好，{{display_name}}：

账单编号：{{invoice_no}}
{{#product_name}}账单内容：{{product_name}}
{{/product_name}}账单金额：¥{{amount}}
应付日期：{{due_date}}

{{notice_message}}

如有疑问，请联系 {{site_name}} 客服或提交工单。
TEXT,
                'variables' => ['site_name', 'display_name', 'invoice_no', 'product_name', 'amount', 'due_date', 'notice_message'],
            ],
            '100005' => [
                'code' => '100005',
                'name' => '账单逾期催款',
                'description' => '账单逾期后自动发送催缴提醒。',
                'subject' => '【{{site_name}}】账单逾期催款 #{{invoice_no}}',
                'content' => <<<'TEXT'
您好，{{display_name}}：

账单编号：{{invoice_no}}
{{#product_name}}账单内容：{{product_name}}
{{/product_name}}账单金额：¥{{amount}}
应付日期：{{due_date}}

{{notice_message}}

如有疑问，请联系 {{site_name}} 客服或提交工单。
TEXT,
                'variables' => ['site_name', 'display_name', 'invoice_no', 'product_name', 'amount', 'due_date', 'notice_message'],
            ],
            '100006' => [
                'code' => '100006',
                'name' => '服务到期暂停通知',
                'description' => '服务因过期被系统暂停时发送通知。',
                'subject' => '【{{site_name}}】服务到期暂停通知',
                'content' => <<<'TEXT'
您好，{{display_name}}：

您的以下服务因到期未续费，已被系统自动暂停：
服务名称：{{service_name}}
到期时间：{{expires_at}}

如何恢复服务：
请登录控制台完成续费，支付成功后系统将在几分钟内自动恢复服务，无需人工干预。

如有疑问，请联系 {{site_name}} 客服或提交工单。
TEXT,
                'variables' => ['site_name', 'display_name', 'service_name', 'expires_at'],
            ],
            '100007' => [
                'code' => '100007',
                'name' => '服务恢复通知',
                'description' => '服务续费成功恢复后发送通知。',
                'subject' => '服务恢复通知',
                'content' => <<<'TEXT'
您好，{{display_name}}：

您的服务 {{service_name}} 已因续费成功恢复为正常状态。
新的到期时间：{{expires_at}}
感谢您的续费。
TEXT,
                'variables' => ['display_name', 'service_name', 'expires_at'],
            ],
            '100008' => [
                'code' => '100008',
                'name' => '账单通知',
                'description' => '管理员主动发送账单提醒或账单确认时使用。',
                'subject' => '【{{site_name}}】{{notice_title}} #{{invoice_no}}',
                'content' => <<<'TEXT'
您好，{{display_name}}：

账单编号：{{invoice_no}}
{{#product_name}}账单内容：{{product_name}}
{{/product_name}}账单金额：¥{{amount}}
账单状态：{{status_label}}
{{#due_at}}到期时间：{{due_at}}
{{/due_at}}{{#paid_at}}支付时间：{{paid_at}}
{{/paid_at}}{{#payment_method}}支付方式：{{payment_method}}
{{/payment_method}}{{#trade_no}}支付流水号：{{trade_no}}
{{/trade_no}}
{{notice_message}}
TEXT,
                'variables' => ['site_name', 'display_name', 'notice_title', 'invoice_no', 'product_name', 'amount', 'status_label', 'due_at', 'paid_at', 'payment_method', 'trade_no', 'notice_message'],
            ],
            '100009' => [
                'code' => '100009',
                'name' => '手动入账通知',
                'description' => '管理员手动设为已支付后发送通知。',
                'subject' => '账单支付确认通知',
                'content' => <<<'TEXT'
您好：

您的账单 {{invoice_no}} 已由管理员手动入账。
支付金额：¥{{paid_amount}}
支付方式：{{payment_method}}
支付时间：{{paid_at}}
{{#trade_no}}支付流水号：{{trade_no}}
{{/trade_no}}{{#remark}}备注：{{remark}}
{{/remark}}
如对本次处理有疑问，请及时联系管理员。
TEXT,
                'variables' => ['invoice_no', 'paid_amount', 'payment_method', 'paid_at', 'trade_no', 'remark'],
            ],
            '100010' => [
                'code' => '100010',
                'name' => '新工单提醒',
                'description' => '客户提交新工单后通知管理员。',
                'subject' => '【{{site_name}}】新工单提醒 #{{ticket_id}}',
                'content' => <<<'TEXT'
您好，{{recipient_name}}：

有客户提交了新的工单，请及时处理。
工单编号：#{{ticket_id}}
工单标题：{{ticket_subject}}
工单分类：{{department}}
优先级：{{priority}}
当前状态：{{status}}
提交用户：{{client_name}}
用户邮箱：{{client_email}}
工单内容：{{message_preview}}

请尽快登录后台工单页面查看并回复。
TEXT,
                'variables' => ['site_name', 'recipient_name', 'ticket_id', 'ticket_subject', 'department', 'priority', 'status', 'client_name', 'client_email', 'message_preview'],
            ],
            '100011' => [
                'code' => '100011',
                'name' => '工单待回复提醒',
                'description' => '客户补充工单回复后通知管理员。',
                'subject' => '【{{site_name}}】工单待回复提醒 #{{ticket_id}}',
                'content' => <<<'TEXT'
您好，{{recipient_name}}：

客户刚刚补充了工单回复，请及时跟进。
工单编号：#{{ticket_id}}
工单标题：{{ticket_subject}}
工单分类：{{department}}
优先级：{{priority}}
当前状态：{{status}}
提交用户：{{client_name}}
用户邮箱：{{client_email}}
最新回复：{{message_preview}}

请尽快登录后台工单页面查看并回复。
TEXT,
                'variables' => ['site_name', 'recipient_name', 'ticket_id', 'ticket_subject', 'department', 'priority', 'status', 'client_name', 'client_email', 'message_preview'],
            ],
            '100012' => [
                'code' => '100012',
                'name' => '工单回复通知',
                'description' => '管理员回复工单后通知用户。',
                'subject' => '【{{site_name}}】工单回复通知 #{{ticket_id}}',
                'content' => <<<'TEXT'
您好，{{display_name}}：

您的工单已有管理员回复。
工单编号：#{{ticket_id}}
工单标题：{{ticket_subject}}
当前状态：{{status}}
回复人员：{{staff_name}}
回复内容：{{message_preview}}
{{#tickets_url}}

查看工单：{{tickets_url}}
{{/tickets_url}}
{{#login_tip}}

{{login_tip}}
{{/login_tip}}
TEXT,
                'variables' => ['site_name', 'display_name', 'ticket_id', 'ticket_subject', 'status', 'staff_name', 'message_preview', 'tickets_url', 'login_tip'],
            ],
            '100013' => [
                'code' => '100013',
                'name' => '新账单提醒',
                'description' => '用户创建新账单后通知管理员。',
                'subject' => '【{{site_name}}】新账单提醒 #{{invoice_no}}',
                'content' => <<<'TEXT'
您好，{{recipient_name}}：

有用户刚刚创建了新的账单，请及时关注。
用户名称：{{user_name}}
用户邮箱：{{user_email}}
账单编号：{{invoice_no}}
账单类型：{{order_type_label}}
配置名称：{{product_name}}
计费周期：{{billing_cycle_label}}
账单金额：¥{{order_amount}}
账单状态：{{order_status_label}}
创建时间：{{created_at}}

请尽快登录后台账单页面查看详情。
TEXT,
                'variables' => ['site_name', 'recipient_name', 'user_name', 'user_email', 'invoice_no', 'order_type_label', 'product_name', 'billing_cycle_label', 'order_amount', 'order_status_label', 'created_at'],
            ],
            '100014' => [
                'code' => '100014',
                'name' => '用户支付完成提醒',
                'description' => '用户账单支付完成后通知管理员。',
                'subject' => '【{{site_name}}】用户支付完成 #{{invoice_no}}',
                'content' => <<<'TEXT'
您好，{{recipient_name}}：

有用户账单已完成支付，请及时跟进后续处理。
用户名称：{{user_name}}
用户邮箱：{{user_email}}
账单编号：{{invoice_no}}
配置名称：{{product_name}}
计费周期：{{billing_cycle_label}}
支付金额：¥{{paid_amount}}
支付方式：{{payment_method}}
{{#trade_no}}支付流水号：{{trade_no}}
{{/trade_no}}支付时间：{{paid_at}}

请登录后台账单页面查看详情。
TEXT,
                'variables' => ['site_name', 'recipient_name', 'user_name', 'user_email', 'invoice_no', 'product_name', 'billing_cycle_label', 'paid_amount', 'payment_method', 'trade_no', 'paid_at'],
            ],
            '100015' => [
                'code' => '100015',
                'name' => '登录失败提醒',
                'description' => '客户登录失败进入风控状态后发送一次安全提醒。',
                'subject' => '{{site_name}} 登录失败提醒',
                'content' => <<<'TEXT'
您好，{{display_name}}：

检测到您的账户发生了一次登录失败尝试，详情如下：
尝试账户：{{account}}
失败时间：{{attempt_at}}
来源 IP：{{ip}}
设备环境：{{device}}

系统已提升当前账户的登录风控级别，后续登录需要先完成行为验证。
如非本人操作，请立即修改密码，并检查账户邮箱、手机号和安全设置。
TEXT,
                'variables' => ['site_name', 'display_name', 'account', 'attempt_at', 'ip', 'device'],
            ],
            '100016' => [
                'code' => '100016',
                'name' => '异地登录提醒',
                'description' => '检测到账户在新 IP 登录时发送提醒。',
                'subject' => '{{site_name}} 异地登录提醒',
                'content' => <<<'TEXT'
您好，{{display_name}}：

检测到您的账户发生了一次新的登录环境访问，详情如下：
登录邮箱：{{email}}
登录时间：{{login_at}}
来源 IP：{{ip}}
上一登录 IP：{{previous_ip}}
登录设备：{{device}}

如果这是您本人的操作，可忽略此邮件。
如非本人操作，请立即修改密码，并检查邮箱、手机号和实名认证信息。
TEXT,
                'variables' => ['site_name', 'display_name', 'email', 'login_at', 'ip', 'previous_ip', 'device'],
            ],
            '100017' => [
                'code' => '100017',
                'name' => '密码变更提醒',
                'description' => '客户密码修改成功后发送安全提醒。',
                'subject' => '{{site_name}} 密码变更提醒',
                'content' => <<<'TEXT'
您好，{{display_name}}：

您的账户密码已完成修改，详情如下：
操作时间：{{changed_at}}
来源 IP：{{ip}}
设备环境：{{device}}

如非本人操作，请立即通过找回密码流程重置密码，并尽快联系客服处理。
TEXT,
                'variables' => ['site_name', 'display_name', 'changed_at', 'ip', 'device'],
            ],
            '100018' => [
                'code' => '100018',
                'name' => '手机号变更提醒',
                'description' => '客户安全手机号修改成功后发送安全提醒。',
                'subject' => '{{site_name}} 手机号变更提醒',
                'content' => <<<'TEXT'
您好，{{display_name}}：

您的账户安全手机号已变更，详情如下：
原手机号：{{old_phone}}
新手机号：{{new_phone}}
操作时间：{{changed_at}}
来源 IP：{{ip}}
设备环境：{{device}}

如非本人操作，请立即检查账户安全并联系客服。
TEXT,
                'variables' => ['site_name', 'display_name', 'old_phone', 'new_phone', 'changed_at', 'ip', 'device'],
            ],
            '100019' => [
                'code' => '100019',
                'name' => '邮箱变更提醒',
                'description' => '客户安全邮箱修改成功后发送安全提醒。',
                'subject' => '{{site_name}} 邮箱变更提醒',
                'content' => <<<'TEXT'
您好，{{display_name}}：

您的账户安全邮箱已变更，详情如下：
原邮箱：{{old_email}}
新邮箱：{{new_email}}
操作时间：{{changed_at}}
来源 IP：{{ip}}
设备环境：{{device}}

如非本人操作，请立即检查账户安全并联系客服。
TEXT,
                'variables' => ['site_name', 'display_name', 'old_email', 'new_email', 'changed_at', 'ip', 'device'],
            ],
        ];
    }

    public static function find(string $code): ?array
    {
        return self::all()[trim($code)] ?? null;
    }

    public static function subjectSettingKey(string $code): string
    {
        return 'email_template_subject_'.trim($code);
    }

    public static function contentSettingKey(string $code): string
    {
        return 'email_template_content_'.trim($code);
    }
}
