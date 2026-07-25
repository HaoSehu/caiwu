<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        if (Schema::hasColumn('notification_templates', 'css')) {
            Schema::table('notification_templates', function (Blueprint $table): void {
                $table->dropColumn('css');
            });
        }

        foreach ($this->templates() as $code => $template) {
            DB::table('notification_templates')
                ->where('channel', 'email')
                ->where('code', (string) $code)
                ->where('is_custom', false)
                ->update([
                    'content' => $this->content((string) $code, $template),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        //
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            '100001' => [
                'title' => '邮箱验证码',
                'lead' => '请使用以下验证码完成邮箱验证。',
                'highlight' => '{{code}}',
                'rows' => [['有效期', '{{expire_minutes}} 分钟']],
                'notice' => '如非本人操作，请忽略此邮件。',
                'accent' => '#1f5eff',
            ],
            '100002' => [
                'title' => '{{site_name}} 登录提醒',
                'lead' => '{{display_name}}，您的账号刚刚完成一次登录。',
                'rows' => [['登录账号', '{{email}}'], ['登录时间', '{{login_at}}'], ['IP 地址', '{{ip}}'], ['设备', '{{device}}']],
                'notice' => '如非本人操作，请立即修改密码并联系站内支持。',
                'accent' => '#0f766e',
            ],
            '100003' => [
                'title' => '服务续费提醒',
                'lead' => '{{display_name}}，您的服务即将到期，请提前安排续费。',
                'rows' => [['服务名称', '{{service_name}}'], ['剩余时间', '{{days_left}} 天'], ['到期时间', '{{expires_at}}'], ['计费周期', '{{billing_cycle_label}}']],
                'notice' => '{{urgency_message}}',
                'accent' => '#b45309',
            ],
            '100004' => [
                'title' => '账单付款提醒',
                'lead' => '{{display_name}}，您有一笔账单等待支付。',
                'rows' => [['账单号', '{{invoice_no}}'], ['关联产品', '{{product_name}}'], ['待付金额', '¥{{amount}}'], ['应付日期', '{{due_date}}']],
                'notice' => '{{notice_message}}',
                'accent' => '#1d4ed8',
            ],
            '100005' => [
                'title' => '账单逾期催款',
                'lead' => '{{display_name}}，以下账单已经逾期，请尽快处理。',
                'rows' => [['账单号', '{{invoice_no}}'], ['关联产品', '{{product_name}}'], ['逾期金额', '¥{{amount}}'], ['应付日期', '{{due_date}}']],
                'notice' => '{{notice_message}}',
                'accent' => '#b91c1c',
            ],
            '100006' => [
                'title' => '服务到期暂停通知',
                'lead' => '{{display_name}}，您的服务因到期未续费已暂停。',
                'rows' => [['服务名称', '{{service_name}}'], ['到期时间', '{{expires_at}}']],
                'notice' => '续费完成后系统将按流程恢复服务。',
                'accent' => '#b91c1c',
            ],
            '100007' => [
                'title' => '服务恢复通知',
                'lead' => '{{display_name}}，您的服务已恢复。',
                'rows' => [['服务名称', '{{service_name}}'], ['新的到期时间', '{{expires_at}}']],
                'notice' => '您可以登录控制台继续管理该服务。',
                'accent' => '#15803d',
            ],
            '100008' => [
                'title' => '{{notice_title}}',
                'lead' => '{{display_name}}，您的账单状态有更新。',
                'rows' => [['账单号', '{{invoice_no}}'], ['关联产品', '{{product_name}}'], ['金额', '¥{{amount}}'], ['状态', '{{status_label}}'], ['到期时间', '{{due_at}}'], ['支付时间', '{{paid_at}}'], ['支付方式', '{{payment_method}}'], ['交易号', '{{trade_no}}']],
                'notice' => '{{notice_message}}',
                'accent' => '#1d4ed8',
            ],
            '100009' => [
                'title' => '手动入账通知',
                'lead' => '您的账单已由管理员人工确认入账。',
                'rows' => [['账单号', '{{invoice_no}}'], ['入账金额', '¥{{paid_amount}}'], ['支付方式', '{{payment_method}}'], ['入账时间', '{{paid_at}}'], ['交易号', '{{trade_no}}']],
                'notice' => '{{remark}}',
                'accent' => '#0f766e',
            ],
            '100010' => [
                'title' => '新工单提醒',
                'lead' => '{{recipient_name}}，有客户提交了新的工单。',
                'rows' => [['工单编号', '#{{ticket_id}}'], ['标题', '{{ticket_subject}}'], ['部门', '{{department}}'], ['优先级', '{{priority}}'], ['客户', '{{client_name}}'], ['客户邮箱', '{{client_email}}']],
                'notice' => '{{message_preview}}',
                'accent' => '#7c3aed',
            ],
            '100011' => [
                'title' => '工单待回复提醒',
                'lead' => '{{recipient_name}}，客户补充了新的工单回复。',
                'rows' => [['工单编号', '#{{ticket_id}}'], ['标题', '{{ticket_subject}}'], ['部门', '{{department}}'], ['优先级', '{{priority}}'], ['状态', '{{status}}'], ['客户', '{{client_name}}']],
                'notice' => '{{message_preview}}',
                'accent' => '#7c3aed',
            ],
            '100012' => [
                'title' => '工单回复通知',
                'lead' => '{{display_name}}，您的工单已有新的回复。',
                'rows' => [['工单编号', '#{{ticket_id}}'], ['标题', '{{ticket_subject}}'], ['状态', '{{status}}'], ['回复人', '{{staff_name}}']],
                'notice' => '{{message_preview}} {{login_tip}}',
                'accent' => '#2563eb',
            ],
            '100013' => [
                'title' => '新账单提醒',
                'lead' => '{{recipient_name}}，用户创建了一笔新账单。',
                'rows' => [['用户', '{{user_name}}'], ['邮箱', '{{user_email}}'], ['账单号', '{{invoice_no}}'], ['订单类型', '{{order_type_label}}'], ['商品', '{{product_name}}'], ['周期', '{{billing_cycle_label}}'], ['金额', '¥{{order_amount}}'], ['状态', '{{order_status_label}}'], ['创建时间', '{{created_at}}']],
                'notice' => '请按业务流程关注该账单。',
                'accent' => '#7c3aed',
            ],
            '100014' => [
                'title' => '用户支付完成提醒',
                'lead' => '{{recipient_name}}，有用户完成了账单支付。',
                'rows' => [['用户', '{{user_name}}'], ['邮箱', '{{user_email}}'], ['账单号', '{{invoice_no}}'], ['商品', '{{product_name}}'], ['周期', '{{billing_cycle_label}}'], ['支付金额', '¥{{paid_amount}}'], ['支付方式', '{{payment_method}}'], ['交易号', '{{trade_no}}'], ['支付时间', '{{paid_at}}']],
                'notice' => '请按业务流程跟进开通或核账。',
                'accent' => '#0f766e',
            ],
            '100015' => [
                'title' => '{{site_name}} 登录失败提醒',
                'lead' => '{{display_name}}，您的账号触发了登录失败提醒。',
                'rows' => [['登录账号', '{{account}}'], ['尝试时间', '{{attempt_at}}'], ['IP 地址', '{{ip}}'], ['设备', '{{device}}']],
                'notice' => '如非本人操作，请及时修改密码。',
                'accent' => '#b91c1c',
            ],
            '100016' => [
                'title' => '{{site_name}} 异地登录提醒',
                'lead' => '{{display_name}}，检测到账户在新的 IP 登录。',
                'rows' => [['登录邮箱', '{{email}}'], ['登录时间', '{{login_at}}'], ['当前 IP', '{{ip}}'], ['上次 IP', '{{previous_ip}}'], ['设备', '{{device}}']],
                'notice' => '如非本人操作，请立即修改密码。',
                'accent' => '#b45309',
            ],
            '100017' => [
                'title' => '{{site_name}} 密码变更提醒',
                'lead' => '{{display_name}}，您的账号密码已变更。',
                'rows' => [['变更时间', '{{changed_at}}'], ['IP 地址', '{{ip}}'], ['设备', '{{device}}']],
                'notice' => '如非本人操作，请立即找回密码并联系站内支持。',
                'accent' => '#b91c1c',
            ],
            '100018' => [
                'title' => '{{site_name}} 手机号变更提醒',
                'lead' => '{{display_name}}，您的安全手机号已变更。',
                'rows' => [['原手机号', '{{old_phone}}'], ['新手机号', '{{new_phone}}'], ['变更时间', '{{changed_at}}'], ['IP 地址', '{{ip}}'], ['设备', '{{device}}']],
                'notice' => '如非本人操作，请联系客服处理。',
                'accent' => '#b45309',
            ],
            '100019' => [
                'title' => '{{site_name}} 邮箱变更提醒',
                'lead' => '{{display_name}}，您的安全邮箱已变更。',
                'rows' => [['原邮箱', '{{old_email}}'], ['新邮箱', '{{new_email}}'], ['变更时间', '{{changed_at}}'], ['IP 地址', '{{ip}}'], ['设备', '{{device}}']],
                'notice' => '如非本人操作，请联系客服处理。',
                'accent' => '#b45309',
            ],
            '100020' => [
                'title' => '即将自动续费提醒',
                'lead' => '{{display_name}}，您的服务将在明天尝试自动续费。',
                'rows' => [['服务名称', '{{service_name}}'], ['到期时间', '{{expires_at}}'], ['计费周期', '{{billing_cycle_label}}'], ['自动续费日期', '{{auto_renew_date}}']],
                'notice' => '{{urgency_message}}',
                'accent' => '#b45309',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $template
     */
    private function content(string $code, array $template): string
    {
        $style = $this->style($code, (string) $template['accent']);
        $rows = '';
        foreach ((array) ($template['rows'] ?? []) as $row) {
            if (! is_array($row) || count($row) < 2) {
                continue;
            }

            $rows .= '      <div class="email-detail-row"><span>'.(string) $row[0].'</span><strong>'.(string) $row[1].'</strong></div>'."\n";
        }

        $highlight = trim((string) ($template['highlight'] ?? ''));
        $highlightHtml = $highlight !== '' ? '    <div class="email-highlight">'.$highlight.'</div>'."\n" : '';

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
      <h1>{$template['title']}</h1>
      <p class="email-lead">{$template['lead']}</p>
{$highlightHtml}      <div class="email-details">
{$rows}      </div>
      <p class="email-note">{$template['notice']}</p>
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
.cw-email-template-{$code} .email-highlight {
  display: inline-block;
  margin: 22px 28px 4px;
  padding: 14px 18px;
  border: 1px solid {$accent};
  background: #f8fbff;
  color: {$accent};
  font-size: 28px;
  line-height: 1;
  font-weight: 700;
  letter-spacing: 0.16em;
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
  .cw-email-template-{$code} .email-details,
  .cw-email-template-{$code} .email-highlight {
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
