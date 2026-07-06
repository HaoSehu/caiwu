<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        foreach ($this->templates() as $index => $template) {
            $this->upsertTemplate($template, $index + 1);
        }

        $smsCodes = collect($this->templates())
            ->where('channel', 'sms')
            ->pluck('code')
            ->all();

        DB::table('notification_templates')
            ->where('channel', 'sms')
            ->where('is_custom', false)
            ->whereNotIn('code', $smsCodes)
            ->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        $codesByChannel = collect($this->templates())
            ->groupBy('channel')
            ->map(fn ($items) => $items->pluck('code')->all());

        foreach ($codesByChannel as $channel => $codes) {
            DB::table('notification_templates')
                ->where('channel', $channel)
                ->where('is_custom', false)
                ->whereIn('code', $codes)
                ->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $template
     */
    private function upsertTemplate(array $template, int $sortOrder): void
    {
        $now = now();
        $existing = DB::table('notification_templates')
            ->where('channel', $template['channel'])
            ->where('code', $template['code'])
            ->first();

        $metadata = [
            'name' => $template['name'],
            'description' => $template['description'],
            'audience' => $template['audience'],
            'variables_json' => json_encode($template['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'provider_variables_json' => json_encode($template['provider_variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'sort_order' => $sortOrder,
            'updated_at' => $now,
        ];

        $editable = [
            'subject' => $template['subject'],
            'content' => $template['content'],
            'provider_template_id' => $template['provider_template_id'] ?? null,
        ];

        if ($existing === null) {
            DB::table('notification_templates')->insert(array_merge($metadata, $editable, [
                'channel' => $template['channel'],
                'code' => $template['code'],
                'is_enabled' => true,
                'is_custom' => false,
                'created_at' => $now,
            ]));

            return;
        }

        DB::table('notification_templates')
            ->where('id', $existing->id)
            ->update(((bool) ($existing->is_custom ?? false)) ? $metadata : array_merge($metadata, $editable));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            $this->email('100001', '邮箱验证码', '发送邮箱验证码时使用。', '邮箱验证码', '您的邮箱验证码为：{{code}}，{{expire_minutes}}分钟内有效。如非本人操作，请忽略此邮件。', ['code', 'expire_minutes']),
            $this->email('100002', '登录提醒', '客户登录成功后发送安全提醒。', '{{site_name}} 登录提醒', '账号{{email}}于{{login_at}}登录，IP {{ip}}，设备{{device}}。如非本人操作请立即修改密码。', ['site_name', 'display_name', 'email', 'login_at', 'ip', 'device']),
            $this->email('100003', '服务续费提醒', '服务到期前自动发送续费提醒。', '【{{site_name}}】服务续费提醒', '{{service_name}}将在{{days_left}}天后到期（{{expires_at}}），周期{{billing_cycle_label}}。{{urgency_message}}', ['site_name', 'display_name', 'service_name', 'days_left', 'expires_at', 'billing_cycle_label', 'urgency_message']),
            $this->email('100004', '账单付款提醒', '账单到期前发送付款提醒。', '【{{site_name}}】账单付款提醒 #{{invoice_no}}', '账单{{invoice_no}}待支付，金额¥{{amount}}，应付日期{{due_date}}。{{notice_message}}', ['site_name', 'display_name', 'invoice_no', 'product_name', 'amount', 'due_date', 'notice_message']),
            $this->email('100005', '账单逾期催款', '账单逾期后自动发送催缴提醒。', '【{{site_name}}】账单逾期催款 #{{invoice_no}}', '账单{{invoice_no}}已逾期，金额¥{{amount}}，应付日期{{due_date}}。{{notice_message}}', ['site_name', 'display_name', 'invoice_no', 'product_name', 'amount', 'due_date', 'notice_message']),
            $this->email('100006', '服务到期暂停通知', '服务因过期被系统暂停时发送通知。', '【{{site_name}}】服务到期暂停通知', '您的服务{{service_name}}因到期未续费已暂停，到期时间{{expires_at}}，请续费恢复。', ['site_name', 'display_name', 'service_name', 'expires_at']),
            $this->email('100007', '服务恢复通知', '服务续费成功恢复后发送通知。', '服务恢复通知', '您的服务{{service_name}}已恢复，新的到期时间{{expires_at}}。', ['display_name', 'service_name', 'expires_at']),
            $this->email('100008', '账单通知', '管理员主动发送账单提醒或账单确认时使用。', '【{{site_name}}】{{notice_title}} #{{invoice_no}}', '账单{{invoice_no}}，金额¥{{amount}}，状态{{status_label}}。{{notice_message}}', ['site_name', 'display_name', 'notice_title', 'invoice_no', 'product_name', 'amount', 'status_label', 'due_at', 'paid_at', 'payment_method', 'trade_no', 'notice_message']),
            $this->email('100009', '手动入账通知', '管理员手动设为已支付后发送通知。', '账单支付确认通知', '账单{{invoice_no}}已人工确认入账，金额¥{{paid_amount}}，方式{{payment_method}}，时间{{paid_at}}。', ['invoice_no', 'paid_amount', 'payment_method', 'paid_at', 'trade_no', 'remark']),
            $this->email('100010', '新工单提醒', '客户提交新工单后通知管理员。', '【{{site_name}}】新工单提醒 #{{ticket_id}}', '新工单#{{ticket_id}}：{{ticket_subject}}，用户{{client_name}}，优先级{{priority}}，请及时处理。', ['site_name', 'recipient_name', 'ticket_id', 'ticket_subject', 'department', 'priority', 'status', 'client_name', 'client_email', 'message_preview'], 'admin'),
            $this->email('100011', '工单待回复提醒', '客户补充工单回复后通知管理员。', '【{{site_name}}】工单待回复提醒 #{{ticket_id}}', '工单#{{ticket_id}}有客户新回复：{{ticket_subject}}，用户{{client_name}}，请及时跟进。', ['site_name', 'recipient_name', 'ticket_id', 'ticket_subject', 'department', 'priority', 'status', 'client_name', 'client_email', 'message_preview'], 'admin'),
            $this->email('100012', '工单回复通知', '管理员回复工单后通知用户。', '【{{site_name}}】工单回复通知 #{{ticket_id}}', '您的工单#{{ticket_id}}已有回复，标题{{ticket_subject}}，状态{{status}}。请登录控制台查看。', ['site_name', 'display_name', 'ticket_id', 'ticket_subject', 'status', 'staff_name', 'message_preview', 'tickets_url', 'login_tip']),
            $this->email('100013', '新账单提醒', '用户创建新账单后通知管理员。', '【{{site_name}}】新账单提醒 #{{invoice_no}}', '新账单{{invoice_no}}，用户{{user_name}}，商品{{product_name}}，金额¥{{order_amount}}，请关注。', ['site_name', 'recipient_name', 'user_name', 'user_email', 'invoice_no', 'order_type_label', 'product_name', 'billing_cycle_label', 'order_amount', 'order_status_label', 'created_at'], 'admin'),
            $this->email('100014', '用户支付完成提醒', '用户账单支付完成后通知管理员。', '【{{site_name}}】用户支付完成 #{{invoice_no}}', '账单{{invoice_no}}已支付，用户{{user_name}}，金额¥{{paid_amount}}，方式{{payment_method}}，请跟进。', ['site_name', 'recipient_name', 'user_name', 'user_email', 'invoice_no', 'product_name', 'billing_cycle_label', 'paid_amount', 'payment_method', 'trade_no', 'paid_at'], 'admin'),
            $this->email('100015', '登录失败提醒', '客户登录失败进入风控状态后发送一次安全提醒。', '{{site_name}} 登录失败提醒', '账号{{account}}于{{attempt_at}}登录失败，IP {{ip}}，设备{{device}}。如非本人操作请修改密码。', ['site_name', 'display_name', 'account', 'attempt_at', 'ip', 'device']),
            $this->email('100016', '异地登录提醒', '检测到账户在新 IP 登录时发送提醒。', '{{site_name}} 异地登录提醒', '账号于{{login_at}}在新 IP {{ip}}登录，上次 IP {{previous_ip}}。如非本人操作请修改密码。', ['site_name', 'display_name', 'email', 'login_at', 'ip', 'previous_ip', 'device']),
            $this->email('100017', '密码变更提醒', '客户密码修改成功后发送安全提醒。', '{{site_name}} 密码变更提醒', '您的账号密码已于{{changed_at}}修改，IP {{ip}}，设备{{device}}。如非本人操作请立即找回密码。', ['site_name', 'display_name', 'changed_at', 'ip', 'device']),
            $this->email('100018', '手机号变更提醒', '客户安全手机号修改成功后发送安全提醒。', '{{site_name}} 手机号变更提醒', '安全手机号已从{{old_phone}}变更为{{new_phone}}，时间{{changed_at}}。如非本人操作请联系客服。', ['site_name', 'display_name', 'old_phone', 'new_phone', 'changed_at', 'ip', 'device']),
            $this->email('100019', '邮箱变更提醒', '客户安全邮箱修改成功后发送安全提醒。', '{{site_name}} 邮箱变更提醒', '安全邮箱已从{{old_email}}变更为{{new_email}}，时间{{changed_at}}。如非本人操作请联系客服。', ['site_name', 'display_name', 'old_email', 'new_email', 'changed_at', 'ip', 'device']),
            $this->email('100020', '即将自动续费提醒', '自动续费执行前一天发送提醒邮件。', '【{{site_name}}】即将自动续费提醒', '服务{{service_name}}将于{{auto_renew_date}}自动续费，到期时间{{expires_at}}，周期{{billing_cycle_label}}。', ['site_name', 'display_name', 'service_name', 'expires_at', 'billing_cycle_label', 'auto_renew_date', 'urgency_message']),

            $this->sms('100009', '账单支付', '您好，您已成功支付账单号{invoiceid}，账单金额{total}，谢谢支持！', ['invoiceid', 'total']),
            $this->sms('100005', '账单支付逾期', '您有一笔账单已过期，账单号{invoiceid}，金额{total}，请及时关注！', ['invoiceid', 'total']),
            $this->sms('100010', '提交工单', '您好，我们已经收到您提交的工单：{subject}。团队将火速处理您的问题，请耐心等待！', ['subject']),
            $this->sms('100012', '工单回复', '您提交的工单{subject}有新的回复，请注意查收！', ['subject']),
            $this->sms('100006', '产品暂停', '您好，您购买的产品{product_name}由于{description}的缘故，现已被暂停所有功能，如需恢复使用，请尽快处理！', ['product_name', 'description']),
            $this->sms('100004', '未支付账单', '您好，您已成功支付账单号{invoiceid}，账单金额{total}没有支付。', ['invoiceid', 'total']),
            $this->sms('100001', '发送验证码', '您的验证码{code}，该验证码5分钟内有效,请勿泄漏于他人。', ['code']),
            $this->sms('100002', '登录短信提醒', '您好，您的账号在{time}时间登录。如您未曾尝试登录，请立即更改登录密码，以防账号被盗。', ['time']),
            $this->sms('100021', '订单退款', '您好，您的订单{order_id}，金额{order_total_fee}已退款，请及时关注！', ['order_id', 'order_total_fee']),
            $this->sms('100014', '订单支付提醒(客户)', '您的订单（编号{order_id}）已经完成付款，付款金额为：{order_total_fee}。', ['order_id', 'order_total_fee']),
            $this->sms('100003', '账单未付款提醒', '您购买的产品{product_name}（主机名{hostname}）将于{product_end_time}到期。为了保证届时可以正常使用，请在产品到期之前先行续费。', ['product_name', 'hostname', 'product_end_time']),
            $this->sms('100020', '自动生成续费账单提醒', '您购买的产品{product_name}（主机名{hostname}）将于{product_end_time}到期。为了保证届时可以正常使用，请在产品到期之前先行续费。', ['product_name', 'hostname', 'product_end_time']),
            $this->sms('100022', '第3次逾期提醒', '您在{product_first_time}订购的{product_name}产品（主机名：{hostname}）支付尚未完成，暂时无法开通。为了避免订单过期，请您及时付款！', ['product_first_time', 'product_name', 'hostname']),
            $this->sms('100023', '第2次逾期提醒', '您在{product_first_time}订购的{product_name}产品（主机名：{hostname}）支付尚未完成，暂时无法开通。为了避免订单过期，请您及时付款！', ['product_first_time', 'product_name', 'hostname']),
            $this->sms('100024', '第1次逾期提醒', '您在{product_first_time}订购的{product_name}产品（主机名：{hostname}）支付尚未完成，暂时无法开通。为了避免订单过期，请您及时付款！', ['product_first_time', 'product_name', 'hostname']),
            $this->sms('100013', '下单提醒(客户)', '您已成功下单，请及时付款，以免订单失效。以下为账单信息产品名称：{product_name}产品单价：{product_price}付款周期：{product_binlly_cycle}订单创建时间：{order_create_time}。', ['product_name', 'product_price', 'product_binlly_cycle', 'order_create_time']),
            $this->sms('100025', '产品开通提醒(用户)', '您好，您购买的产品 {product_name} 现已开通，购买时间：{product_first_time}，到期时间：{product_end_time}，付款周期：{product_binlly_cycle}，感谢使用！', ['product_name', 'product_first_time', 'product_end_time', 'product_binlly_cycle']),
            $this->sms('100026', '未续期产品删除提醒(用户)', '您购买的产品{product_name}（主机名：{hostname}）由于未能在指定时间内续费，已于{product_terminate_time}自动删除。对此造成的不便我们深表歉意，希望您能选择我们的其他产品。', ['product_name', 'hostname', 'product_terminate_time']),
            $this->sms('100031', '续费成功提醒(用户)', '您购买的产品（{product_name}）现已续费成功，服务将持续至{product_end_time}，感谢您对我们的信赖！', ['product_name', 'product_end_time']),
            $this->sms('100027', '未实名暂停产品', '您购买的产品：{product_name}，主机名（{hostname}）由于为实名认证的缘故，现已被暂停所有功能。如需恢复使用，请尽快进行实名认证，否则产品将会在{product_end_time}日自动删除。', ['product_name', 'hostname', 'product_end_time']),
            $this->sms('100028', '工单已开通提醒(客户)', '我们已经收到您在{ticket_createtime}（时间）提交的工单：（{ticketnumber_tickettitle}）。团队将火速处理您的问题，请耐心等待！', ['ticket_createtime', 'ticketnumber_tickettitle']),
            $this->sms('100029', '成功绑定提醒(客户)', '您好，您的账号已成功绑定！如有疑问，请联系客服。', []),
            $this->sms('100030', '注册成功', '您已成功注册账号，感谢您的使用。请完善账号个人信息并妥善保管，切勿向他人透漏登录密码！', []),
            $this->sms('100008', '信用额账单提醒', '您有一笔账单产生：账单号{invoiceid}，金额{total}元，请及时付款！以免相关产品被暂停。', ['invoiceid', 'total']),
            $this->sms('100007', '解除暂停提醒(用户)', '您拥有的产品{product_name}现已解除暂停恢复使用，感谢您的支持！', ['product_name']),
            $this->sms('100032', '实名认证通过提醒（用户）', '{username}，您提交的实名认证审核已通过！您可以登录平台查看审核结果。', ['username']),
            $this->sms('100033', '账号绑定提示（用户）', '尊敬的{username}，您好，您的账号已成功绑定，如非您本人操作，请立即更改登录密码，以防账号被盗。', ['username']),
        ];
    }

    /**
     * @param  array<int, string>  $variables
     * @return array<string, mixed>
     */
    private function email(string $code, string $name, string $description, string $subject, string $content, array $variables, string $audience = 'user'): array
    {
        return [
            'channel' => 'email',
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'audience' => $audience,
            'subject' => $subject,
            'content' => $content,
            'variables' => $variables,
            'provider_variables' => [],
            'provider_template_id' => null,
        ];
    }

    /**
     * @param  array<int, string>  $variables
     * @return array<string, mixed>
     */
    private function sms(string $code, string $name, string $content, array $variables): array
    {
        return [
            'channel' => 'sms',
            'code' => $code,
            'name' => $name,
            'description' => $name.'短信模板。',
            'audience' => 'user',
            'subject' => null,
            'content' => $content,
            'variables' => $variables,
            'provider_variables' => $variables,
            'provider_template_id' => null,
        ];
    }
};
