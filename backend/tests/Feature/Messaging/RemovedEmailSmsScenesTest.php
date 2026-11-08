<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Constants\UserNotificationType;
use App\Jobs\SendTemplateEmailJob;
use App\Jobs\SendTicketNotificationEmailJob;
use App\Models\User;
use App\Services\Auth\VerificationService;
use App\Services\Mail\MailDriverManager;
use App\Services\Messaging\MessageConfigurationRepository;
use App\Services\Messaging\MessageDispatcher;
use App\Services\Sms\SmsDriverManager;
use App\Services\Ticket\TicketService;
use App\Services\Verification\VerificationDriverManager;
use App\Support\MessageGroups;
use App\Support\MessageTypeCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\Mail\FakeMailDriver;
use Tests\Fixtures\Sms\FakeSmsDriver;
use Tests\Fixtures\Verification\FakeVerificationDriver;
use Tests\TestCase;

/**
 * 四个场景移除邮件与短信渠道（2026-11-07 用户裁决）。
 *
 * 覆盖范围：
 * - 信用额账单已生成（credit_invoice_created）
 * - 实名认证未通过（real_name_rejected）
 * - 工单重新开启（ticket_reopened）
 * - 系统重装成功（service_reinstalled）
 *
 * 每个场景两组断言：
 * 1. 删除后：目录、模板、关联、配置都不再有 email / sms；真实业务入口不再派发邮件任务；
 * 2. 插件通道仍可发起：mock 驱动经 MessageDispatcher 与真实发送入口（Job / 业务 Service）
 *    能完整走通邮件与短信链路，证明「删的是这四个场景的文案载体，不是通道能力」。
 *
 * 不真正对外发送：邮件用 FakeMailDriver、短信用 FakeSmsDriver，只记录请求。
 */
class RemovedEmailSmsScenesTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<string> */
    private const SCENES = [
        'credit_invoice_created',
        'real_name_rejected',
        'ticket_reopened',
        'service_reinstalled',
    ];

    /** @var list<string> */
    private const CHANNELS = ['email', 'sms'];

    public function test_四个场景不再声明邮件与短信渠道(): void
    {
        foreach (self::SCENES as $scene) {
            $type = MessageTypeCatalog::get('customer', $scene);

            $this->assertSame(['message'], $type['channels'], "场景 {$scene} 只应保留站内消息渠道");
            $this->assertNotContains('email', $type['channels'], "场景 {$scene} 不应再声明邮件");
            $this->assertNotContains('sms', $type['channels'], "场景 {$scene} 不应再声明短信");
        }
    }

    public function test_四个场景没有邮件与短信模板及其场景关联(): void
    {
        foreach (self::CHANNELS as $channel) {
            $this->assertSame(
                0,
                DB::table('notification_templates')
                    ->whereIn('scene_code', self::SCENES)
                    ->where('channel', $channel)
                    ->count(),
                "{$channel} 模板不应再挂在四个场景下"
            );

            $this->assertSame(
                0,
                DB::table('message_scene_templates')
                    ->whereIn('scene_code', self::SCENES)
                    ->where('channel', $channel)
                    ->count(),
                "{$channel} 场景关联应已删除"
            );
        }

        // 站内消息必须保留：删除只针对邮件与短信
        $this->assertSame(
            count(self::SCENES),
            DB::table('message_scene_templates')
                ->whereIn('scene_code', self::SCENES)
                ->where('channel', 'message')
                ->count(),
            '四个场景的站内消息关联必须保留'
        );
    }

    public function test_四个场景的已存配置不再包含邮件与短信渠道键(): void
    {
        $repository = app(MessageConfigurationRepository::class);

        foreach (self::SCENES as $scene) {
            $stored = $repository->get('customer', $scene);
            $this->assertGreaterThan(0, $stored['revision'], "场景 {$scene} 应有已保存配置");

            $type = MessageTypeCatalog::get('customer', $scene);
            $groups = MessageGroups::fromStored('customer', $type, $stored['configurations']);

            foreach ($groups as $group) {
                $channels = array_keys((array) $group['configurations']);

                $this->assertNotContains('email', $channels, "场景 {$scene} 的配置不应再有邮件渠道");
                $this->assertNotContains('sms', $channels, "场景 {$scene} 的配置不应再有短信渠道");
                $this->assertContains('message', $channels, "场景 {$scene} 的配置应保留站内消息渠道");
            }
        }
    }

    public function test_四个场景的消息发送不再产生任何投递记录(): void
    {
        $email = new FakeMailDriver;
        $sms = new FakeSmsDriver;
        $this->app->instance(MailDriverManager::class, new MailDriverManager([$email]));
        $this->app->instance(SmsDriverManager::class, new SmsDriverManager([$sms]));

        $dispatcher = app(MessageDispatcher::class);
        $this->enableCustomerChannel('order_paid', 'email');
        $this->enableCustomerChannel('order_paid', 'sms');

        // 只看本用例产生的投递，不用全表 count：真实库里这四个场景的历史流水不为零
        $beforeDeliveries = DB::table('message_deliveries')->count();
        $beforeMessages = DB::table('messages')->count();

        foreach (self::SCENES as $scene) {
            $sent = $dispatcher->sendNow(
                'removed-scene.'.$scene.'.1',
                $scene,
                'removed_scene',
                1,
                [[
                    'audience' => 'customer',
                    'account_id' => 1,
                    'target_key' => 'user:1',
                    'email' => 'scene@example.test',
                    'mobile' => '13800000000',
                    'name' => '测试客户',
                ]],
                ['reason' => '资料不清晰', 'ticket_subject' => '无法登录'],
            );

            // 只剩站内消息渠道，sendNow 不展开 message（由 createForScene 负责），
            // 因此没有任何目标可投递，返回 false。
            $this->assertFalse($sent, "场景 {$scene} 不应再产生邮件或短信投递");
        }

        $this->assertSame(0, $email->count(), '不应发出任何邮件');
        $this->assertSame([], $sms->messages, '不应发出任何短信');
        $this->assertSame($beforeDeliveries, DB::table('message_deliveries')->count(), '不应新增投递记录');
        $this->assertSame($beforeMessages, DB::table('messages')->count(), '不应产生新的消息流水');

        // 更直接的守卫：目录里这四个场景的渠道必须只剩 message。
        // 单靠 sendNow 返回 false 不够——「有启用渠道但地址为空」也会返回 false。
        foreach (self::SCENES as $scene) {
            $this->assertSame(['message'], MessageTypeCatalog::get('customer', $scene)['channels'], "场景 {$scene} 渠道声明必须只剩站内消息");
            $this->assertSame(
                [],
                DB::table('message_scene_templates')
                    ->where('scene_code', $scene)
                    ->whereIn('channel', self::CHANNELS)
                    ->pluck('channel')
                    ->all(),
                "场景 {$scene} 不得再有邮件或短信模板关联"
            );
        }
    }

    public function test_工单重新开启不再派发邮件任务(): void
    {
        Queue::fake();

        $user = $this->makeUser(['email' => 'reopen@example.test']);
        $service = app(TicketService::class);

        $ticket = $service->create($user->id, [
            'department' => 'support',
            'subject' => '重开场景测试',
            'content' => '问题描述',
        ]);
        $service->clientClose($ticket->refresh(), $user->id);
        $service->reopen($ticket->refresh()->refresh());

        Queue::assertNotPushed(SendTicketNotificationEmailJob::class);

        // 站内消息仍然写入
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => UserNotificationType::TICKET_REOPENED,
        ]);
    }

    /**
     * 升级窗口：删除前派发、仍留在队列里的 100064 任务不得抛异常。
     *
     * 它已不在 SCENE_BY_TEMPLATE 里，若走旧路径回退，模板又已被迁移删除，
     * 只会得到「邮件模板不存在」并堆进 failed_jobs。这里验证优雅丢弃。
     */
    public function test_残留的工单重开队列任务被优雅丢弃(): void
    {
        $email = new FakeMailDriver;
        $this->app->instance(MailDriverManager::class, new MailDriverManager([$email]));

        $job = new SendTicketNotificationEmailJob(
            'legacy@example.test',
            '100064',
            ['display_name' => '旧任务', 'ticket_id' => 1, 'ticket_subject' => '历史工单'],
            ['scene' => 'ticket_reopened', 'ticket_id' => 1]
        );

        // 不得抛异常
        $this->app->call([$job, 'handle']);

        $this->assertSame(0, $email->count(), '已下线模板不得再发出邮件');
        $this->assertSame(
            0,
            DB::table('message_logs')->where('template_code', '100064')->count(),
            '不得留下投递尝试'
        );
    }

    public function test_实名认证未通过不再进入邮件或短信链路(): void
    {
        $email = new FakeMailDriver;
        $sms = new FakeSmsDriver;
        $this->app->instance(MailDriverManager::class, new MailDriverManager([$email]));
        $this->app->instance(SmsDriverManager::class, new SmsDriverManager([$sms]));

        app(VerificationDriverManager::class)->register(new FakeVerificationDriver);

        $user = $this->makeUser();
        $service = app(VerificationService::class);
        $service->initVerification($user, '李四', '110101199001011234');

        // 驱动返回「认证失败」，业务侧会写一条实名认证未通过的站内消息
        $service->syncUserStatus($user->refresh(), ['status' => 2, 'msg' => '公安库比对不通过'], 'cert-fake-001');

        $this->assertSame(0, $email->count(), '实名认证未通过不应再发邮件');
        $this->assertSame([], $sms->messages, '实名认证未通过不应再发短信');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => UserNotificationType::REAL_NAME_REJECTED,
        ]);
    }

    /**
     * 通道能力验证：用驱动替身走完整邮件链路。
     *
     * 断言的是「分发器 → 传输层 → 驱动」这条链路仍然通，以及模板变量渲染、
     * 审计日志落库——不是验证某个真实插件可用（真实插件的可用性取决于
     * SMTP 凭据与网络，不应进单元测试）。
     */
    public function test_邮件通道在未受影响场景仍可发起(): void
    {
        $email = new FakeMailDriver;
        $this->app->instance(MailDriverManager::class, new MailDriverManager([$email]));

        // 用未受本次删除影响的场景（order_created），并在测试内自备模板与启用配置，
        // 不依赖环境里模板是否已落库（idc_test 与 idc 的模板集可能不同）。
        $this->enableCustomerChannel('order_created', 'email');
        $this->ensureSceneEmailTemplate('order_created', '100010', '【{{site_name}}】新订单待支付 #{{invoice_no}}');

        // 任务声明 afterCommit()，测试事务内 dispatch 不会执行；直接调用 handle 走同一链路。
        $job = new SendTemplateEmailJob('plugin-check@example.test', '100010', [
            'display_name' => '插件验证',
            'email' => 'plugin-check@example.test',
            'invoice_no' => 'PLUGIN-1',
        ]);
        $this->app->call([$job, 'handle']);

        $this->assertSame(1, $email->count(), '被测场景之外，邮件通道必须仍可发起');
        $this->assertSame('plugin-check@example.test', $email->lastTo());
        $this->assertStringContainsString('PLUGIN-1', $email->lastHtml(), '模板变量应完成渲染');

        // 审计同样落库，管理端可见
        $this->assertDatabaseHas('message_logs', [
            'channel' => 'email',
            'recipient' => 'plugin-check@example.test',
        ]);
    }

    /**
     * 通道能力验证：用驱动替身走完整短信链路（同上，不验证真实插件）。
     */
    public function test_短信通道在未受影响场景仍可发起(): void
    {
        $sms = new FakeSmsDriver;
        $this->app->instance(SmsDriverManager::class, new SmsDriverManager([$sms]));

        $dispatcher = app(MessageDispatcher::class);
        $this->enableCustomerChannel('order_paid', 'sms');

        $sent = $dispatcher->sendNow(
            'plugin-check.sms.1',
            'order_paid',
            'invoice',
            9001,
            [[
                'audience' => 'customer',
                'account_id' => 1,
                'target_key' => 'user:1',
                'email' => '',
                'mobile' => '13800000000',
                'name' => '短信验证',
            ]],
            ['invoice_no' => 'INV1', 'amount' => '10.00'],
        );

        $this->assertTrue($sent, '被测场景之外，短信通道必须仍可发起');
        $this->assertCount(1, $sms->messages, '短信驱动应收到一条发送请求');
        $this->assertDatabaseHas('message_logs', [
            'channel' => 'sms',
            'recipient' => '13800000000',
        ]);
    }

    /**
     * 把指定场景的客户组某渠道打开并保存（测试内）。
     */
    private function enableCustomerChannel(string $scene, string $channel): void
    {
        $repository = app(MessageConfigurationRepository::class);
        $stored = $repository->get('customer', $scene);
        $type = MessageTypeCatalog::get('customer', $scene);
        $groups = MessageGroups::fromStored('customer', $type, $stored['configurations']);

        $groups[0]['enabled'] = true;
        $groups[0]['configurations'][$channel] = array_merge(
            (array) ($groups[0]['configurations'][$channel] ?? MessageGroups::defaultChannelConfiguration($type, $channel)),
            ['enabled' => true]
        );

        $repository->save('customer', $scene, (int) $stored['revision'], ['_groups' => $groups]);
    }

    /**
     * 保证场景内存在可用的邮件模板（测试自备，避免依赖环境模板集）。
     */
    private function ensureSceneEmailTemplate(string $scene, string $code, string $subject): void
    {
        $existing = DB::table('notification_templates')
            ->where('channel', 'email')
            ->where('code', $code)
            ->first(['scene_code']);

        if ($existing !== null) {
            // (channel, code) 有唯一键，已存在但属于别的场景时不能悄悄复用它——那会让
            // 本测试验证到别人的模板。先断言模板确实归本场景，再补关联即可。
            $this->assertSame(
                $scene,
                (string) $existing->scene_code,
                "模板 email:{$code} 已被场景 {$existing->scene_code} 占用，(channel, code) 唯一键下本用例无法自建"
            );
        } else {
            $sortBase = (int) DB::table('notification_templates')->max('sort_order');

            DB::table('notification_templates')->insert([
                'channel' => 'email',
                'code' => $code,
                'name' => '测试场景邮件',
                'description' => '测试自备模板',
                'audience' => 'user',
                'scene_code' => $scene,
                'subject' => $subject,
                'content' => '<p>{{display_name}}，您的订单 {{invoice_no}} 已生成。</p>',
                'variables_json' => json_encode(['display_name', 'invoice_no'], JSON_UNESCAPED_UNICODE),
                'provider_variables_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'provider_template_id' => null,
                'is_enabled' => true,
                'is_custom' => false,
                'sort_order' => ++$sortBase,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('message_scene_templates')->insertOrIgnore([
            'audience' => 'customer',
            'scene_code' => $scene,
            'channel' => 'email',
            'template_code' => $code,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'email' => 'removed-scene-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'removed-scene-tester',
        ], $attributes));
    }
}
