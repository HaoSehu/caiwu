<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 补齐短信模板：让「邮件有的消息类型，短信也有」。
 *
 * 现状（2026-11-04 实测）：28 个有模板的场景中 5 个仅邮件、1 个仅短信。
 * 本迁移：
 * 1. 为 5 个仅邮件场景补短信模板（100034–100038）；
 * 2. 为管理员侧 4 个场景补独立的短信模板（100039–100042），不再复用客户口吻的 100010/100012/100013/100014；
 * 3. 新增实名认证场景（real_name_approved / real_name_rejected），把已存在的 sms:100032 挂回去；
 * 4. 把新模板挂到 message_scene_templates；
 * 5. 按实际模板重算各场景的渠道声明（去掉没有模板载体的 message/wechat），
 *    并让「仅短信」的 account_bound 也拥有邮件模板（100039 之外的 100043）。
 *
 * 只新增数据，不修改已存在的模板内容；重复执行安全（insertOrIgnore / 存在即跳过）。
 */
return new class extends Migration
{
    /**
     * 新短信模板：code => [名称, 正文, 变量, 场景, 受众]。
     *
     * @var array<int|string, array{0: string, 1: string, 2: list<string>, 3: string, 4: string}>
     */
    private const SMS_TEMPLATES = [
        // ── 补齐仅邮件的客户场景 ──
        '100034' => [
            '系统重装成功',
            '您好，您的产品{product_name}已完成系统重装，新的操作系统为{operating_system}，主IP：{product_main_ip}，请使用新信息登录。',
            ['product_name', 'operating_system', 'product_main_ip'],
            'service_reinstalled',
            'customer',
        ],
        '100035' => [
            '解除停用失败',
            '您好，您的产品{product_name}解除停用未生效，请稍后重试或联系客服处理。',
            ['product_name'],
            'service_suspend_failed',
            'customer',
        ],
        '100036' => [
            '实例归属变更',
            '您好，您的实例{service_name}归属已变更（{direction_label}），对方账号{counterparty_email}，如有疑问请联系管理员。',
            ['service_name', 'direction_label', 'counterparty_email'],
            'service_transferred',
            'customer',
        ],
        '100037' => [
            '工单自动关闭',
            '您的工单{ticket_subject}已自动关闭，原因：{close_reason}。如问题未解决，请重新提交工单。',
            ['ticket_subject', 'close_reason'],
            'ticket_auto_closed',
            'customer',
        ],
        '100038' => [
            '管理员登录提示',
            '管理员账号{account}于{login_at}在IP {ip}登录，如非本人操作请立即修改密码。',
            ['account', 'login_at', 'ip'],
            'admin_login_alert',
            'admin',
        ],

        // ── 管理员侧：独立短信模板，不再复用客户口吻 ──
        '100039' => [
            '新订单提醒(管理员)',
            '有新订单{bill_no}，客户{user_name}，产品{product_name}，金额{amount}元，请及时跟进。',
            ['bill_no', 'user_name', 'product_name', 'amount'],
            'admin_order_created',
            'admin',
        ],
        '100040' => [
            '订单支付提醒(管理员)',
            '订单{bill_no}已支付，客户{user_name}，金额{amount}元。',
            ['bill_no', 'user_name', 'amount'],
            'admin_order_paid',
            'admin',
        ],
        '100041' => [
            '新工单提醒(管理员)',
            '有新工单{ticket_subject}，客户{client_name}，请及时处理。',
            ['ticket_subject', 'client_name'],
            'admin_ticket_created',
            'admin',
        ],
        '100042' => [
            '工单回复提醒(管理员)',
            '工单{ticket_subject}有客户新回复，客户{client_name}，请及时跟进。',
            ['ticket_subject', 'client_name'],
            'admin_ticket_replied',
            'admin',
        ],
    ];

    /**
     * 补齐仅短信场景的邮件模板：code => [名称, 主题, 正文, 变量, 场景]。
     *
     * @var array<int|string, array{0: string, 1: string, 2: string, 3: list<string>, 4: string}>
     */
    private const EMAIL_TEMPLATES = [
        '100031' => [
            '账号绑定成功',
            '【{{site_name}}】账号绑定成功',
            '<p>{{display_name}}，您的账号已成功绑定。</p><p>如非本人操作，请立即修改密码并联系管理员。</p>',
            ['display_name'],
            'account_bound',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        $now = now();
        $sortBase = (int) DB::table('notification_templates')->max('sort_order');

        foreach (self::SMS_TEMPLATES as $code => $definition) {
            $this->insertSms((string) $code, $definition, $now, ++$sortBase);
        }

        foreach (self::EMAIL_TEMPLATES as $code => $definition) {
            $this->insertEmail((string) $code, $definition, $now, ++$sortBase);
        }

        // 已存在的 sms:100032（实名认证通过）挂回新场景
        $this->attach('sms', '100032', 'customer', 'real_name_approved');
    }

    /**
     * @param  array{0: string, 1: string, 2: list<string>, 3: string, 4: string}  $definition
     */
    private function insertSms(string $code, array $definition, DateTimeInterface $now, int $sortOrder): void
    {
        [$name, $content, $variables, $scene, $audience] = $definition;

        DB::table('notification_templates')->insertOrIgnore([
            'channel' => 'sms',
            'code' => $code,
            'name' => $name,
            'description' => $name.'短信模板。',
            'audience' => $audience === 'admin' ? 'admin' : 'user',
            'scene_code' => $scene,
            'subject' => null,
            'content' => $content,
            'variables_json' => json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'provider_variables_json' => json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'provider_template_id' => null,
            'is_enabled' => true,
            'is_custom' => false,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->attach('sms', $code, $audience, $scene);
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: list<string>, 4: string}  $definition
     */
    private function insertEmail(string $code, array $definition, DateTimeInterface $now, int $sortOrder): void
    {
        [$name, $subject, $content, $variables, $scene] = $definition;

        DB::table('notification_templates')->insertOrIgnore([
            'channel' => 'email',
            'code' => $code,
            'name' => $name,
            'description' => $name.'邮件模板。',
            'audience' => 'user',
            'scene_code' => $scene,
            'subject' => $subject,
            'content' => $content,
            'variables_json' => json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'provider_variables_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'provider_template_id' => null,
            'is_enabled' => true,
            'is_custom' => false,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->attach('email', $code, 'customer', $scene);
    }

    private function attach(string $channel, string $templateCode, string $audience, string $scene): void
    {
        if (! Schema::hasTable('message_scene_templates')) {
            return;
        }

        DB::table('message_scene_templates')->insertOrIgnore([
            'audience' => $audience,
            'scene_code' => $scene,
            'channel' => $channel,
            'template_code' => $templateCode,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // 模板补齐是数据新增，不做回滚（与历史模板迁移一致）。
    }
};
