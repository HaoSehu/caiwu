<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendClientLoginEmailAlertJob;
use App\Jobs\SendLoginAlertJob;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Auth\AuthService;
use App\Services\Auth\LoginAlertSettingService;
use App\Services\Notification\UserNotificationService;
use App\Services\System\NotificationTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * M10 登录成功提醒（补全）回归：
 * 1. 登录成功触发邮件 Job（既有链路）+ 站内信 Job（SendLoginAlertJob）双通道；
 * 2. 用户级开关关闭时不发送任何通道；
 * 3. settings 通道配置可裁剪（仅 inbox 时邮件不投递）；
 * 4. 管理员代登录（login_as）不触发任何登录提醒（既有语义，回归用例）；
 * 5. SendLoginAlertJob 落库 user_notifications（type=login_alert）。
 *
 * 注意：提醒投递位于 app()->terminating 回调，测试内通过 app()->terminate() 显式触发；
 * 测试环境无 Redis，redis_volatile 降级为 array 驱动；队列改为 database + Queue::fake，
 * 避免 sync 驱动退化成同步直发（Mail array 驱动下无法断言 Job）。
 */
class LoginAlertInboxTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // redis_volatile 承载登录风控/代登录凭证缓存，测试环境降级为 array
        config(['cache.stores.redis_volatile' => ['driver' => 'array', 'serialize' => false]]);
        app('cache')->forgetDriver('redis_volatile');

        // sync 会绕过队列直接发送，测试改为队列驱动 + fake 以便断言投递
        config(['queue.default' => 'database']);
        Queue::fake();
    }

    protected function tearDown(): void
    {
        // 事务回滚不清理 Setting 静态组缓存，显式失效防止用例间串扰
        Setting::forgetCachedGroup('users');

        parent::tearDown();
    }

    public function test_login_dispatches_mail_and_inbox_alert_jobs(): void
    {
        $user = $this->makeUser(['login_email_alert' => 1, 'login_notify' => 1]);

        $this->postJson('/api/v2/client/login', [
            'account' => $user->email,
            'password' => 'secret123',
        ])->assertOk();

        app()->terminate();

        Queue::assertPushed(SendClientLoginEmailAlertJob::class);
        Queue::assertPushed(SendLoginAlertJob::class);
    }

    public function test_login_alert_skipped_when_user_switch_disabled(): void
    {
        $user = $this->makeUser(['login_email_alert' => 0, 'login_notify' => 0]);

        $this->postJson('/api/v2/client/login', [
            'account' => $user->email,
            'password' => 'secret123',
        ])->assertOk();

        app()->terminate();

        Queue::assertNotPushed(SendClientLoginEmailAlertJob::class);
        Queue::assertNotPushed(SendLoginAlertJob::class);
    }

    public function test_channel_setting_can_restrict_alert_to_inbox_only(): void
    {
        Setting::setValue(LoginAlertSettingService::SETTING_GROUP, LoginAlertSettingService::KEY_CHANNELS, 'inbox');

        $user = $this->makeUser(['login_email_alert' => 1, 'login_notify' => 1]);

        $this->postJson('/api/v2/client/login', [
            'account' => $user->email,
            'password' => 'secret123',
        ])->assertOk();

        app()->terminate();

        Queue::assertPushed(SendLoginAlertJob::class);
        Queue::assertNotPushed(SendClientLoginEmailAlertJob::class);
    }

    public function test_admin_login_as_does_not_trigger_alerts(): void
    {
        $user = $this->makeUser(['login_email_alert' => 1, 'login_notify' => 1]);

        $service = app(AuthService::class);
        $issued = $service->issueAdminLoginAsCode($user, ['admin_id' => 1]);
        $service->exchangeAdminLoginAsCode((string) $issued['login_code'], '127.0.0.1');

        app()->terminate();

        Queue::assertNotPushed(SendClientLoginEmailAlertJob::class);
        Queue::assertNotPushed(SendLoginAlertJob::class);
    }

    public function test_send_login_alert_job_writes_inbox_message(): void
    {
        $user = $this->makeUser(['login_email_alert' => 1, 'login_notify' => 1]);

        (new SendLoginAlertJob(
            userId: (int) $user->id,
            displayName: '登录提醒测试用户',
            loginAt: '2026-10-11 12:00:00',
            ip: '203.0.113.10',
        ))->handle(
            app(UserNotificationService::class),
            app(NotificationTemplateService::class)
        );

        $notification = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'login_alert')
            ->first();

        $this->assertNotNull($notification, '站内信必须写入 user_notifications');
        $this->assertStringContainsString('203.0.113.10', (string) $notification->content);
        $this->assertStringContainsString('2026-10-11 12:00:00', (string) $notification->content);
    }

    public function test_send_login_alert_job_respects_user_switch_at_send_time(): void
    {
        $user = $this->makeUser(['login_email_alert' => 0, 'login_notify' => 0]);

        (new SendLoginAlertJob(
            userId: (int) $user->id,
            displayName: '登录提醒测试用户',
            loginAt: '2026-10-11 12:00:00',
            ip: '203.0.113.10',
        ))->handle(
            app(UserNotificationService::class),
            app(NotificationTemplateService::class)
        );

        $this->assertSame(
            0,
            UserNotification::query()->where('user_id', $user->id)->where('type', 'login_alert')->count(),
            '发送时用户开关已关闭，不得写入站内信'
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeUser(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'email' => 'login-alert-'.uniqid().'@example.test',
            'password' => Hash::make('secret123'),
            'nickname' => 'login-alert-tester',
            'total_sales_amount' => 0,
            'status' => 1,
            'last_login_at' => now()->format('Y-m-d H:i:s'),
            'last_login_ip' => '198.51.100.1',
        ], $overrides));
    }
}
