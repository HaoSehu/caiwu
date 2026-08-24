<?php

declare(strict_types=1);

use App\Support\EmailNotificationTemplateDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 将通知模板同步为默认值：email 取 EmailNotificationTemplateDefaults（2026-08-01
 * 升级后的最新视觉），sms 取 2026_07_06_124000 归档迁移定义的最终默认内容。
 *
 * 背景：邮件模板默认值于 2026-08-01 提交 fda8ddac 升级（MSO 条件注释、logo 尺寸、
 * h1 字重、深色模式），但未伴随数据迁移，数据库模板仍是 7 月旧版；实际发信读取
 * 数据库模板，因此同步数据库即可让新版视觉生效。sms 模板数据在归档迁移中，schema
 * dump 重建的环境（如测试库）会缺失，一并补齐。
 *
 * 仅覆盖 is_custom=0 的记录，保留管理员自定义模板；缺失模板按默认值补入。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        $this->syncTemplates(EmailNotificationTemplateDefaults::templates(), 'email');
        $this->syncTemplates($this->smsTemplates(), 'sms');
    }

    /**
     * @param  array<int, array<string, mixed>>  $templates
     */
    private function syncTemplates(array $templates, string $channel): void
    {
        $now = now();

        foreach ($templates as $index => $template) {
            $code = (string) $template['code'];
            $payload = [
                'name' => $template['name'],
                'description' => $template['description'],
                'audience' => $template['audience'],
                'subject' => $template['subject'] ?? null,
                'content' => $template['content'],
                'variables_json' => json_encode($template['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'provider_variables_json' => json_encode($template['provider_variables'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'provider_template_id' => $template['provider_template_id'] ?? null,
                'is_enabled' => true,
                'is_custom' => false,
                'sort_order' => $index + 1,
                'updated_at' => $now,
            ];

            $existing = DB::table('notification_templates')
                ->where('channel', $channel)
                ->where('code', $code)
                ->first(['id', 'is_custom']);

            if ($existing === null) {
                DB::table('notification_templates')->insert(
                    $payload + ['channel' => $channel, 'code' => $code, 'created_at' => $now]
                );

                continue;
            }

            if ((int) $existing->is_custom === 1) {
                continue;
            }

            DB::table('notification_templates')
                ->where('id', $existing->id)
                ->update($payload);
        }
    }

    /**
     * sms 默认模板：提取自 2026_07_06_124000_seed_notification_templates_defaults
     * 归档迁移，code 已为最终数字形态，内容保持单括号变量语法。
     *
     * @return array<int, array<string, mixed>>
     */
    private function smsTemplates(): array
    {
        return [
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

    public function down(): void
    {
        // 模板默认值同步是数据更新，不做回滚（与历史模板迁移一致）。
    }
};
