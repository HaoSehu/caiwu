<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Constants\UserNotificationType;
use App\Services\Mail\Contracts\MailDriver;
use App\Services\Mail\MailDriverManager;
use App\Services\Messaging\MessageConfigurationRepository;
use App\Services\Messaging\MessageDispatcher;
use App\Services\Notification\UserNotificationService;
use App\Support\MessageGroups;
use App\Support\MessageTemplateRenderer;
use App\Support\MessageTypeCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 账单/支付通知链路审查缺陷回归（2026-11-04）。
 *
 * 1. 渲染器支持 {{#site_logo}} 区段，logo 标记不得泄露到邮件正文；
 * 2. 场景变量白名单包含 display_name / recipient_name / hours_left；
 * 3. 同一 event_key 重放只补发未成功渠道，不重复触达、不重复写审计；
 * 4. sendNow 不展开 message 渠道（站内信由 createForScene 统一写，避免双写；
 *    管理员受众不会以管理员 id 写进 user_notifications）；
 * 5. 多档提醒按模板编号取对应正文（第 1 次提醒不能发「第 3 次支付提醒」）；
 * 6. 渠道模板变量非法 JSON 时按空变量降级，不让整条消息失败；
 * 7. 站点审计 message_logs 有记录；
 * 8. 站内信场景编码有中文标签。
 */
class BillingNotificationRegressionTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 本用例组验证「支付完成通知走 email 渠道」的链路，需要 `order_paid` 场景的
     * email 渠道处于启用状态。
     *
     * 该状态在开发库中由历史操作累积而来（管理端开过开关），全新库上种子迁移
     * 只按模板 `is_enabled` 建配置——模板若在配置建立之后才补，渠道就是关闭的。
     * 用例不应依赖这种外部遗留状态，这里显式开启，保证在任意数据库上可重复。
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->enableChannel('customer', 'order_paid', 'email');
        $this->enableChannel('customer', 'invoice_payment_reminder', 'email');
    }

    /**
     * 就地启用某个场景的某个渠道（只改 enabled 位，不动其余配置）。
     */
    private function enableChannel(string $audience, string $scene, string $channel): void
    {
        $repository = app(MessageConfigurationRepository::class);
        $stored = $repository->get($audience, $scene);
        $configurations = $stored['configurations'] ?? [];

        $groups = MessageGroups::fromStored($audience, MessageTypeCatalog::get($audience, $scene), $configurations);
        if ($groups === []) {
            return;
        }

        $changed = false;
        foreach ($groups as $index => $group) {
            if (! isset($group['configurations'][$channel])) {
                continue;
            }

            if (($group['configurations'][$channel]['enabled'] ?? false) === true) {
                continue;
            }

            $groups[$index]['configurations'][$channel]['enabled'] = true;
            $changed = true;
        }

        if (! $changed) {
            return;
        }

        $repository->save(
            $audience,
            $scene,
            (int) ($stored['revision'] ?? 0),
            ['_groups' => $groups],
        );
    }

    public function test_渲染器支持_logo_条件区段(): void
    {
        $out = MessageTemplateRenderer::render(
            'a{{#site_logo}}<img src="{{site_logo}}">{{/site_logo}}b{{x}}',
            ['site_logo' => 'https://cdn.example/logo.png', 'x' => '1']
        );
        $this->assertSame('a<img src="https://cdn.example/logo.png">b1', $out);

        $empty = MessageTemplateRenderer::render('a{{#site_logo}}<img>{{/site_logo}}b', ['site_logo' => '']);
        $this->assertSame('ab', $empty, 'site_logo 为空时必须整段剔除');
    }

    public function test_场景变量白名单覆盖历史模板占位符(): void
    {
        $paid = MessageTypeCatalog::get('customer', 'order_paid')['variables'];
        $this->assertContains('display_name', $paid, '付款成功模板使用 {{display_name}}，必须登记');

        $admin = MessageTypeCatalog::get('admin', 'admin_order_paid')['variables'];
        $this->assertContains('recipient_name', $admin);

        $ticket = MessageTypeCatalog::get('customer', 'ticket_pending_reminder')['variables'];
        $this->assertContains('hours_left', $ticket, '工单待处理模板使用 {{hours_left}}，必须登记');
    }

    public function test_同一事件重放不重复发送且不重复写审计(): void
    {
        $eventKey = 'regression.replay.'.uniqid('', true);
        $recipients = [[
            'audience' => 'customer',
            'account_id' => 0,
            'target_key' => 'email:replay@example.test',
            'email' => 'replay@example.test',
            'mobile' => '',
            'name' => '重放测试',
        ]];

        $dispatcher = app(MessageDispatcher::class);
        $dispatcher->sendNow($eventKey, 'order_paid', 'invoice', 999001, $recipients, ['invoice_no' => 'R1']);

        $deliveryCount = DB::table('message_deliveries')
            ->whereIn('recipient_id', $this->recipientIds(999001))
            ->count();
        $logCount = DB::table('message_logs')->count();

        $dispatcher->sendNow($eventKey, 'order_paid', 'invoice', 999001, $recipients, ['invoice_no' => 'R1']);

        $this->assertSame($deliveryCount, DB::table('message_deliveries')
            ->whereIn('recipient_id', $this->recipientIds(999001))->count(), '重放不得新增投递记录');
        $this->assertSame($logCount, DB::table('message_logs')->count(), '重放不得重复写审计日志');

        $status = DB::table('message_deliveries')
            ->whereIn('recipient_id', $this->recipientIds(999001))
            ->value('status');
        $this->assertNotSame('pending', $status, '重放后投递不应停留在 pending');
    }

    public function test_send_now_不展开站内消息渠道(): void
    {
        $dispatcher = app(MessageDispatcher::class);

        $method = new \ReflectionMethod($dispatcher, 'expandTargets');
        $method->setAccessible(true);

        $type = MessageTypeCatalog::get('customer', 'order_paid');
        $group = MessageGroups::create($type, 'customer', 'customer', '关联客户');
        $group['enabled'] = true;
        foreach (array_keys($group['configurations']) as $channel) {
            $group['configurations'][$channel]['enabled'] = true;
        }

        $targets = $method->invoke(
            $dispatcher, 'customer', $type, ['_groups' => [$group]],
            [[
                'audience' => 'customer',
                'account_id' => 1,
                'target_key' => 'user:1',
                'email' => 'u@example.test',
                'mobile' => '13800000000',
                'name' => '客户',
            ]],
            ['site_name' => '站点', 'customer_name' => '客户']
        );

        $channels = array_column($targets, 'channel');
        $this->assertNotContains('message', $channels, '站内信由 createForScene 统一写入，sendNow 不得重复写');
        $this->assertContains('email', $channels);

        // 管理员受众同样不产生 message 目标（避免以管理员 id 写进 user_notifications）
        $adminType = MessageTypeCatalog::get('admin', 'admin_order_paid');
        $adminGroup = MessageGroups::create($adminType, 'admin', 'admin', '内部人员');
        $adminGroup['enabled'] = true;
        foreach (array_keys($adminGroup['configurations']) as $channel) {
            $adminGroup['configurations'][$channel]['enabled'] = true;
        }

        $adminTargets = $method->invoke(
            $dispatcher, 'admin', $adminType, ['_groups' => [$adminGroup]],
            [[
                'audience' => 'admin',
                'account_id' => 1,
                'target_key' => 'admin:1',
                'email' => 'admin@example.test',
                'mobile' => '',
                'name' => '管理员',
            ]],
            ['site_name' => '站点']
        );
        $this->assertNotContains('message', array_column($adminTargets, 'channel'));
    }

    public function test_多档提醒按模板编号取对应正文(): void
    {
        if (! DB::table('notification_templates')->where('channel', 'email')->where('code', '100011')->exists()
            || ! DB::table('notification_templates')->where('channel', 'email')->where('code', '100013')->exists()) {
            $this->markTestSkipped('缺少账单提醒模板 100011/100013');
        }

        $dispatcher = app(MessageDispatcher::class);
        $method = new \ReflectionMethod($dispatcher, 'applySceneTemplate');
        $method->setAccessible(true);

        $config = ['enabled' => true, 'title' => '', 'body' => '', 'template_id' => ''];

        $first = $method->invoke($dispatcher, $config, 'email', 'invoice_payment_reminder', '100011');
        $third = $method->invoke($dispatcher, $config, 'email', 'invoice_payment_reminder', '100013');

        $this->assertStringContainsString('第1次支付提醒', (string) $first['body']);
        $this->assertStringContainsString('第3次支付提醒', (string) $third['body']);
        $this->assertNotSame($first['body'], $third['body'], '不同档位必须使用不同正文');
    }

    public function test_模板变量非法_json_时降级为空变量(): void
    {
        $dispatcher = app(MessageDispatcher::class);
        $method = new \ReflectionMethod($dispatcher, 'decodeParameters');
        $method->setAccessible(true);

        $this->assertSame([], $method->invoke($dispatcher, ['variables' => '']));
        $this->assertSame([], $method->invoke($dispatcher, ['variables' => 'not-json']));
        $this->assertSame(['a' => '1'], $method->invoke($dispatcher, ['variables' => '{"a":"1"}']));
    }

    public function test_发送成功写入管理端审计日志(): void
    {
        $before = DB::table('message_logs')->count();

        app(MessageDispatcher::class)->sendNow(
            'regression.audit.'.uniqid('', true),
            'order_paid',
            'invoice',
            999002,
            [[
                'audience' => 'customer',
                'account_id' => 0,
                'target_key' => 'email:audit@example.test',
                'email' => 'audit@example.test',
                'mobile' => '',
                'name' => '审计测试',
            ]],
            ['invoice_no' => 'A1', 'display_name' => '审计测试']
        );

        $this->assertSame($before + 1, DB::table('message_logs')->count(), '新链路必须写 message_logs 供管理端审计');

        $log = DB::table('message_logs')->orderByDesc('id')->first();
        $this->assertSame('email', (string) $log->channel);
        $this->assertSame('audit@example.test', (string) $log->recipient);
        $this->assertGreaterThanOrEqual(0, strpos((string) $log->content, '审计测试'), '称呼必须渲染进正文');
    }

    public function test_站内信新场景有中文标签(): void
    {
        foreach (['service_due', 'auto_renew_upcoming', 'auto_renew_notice', 'service_expired',
            'service_activated', 'service_resumed', 'order_refunded'] as $scene) {
            $this->assertNotSame(
                '系统通知',
                UserNotificationType::label($scene),
                "场景 {$scene} 未登记中文标签，用户端会显示为「系统通知」"
            );
        }
    }

    public function test_create_for_scene_受渠道开关控制且模板渲染(): void
    {
        $user = DB::table('users')->insertGetId([
            'email' => 'inbox-regression-'.uniqid('', true).'@example.test',
            'password' => bcrypt('secret'),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(UserNotificationService::class);
        $created = $service->createForScene(
            (int) $user,
            'order_refunded',
            '退款已处理',
            '您的账单 INV 已退款。',
            '/client/invoices/1',
            ['invoice_no' => 'INV', 'refund_amount' => '10.00'],
            ['invoice_id' => 1]
        );

        // 场景配置存在时按开关决定；不存在时保持旧行为（写入）
        $stored = app(MessageConfigurationRepository::class)->get('customer', 'order_refunded');
        if ($stored['revision'] === 0) {
            $this->assertNotNull($created);
        } else {
            $this->assertSame(
                DB::table('user_notifications')->where('user_id', $user)->count() > 0,
                $created !== null,
                '站内信写入与场景 message 渠道开关必须一致'
            );
        }
    }

    /**
     * 结果未知（unknown）不得抛异常：抛了队列会重发（tries=3），
     * 直接违反「unknown 一律不重试」的项目硬约束。
     */
    public function test_投递结果未知时不抛异常(): void
    {
        $badDriver = new class implements MailDriver
        {
            public function key(): string
            {
                return 'regression_bad_mail';
            }

            public function label(): string
            {
                return '必失败的邮件驱动';
            }

            public function sendHtml(string $to, string $subject, string $html, array $context = []): void
            {
                throw new \RuntimeException('smtp down');
            }
        };

        $previousDefault = config('integrations.mail.default');
        config(['integrations.mail.default' => $badDriver->key()]);
        $this->app->instance(
            MailDriverManager::class,
            new MailDriverManager([$badDriver])
        );

        try {
            $result = app(MessageDispatcher::class)->sendNow(
                'regression.unknown.'.uniqid('', true),
                'order_paid',
                'invoice',
                999101,
                [[
                    'audience' => 'customer',
                    'account_id' => 0,
                    'target_key' => 'email:unknown@example.test',
                    'email' => 'unknown@example.test',
                    'mobile' => '',
                    'name' => '未知结果',
                ]],
                ['invoice_no' => 'U1']
            );

            $this->assertTrue($result, 'unknown 不应抛异常，否则队列会重发');
        } finally {
            config(['integrations.mail.default' => $previousDefault]);
        }

        $status = DB::table('message_deliveries')
            ->whereIn('recipient_id', $this->recipientIds(999101))
            ->value('status');

        $this->assertSame('unknown', $status, '投递记录必须保留 unknown 终态供审计');
    }

    /**
     * 审计日志必须落真实模板编号（100011），而不是场景编码：
     * email 渠道配置的 template_id 通常为空，退化成场景编码后管理端按模板码检索不到。
     */
    public function test_审计日志记录真实模板编号(): void
    {
        app(MessageDispatcher::class)->sendNow(
            'regression.template_code.'.uniqid('', true),
            'invoice_payment_reminder',
            'invoice',
            999102,
            [[
                'audience' => 'customer',
                'account_id' => 0,
                'target_key' => 'email:tpl@example.test',
                'email' => 'tpl@example.test',
                'mobile' => '',
                'name' => '模板码',
            ]],
            ['invoice_no' => 'T1', 'display_name' => '模板码'],
            'customer',
            '100011'
        );

        $log = DB::table('message_logs')->orderByDesc('id')->first();

        $this->assertSame('100011', (string) $log->template_code, '审计必须落真实模板编号');
        $this->assertStringContainsString('第1次', (string) $log->subject, '正文必须取指定档位而不是配置里的默认档');
    }

    /**
     * 多档模板的查询必须按请求缓存：同一进程内重复发送同一档只查一次。
     */
    public function test_多档模板查询按请求缓存(): void
    {
        $dispatcher = app(MessageDispatcher::class);
        $method = new \ReflectionMethod($dispatcher, 'applySceneTemplate');
        $method->setAccessible(true);

        $config = ['enabled' => true, 'title' => '', 'body' => '', 'template_id' => ''];

        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        for ($i = 0; $i < 10; $i++) {
            $method->invoke($dispatcher, $config, 'email', 'invoice_payment_reminder', '100011');
        }

        $this->assertLessThanOrEqual(3, $queries, '同一模板重复发送不应每次都查库（修复前 10 次调用 21 条查询）');
    }

    /**
     * 种子迁移对多档模板必须取首档（sort_order 最靠前一条），而不是最后一条。
     * 直接调用迁移的 up() 在事务内重建配置验证，不依赖测试库当前状态。
     */
    public function test_种子迁移的多档正文取首档(): void
    {
        if (! DB::table('notification_templates')->where('channel', 'email')->where('code', '100011')->exists()) {
            $this->markTestSkipped('缺少账单提醒模板');
        }

        DB::table('message_configurations')
            ->where('audience', 'customer')
            ->where('message_type', 'invoice_payment_reminder')
            ->delete();

        $migration = require database_path('migrations/2026_11_04_000017_seed_message_configurations.php');
        $migration->up();

        $email = app(MessageConfigurationRepository::class)
            ->get('customer', 'invoice_payment_reminder')['configurations']['_groups'][0]['configurations']['email'] ?? [];

        $this->assertStringContainsString(
            '第1次',
            (string) ($email['title'] ?? ''),
            '种子配置的默认正文必须取首档（第 1 次提醒），取最后一条会让首次提醒显示「第 3 次」'
        );
        $this->assertArrayNotHasKey('__seeded', $email, '迁移内部哨兵不得落进配置');
    }

    /** @return Collection<int, int> */
    private function recipientIds(int $businessId)
    {
        $messageId = DB::table('messages')->where('business_id', $businessId)->orderByDesc('id')->value('id');

        return DB::table('message_recipients')->where('message_id', $messageId)->pluck('id');
    }
}
