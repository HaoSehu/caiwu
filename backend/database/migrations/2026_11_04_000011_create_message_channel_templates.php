<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 建立站内消息（message 渠道）模板体系。
 *
 * 背景：站内消息此前走 user_notifications 表、标题正文硬编码在业务代码里，
 * 没有模板载体，导致管理端「站内消息」列永远是横杠、开关无实际作用。
 *
 * 本迁移为每个有站内消息业务的场景建一条 message 模板，内容与业务代码现有的
 * 硬编码文案一致；模板编码复用同场景邮件编号（统一编号约定）。
 * 之后业务代码改为读模板，开关才能真正生效。
 *
 * 已存在则跳过，重复执行安全。
 */
return new class extends Migration
{
    /**
     * 场景 => [模板名, 标题模板, 正文模板, 变量, 邮件同编号]。
     *
     * 文案取自各业务服务里现有的 userNotificationService->create 调用，
     * 保证接入模板后用户看到的站内消息不变。
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: list<string>, 4: string}>
     */
    private const MESSAGE_TEMPLATES = [
        'service_due' => [
            '服务续费提醒',
            '服务续费提醒',
            '您的服务「{{service_name}}」{{urgency_message}}',
            ['service_name', 'urgency_message'],
            '100018',
        ],
        'auto_renew_upcoming' => [
            '即将自动续费',
            '即将自动续费',
            '{{urgency_message}}',
            ['urgency_message'],
            '100014',
        ],
        'invoice_payment_reminder' => [
            '账单待支付提醒',
            '账单待支付提醒',
            '{{notice_message}}',
            ['notice_message'],
            '100011',
        ],
        'service_expired' => [
            '服务已到期暂停',
            '服务已到期暂停',
            '您的服务「{{service_name}}」已于 {{expires_at}} 到期并暂停，请尽快续费恢复使用。',
            ['service_name', 'expires_at'],
            '100046',
        ],
        'order_created' => [
            '订单创建提醒',
            '订单创建提醒',
            '您有一笔新订单待支付：{{order_no}}，金额 {{amount}} 元。',
            ['order_no', 'amount'],
            '100010',
        ],
        'order_paid' => [
            '订单支付成功',
            '订单支付成功',
            '您的订单 {{invoice_no}} 已完成支付，金额 {{paid_amount}} 元。',
            ['invoice_no', 'paid_amount'],
            '100016',
        ],
        'ticket_opened' => [
            '工单提交成功',
            '工单提交成功',
            '您的工单「{{ticket_subject}}」已提交，我们会尽快处理。',
            ['ticket_subject'],
            '100023',
        ],
        'ticket_replied' => [
            '工单收到回复',
            '工单收到回复',
            '您的工单「{{ticket_subject}}」已有新回复，请注意查看。',
            ['ticket_subject'],
            '100024',
        ],
        'ticket_auto_closed' => [
            '工单已自动关闭',
            '工单已自动关闭',
            '您的工单「{{ticket_subject}}」已自动关闭。',
            ['ticket_subject'],
            '100025',
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

        foreach (self::MESSAGE_TEMPLATES as $scene => $definition) {
            [$name, $title, $body, $variables, $code] = $definition;

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
