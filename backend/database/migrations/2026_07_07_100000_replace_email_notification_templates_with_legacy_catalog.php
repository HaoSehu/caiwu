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

        DB::table('notification_templates')
            ->where('channel', 'email')
            ->delete();

        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->where('group_key', 'notification')
                ->where('item_key', 'like', 'email_template_%')
                ->delete();
        }

        $now = now();
        foreach ($this->templates() as $index => $template) {
            DB::table('notification_templates')->insert([
                'channel' => 'email',
                'code' => $template['code'],
                'name' => $template['name'],
                'description' => $template['description'],
                'audience' => $template['audience'],
                'subject' => $template['subject'],
                'content' => $this->content(
                    (string) $template['code'],
                    (string) $template['name'],
                    (string) $template['lead'],
                    (array) $template['rows'],
                    (string) $template['notice'],
                    (string) $template['accent']
                ),
                'variables_json' => json_encode($template['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'provider_variables_json' => '[]',
                'provider_template_id' => null,
                'is_enabled' => true,
                'is_custom' => false,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        DB::table('notification_templates')
            ->where('channel', 'email')
            ->whereIn('code', array_column($this->templates(), 'code'))
            ->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            $this->email('100001', '验证码邮件', '发送邮箱验证码时使用。', '【{{site_name}}】验证码邮件', '请使用以下验证码完成验证。', [['验证码', '{{code}}'], ['有效期', '{{expire_minutes}} 分钟']], '如非本人操作，请忽略此邮件。', ['site_name', 'code', 'expire_minutes'], 'user', '#1f5eff'),
            $this->email('100002', '注册成功', '客户注册成功后发送欢迎通知。', '【{{site_name}}】注册成功', '{{display_name}}，您的账号已注册成功。', [['登录邮箱', '{{email}}'], ['注册时间', '{{registered_at}}']], '请妥善保管账号信息。', ['site_name', 'display_name', 'email', 'registered_at'], 'user', '#0f766e'),
            $this->email('100003', '登录IP提醒', '客户登录或登录异常时发送 IP 安全提醒。', '【{{site_name}}】登录IP提醒', '{{display_name}}，您的账号发生登录行为。', [['登录账号', '{{email}}{{account}}'], ['登录时间', '{{login_at}}{{attempt_at}}'], ['当前 IP', '{{ip}}'], ['上次 IP', '{{previous_ip}}'], ['设备', '{{device}}']], '如非本人操作，请立即修改密码并联系站内支持。', ['site_name', 'display_name', 'email', 'account', 'login_at', 'attempt_at', 'ip', 'previous_ip', 'device'], 'user', '#b45309'),
            $this->email('100004', '绑定通知', '客户绑定或变更安全信息后发送提醒。', '【{{site_name}}】绑定通知', '{{display_name}}，您的账号安全信息已更新。', [['绑定类型', '{{bind_type}}'], ['绑定账号', '{{bind_account}}'], ['原手机号', '{{old_phone}}'], ['新手机号', '{{new_phone}}'], ['原邮箱', '{{old_email}}'], ['新邮箱', '{{new_email}}'], ['变更时间', '{{bound_at}}{{changed_at}}'], ['IP 地址', '{{ip}}'], ['设备', '{{device}}']], '如非本人操作，请联系客服处理。', ['site_name', 'display_name', 'bind_type', 'bind_account', 'old_phone', 'new_phone', 'old_email', 'new_email', 'bound_at', 'changed_at', 'ip', 'device'], 'user', '#2563eb'),
            $this->email('100005', '产品开通通知', '产品或服务开通成功后发送通知。', '【{{site_name}}】产品开通通知', '{{display_name}}，您的产品已开通。', [['产品名称', '{{product_name}}{{service_name}}'], ['主机名', '{{hostname}}'], ['主 IP', '{{product_main_ip}}'], ['用户名', '{{product_username}}'], ['操作系统', '{{operating_system}}'], ['开通时间', '{{activated_at}}'], ['到期时间', '{{expires_at}}'], ['付款周期', '{{billing_cycle_label}}']], '您可以登录控制台查看和管理该产品。', ['site_name', 'display_name', 'product_name', 'service_name', 'hostname', 'product_main_ip', 'product_username', 'operating_system', 'activated_at', 'expires_at', 'billing_cycle_label'], 'user', '#15803d'),
            $this->email('100006', '产品停用通知', '产品或服务被停用时发送通知。', '【{{site_name}}】产品停用通知', '{{display_name}}，您的产品已被停用。', [['产品名称', '{{product_name}}{{service_name}}'], ['停用原因', '{{reason}}'], ['到期时间', '{{expires_at}}'], ['停用时间', '{{suspended_at}}']], '请按提示处理后恢复使用。', ['site_name', 'display_name', 'product_name', 'service_name', 'reason', 'expires_at', 'suspended_at'], 'user', '#b91c1c'),
            $this->email('100007', '产品恢复通知', '产品或服务恢复后发送通知。', '【{{site_name}}】产品恢复通知', '{{display_name}}，您的产品已恢复。', [['产品名称', '{{product_name}}{{service_name}}'], ['恢复时间', '{{resumed_at}}'], ['新的到期时间', '{{expires_at}}']], '您可以继续使用该产品。', ['site_name', 'display_name', 'product_name', 'service_name', 'resumed_at', 'expires_at'], 'user', '#15803d'),
            $this->email('100008', '产品删除通知', '产品或服务删除后发送通知。', '【{{site_name}}】产品删除通知', '{{display_name}}，您的产品已删除。', [['产品名称', '{{product_name}}{{service_name}}'], ['删除时间', '{{terminated_at}}'], ['说明', '{{notice_message}}']], '如有疑问，请联系站内支持。', ['site_name', 'display_name', 'product_name', 'service_name', 'terminated_at', 'notice_message'], 'user', '#b91c1c'),
            $this->email('100009', '重装系统成功通知', '产品重装系统成功后发送通知。', '【{{site_name}}】重装系统成功通知', '{{display_name}}，您的产品已完成系统重装。', [['产品名称', '{{product_name}}{{service_name}}'], ['操作系统', '{{operating_system}}'], ['主 IP', '{{product_main_ip}}'], ['用户名', '{{product_username}}'], ['完成时间', '{{completed_at}}']], '请使用新的系统信息登录产品。', ['site_name', 'display_name', 'product_name', 'service_name', 'operating_system', 'product_main_ip', 'product_username', 'completed_at'], 'user', '#0f766e'),
            $this->email('100010', '新订单待支付', '客户创建新订单或账单后发送待支付通知。', '【{{site_name}}】新订单待支付 #{{invoice_no}}', '{{display_name}}，您有一笔新订单待支付。', [['订单号', '{{order_no}}'], ['账单号', '{{invoice_no}}'], ['产品名称', '{{product_name}}'], ['付款周期', '{{billing_cycle_label}}'], ['应付金额', '¥{{amount}}{{order_amount}}'], ['到期日期', '{{due_date}}']], '请及时完成付款，以免订单失效。', ['site_name', 'display_name', 'order_no', 'invoice_no', 'product_name', 'billing_cycle_label', 'amount', 'order_amount', 'due_date'], 'user', '#1d4ed8'),
            $this->email('100011', '第1次支付提醒', '订单或账单第一次支付提醒。', '【{{site_name}}】第1次支付提醒 #{{invoice_no}}', '{{display_name}}，您的账单仍未支付。', [['账单号', '{{invoice_no}}'], ['订单号', '{{order_no}}'], ['产品名称', '{{product_name}}'], ['待付金额', '¥{{amount}}'], ['到期日期', '{{due_date}}']], '{{notice_message}}', ['site_name', 'display_name', 'invoice_no', 'order_no', 'product_name', 'amount', 'due_date', 'notice_message'], 'user', '#1d4ed8'),
            $this->email('100012', '第2次支付提醒', '订单或账单第二次支付提醒。', '【{{site_name}}】第2次支付提醒 #{{invoice_no}}', '{{display_name}}，您的账单即将逾期。', [['账单号', '{{invoice_no}}'], ['订单号', '{{order_no}}'], ['产品名称', '{{product_name}}'], ['待付金额', '¥{{amount}}'], ['到期日期', '{{due_date}}']], '{{notice_message}}', ['site_name', 'display_name', 'invoice_no', 'order_no', 'product_name', 'amount', 'due_date', 'notice_message'], 'user', '#b45309'),
            $this->email('100013', '第3次支付提醒', '订单或账单第三次支付提醒。', '【{{site_name}}】第3次支付提醒 #{{invoice_no}}', '{{display_name}}，您的账单已逾期，请尽快处理。', [['账单号', '{{invoice_no}}'], ['订单号', '{{order_no}}'], ['产品名称', '{{product_name}}'], ['逾期金额', '¥{{amount}}'], ['到期日期', '{{due_date}}']], '{{notice_message}}', ['site_name', 'display_name', 'invoice_no', 'order_no', 'product_name', 'amount', 'due_date', 'notice_message'], 'user', '#b91c1c'),
            $this->email('100014', '自动续费预告通知', '自动续费执行前发送提醒。', '【{{site_name}}】自动续费预告通知', '{{display_name}}，您的产品即将自动续费。', [['产品名称', '{{service_name}}{{product_name}}'], ['到期时间', '{{expires_at}}'], ['付款周期', '{{billing_cycle_label}}'], ['自动续费日期', '{{auto_renew_date}}']], '{{urgency_message}}', ['site_name', 'display_name', 'service_name', 'product_name', 'expires_at', 'billing_cycle_label', 'auto_renew_date', 'urgency_message'], 'user', '#b45309'),
            $this->email('100015', '自动续费通知', '自动续费订单生成或扣款时发送通知。', '【{{site_name}}】自动续费通知', '{{display_name}}，您的产品自动续费流程已触发。', [['产品名称', '{{service_name}}{{product_name}}'], ['订单号', '{{order_no}}'], ['账单号', '{{invoice_no}}'], ['续费金额', '¥{{amount}}{{order_amount}}'], ['续费时间', '{{renewed_at}}']], '{{notice_message}}', ['site_name', 'display_name', 'service_name', 'product_name', 'order_no', 'invoice_no', 'amount', 'order_amount', 'renewed_at', 'notice_message'], 'user', '#0f766e'),
            $this->email('100016', '付款成功通知', '客户付款成功或管理员手动入账后发送通知。', '【{{site_name}}】付款成功通知 #{{invoice_no}}', '{{display_name}}，您的付款已确认。', [['账单号', '{{invoice_no}}'], ['订单号', '{{order_no}}'], ['产品名称', '{{product_name}}'], ['支付金额', '¥{{paid_amount}}{{amount}}'], ['支付方式', '{{payment_method}}'], ['交易号', '{{trade_no}}'], ['支付时间', '{{paid_at}}']], '{{notice_message}}{{remark}}', ['site_name', 'display_name', 'invoice_no', 'order_no', 'product_name', 'paid_amount', 'amount', 'payment_method', 'trade_no', 'paid_at', 'notice_message', 'remark'], 'user', '#15803d'),
            $this->email('100017', '账单退款通知', '账单或订单退款后发送通知。', '【{{site_name}}】账单退款通知 #{{invoice_no}}', '{{display_name}}，您的账单已退款。', [['账单号', '{{invoice_no}}'], ['订单号', '{{order_no}}'], ['产品名称', '{{product_name}}'], ['退款金额', '¥{{refund_amount}}'], ['退款方式', '{{refund_method_label}}'], ['退款时间', '{{refunded_at}}'], ['说明', '{{refund_reason}}']], '退款到账时间以支付渠道实际处理为准。', ['site_name', 'display_name', 'invoice_no', 'order_no', 'product_name', 'refund_amount', 'refund_method_label', 'refunded_at', 'refund_reason'], 'user', '#0f766e'),
            $this->email('100018', '第1次续费提醒', '产品到期前第一次续费提醒。', '【{{site_name}}】第1次续费提醒', '{{display_name}}，您的产品即将到期。', [['产品名称', '{{service_name}}{{product_name}}'], ['剩余时间', '{{days_left}} 天'], ['到期时间', '{{expires_at}}'], ['付款周期', '{{billing_cycle_label}}']], '{{urgency_message}}', ['site_name', 'display_name', 'service_name', 'product_name', 'days_left', 'expires_at', 'billing_cycle_label', 'urgency_message'], 'user', '#b45309'),
            $this->email('100019', '第2次续费提醒', '产品到期前第二次续费提醒。', '【{{site_name}}】第2次续费提醒', '{{display_name}}，您的产品到期时间临近。', [['产品名称', '{{service_name}}{{product_name}}'], ['剩余时间', '{{days_left}} 天'], ['到期时间', '{{expires_at}}'], ['付款周期', '{{billing_cycle_label}}']], '{{urgency_message}}', ['site_name', 'display_name', 'service_name', 'product_name', 'days_left', 'expires_at', 'billing_cycle_label', 'urgency_message'], 'user', '#b91c1c'),
            $this->email('100020', '信用额账单已生成', '信用额账单生成后发送通知。', '【{{site_name}}】信用额账单已生成 #{{invoice_no}}', '{{display_name}}，您的信用额账单已生成。', [['账单号', '{{invoice_no}}'], ['产品名称', '{{product_name}}'], ['账单金额', '¥{{amount}}'], ['到期时间', '{{due_at}}{{due_date}}']], '{{notice_message}}', ['site_name', 'display_name', 'invoice_no', 'product_name', 'amount', 'due_at', 'due_date', 'notice_message'], 'user', '#7c3aed'),
            $this->email('100021', '管理员新订单通知', '客户创建新订单后通知管理员。', '【{{site_name}}】管理员新订单通知 #{{invoice_no}}', '{{recipient_name}}，有客户创建了新订单。', [['客户', '{{user_name}}'], ['客户邮箱', '{{user_email}}'], ['订单号', '{{order_no}}'], ['账单号', '{{invoice_no}}'], ['订单类型', '{{order_type_label}}'], ['产品名称', '{{product_name}}'], ['付款周期', '{{billing_cycle_label}}'], ['订单金额', '¥{{order_amount}}'], ['订单状态', '{{order_status_label}}'], ['创建时间', '{{created_at}}']], '请按业务流程关注该订单。', ['site_name', 'recipient_name', 'user_name', 'user_email', 'order_no', 'invoice_no', 'order_type_label', 'product_name', 'billing_cycle_label', 'order_amount', 'order_status_label', 'created_at'], 'admin', '#7c3aed'),
            $this->email('100022', '管理员订单支付通知', '客户订单支付完成后通知管理员。', '【{{site_name}}】管理员订单支付通知 #{{invoice_no}}', '{{recipient_name}}，有客户完成了订单支付。', [['客户', '{{user_name}}'], ['客户邮箱', '{{user_email}}'], ['订单号', '{{order_no}}'], ['账单号', '{{invoice_no}}'], ['产品名称', '{{product_name}}'], ['付款周期', '{{billing_cycle_label}}'], ['支付金额', '¥{{paid_amount}}'], ['支付方式', '{{payment_method}}'], ['交易号', '{{trade_no}}'], ['支付时间', '{{paid_at}}']], '请按业务流程跟进开通或核账。', ['site_name', 'recipient_name', 'user_name', 'user_email', 'order_no', 'invoice_no', 'product_name', 'billing_cycle_label', 'paid_amount', 'payment_method', 'trade_no', 'paid_at'], 'admin', '#0f766e'),
            $this->email('100023', '工单开通通知', '客户工单创建成功后发送通知。', '【{{site_name}}】工单开通通知 #{{ticket_id}}', '{{display_name}}，您的工单已创建。', [['工单编号', '#{{ticket_id}}'], ['工单标题', '{{ticket_subject}}'], ['部门', '{{department}}'], ['优先级', '{{priority}}'], ['创建时间', '{{ticket_created_at}}']], '{{message_preview}} {{login_tip}}', ['site_name', 'display_name', 'ticket_id', 'ticket_subject', 'department', 'priority', 'ticket_created_at', 'message_preview', 'login_tip'], 'user', '#2563eb'),
            $this->email('100024', '工单新回复通知', '管理员回复客户工单后发送通知。', '【{{site_name}}】工单新回复通知 #{{ticket_id}}', '{{display_name}}，您的工单有新回复。', [['工单编号', '#{{ticket_id}}'], ['工单标题', '{{ticket_subject}}'], ['状态', '{{status}}'], ['回复人', '{{staff_name}}']], '{{message_preview}} {{login_tip}}', ['site_name', 'display_name', 'ticket_id', 'ticket_subject', 'status', 'staff_name', 'message_preview', 'tickets_url', 'login_tip'], 'user', '#2563eb'),
            $this->email('100025', '工单自动关闭通知', '工单自动关闭后发送通知。', '【{{site_name}}】工单自动关闭通知 #{{ticket_id}}', '{{display_name}}，您的工单已自动关闭。', [['工单编号', '#{{ticket_id}}'], ['工单标题', '{{ticket_subject}}'], ['关闭时间', '{{ticket_closed_at}}'], ['关闭原因', '{{close_reason}}']], '如问题仍未解决，可以重新提交工单。', ['site_name', 'display_name', 'ticket_id', 'ticket_subject', 'ticket_closed_at', 'close_reason'], 'user', '#667085'),
            $this->email('100026', '管理员新工单提示', '客户提交新工单后通知管理员。', '【{{site_name}}】管理员新工单提示 #{{ticket_id}}', '{{recipient_name}}，有客户提交了新工单。', [['工单编号', '#{{ticket_id}}'], ['标题', '{{ticket_subject}}'], ['部门', '{{department}}'], ['优先级', '{{priority}}'], ['状态', '{{status}}'], ['客户', '{{client_name}}'], ['客户邮箱', '{{client_email}}']], '{{message_preview}}', ['site_name', 'recipient_name', 'ticket_id', 'ticket_subject', 'department', 'priority', 'status', 'client_name', 'client_email', 'message_preview'], 'admin', '#7c3aed'),
            $this->email('100027', '管理员工单回复通知', '客户回复工单后通知管理员。', '【{{site_name}}】管理员工单回复通知 #{{ticket_id}}', '{{recipient_name}}，客户补充了新的工单回复。', [['工单编号', '#{{ticket_id}}'], ['标题', '{{ticket_subject}}'], ['部门', '{{department}}'], ['优先级', '{{priority}}'], ['状态', '{{status}}'], ['客户', '{{client_name}}'], ['客户邮箱', '{{client_email}}']], '{{message_preview}}', ['site_name', 'recipient_name', 'ticket_id', 'ticket_subject', 'department', 'priority', 'status', 'client_name', 'client_email', 'message_preview'], 'admin', '#7c3aed'),
            $this->email('100028', '管理员登录提示', '管理员登录后台后发送安全提醒。', '【{{site_name}}】管理员登录提示', '{{recipient_name}}，您的管理员账号发生登录行为。', [['登录账号', '{{account}}'], ['登录时间', '{{login_at}}'], ['IP 地址', '{{ip}}'], ['设备', '{{device}}']], '如非本人操作，请立即修改密码并检查后台账号权限。', ['site_name', 'recipient_name', 'account', 'login_at', 'ip', 'device'], 'admin', '#b45309'),
            $this->email('100029', '产品解除停用失败通知', '产品解除停用失败后发送通知。', '【{{site_name}}】产品解除停用失败通知', '{{display_name}}，您的产品解除停用失败。', [['产品名称', '{{product_name}}{{service_name}}'], ['失败原因', '{{failure_reason}}'], ['处理时间', '{{failed_at}}']], '系统会保留失败记录，请联系站内支持处理。', ['site_name', 'display_name', 'product_name', 'service_name', 'failure_reason', 'failed_at'], 'user', '#b91c1c'),
        ];
    }

    /**
     * @param  array<int, string>  $variables
     * @param  array<int, array{0: string, 1: string}>  $rows
     * @return array<string, mixed>
     */
    private function email(
        string $code,
        string $name,
        string $description,
        string $subject,
        string $lead,
        array $rows,
        string $notice,
        array $variables,
        string $audience,
        string $accent
    ): array {
        return compact('code', 'name', 'description', 'subject', 'lead', 'rows', 'notice', 'variables', 'audience', 'accent');
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $rows
     */
    private function content(string $code, string $title, string $lead, array $rows, string $notice, string $accent): string
    {
        $style = $this->style($code, $accent);
        $rowHtml = '';

        foreach ($rows as $row) {
            $rowHtml .= '      <div class="email-detail-row"><span>'.(string) $row[0].'</span><strong>'.(string) $row[1].'</strong></div>'."\n";
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
{$style}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-{$code}">
    <div class="email-container">
      <div class="email-kicker">{{site_name}}</div>
      <h1>{$title}</h1>
      <p class="email-lead">{$lead}</p>
      <div class="email-details">
{$rowHtml}      </div>
      <p class="email-note">{$notice}</p>
      <p class="email-footer">本邮件由 {{site_name}} 系统发送，请勿直接回复。</p>
    </div>
  </div>
</body>
</html>
HTML;
    }

    private function style(string $code, string $accent): string
    {
        return <<<CSS
body {
  margin: 0;
  background: #eef2f7;
  color: #20232a;
  font-family: "PingFang SC", "Microsoft YaHei", Arial, sans-serif;
}
.cw-email-template-{$code} {
  width: 100%;
  box-sizing: border-box;
  padding: 32px 12px;
  background: #eef2f7;
}
.cw-email-template-{$code} .email-container {
  max-width: 680px;
  margin: 0 auto;
  overflow: hidden;
  border: 1px solid #d7deea;
  background: #ffffff;
}
.cw-email-template-{$code} .email-kicker {
  border-top: 4px solid {$accent};
  padding: 22px 28px 0;
  color: {$accent};
  font-size: 13px;
  font-weight: 700;
}
.cw-email-template-{$code} h1 {
  margin: 8px 28px 0;
  color: #111827;
  font-size: 26px;
  line-height: 1.4;
}
.cw-email-template-{$code} .email-lead,
.cw-email-template-{$code} .email-note,
.cw-email-template-{$code} .email-footer {
  margin: 14px 28px 0;
  color: #4b5563;
  font-size: 14px;
  line-height: 1.8;
}
.cw-email-template-{$code} .email-details {
  margin: 22px 28px 0;
  border: 1px solid #e1e7f0;
  background: #f8fafc;
}
.cw-email-template-{$code} .email-detail-row {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  padding: 12px 14px;
  border-bottom: 1px solid #e1e7f0;
}
.cw-email-template-{$code} .email-detail-row:last-child {
  border-bottom: 0;
}
.cw-email-template-{$code} .email-detail-row span {
  color: #667085;
  white-space: nowrap;
}
.cw-email-template-{$code} .email-detail-row strong {
  color: #111827;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-{$code} .email-note {
  border-left: 3px solid {$accent};
  padding-left: 12px;
}
.cw-email-template-{$code} .email-footer {
  margin-top: 24px;
  border-top: 1px solid #e1e7f0;
  padding: 16px 0 22px;
  color: #8a95a5;
  font-size: 12px;
}
@media screen and (max-width: 640px) {
  .cw-email-template-{$code} {
    padding: 18px 10px;
  }
  .cw-email-template-{$code} .email-kicker,
  .cw-email-template-{$code} h1,
  .cw-email-template-{$code} .email-lead,
  .cw-email-template-{$code} .email-note,
  .cw-email-template-{$code} .email-footer,
  .cw-email-template-{$code} .email-details {
    margin-left: 18px;
    margin-right: 18px;
  }
  .cw-email-template-{$code} h1 {
    font-size: 22px;
  }
  .cw-email-template-{$code} .email-detail-row {
    display: block;
  }
  .cw-email-template-{$code} .email-detail-row strong {
    display: block;
    margin-top: 6px;
    text-align: left;
  }
}
CSS;
    }
};
