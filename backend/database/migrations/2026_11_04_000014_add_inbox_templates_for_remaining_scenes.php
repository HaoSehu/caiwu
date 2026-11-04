<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 为剩余 16 个业务场景补齐站内消息（message 渠道）模板。
 *
 * 验证码类场景（注册/登录验证码、找回密码）刻意不补：
 * 用户此时尚未登录，站内信没有意义。
 *
 * 编码复用同场景邮件编号，文案与既有邮件语义一致。
 * 已存在则跳过，重复执行安全。
 */
return new class extends Migration
{
    /**
     * 场景 => [模板名, 标题, 正文, 变量, 编号]。
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: list<string>, 4: string}>
     */
    private const TEMPLATES = [
        'security_changed' => [
            '账号安全变更',
            '账号安全信息已变更',
            '您的账号安全资料已于 {{changed_at}} 变更（{{bind_type}}：{{bind_account}}）。如非本人操作，请立即联系客服。',
            ['changed_at', 'bind_type', 'bind_account'],
            '100004',
        ],
        'login_alert' => [
            '登录提醒',
            '账号登录提醒',
            '您的账号于 {{login_at}} 在 IP {{ip}} 登录，设备 {{device}}。如非本人操作，请立即修改密码。',
            ['login_at', 'ip', 'device'],
            '100003',
        ],
        'registration_success' => [
            '注册成功',
            '注册成功',
            '{{display_name}}，您的账号已注册成功，欢迎使用。',
            ['display_name'],
            '100002',
        ],
        'service_activated' => [
            '服务开通成功',
            '服务已开通',
            '您的服务「{{service_name}}」已开通，到期时间 {{expires_at}}。',
            ['service_name', 'expires_at'],
            '100005',
        ],
        'service_reinstalled' => [
            '系统重装成功',
            '系统重装成功',
            '您的服务「{{service_name}}」已完成系统重装，操作系统 {{operating_system}}，主 IP {{product_main_ip}}。',
            ['service_name', 'operating_system', 'product_main_ip'],
            '100009',
        ],
        'service_suspended' => [
            '服务暂停',
            '服务已暂停',
            '您的服务「{{service_name}}」已被暂停，原因：{{reason}}。请尽快处理以恢复使用。',
            ['service_name', 'reason'],
            '100006',
        ],
        'service_resumed' => [
            '服务恢复',
            '服务已恢复',
            '您的服务「{{service_name}}」已恢复，新的到期时间 {{expires_at}}。',
            ['service_name', 'expires_at'],
            '100007',
        ],
        'service_suspend_failed' => [
            '解除停用失败',
            '解除停用未生效',
            '您的服务「{{service_name}}」解除停用未生效，请稍后重试或联系客服。',
            ['service_name'],
            '100029',
        ],
        'service_terminated' => [
            '服务删除',
            '服务已删除',
            '您的服务「{{service_name}}」已删除。{{notice_message}}',
            ['service_name', 'notice_message'],
            '100008',
        ],
        'service_transferred' => [
            '实例归属变更',
            '实例归属已变更',
            '您的实例「{{service_name}}」归属已变更（{{direction_label}}），对方账号 {{counterparty_email}}。',
            ['service_name', 'direction_label', 'counterparty_email'],
            '100030',
        ],
        'order_refunded' => [
            '账单退款',
            '退款已处理',
            '您的账单 {{invoice_no}} 已退款，金额 {{refund_amount}} 元。',
            ['invoice_no', 'refund_amount'],
            '100017',
        ],
        'auto_renew_notice' => [
            '自动续费通知',
            '自动续费已完成',
            '您的服务「{{service_name}}」已完成自动续费，账单号 {{invoice_no}}，金额 {{amount}} 元。',
            ['service_name', 'invoice_no', 'amount'],
            '100015',
        ],
        'credit_invoice_created' => [
            '信用额账单已生成',
            '信用额账单已生成',
            '您的信用额账单 {{invoice_no}} 已生成，金额 {{amount}} 元，请于 {{due_at}} 前完成支付。',
            ['invoice_no', 'amount', 'due_at'],
            '100020',
        ],
        'account_bound' => [
            '账号绑定成功',
            '账号绑定成功',
            '您的账号已成功绑定。如非本人操作，请立即修改密码并联系管理员。',
            [],
            '100048',
        ],
        'real_name_approved' => [
            '实名认证通过',
            '实名认证已通过',
            '{{display_name}}，您提交的实名认证审核已通过，可以正常使用全部服务。',
            ['display_name'],
            '100044',
        ],
        'real_name_rejected' => [
            '实名认证未通过',
            '实名认证未通过',
            '您提交的实名认证审核未通过，原因：{{reason}}。请补充资料后重新提交。',
            ['reason'],
            '100045',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')
            || ! Schema::hasTable('message_scene_templates')) {
            return;
        }

        $now = now();
        $sortBase = (int) DB::table('notification_templates')->max('sort_order');

        foreach (self::TEMPLATES as $scene => [$name, $title, $body, $variables, $code]) {
            $exists = DB::table('notification_templates')
                ->where('channel', 'message')
                ->where('code', $code)
                ->exists();

            if (! $exists) {
                DB::table('notification_templates')->insert([
                    'channel' => 'message',
                    'code' => $code,
                    'name' => $name,
                    'description' => $name.'站内消息模板。',
                    'audience' => 'user',
                    'scene_code' => $scene,
                    'subject' => $title,
                    'content' => $body,
                    'variables_json' => json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'provider_variables_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'provider_template_id' => null,
                    'is_enabled' => true,
                    'is_custom' => false,
                    'sort_order' => ++$sortBase,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('message_scene_templates')->insertOrIgnore([
                'audience' => 'customer',
                'scene_code' => $scene,
                'channel' => 'message',
                'template_code' => $code,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // 模板新增不回滚。
    }
};
