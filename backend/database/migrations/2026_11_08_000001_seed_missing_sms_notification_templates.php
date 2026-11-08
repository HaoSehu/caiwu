<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 补种缺失的短信默认模板。
 *
 * 背景：`2026_07_06_124000_seed_notification_templates_defaults` 已归档到
 * `database/_archive/migrations/`，但 `migrations` 表保留了它的执行记录。
 * 结果是在全新数据库上 `migrate:fresh` 时：
 *   1. 种子迁移不再执行 → 模板从未创建；
 *   2. 后续 `2026_11_04_000007_unify_email_sms_template_codes` 只做名称→编号
 *      的 UPDATE，找不到行就静默跳过；
 *   3. `SmsTemplateCatalog` 引用的 100001 等编号在库里不存在，
 *      `MessageTemplateCodeConsistencyTest` 与计费通知链路因此失败。
 *
 * 本迁移把这些短信模板按当前编号补齐。已存在的行不覆盖（`is_custom=1` 的
 * 管理员自定义内容更不能动），因此可安全重复执行。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        $now = now();

        foreach ($this->smsTemplates() as $index => $template) {
            $exists = DB::table('notification_templates')
                ->where('channel', 'sms')
                ->where('code', $template['code'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('notification_templates')->insert([
                'channel' => 'sms',
                'code' => $template['code'],
                'name' => $template['name'],
                'description' => $template['description'],
                'audience' => $template['audience'] ?? 'user',
                'subject' => null,
                'content' => $template['content'],
                'variables_json' => json_encode($template['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'provider_variables_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'provider_template_id' => null,
                'is_enabled' => true,
                'is_custom' => false,
                'sort_order' => 1000 + $index,
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

        $codes = array_column($this->smsTemplates(), 'code');

        DB::table('notification_templates')
            ->where('channel', 'sms')
            ->where('is_custom', false)
            ->whereIn('code', $codes)
            ->delete();
    }

    /**
     * 名称与内容取自归档的种子迁移，编号按 `SmsTemplateCatalog` 与
     * `2026_11_04_000007_unify_email_sms_template_codes` 的当前口径。
     *
     * @return list<array{code: string, name: string, description: string, content: string, variables: list<string>, audience?: string}>
     */
    private function smsTemplates(): array
    {
        return [
            [
                'code' => '100001',
                'name' => '发送验证码',
                'description' => '发送短信验证码时使用。',
                'content' => '您的验证码{code}，该验证码5分钟内有效,请勿泄漏于他人。',
                'variables' => ['code'],
            ],
            [
                'code' => '100002',
                'name' => '注册成功',
                'description' => '客户注册成功后发送。',
                'content' => '您已成功注册账号，感谢您的使用。请完善账号个人信息并妥善保管，切勿向他人透漏登录密码！',
                'variables' => [],
            ],
            [
                'code' => '100003',
                'name' => '登录短信提醒',
                'description' => '客户登录成功后发送安全提醒。',
                'content' => '您好，您的账号在{time}时间登录。如您未曾尝试登录，请立即更改登录密码，以防账号被盗。',
                'variables' => ['time'],
            ],
            [
                'code' => '100005',
                'name' => '产品开通提醒(用户)',
                'description' => '服务开通成功后通知用户。',
                'content' => '您好，您购买的产品 {product_name} 现已开通，购买时间：{product_first_time}，到期时间：{product_end_time}，付款周期：{product_binlly_cycle}，感谢使用！',
                'variables' => ['product_name', 'product_first_time', 'product_end_time', 'product_binlly_cycle'],
            ],
            [
                'code' => '100006',
                'name' => '产品暂停',
                'description' => '服务被暂停时通知用户。',
                'content' => '您好，您购买的产品{product_name}由于{description}的缘故，现已被暂停所有功能，如需恢复使用，请尽快处理！',
                'variables' => ['product_name', 'description'],
            ],
            [
                'code' => '100007',
                'name' => '解除暂停提醒(用户)',
                'description' => '服务恢复后通知用户。',
                'content' => '您拥有的产品{product_name}现已解除暂停恢复使用，感谢您的支持！',
                'variables' => ['product_name'],
            ],
            [
                'code' => '100008',
                'name' => '未续期产品删除提醒(用户)',
                'description' => '服务因未续费被删除时通知用户。',
                'content' => '您购买的产品{product_name}（主机名：{hostname}）由于未能在指定时间内续费，已于{product_terminate_time}自动删除。对此造成的不便我们深表歉意，希望您能选择我们的其他产品。',
                'variables' => ['product_name', 'hostname', 'product_terminate_time'],
            ],
            [
                'code' => '100010',
                'name' => '下单提醒(客户)',
                'description' => '客户下单成功后发送。',
                'content' => '您已成功下单，请及时付款，以免订单失效。以下为账单信息产品名称：{product_name}产品单价：{product_price}付款周期：{product_binlly_cycle}订单创建时间：{order_create_time}。',
                'variables' => ['product_name', 'product_price', 'product_binlly_cycle', 'order_create_time'],
            ],
            [
                'code' => '100016',
                'name' => '账单支付',
                'description' => '账单支付成功后发送。',
                'content' => '您好，您已成功支付账单号{invoiceid}，账单金额{total}，谢谢支持！',
                'variables' => ['invoiceid', 'total'],
            ],
            [
                'code' => '100017',
                'name' => '订单退款',
                'description' => '订单退款完成后发送。',
                'content' => '您好，您的订单{order_id}，金额{order_total_fee}已退款，请及时关注！',
                'variables' => ['order_id', 'order_total_fee'],
            ],
            [
                'code' => '100018',
                'name' => '续费成功提醒(用户)',
                'description' => '服务续费成功后通知用户。',
                'content' => '您购买的产品（{product_name}）现已续费成功，服务将持续至{product_end_time}，感谢您对我们的信赖！',
                'variables' => ['product_name', 'product_end_time'],
            ],
            [
                'code' => '100023',
                'name' => '提交工单',
                'description' => '客户提交工单后发送。',
                'content' => '您好，我们已经收到您提交的工单：{subject}。团队将火速处理您的问题，请耐心等待！',
                'variables' => ['subject'],
            ],
            [
                'code' => '100024',
                'name' => '工单回复',
                'description' => '工单收到回复时发送。',
                'content' => '您提交的工单{subject}有新的回复，请注意查收！',
                'variables' => ['subject'],
            ],
        ];
    }
};
